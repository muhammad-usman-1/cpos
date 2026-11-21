<?php

namespace Database\Factories;

use App\Enums\CustomerTag;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tag = fake()->optional(0.3)->randomElement(CustomerTag::cases());

        return [
            'name' => fake()->randomElement(['Ahmed', 'Ali', 'Usman', 'Bilal', 'Hamza', 'Fahad', 'Kashif', 'Asad', 'Ayesha', 'Fatima', 'Sana', 'Hina', 'Maryam', 'Zainab'])
                .' '.fake()->randomElement(['Khan', 'Butt', 'Qureshi', 'Siddiqui', 'Malik', 'Raza', 'Iqbal', 'Hussain', 'Javed']),
            'phone' => fake()->unique()->numerify('03#########'),
            'alt_phone' => fake()->optional(0.2)->numerify('03#########'),
            'city' => fake()->randomElement(['Lahore', 'Lahore', 'Lahore', 'Gujranwala', 'Sheikhupura']),
            'address' => fake()->optional(0.6)->randomElement(['Model Town', 'Johar Town', 'Gulberg', 'Iqbal Town', 'DHA Phase 5', 'Samanabad']),
            'tag' => $tag,
            'discount_percent' => $tag === CustomerTag::Vip ? 5 : ($tag === CustomerTag::Wholesale ? 10 : 0),
            'birthday' => fake()->optional(0.3)->dateTimeBetween('-60 years', '-18 years'),
            'is_active' => true,
        ];
    }
}
