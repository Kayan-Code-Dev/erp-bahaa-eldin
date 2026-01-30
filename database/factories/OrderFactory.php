<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Client;
use App\Models\Inventory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalPrice = $this->faker->randomFloat(2, 10, 500);
        $paid = $this->faker->randomFloat(2, 0, $totalPrice);
        $remaining = $totalPrice - $paid;

        return [
            'client_id' => Client::factory(),
            'inventory_id' => Inventory::factory(),
            'total_price' => $totalPrice,
            'status' => $this->faker->randomElement(['created', 'partially_paid', 'paid', 'delivered', 'finished', 'canceled']),
            'paid' => $paid,
            'remaining' => $remaining,
            'visit_datetime' => $this->faker->optional()->dateTime(),
        ];
    }
}
