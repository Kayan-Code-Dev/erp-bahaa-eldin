<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 10, 500);
        $status = $this->faker->randomElement(['pending', 'paid', 'canceled']);
        $paymentType = $this->faker->randomElement(['initial', 'normal', 'fee']);

        return [
            'order_id' => Order::factory(),
            'amount' => $amount,
            'status' => $status,
            'payment_type' => $paymentType,
            'payment_date' => $status === 'paid' ? $this->faker->dateTimeBetween('-30 days', 'now') : ($status === 'pending' ? null : $this->faker->optional()->dateTimeBetween('-30 days', 'now')),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the payment is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'payment_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'payment_date' => null,
        ]);
    }

    /**
     * Indicate that the payment is canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'notes' => ($attributes['notes'] ?? '') . ($attributes['notes'] ? "\n" : '') . 'Canceled: ' . $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the payment is an initial payment.
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'initial',
            'status' => 'paid',
            'payment_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the payment is a fee payment.
     */
    public function fee(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'fee',
            'notes' => $this->faker->randomElement(['Repair fee', 'Late fee', 'Delivery fee', 'Setup fee', 'Processing fee']),
        ]);
    }

    /**
     * Indicate that the payment is a normal payment.
     */
    public function normal(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_type' => 'normal',
        ]);
    }
}

