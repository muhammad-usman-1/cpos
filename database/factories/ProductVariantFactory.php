<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Stock starts at zero: use StockService (or a purchase) to add stock so the ledger stays correct.
 *
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'colour_id' => null,
            'stock_qty' => 0,
            'min_stock' => fake()->randomElement([0, 2, 3, 5]),
            'is_active' => true,
        ];
    }
}
