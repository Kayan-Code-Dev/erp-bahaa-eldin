<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Cashbox;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([Transaction::TYPE_INCOME, Transaction::TYPE_EXPENSE]);
        $amount = fake()->randomFloat(2, 10, 1000);
        $categories = array_keys(Transaction::getCategories());
        
        return [
            'cashbox_id' => Cashbox::factory(),
            'type' => $type,
            'amount' => $amount,
            'balance_after' => fake()->randomFloat(2, 0, 10000),
            'category' => fake()->randomElement($categories),
            'description' => fake()->sentence(),
            'reference_type' => null,
            'reference_id' => null,
            'reversed_transaction_id' => null,
            'created_by' => User::factory(),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the transaction is an income.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Transaction::TYPE_INCOME,
            'category' => Transaction::CATEGORY_PAYMENT,
        ]);
    }

    /**
     * Indicate that the transaction is an expense.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Transaction::TYPE_EXPENSE,
            'category' => Transaction::CATEGORY_EXPENSE,
        ]);
    }

    /**
     * Indicate that the transaction is a reversal.
     */
    public function reversal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Transaction::TYPE_REVERSAL,
            'category' => Transaction::CATEGORY_REVERSAL,
        ]);
    }
}




