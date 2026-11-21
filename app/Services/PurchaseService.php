<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Purchases update stock, product cost and the supplier ledger in one transaction (PUR02, PUR03, PUR05).
 */
class PurchaseService
{
    public function __construct(
        private StockService $stock,
        private LedgerService $ledger,
        private PaymentService $payments,
    ) {}

    /**
     * @param  array{supplier_id: int, purchase_date: string, invoice_no?: string|null, discount?: float|string|null, other_charges?: float|string|null, paid_amount?: float|string|null, payment_method?: string|null, notes?: string|null, items: list<array{product_variant_id: int, quantity: float|string, rate: float|string}>}  $data
     */
    public function create(array $data, ?UploadedFile $attachment = null): Purchase
    {
        return DB::transaction(function () use ($data, $attachment): Purchase {
            $purchase = new Purchase(['created_by' => auth()->id(), 'status' => PurchaseStatus::Posted]);

            $this->fillAndSave($purchase, $data, $attachment);
            $this->apply($purchase);

            return $purchase;
        });
    }

    /**
     * Editing reverses the old stock and ledger effect, then posts the new one, so history stays traceable.
     *
     * @param  array{supplier_id: int, purchase_date: string, invoice_no?: string|null, discount?: float|string|null, other_charges?: float|string|null, paid_amount?: float|string|null, payment_method?: string|null, notes?: string|null, items: list<array{product_variant_id: int, quantity: float|string, rate: float|string}>}  $data
     */
    public function update(Purchase $purchase, array $data, ?UploadedFile $attachment = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $attachment): Purchase {
            $purchase = $this->lockEditable($purchase);

            $this->unapply($purchase, 'Edit of '.$purchase->number);
            $purchase->items()->delete();

            $this->fillAndSave($purchase, $data, $attachment);
            $this->apply($purchase);

            return $purchase;
        });
    }

    public function cancel(Purchase $purchase, string $reason): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason): Purchase {
            $purchase = $this->lockEditable($purchase);

            $this->unapply($purchase, 'Cancelled '.$purchase->number.': '.$reason);

            $purchase->update([
                'status' => PurchaseStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $reason,
            ]);

            $this->refreshLatestCost($purchase);

            return $purchase;
        });
    }

    /**
     * Return goods to the supplier: stock goes out and the supplier balance is reduced (PUR04).
     *
     * @param  array{return_date: string, notes?: string|null, items: array<int|string, array{purchase_item_id: int, quantity: float|string|null}>}  $data
     */
    public function recordReturn(Purchase $purchase, array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $data): PurchaseReturn {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if ($purchase->isCancelled()) {
                throw ValidationException::withMessages(['purchase' => __('A cancelled purchase cannot be returned.')]);
            }

            $lines = collect($data['items'])->filter(fn (array $line): bool => (float) ($line['quantity'] ?? 0) > 0);

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('Enter a return quantity for at least one item.')]);
            }

            $return = PurchaseReturn::create([
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'return_date' => $data['return_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $total = 0.0;

            foreach ($lines as $line) {
                /** @var PurchaseItem $item */
                $item = $purchase->items()->lockForUpdate()->findOrFail($line['purchase_item_id']);
                $quantity = round((float) $line['quantity'], 3);

                if ($quantity > $item->returnableQty()) {
                    throw ValidationException::withMessages([
                        'items' => __('Return quantity for :display_name is more than the :value still returnable.', ['display_name' => $item->variant->displayName(), 'value' => $this->stock->formatQty($item->returnableQty())]),
                    ]);
                }

                $amount = round($quantity * (float) $item->rate, 2);
                $total += $amount;

                $return->items()->create([
                    'purchase_item_id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $quantity,
                    'rate' => $item->rate,
                    'amount' => $amount,
                ]);

                $this->removeFromAverageCost($item->product_variant_id, $quantity, (float) $item->rate);
                $this->stock->record($item->product_variant_id, StockMovementType::PurchaseReturn, 0, $quantity, [
                    'unit_cost' => $item->rate,
                    'notes' => 'Return '.$return->number.' against '.$purchase->number,
                    'reference' => $return,
                ]);

                $item->update(['returned_qty' => round((float) $item->returned_qty + $quantity, 3)]);
            }

            $return->update(['total' => round($total, 2)]);

            $this->ledger->post(
                $purchase->supplier,
                LedgerEntry::PURCHASE_RETURN,
                'Purchase return '.$return->number.' against '.$purchase->number,
                $total,
                0,
                $return,
                $data['return_date'],
            );

            return $return;
        });
    }

    /**
     * @param  array{supplier_id: int, purchase_date: string, invoice_no?: string|null, discount?: float|string|null, other_charges?: float|string|null, paid_amount?: float|string|null, payment_method?: string|null, notes?: string|null, items: list<array{product_variant_id: int, quantity: float|string, rate: float|string}>}  $data
     */
    private function fillAndSave(Purchase $purchase, array $data, ?UploadedFile $attachment): void
    {
        $lines = collect($data['items'])->map(fn (array $line): array => [
            'product_variant_id' => (int) $line['product_variant_id'],
            'quantity' => round((float) $line['quantity'], 3),
            'rate' => round((float) $line['rate'], 2),
            'amount' => round((float) $line['quantity'] * (float) $line['rate'], 2),
        ]);

        $subtotal = round($lines->sum('amount'), 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $otherCharges = round((float) ($data['other_charges'] ?? 0), 2);
        $total = round($subtotal - $discount + $otherCharges, 2);
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);

        if ($total < 0) {
            throw ValidationException::withMessages(['discount' => __('Discount cannot be more than the purchase amount.')]);
        }

        if ($paid > $total) {
            throw ValidationException::withMessages(['paid_amount' => __('Amount paid cannot be more than the purchase total.')]);
        }

        $oldAttachment = $purchase->attachment_path;

        $purchase->fill([
            'supplier_id' => $data['supplier_id'],
            'purchase_date' => $data['purchase_date'],
            'invoice_no' => $data['invoice_no'] ?? null,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'other_charges' => $otherCharges,
            'total' => $total,
            'paid_amount' => $paid,
            'payment_method' => $paid > 0 ? ($data['payment_method'] ?? 'cash') : null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($attachment) {
            $purchase->attachment_path = $attachment->store('purchase-invoices', 'public');
        }

        $purchase->save();

        if ($attachment && $oldAttachment) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($oldAttachment));
        }

        $purchase->items()->createMany($lines->all());
    }

    private function apply(Purchase $purchase): void
    {
        $purchase->load('items', 'supplier');

        foreach ($purchase->items as $item) {
            $this->addToCost($item->product_variant_id, (float) $item->quantity, (float) $item->rate);
            $this->stock->record($item->product_variant_id, StockMovementType::Purchase, (float) $item->quantity, 0, [
                'unit_cost' => $item->rate,
                'notes' => trim('Purchase '.$purchase->number.' '.($purchase->invoice_no ? 'inv '.$purchase->invoice_no : '')),
                'reference' => $purchase,
            ]);
        }

        $description = 'Purchase '.$purchase->number.($purchase->invoice_no ? ' (invoice '.$purchase->invoice_no.')' : '');
        $this->ledger->post($purchase->supplier, LedgerEntry::PURCHASE, $description, 0, (float) $purchase->total, $purchase, $purchase->purchase_date);

        if ((float) $purchase->paid_amount > 0) {
            $this->payments->paySupplier($purchase->supplier, [
                'payment_date' => $purchase->purchase_date->toDateString(),
                'amount' => $purchase->paid_amount,
                'method' => $purchase->payment_method,
                'notes' => 'Paid at purchase '.$purchase->number,
            ], $purchase);
        }
    }

    private function unapply(Purchase $purchase, string $reason): void
    {
        $purchase->load('items');

        foreach ($purchase->items as $item) {
            $this->removeFromAverageCost($item->product_variant_id, (float) $item->quantity, (float) $item->rate);
        }

        $this->stock->reverseFor($purchase, $reason);
        $this->ledger->reverseFor($purchase, $reason);
        $this->payments->cancelForSource($purchase, $reason);
    }

    private function lockEditable(Purchase $purchase): Purchase
    {
        $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

        if (! $purchase->isEditable()) {
            throw ValidationException::withMessages([
                'purchase' => $purchase->isCancelled()
                    ? 'This purchase is already cancelled.'
                    : 'This purchase has returns against it and can no longer be edited or cancelled.',
            ]);
        }

        return $purchase;
    }

    /**
     * Blend the purchased quantity into the weighted average cost and set the latest cost.
     */
    private function addToCost(int $variantId, float $quantity, float $rate): void
    {
        $product = $this->lockProductFor($variantId);
        $stockBefore = (float) $product->variants()->withTrashed()->sum('stock_qty');

        $average = $stockBefore > 0
            ? (($stockBefore * (float) $product->average_cost) + ($quantity * $rate)) / ($stockBefore + $quantity)
            : $rate;

        Product::withTrashed()->whereKey($product->id)->update([
            'cost_price' => $rate,
            'average_cost' => round($average, 2),
        ]);
    }

    /**
     * Take a quantity out of the weighted average at the rate it came in. Call before stock is reduced.
     */
    private function removeFromAverageCost(int $variantId, float $quantity, float $rate): void
    {
        $product = $this->lockProductFor($variantId);
        $stockBefore = (float) $product->variants()->withTrashed()->sum('stock_qty');
        $remaining = $stockBefore - $quantity;

        if ($remaining <= 0) {
            return;
        }

        $average = max(0, (($stockBefore * (float) $product->average_cost) - ($quantity * $rate)) / $remaining);

        Product::withTrashed()->whereKey($product->id)->update(['average_cost' => round($average, 2)]);
    }

    /**
     * After a cancellation, the latest cost falls back to the most recent remaining purchase rate.
     */
    private function refreshLatestCost(Purchase $purchase): void
    {
        $productIds = ProductVariant::withTrashed()
            ->whereIn('id', $purchase->items->pluck('product_variant_id'))
            ->pluck('product_id')
            ->unique();

        foreach ($productIds as $productId) {
            $latestRate = PurchaseItem::query()
                ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
                ->join('product_variants', 'product_variants.id', '=', 'purchase_items.product_variant_id')
                ->where('product_variants.product_id', $productId)
                ->where('purchases.status', PurchaseStatus::Posted)
                ->whereNull('purchases.deleted_at')
                ->orderByDesc('purchases.purchase_date')
                ->orderByDesc('purchases.id')
                ->value('purchase_items.rate');

            if ($latestRate !== null) {
                Product::withTrashed()->whereKey($productId)->update(['cost_price' => $latestRate]);
            }
        }
    }

    private function lockProductFor(int $variantId): Product
    {
        $productId = ProductVariant::withTrashed()->whereKey($variantId)->value('product_id');

        return Product::withTrashed()->lockForUpdate()->findOrFail($productId);
    }
}
