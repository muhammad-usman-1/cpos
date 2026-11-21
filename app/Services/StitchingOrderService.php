<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerProfile;
use App\Models\LedgerEntry;
use App\Models\Sale;
use App\Models\StitchingOrder;
use App\Models\TailorAssignment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stitching and alteration orders (ORD01 to ORD10). The order total goes on the customer's account when the order is
 * taken; advances and the final balance are payments against it.
 */
class StitchingOrderService
{
    public function __construct(
        private MeasurementService $measurements,
        private LedgerService $ledger,
        private PaymentService $payments,
        private NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $measurementPhoto = null): StitchingOrder
    {
        return DB::transaction(function () use ($data, $measurementPhoto): StitchingOrder {
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $order = new StitchingOrder([
                'customer_id' => $customer->id,
                'status' => OrderStatus::Received,
                'order_date' => today(),
                'created_by' => auth()->id(),
                'paid_amount' => 0,
            ]);

            $this->fill($order, $customer, $data, $measurementPhoto);
            $order->save();
            $this->saveExtras($order, $data['extras'] ?? []);
            $this->log($order, null, OrderStatus::Received, 'Order taken');
            $this->postCharges($order);

            $advance = (float) ($data['advance_amount'] ?? 0);

            if ($advance > 0) {
                $this->collect($order, $advance, $data['advance_method'] ?? PaymentMethod::Cash->value, $data['advance_reference'] ?? null, 'Advance');
            }

            return $order->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StitchingOrder $order, array $data, ?UploadedFile $measurementPhoto = null): StitchingOrder
    {
        return DB::transaction(function () use ($order, $data, $measurementPhoto): StitchingOrder {
            $order = $this->lockOpen($order);
            $oldTotal = (float) $order->total;

            $this->fill($order, $order->customer, $data, $measurementPhoto);
            $order->save();
            $order->extras()->delete();
            $this->saveExtras($order, $data['extras'] ?? []);

            if (round((float) $order->total - $oldTotal, 2) != 0.0) {
                $this->ledger->reverseFor($order, 'Charges changed on '.$order->number);
                $this->postCharges($order);
            }

            return $order->refresh();
        });
    }

    public function changeStatus(StitchingOrder $order, OrderStatus $status, ?string $notes = null): StitchingOrder
    {
        if (! in_array($status, OrderStatus::workflow(), true)) {
            throw ValidationException::withMessages(['status' => __('Use the delivery or cancel step for this status.')]);
        }

        return DB::transaction(function () use ($order, $status, $notes): StitchingOrder {
            $order = $this->lockOpen($order);

            if ($order->status === $status) {
                return $order;
            }

            $from = $order->status;
            $order->update([
                'status' => $status,
                'ready_at' => $status === OrderStatus::Ready ? now() : $order->ready_at,
            ]);
            $this->log($order, $from, $status, $notes);

            if ($status === OrderStatus::Ready) {
                $this->notifications->notifyOrderReady($order);
            }

            return $order;
        });
    }

    /**
     * Payment against the order, for example an extra advance.
     */
    public function addPayment(StitchingOrder $order, float $amount, string $method, ?string $reference = null): void
    {
        DB::transaction(function () use ($order, $amount, $method, $reference): void {
            $order = $this->lockOpen($order);
            $this->collect($order, $amount, $method, $reference, 'Payment');
        });
    }

    /**
     * Hand over the clothes: collect what is left and mark Delivered (ORD09).
     */
    public function deliver(StitchingOrder $order, float $amount, string $method, ?string $reference, ?string $notes): StitchingOrder
    {
        return DB::transaction(function () use ($order, $amount, $method, $reference, $notes): StitchingOrder {
            $order = $this->lockOpen($order);
            $from = $order->status;

            if ($amount > 0) {
                $this->collect($order, $amount, $method, $reference, 'Balance on delivery');
            }

            $order->refresh();
            $order->update(['status' => OrderStatus::Delivered, 'delivered_at' => now()]);

            $remaining = $order->balanceDue();
            $note = trim(($notes ?? '').($remaining > 0 ? ' Delivered with '.number_format($remaining, 2).' left on account.' : ''));
            $this->log($order, $from, OrderStatus::Delivered, $note !== '' ? $note : null);

            return $order;
        });
    }

    /**
     * Cancel the order. Charges are reversed; money already paid is refunded or kept as customer credit.
     */
    public function cancel(StitchingOrder $order, string $reason, ?string $refundMethod): StitchingOrder
    {
        return DB::transaction(function () use ($order, $reason, $refundMethod): StitchingOrder {
            $order = $this->lockOpen($order);
            $from = $order->status;

            $this->ledger->reverseFor($order, 'Cancelled '.$order->number.': '.$reason);

            $paid = $this->payments->paidForOrder($order);

            if ($refundMethod && $paid > 0) {
                $this->payments->refund($order->customer, [
                    'payment_date' => today()->toDateString(),
                    'amount' => $paid,
                    'method' => $refundMethod,
                    'notes' => 'Refund for cancelled '.$order->number,
                ], $order);
            }

            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->refreshPaid($order);
            $this->log($order, $from, OrderStatus::Cancelled, $reason.($refundMethod || $paid <= 0 ? '' : ' Advance kept as customer credit.'));

            return $order;
        });
    }

    /**
     * Give pieces to a tailor or workshop (ORD06).
     */
    public function assign(StitchingOrder $order, User $tailor, int $pieces, ?float $ratePerPiece, ?string $notes): TailorAssignment
    {
        return DB::transaction(function () use ($order, $tailor, $pieces, $ratePerPiece, $notes): TailorAssignment {
            $order = $this->lockOpen($order);

            $assignment = $order->assignments()->create([
                'tailor_id' => $tailor->id,
                'pieces_given' => $pieces,
                'given_at' => now(),
                'rate_per_piece' => $ratePerPiece,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            $order->update(['tailor_id' => $tailor->id]);
            $this->log($order, $order->status, $order->status, 'Given to '.$tailor->name.': '.$pieces.' '.str('piece')->plural($pieces));

            return $assignment;
        });
    }

    public function receivePieces(TailorAssignment $assignment, int $pieces): TailorAssignment
    {
        return DB::transaction(function () use ($assignment, $pieces): TailorAssignment {
            $assignment = TailorAssignment::query()->with('order', 'tailor')->lockForUpdate()->findOrFail($assignment->id);

            if ($assignment->pieces_received + $pieces > $assignment->pieces_given) {
                throw ValidationException::withMessages(['pieces' => __('Only :pieces_received pieces are still with :name.', ['pieces_received' => ($assignment->pieces_given - $assignment->pieces_received), 'name' => $assignment->tailor->name])]);
            }

            $assignment->update([
                'pieces_received' => $assignment->pieces_received + $pieces,
                'received_at' => now(),
            ]);

            $order = $assignment->order;
            $this->log($order, $order->status, $order->status, 'Received from '.$assignment->tailor->name.': '.$pieces.' '.str('piece')->plural($pieces));

            return $assignment;
        });
    }

    /**
     * Recalculate paid amount from live payments against the order.
     */
    public function refreshPaid(StitchingOrder $order): void
    {
        $this->payments->refreshOrderPaid($order);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(StitchingOrder $order, Customer $customer, array $data, ?UploadedFile $measurementPhoto): void
    {
        $profile = CustomerProfile::query()->where('customer_id', $customer->id)->findOrFail($data['customer_profile_id']);

        if (! empty($data['sale_id']) && ! Sale::query()->whereKey($data['sale_id'])->where('customer_id', $customer->id)->exists()) {
            throw ValidationException::withMessages(['sale_id' => __('That sale does not belong to this customer.')]);
        }

        if (! empty($data['parent_order_id']) && ! StitchingOrder::query()->whereKey($data['parent_order_id'])->where('customer_id', $customer->id)->exists()) {
            throw ValidationException::withMessages(['parent_order_id' => __('The original order does not belong to this customer.')]);
        }

        $values = array_filter((array) ($data['measurements'] ?? []), fn ($value) => trim((string) $value) !== '');
        $measurement = $values !== [] || $measurementPhoto
            ? $this->measurements->save($profile, $values, $data['measurement_unit'] ?? 'in', $data['measurement_notes'] ?? null, $measurementPhoto)
            : $profile->currentMeasurement;

        $extrasTotal = round(collect($data['extras'] ?? [])->sum(fn ($extra) => (float) ($extra['amount'] ?? 0)), 2);
        $charges = round((float) $data['stitching_charges'], 2);

        $order->fill([
            'type' => $data['type'],
            'parent_order_id' => $data['parent_order_id'] ?? null,
            'customer_profile_id' => $profile->id,
            'measurement_id' => $measurement?->id,
            'cloth_source' => $data['cloth_source'],
            'sale_id' => $data['cloth_source'] === 'shop' ? ($data['sale_id'] ?? null) : null,
            'cloth_description' => $data['cloth_description'] ?? null,
            'cloth_quantity' => $data['cloth_quantity'] ?? null,
            'pieces' => $data['pieces'],
            'design_notes' => $data['design_notes'] ?? null,
            'stitching_charges' => $charges,
            'extras_total' => $extrasTotal,
            'total' => round($charges + $extrasTotal, 2),
            'promised_date' => $data['promised_date'],
        ]);
    }

    /**
     * @param  array<int|string, array{description?: string|null, amount?: float|string|null}>  $extras
     */
    private function saveExtras(StitchingOrder $order, array $extras): void
    {
        foreach ($extras as $extra) {
            if (trim((string) ($extra['description'] ?? '')) === '' || (float) ($extra['amount'] ?? 0) <= 0) {
                continue;
            }

            $order->extras()->create(['description' => $extra['description'], 'amount' => round((float) $extra['amount'], 2)]);
        }
    }

    private function postCharges(StitchingOrder $order): void
    {
        if ((float) $order->total <= 0) {
            return;
        }

        $this->ledger->post($order->customer, LedgerEntry::STITCHING_ORDER, ucfirst($order->type->value).' order '.$order->number, (float) $order->total, 0, $order, $order->order_date);
    }

    private function collect(StitchingOrder $order, float $amount, string $method, ?string $reference, string $label): void
    {
        $balance = round((float) $order->total - $this->payments->paidForOrder($order), 2);

        if ($amount - $balance > 0.009) {
            throw ValidationException::withMessages(['amount' => __('Amount is more than the balance of :amount.', ['amount' => number_format(max($balance, 0), 2)])]);
        }

        $this->payments->receive($order->customer, [
            'payment_date' => today()->toDateString(),
            'amount' => $amount,
            'method' => $method,
            'reference_no' => $reference,
            'notes' => $label.' for '.$order->number,
        ], $order);

        $this->refreshPaid($order);
    }

    private function lockOpen(StitchingOrder $order): StitchingOrder
    {
        $order = StitchingOrder::query()->with('customer')->lockForUpdate()->findOrFail($order->id);

        if ($order->status->isClosed()) {
            throw ValidationException::withMessages(['order' => __('This order is already :label.', ['label' => $order->status->label()])]);
        }

        return $order;
    }

    private function log(StitchingOrder $order, ?OrderStatus $from, OrderStatus $to, ?string $notes): void
    {
        $order->statusLogs()->create([
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
