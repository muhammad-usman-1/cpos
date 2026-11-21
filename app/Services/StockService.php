<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Every change to stock goes through this service, so each one leaves a stock movement entry (STK06, STK10).
 * Callers must run inside a database transaction.
 */
class StockService
{
    /**
     * @param  array{unit_cost?: float|string|null, reason?: AdjustmentReason|null, notes?: string|null, reference?: Model|null}  $options
     *
     * @throws ValidationException
     */
    public function record(ProductVariant|int $variant, StockMovementType $type, float $qtyIn, float $qtyOut, array $options = []): StockMovement
    {
        $variantId = $variant instanceof ProductVariant ? $variant->id : $variant;

        /** @var ProductVariant $locked */
        $locked = ProductVariant::withTrashed()->with('product', 'colour')->lockForUpdate()->findOrFail($variantId);

        $newBalance = round((float) $locked->stock_qty + $qtyIn - $qtyOut, 3);

        if ($qtyOut > 0 && $newBalance < 0 && ! Setting::allowsNegativeStock()) {
            throw ValidationException::withMessages([
                'stock' => __('Not enough stock for :display_name. Available: :stock_qty, required: :value.', ['display_name' => $locked->displayName(), 'stock_qty' => $this->formatQty((float) $locked->stock_qty), 'value' => $this->formatQty($qtyOut)]),
            ]);
        }

        ProductVariant::withTrashed()->whereKey($locked->id)->update(['stock_qty' => $newBalance]);

        $reference = $options['reference'] ?? null;

        return StockMovement::create([
            'product_variant_id' => $locked->id,
            'type' => $type,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'balance_after' => $newBalance,
            'unit_cost' => $options['unit_cost'] ?? null,
            'reason' => $options['reason'] ?? null,
            'notes' => $options['notes'] ?? null,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => auth()->id(),
            'moved_at' => now(),
        ]);
    }

    /**
     * Post opposite movements for every live movement of a document (used when a purchase is edited or cancelled).
     *
     * @throws ValidationException
     */
    public function reverseFor(Model $reference, string $notes): void
    {
        $movements = StockMovement::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->whereNull('reversed_at')
            ->where('type', '!=', StockMovementType::Reversal)
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            $this->record($movement->product_variant_id, StockMovementType::Reversal, (float) $movement->qty_out, (float) $movement->qty_in, [
                'unit_cost' => $movement->unit_cost,
                'notes' => $notes,
                'reference' => $reference,
            ]);

            $movement->forceFill(['reversed_at' => now()])->save();
        }
    }

    public function formatQty(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
