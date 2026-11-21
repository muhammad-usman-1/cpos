<?php

namespace Database\Factories;

use App\Models\Colour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Colour>
 */
class ColourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['White', 'Black', 'Navy Blue', 'Sky Blue', 'Maroon', 'Pink', 'Mustard', 'Sea Green', 'Grey', 'Off White', 'Brown', 'Peach']),
            'is_active' => true,
        ];
    }
}
