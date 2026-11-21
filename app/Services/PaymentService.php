<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundMethod;
use App\Enums\SaleStatus;
use App\Models\Contracts\LedgerParty;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StitchingOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every rupee in or out is a payment row, so day closing and the cash book can rely on one table.
 * Money paid out is a ledger debit, money received is a ledger credit.
 */
class PaymentService
{
    public function __construct(private LedgerService $ledger) {}

    /**
     * Record a payment made to a supplier and debit the supplier ledger (SUP03).
     *
     * @param  array{payment_date: string, amount: float|string, method: PaymentMethod|string, reference_no?: string|null, notes?: string|null}  $data
     */
    public function paySupplier(Supplier $supplier, array $data, ?Model $source = null): Payment
    {
        return $this->record($supplier, Payment::OUT, $data, $source);
    }

    /**
     * Money received from a customer, or from a walk-in when $customer is null.
     *
     * @param  array{payment_date: string, amount: float|string, method: PaymentMethod|string, reference_no?: string|null, notes?: string|null}  $data
     */
    public function receive(?Customer $customer, array $data, ?Model $source = null): Payment
    {
        return $this->record($customer, Payment::IN, $data, $source);
    }

    /**
     * Payment against a customer's balance, applied to credit invoices and stitching orders (PAY02).
     * Lines with no amount are skipped; whatever is not allocated stays on account as an advance.
     *
     * @param  array{payment_date: string, amount: float|string, method: PaymentMethod|string, reference_no?: string|null, notes?: string|null}  $data
     * @param  list<array{type: string, id: int|string, amount: float|string|null}>  $allocations
     */
    public function receiveOnAccount(Customer $customer, array $data, array $allocations): Payment
    {
        return DB::transaction(function () use ($customer, $data, $allocations): Payment {
            $open = $this->openDocuments($customer)->keyBy(fn (array $document) => $document['type'].':'.$document['id']);
            $lines = collect($allocations)->filter(fn (array $line) => (float) ($line['amount'] ?? 0) > 0)->values();
            $total = round((float) $lines->sum(fn (array $line) => (float) $line['amount']), 2);

            if ($total - (float) $data['amount'] > 0.009) {
                throw ValidationException::withMessages(['allocations' => __('Allocated amounts add up to more than the payment.')]);
            }

            foreach ($lines as $line) {
                $document = $open->get($line['type'].':'.$line['id']);

                if (! $document) {
                    throw ValidationException::withMessages(['allocations' => __('One of the selected bills is no longer open.')]);
                }

                if ((float) $line['amount'] - $document['outstanding'] > 0.009) {
                    throw ValidationException::withMessages(['allocations' => __('More than the :amount due on :number.', ['amount' => number_format($document['outstanding'], 2), 'number' => $document['number']])]);
                }
            }

            $payment = $this->record($customer, Payment::IN, $data, null);

            foreach ($lines as $line) {
                $document = $open->get($line['type'].':'.$line['id']);
                $payment->allocations()->create([
                    'allocatable_type' => $document['model']->getMorphClass(),
                    'allocatable_id' => $document['model']->getKey(),
                    'amount' => round((float) $line['amount'], 2),
                ]);

                if ($document['model'] instanceof StitchingOrder) {
                    $this->refreshOrderPaid($document['model']);
                }
            }

            return $payment;
        });
    }

