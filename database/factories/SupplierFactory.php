<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Haji', 'Sheikh', 'Malik', 'Chaudhry', 'Mian']).' '.fake()->randomElement(['Aslam', 'Rafiq', 'Tariq', 'Imran', 'Naveed', 'Shahid', 'Zubair']),
            'shop_name' => fake()->randomElement(['Al-Madina', 'Faisal', 'Rehman', 'Bismillah', 'Ittefaq', 'Usman']).' '.fake()->randomElement(['Textiles', 'Cloth House', 'Fabrics', 'Traders']),
            'phone' => fake()->numerify('03#########'),
            'city' => fake()->randomElement(['Faisalabad', 'Lahore', 'Karachi', 'Multan']),
            'address' => fake()->optional()->randomElement(['Kapra Market', 'Azam Cloth Market', 'Jodia Bazar', 'Rail Bazar']),
            'is_active' => true,
        ];
    }
}
