<?php

namespace Database\Factories;

use App\Enums\ProductGender;
use App\Enums\ProductUnit;
use App\Enums\Season;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->numberBetween(12, 60) * 100;
        $gender = fake()->randomElement([ProductGender::Gents, ProductGender::Ladies, ProductGender::Ladies, ProductGender::Kids]);

        return [
            'design_code' => 'D-'.fake()->unique()->numberBetween(100, 999),
            'name' => $gender === ProductGender::Ladies
                ? fake()->randomElement(['Printed', 'Embroidered', 'Digital print', 'Jacquard', 'Chikankari']).' '.fake()->randomElement(['lawn suit', 'cotton suit', 'khaddar suit'])
                : fake()->randomElement(['Wash and wear', 'Boski', 'Cotton', 'Khaddar', 'Karandi']).' '.($gender === ProductGender::Kids ? 'kids suit' : 'gents suit'),
            'season' => fake()->randomElement(Season::cases()),
            'gender' => $gender,
            'piece_type' => fake()->randomElement($gender->pieceTypes()),
            'unit' => ProductUnit::Suit,
            'fabric_length' => fake()->randomElement([2.5, 4.5, 6.5, 7.5]),
            'length_unit' => 'meter',
            'cost_price' => $cost,
            'average_cost' => $cost,
            'sale_price' => round($cost * 1.4, -1),
            'min_sale_price' => round($cost * 1.2, -1),
            'description' => fake()->optional(0.4)->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Fabric sold by the meter (STK12).
     */
    public function looseFabric(): static
    {
        return $this->state(function (): array {
            $cost = fake()->numberBetween(25, 90) * 10;

            return [
                'name' => fake()->randomElement(['Plain cotton', 'Wash and wear', 'Karandi', 'Boski']).' by meter',
                'unit' => ProductUnit::Meter,
                'gender' => null,
                'piece_type' => null,
                'fabric_length' => null,
                'cost_price' => $cost,
                'average_cost' => $cost,
                'sale_price' => round($cost * 1.35, -1),
                'min_sale_price' => round($cost * 1.15, -1),
            ];
        });
    }
}