    /**
     * Credit invoices and stitching orders that still have money due, oldest first.
     *
     * @return Collection<int, array{type: string, id: int, number: string, date: Carbon, total: float, outstanding: float, model: Sale|StitchingOrder}>
     */
    public function openDocuments(Customer $customer): Collection
    {
        $sales = Sale::query()
            ->where('customer_id', $customer->id)
            ->where('status', SaleStatus::Completed)
            ->where('credit_amount', '>', 0)
            ->get()
            ->map(fn (Sale $sale): array => [
                'type' => 'sale', 'id' => $sale->id, 'number' => $sale->number, 'date' => $sale->sale_date,
                'total' => (float) $sale->total, 'outstanding' => $this->saleOutstanding($sale), 'model' => $sale,
            ]);

        $orders = StitchingOrder::query()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->whereColumn('paid_amount', '<', 'total')
            ->get()
            ->map(fn (StitchingOrder $order): array => [
                'type' => 'stitching_order', 'id' => $order->id, 'number' => $order->number, 'date' => $order->order_date,
                'total' => (float) $order->total, 'outstanding' => $order->balanceDue(), 'model' => $order,
            ]);

        return $sales->concat($orders)
            ->filter(fn (array $document) => $document['outstanding'] > 0.009)
            ->sortBy(fn (array $document) => $document['date']->format('Ymd').str_pad((string) $document['id'], 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * Credit left on an invoice after allocated payments and returns credited to the customer balance.
     */
    public function saleOutstanding(Sale $sale): float
    {
        $returnedToBalance = (float) SaleReturn::query()->where('sale_id', $sale->id)->where('refund_method', RefundMethod::CustomerBalance)->sum('total');

        return max(0, round((float) $sale->credit_amount - $this->allocatedTo($sale) - $returnedToBalance, 2));
    }

    /**
     * Sum of live (not cancelled) payment allocations against a document.
     */
    public function allocatedTo(Model $document): float
    {
        return round((float) PaymentAllocation::query()
            ->where('allocatable_type', $document->getMorphClass())
            ->where('allocatable_id', $document->getKey())
            ->whereHas('payment', fn ($q) => $q->whereNull('cancelled_at'))
            ->sum('amount'), 2);
    }

    /**
     * Order paid amount = payments taken on the order itself + payments on account allocated to it.
     */
    public function paidForOrder(StitchingOrder $order): float
    {
        return round($this->netReceivedFor($order) + $this->allocatedTo($order), 2);
    }

    public function refreshOrderPaid(StitchingOrder $order): void
    {
        StitchingOrder::withTrashed()->whereKey($order->id)->update(['paid_amount' => $this->paidForOrder($order)]);
    }

    /**
     * Money paid back to a customer or walk-in (sale return refund, cancelled order advance).
     *
     * @param  array{payment_date: string, amount: float|string, method: PaymentMethod|string, reference_no?: string|null, notes?: string|null}  $data
     */
    public function refund(?Customer $customer, array $data, ?Model $source = null): Payment
    {
        return $this->record($customer, Payment::OUT, $data, $source);
    }

    /**
     * @param  array{payment_date: string, amount: float|string, method: PaymentMethod|string, reference_no?: string|null, notes?: string|null}  $data
     */
    private function record((Model&LedgerParty)|null $party, string $direction, array $data, ?Model $source): Payment
    {
        return DB::transaction(function () use ($party, $direction, $data, $source): Payment {
            $payment = Payment::create([
                'party_type' => $party?->getMorphClass(),
                'party_id' => $party?->getKey(),
                'direction' => $direction,
                'payment_date' => $data['payment_date'],
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'user_id' => auth()->id(),
            ]);

            if ($party) {
                $label = $direction === Payment::OUT ? ($party instanceof Supplier ? 'Payment' : 'Refund') : 'Received';
                $description = trim($label.' '.$payment->number.' ('.$payment->method->label().') '.($payment->reference_no ?? ''));
                $amount = (float) $payment->amount;

                $this->ledger->post(
                    $party,
                    LedgerEntry::PAYMENT,
                    $description,
                    $direction === Payment::OUT ? $amount : 0,
                    $direction === Payment::IN ? $amount : 0,
                    $payment,
                    $payment->payment_date,
                );
            }

            return $payment;
        });
    }

    /**
     * Cancel a payment. The ledger entry is reversed, never deleted.
     */
    public function cancel(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->cancelled_at !== null) {
                throw ValidationException::withMessages(['payment' => __('This payment is already cancelled.')]);
            }

            $this->ledger->reverseFor($payment, 'Cancelled payment '.$payment->number.': '.$reason);
            $payment->update(['cancelled_at' => now()]);

            $orders = $payment->allocations()->where('allocatable_type', (new StitchingOrder)->getMorphClass())->pluck('allocatable_id');

            if ($payment->source instanceof StitchingOrder) {
                $orders->push($payment->source_id);
            }

            StitchingOrder::withTrashed()->whereIn('id', $orders->unique())->get()->each(fn (StitchingOrder $order) => $this->refreshOrderPaid($order));
        });
    }

    /**
     * Cancel every live payment that was recorded as part of a document (for example paid at the time of purchase).
     */
    public function cancelForSource(Model $source, string $reason): void
    {
        Payment::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('cancelled_at')
            ->get()
            ->each(fn (Payment $payment) => $this->cancel($payment, $reason));
    }

    /**
     * Net money received against a document: payments in minus refunds out, ignoring cancelled ones.
     */
    public function netReceivedFor(Model $source): float
    {
        $payments = Payment::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('cancelled_at')
            ->get(['direction', 'amount']);

        return round(
            (float) $payments->where('direction', Payment::IN)->sum('amount') - (float) $payments->where('direction', Payment::OUT)->sum('amount'),
            2,
        );
    }
}
