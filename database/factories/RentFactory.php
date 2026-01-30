<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Rent;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Cloth;
use App\Models\Order;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rent>
 */
class RentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $appointmentTypes = [
            Rent::TYPE_RENTAL_DELIVERY,
            Rent::TYPE_RENTAL_RETURN,
            Rent::TYPE_MEASUREMENT,
            Rent::TYPE_TAILORING_PICKUP,
            Rent::TYPE_TAILORING_DELIVERY,
            Rent::TYPE_FITTING,
            Rent::TYPE_OTHER,
        ];

        $statuses = [
            Rent::STATUS_SCHEDULED,
            Rent::STATUS_CONFIRMED,
            Rent::STATUS_IN_PROGRESS,
            Rent::STATUS_COMPLETED,
            Rent::STATUS_CANCELLED,
            Rent::STATUS_NO_SHOW,
        ];

        $deliveryDate = $this->faker->dateTimeBetween('today', '+30 days');
        $returnDate = (clone $deliveryDate)->modify('+'.$this->faker->numberBetween(1, 14).' days');

        return [
            'client_id' => Client::factory(),
            'branch_id' => Branch::factory(),
            'cloth_id' => $this->faker->optional(0.7)->passthrough(Cloth::factory()),
            'order_id' => $this->faker->optional(0.5)->passthrough(Order::factory()),
            'appointment_type' => $this->faker->randomElement($appointmentTypes),
            'title' => $this->faker->optional(0.3)->sentence(),
            'delivery_date' => $deliveryDate->format('Y-m-d'),
            'appointment_time' => $this->faker->optional(0.8)->time('H:i'),
            'return_date' => $returnDate->format('Y-m-d'),
            'return_time' => $this->faker->optional(0.6)->time('H:i'),
            'days_of_rent' => $this->faker->numberBetween(1, 14),
            'status' => $this->faker->randomElement($statuses),
            'notes' => $this->faker->optional(0.4)->paragraph(),
            'reminder_sent' => $this->faker->boolean(20), // 20% chance
            'reminder_sent_at' => $this->faker->optional()->dateTimeThisMonth(),
            'created_by' => $this->faker->optional()->passthrough(\App\Models\User::factory()),
            'completed_at' => $this->faker->optional(0.3)->dateTimeThisMonth(),
            'completed_by' => $this->faker->optional()->passthrough(\App\Models\User::factory()),
        ];
    }

    /**
     * Create a scheduled appointment
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Rent::STATUS_SCHEDULED,
        ]);
    }

    /**
     * Create a confirmed appointment
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Rent::STATUS_CONFIRMED,
        ]);
    }

    /**
     * Create a completed appointment
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Rent::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Create a rental delivery appointment
     */
    public function rentalDelivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'appointment_type' => Rent::TYPE_RENTAL_DELIVERY,
        ]);
    }

    /**
     * Create a measurement appointment
     */
    public function measurement(): static
    {
        return $this->state(fn (array $attributes) => [
            'appointment_type' => Rent::TYPE_MEASUREMENT,
        ]);
    }

    /**
     * Create an appointment for today
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_date' => today()->format('Y-m-d'),
        ]);
    }

    /**
     * Create an upcoming appointment
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_date' => $this->faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
        ]);
    }

    /**
     * Create an overdue appointment
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_date' => $this->faker->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
            'status' => Rent::STATUS_SCHEDULED,
        ]);
    }
}
