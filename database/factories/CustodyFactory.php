<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Custody>
 */
class CustodyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'type' => $this->faker->randomElement(['money', 'physical_item', 'document']),
            'description' => $this->faker->sentence(),
            'value' => $this->faker->randomFloat(2, 100, 1000),
            'status' => 'pending',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}


