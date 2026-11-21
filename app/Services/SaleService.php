<?php

namespace App\Services;

use App\Enums\RefundMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\BarcodeLabel;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Counter sales (SAL01 to SAL08): stock out, customer ledger for credit, one payment row per tender.
 */
class SaleService
{
    public function __construct(
        private StockService $stock,
        private LedgerService $ledger,
        private PaymentService $payments,
    ) {}

    /**
     * Park the bill without touching stock or money, to resume later (SAL08).
     *
     * @param  array<string, mixed>  $data
     */
    public function hold(array $data, ?Sale $held = null): Sale
    {
        return DB::transaction(function () use ($data, $held): Sale {
            $sale = $held ? $this->lockHeld($held) : new Sale(['created_by' => auth()->id()]);
            $lines = $this->buildLines($data['items']);
            $totals = $this->totals($lines, $data);

            $sale->fill($totals + [
                'customer_id' => $data['customer_id'] ?? null,
                'status' => SaleStatus::Held,
                'sale_date' => today(),
                'notes' => $data['notes'] ?? null,
                'paid_amount' => 0,
                'credit_amount' => 0,
            ])->save();

            $sale->items()->delete();
            $sale->items()->createMany($lines->map(fn (array $line) => collect($line)->except('product')->all())->all());

            return $sale;
        });
    }

