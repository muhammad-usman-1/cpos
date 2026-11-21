<?php

namespace Database\Factories;

use App\Models\Fabric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fabric>
 */
class FabricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Lawn', 'Cotton', 'Khaddar', 'Linen', 'Silk', 'Chiffon', 'Wash and wear', 'Karandi', 'Cambric']),
            'is_active' => true,
        ];
    }
}
