<?php

namespace Database\Factories;

use App\Enums\MeasurementTemplate;
use App\Models\Customer;
use App\Models\CustomerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => 'Self',
            'template' => MeasurementTemplate::Gents,
        ];
    }

    public function family(): static
    {
        return $this->state(fn (): array => fake()->randomElement([
            ['name' => 'Wife', 'template' => MeasurementTemplate::Ladies],
            ['name' => 'Son', 'template' => MeasurementTemplate::Kids],
            ['name' => 'Daughter', 'template' => MeasurementTemplate::Kids],
            ['name' => 'Father', 'template' => MeasurementTemplate::Gents],
        ]));
    }
}
