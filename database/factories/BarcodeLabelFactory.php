<?php

namespace Database\Factories;

use App\Models\BarcodeLabel;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarcodeLabel>
 */
class BarcodeLabelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'price' => fake()->randomElement([1500, 2000, 2500, 3200, 4500]),
            'copies_printed' => fake()->numberBetween(1, 30),
            'is_active' => true,
        ];
    }

    /**
     * Give the label its PL… code once it has an id, as the generator does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (BarcodeLabel $label): void {
            $label->forceFill(['code' => BarcodeLabel::codeFor($label->id)])->save();
        });
    }
}
