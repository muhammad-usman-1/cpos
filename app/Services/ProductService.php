<?php

namespace App\Services;

use App\Enums\AdjustmentReason;
use App\Enums\ProductUnit;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(private StockService $stock) {}

    /**
     * Create or update a product from the short form. A new product gets one stock line; its barcode / code
     * is the one typed, or generated when left empty. Changing the quantity on edit is posted to the stock
     * ledger as a correction, so stock history stays complete.
     *
     * Older products with stock in several colours take one quantity per colour (quantities[variant id]).
     *
     * @param  array{barcode?: string|null, name: string, quantity?: float|string|null, quantities?: array<int|string, float|string|null>, cost_price?: float|string|null, sale_price: float|string, min_stock?: float|string|null, description?: string|null, is_active?: bool}  $data
     */
    public function save(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $isNew = ! $product->exists;

            $product->fill([
                'name' => $data['name'],
                'sale_price' => $data['sale_price'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('cost_price', $data) && $data['cost_price'] !== null) {
                $product->cost_price = $data['cost_price'];
            }

            if ($isNew) {
                $product->fill([
                    'design_code' => $data['barcode'] ?? '',
                    'unit' => ProductUnit::Suit,
                    'average_cost' => $product->cost_price ?? 0,
                    'created_by' => auth()->id(),
                ]);
            } elseif (! empty($data['barcode'])) {
                $product->design_code = $data['barcode'];
            }

            $product->save();

            $variants = $product->variants()->withTrashed()->get();

            if ($variants->count() > 1) {
                foreach ($data['quantities'] ?? [] as $variantId => $quantity) {
                    $colourVariant = $variants->whereNull('deleted_at')->firstWhere('id', (int) $variantId);

                    if ($colourVariant) {
                        $this->setQuantity($product, $colourVariant, $quantity, false);
                    }
                }

                return $product;
            }

            /** @var ProductVariant $variant */
            $variant = $variants->first() ?? $product->variants()->create(['sku' => $data['barcode'] ?? null]);

            $variant->update(array_filter([
                'sku' => $data['barcode'] ?? null,
                'min_stock' => (float) ($data['min_stock'] ?? 0),
                'is_active' => $data['is_active'] ?? true,
            ], fn ($value) => $value !== null));

            $this->setQuantity($product, $variant->refresh(), $data['quantity'] ?? null, $isNew);

            return $product;
        });
    }

    private function setQuantity(Product $product, ProductVariant $variant, float|string|null $quantity, bool $isNew): void
    {
        if ($quantity === null || $quantity === '') {
            return;
        }

        $difference = round((float) $quantity - (float) $variant->stock_qty, 3);

        if ($difference == 0.0) {
            return;
        }

        if ($isNew) {
            $this->stock->record($variant, StockMovementType::Opening, $difference, 0, [
                'unit_cost' => $product->cost_price,
                'notes' => 'Opening stock',
            ]);

            return;
        }

        $this->stock->record($variant, StockMovementType::Adjustment, max($difference, 0), max(-$difference, 0), [
            'reason' => AdjustmentReason::Correction,
            'notes' => 'Quantity changed on the product form',
        ]);
    }
}
