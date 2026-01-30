<?php

namespace Database\Factories;

use App\Models\Receivable;
use App\Models\Client;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Receivable>
 */
class ReceivableFactory extends Factory
{
    protected $model = Receivable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalAmount = fake()->randomFloat(2, 100, 5000);
        $paidAmount = fake()->randomFloat(2, 0, $originalAmount);
        
        return [
            'client_id' => Client::factory(),
            'order_id' => null,
            'branch_id' => Branch::factory(),
            'original_amount' => $originalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $originalAmount - $paidAmount,
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'description' => fake()->sentence(),
            'notes' => fake()->optional()->paragraph(),
            'status' => Receivable::STATUS_PENDING,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the receivable is fully paid.
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $originalAmount = $attributes['original_amount'] ?? fake()->randomFloat(2, 100, 5000);
            return [
                'paid_amount' => $originalAmount,
                'remaining_amount' => 0,
                'status' => Receivable::STATUS_PAID,
            ];
        });
    }

    /**
     * Indicate that the receivable is partially paid.
     */
    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $originalAmount = $attributes['original_amount'] ?? fake()->randomFloat(2, 100, 5000);
            $paidAmount = $originalAmount * 0.5;
            return [
                'paid_amount' => $paidAmount,
                'remaining_amount' => $originalAmount - $paidAmount,
                'status' => Receivable::STATUS_PARTIAL,
            ];
        });
    }

    /**
     * Indicate that the receivable is overdue.
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            $originalAmount = $attributes['original_amount'] ?? fake()->randomFloat(2, 100, 5000);
            $paidAmount = $attributes['paid_amount'] ?? 0;
            return [
                'due_date' => fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
                'remaining_amount' => $originalAmount - $paidAmount,
                'status' => Receivable::STATUS_OVERDUE,
            ];
        });
    }
}