    /**
     * Complete a new sale, or a held bill when $held is given.
     *
     * @param  array<string, mixed>  $data
     */
    public function complete(array $data, ?Sale $held = null): Sale
    {
        return DB::transaction(function () use ($data, $held): Sale {
            $sale = $held ? $this->lockHeld($held) : new Sale(['created_by' => auth()->id()]);
            $customer = isset($data['customer_id']) ? Customer::query()->findOrFail($data['customer_id']) : null;
            $lines = $this->buildLines($data['items']);
            $this->guardMinimumPrices($lines);

            $totals = $this->totals($lines, $data);
            $tenders = collect($data['payments'] ?? [])->filter(fn (array $payment) => (float) ($payment['amount'] ?? 0) > 0)->values();
            $paid = round((float) $tenders->sum(fn (array $payment) => (float) $payment['amount']), 2);
            $credit = round($totals['total'] - $paid, 2);

            if ($paid - $totals['total'] > 0.009) {
                throw ValidationException::withMessages(['payments' => __('Payments are more than the bill total. Enter the amount kept, and give the rest back as change.')]);
            }

            if ($credit > 0.009 && ! $customer) {
                throw ValidationException::withMessages(['customer_id' => __('Select a customer to give the unpaid :amount on credit (udhaar).', ['amount' => number_format($credit, 2)])]);
            }

            // Cash handed over: kept only when it covers the cash part of the bill, so change is never negative.
            $cashPart = round((float) $tenders->where('method', 'cash')->sum(fn (array $payment) => (float) $payment['amount']), 2);
            $cashReceived = isset($data['cash_received']) && (float) $data['cash_received'] >= $cashPart && $cashPart > 0
                ? round((float) $data['cash_received'], 2)
                : null;

            // A bill made offline keeps the time it was made at the counter, not the upload time.
            $madeAt = isset($data['offline_at']) ? Carbon::parse($data['offline_at'])->setTimezone(config('app.timezone')) : now();

            $sale->fill($totals + [
                'client_uuid' => $data['client_uuid'] ?? $sale->client_uuid,
                'customer_id' => $customer?->id,
                'status' => SaleStatus::Completed,
                'sale_date' => $madeAt->toDateString(),
                'paid_amount' => $paid,
                'cash_received' => $cashReceived,
                'credit_amount' => max($credit, 0),
                'notes' => $data['notes'] ?? null,
                'completed_at' => $madeAt,
                'created_by' => $sale->created_by ?? auth()->id(),
            ])->save();

            $sale->items()->delete();

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $item = $sale->items()->create(collect($line)->except('product')->all() + ['unit_cost' => $product->average_cost]);

                $this->stock->record($item->product_variant_id, StockMovementType::Sale, 0, (float) $item->quantity, [
                    'unit_cost' => $product->average_cost,
                    'notes' => 'Sale '.$sale->number,
                    'reference' => $sale,
                ]);
            }

            if ($customer) {
                $this->ledger->post($customer, LedgerEntry::SALE, 'Sale '.$sale->number, (float) $sale->total, 0, $sale, $sale->sale_date);
            }

            foreach ($tenders as $tender) {
                $this->payments->receive($customer, [
                    'payment_date' => $sale->sale_date->toDateString(),
                    'amount' => $tender['amount'],
                    'method' => $tender['method'],
                    'reference_no' => $tender['reference_no'] ?? null,
                    'notes' => 'Sale '.$sale->number,
                ], $sale);
            }

            return $sale;
        });
    }

    /**
     * Return items from a completed sale (SAL07). Refunds at the net price charged, including the bill discount share.
     *
     * @param  array{return_date: string, refund_method: string, notes?: string|null, items: array<int|string, array{sale_item_id: int|string, quantity?: float|string|null}>}  $data
     */
    public function recordReturn(Sale $sale, array $data): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data): SaleReturn {
            $sale = Sale::query()->with('customer')->lockForUpdate()->findOrFail($sale->id);
            $refundMethod = RefundMethod::from($data['refund_method']);

            if ($sale->isHeld()) {
                throw ValidationException::withMessages(['sale' => __('A held bill has nothing to return.')]);
            }

            if ($refundMethod === RefundMethod::CustomerBalance && ! $sale->customer) {
                throw ValidationException::withMessages(['refund_method' => __('Walk-in sales must be refunded in money, or attach the customer to the sale first.')]);
            }

            $lines = collect($data['items'])->filter(fn (array $line) => (float) ($line['quantity'] ?? 0) > 0);

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('Enter a return quantity for at least one item.')]);
            }

            $return = SaleReturn::create([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'return_date' => $data['return_date'],
                'refund_method' => $refundMethod,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $factor = $sale->netFactor();
            $total = 0.0;

            foreach ($lines as $line) {
                /** @var SaleItem $item */
                $item = $sale->items()->with('variant.product', 'variant.colour')->lockForUpdate()->findOrFail($line['sale_item_id']);
                $quantity = round((float) $line['quantity'], 3);

                if ($quantity > $item->returnableQty()) {
                    throw ValidationException::withMessages([
                        'items' => __('Only :value of :display_name can still be returned.', ['value' => $this->stock->formatQty($item->returnableQty()), 'display_name' => $item->variant->displayName()]),
                    ]);
                }

                $price = round((float) $item->amount / (float) $item->quantity * $factor, 2);
                $amount = round($price * $quantity, 2);
                $total += $amount;

                $return->items()->create([
                    'sale_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'amount' => $amount,
                ]);

                $this->blendReturnedCost($item->product_variant_id, $quantity, (float) $item->unit_cost);
                $this->stock->record($item->product_variant_id, StockMovementType::SaleReturn, $quantity, 0, [
                    'unit_cost' => $item->unit_cost,
                    'notes' => 'Return '.$return->number.' against '.$sale->number,
                    'reference' => $return,
                ]);

                $item->update(['returned_qty' => round((float) $item->returned_qty + $quantity, 3)]);
            }

            $total = round($total, 2);
            $return->update(['total' => $total]);

            if ($sale->customer) {
                $this->ledger->post($sale->customer, LedgerEntry::SALE_RETURN, 'Sale return '.$return->number.' against '.$sale->number, 0, $total, $return, $data['return_date']);
            }

            if ($refundMethod->paymentMethod() && $total > 0) {
                $this->payments->refund($sale->customer, [
                    'payment_date' => $data['return_date'],
                    'amount' => $total,
                    'method' => $refundMethod->paymentMethod(),
                    'notes' => 'Refund for '.$return->number,
                ], $return);
            }

            return $return;
        });
    }

    public function discardHeld(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $this->lockHeld($sale)->delete();
        });
    }

    private function lockHeld(Sale $sale): Sale
    {
        $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

        if (! $sale->isHeld()) {
            throw ValidationException::withMessages(['sale' => __('This bill has already been completed.')]);
        }

        return $sale;
    }

    /**
     * @param  array<int|string, array{product_variant_id: int|string, barcode_label_id?: int|string|null, quantity: float|string, price: float|string, discount?: float|string|null}>  $items
     * @return Collection<int, array{product_variant_id: int, barcode_label_id: int|null, quantity: float, price: float, discount: float, amount: float, product: Product}>
     */
    private function buildLines(array $items): Collection
    {
        $variants = ProductVariant::query()->with('product')->whereIn('id', array_column($items, 'product_variant_id'))->get()->keyBy('id');
        $labelVariants = BarcodeLabel::query()->whereIn('id', array_filter(array_column($items, 'barcode_label_id')))->pluck('product_variant_id', 'id');

        return collect($items)->values()->map(function (array $line, int $index) use ($variants, $labelVariants): array {
            $variant = $variants->get($line['product_variant_id']);
            $labelId = (int) ($line['barcode_label_id'] ?? 0);
            $quantity = round((float) $line['quantity'], 3);
            $price = round((float) $line['price'], 2);
            $discount = round((float) ($line['discount'] ?? 0), 2);
            $amount = round($quantity * $price - $discount, 2);

            if ($amount < 0) {
                throw ValidationException::withMessages(["items.$index.discount" => __('Discount on :display_name is more than the line amount.', ['display_name' => $variant->displayName()])]);
            }

            return [
                'product_variant_id' => $variant->id,
                // Kept only when the label really belongs to this product.
                'barcode_label_id' => $labelId && (int) $labelVariants->get($labelId) === $variant->id ? $labelId : null,
                'quantity' => $quantity,
                'price' => $price,
                'discount' => $discount,
                'amount' => $amount,
                'product' => $variant->product,
            ];
        });
    }

    /**
     * @param  Collection<int, array{product_variant_id: int, quantity: float, price: float, discount: float, amount: float, product: Product}>  $lines
     */
    private function guardMinimumPrices(Collection $lines): void
    {
        if (auth()->user()?->can('sales.below_min_price')) {
            return;
        }

        foreach ($lines->values() as $index => $line) {
            $minimum = (float) $line['product']->min_sale_price;

            if ($minimum > 0 && $line['quantity'] > 0 && $minimum - 0.004 > $line['amount'] / $line['quantity']) {
                throw ValidationException::withMessages([
                    "items.$index.price" => __(':name cannot be sold below :amount each without permission.', ['name' => $line['product']->name, 'amount' => number_format($minimum, 2)]),
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, array{product_variant_id: int, quantity: float, price: float, discount: float, amount: float, product: Product}>  $lines
     * @param  array<string, mixed>  $data
     * @return array{subtotal: float, item_discount: float, bill_discount: float, extra_charges: float, total: float}
     */
    private function totals(Collection $lines, array $data): array
    {
        $subtotal = round($lines->sum(fn (array $line) => $line['quantity'] * $line['price']), 2);
        $itemDiscount = round($lines->sum('discount'), 2);
        $billDiscount = round((float) ($data['bill_discount'] ?? 0), 2);
        $extraCharges = round((float) ($data['extra_charges'] ?? 0), 2);
        $total = round($subtotal - $itemDiscount - $billDiscount + $extraCharges, 2);

        if ($billDiscount > $subtotal - $itemDiscount) {
            throw ValidationException::withMessages(['bill_discount' => __('Bill discount cannot be more than the item total.')]);
        }

        return [
            'subtotal' => $subtotal,
            'item_discount' => $itemDiscount,
            'bill_discount' => $billDiscount,
            'extra_charges' => $extraCharges,
            'total' => $total,
        ];
    }

    /**
     * Returned goods come back at the cost they left with, blended into the average.
     */
    private function blendReturnedCost(int $variantId, float $quantity, float $unitCost): void
    {
        $productId = ProductVariant::withTrashed()->whereKey($variantId)->value('product_id');
        $product = Product::withTrashed()->lockForUpdate()->findOrFail($productId);
        $stockBefore = max(0, (float) $product->variants()->withTrashed()->sum('stock_qty'));

        if ($stockBefore + $quantity <= 0) {
            return;
        }

        $average = ($stockBefore * (float) $product->average_cost + $quantity * $unitCost) / ($stockBefore + $quantity);

        Product::withTrashed()->whereKey($productId)->update(['average_cost' => round($average, 2)]);
    }
}
