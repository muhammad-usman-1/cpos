<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Gul Ahmed', 'Khaadi', 'Alkaram', 'Sapphire', 'Nishat Linen', 'J.', 'Bonanza', 'Sana Safinaz', 'Maria B', 'Local']),
            'is_active' => true,
        ];
    }
}
