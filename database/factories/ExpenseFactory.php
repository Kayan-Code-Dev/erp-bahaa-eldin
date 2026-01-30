<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Cashbox;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = array_keys(Expense::getCategories());
        
        return [
            'branch_id' => null, // Will be set in create() method to use Branch's auto-created cashbox
            'cashbox_id' => null, // Will be set in create() method to use Branch's auto-created cashbox
            'category' => fake()->randomElement($categories),
            'subcategory' => fake()->optional()->word(),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'expense_date' => fake()->date(),
            'vendor' => fake()->optional()->company(),
            'reference_number' => fake()->optional()->bothify('REF-####'),
            'description' => fake()->sentence(),
            'notes' => fake()->optional()->paragraph(),
            'status' => Expense::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
            'created_by' => User::factory(),
            'transaction_id' => null,
        ];
    }

    /**
     * Create a new model instance.
     * Override to use Branch's auto-created cashbox instead of creating a separate one.
     * Disables activity logging to prevent memory exhaustion in tests.
     */
    public function create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
    {
        // Disable activity logging globally to prevent cascading ActivityLog creation
        \App\Models\Traits\LogsActivity::disableActivityLogging();
        
        try {
            // Handle branch and cashbox relationship before calling parent
            if (!isset($attributes['branch_id']) || $attributes['branch_id'] === null) {
                // Create a branch (which auto-creates cashbox)
                $branch = Branch::factory()->create();
                $branch->refresh(); // Refresh to get the auto-created cashbox
                $attributes['branch_id'] = $branch->id;
            } else {
                // Branch ID provided, fetch it to get cashbox
                $branch = Branch::findOrFail($attributes['branch_id']);
                $branch->refresh();
            }
            
            // Use the branch's auto-created cashbox
            $attributes['cashbox_id'] = $branch->cashbox->id;
            
            return parent::create($attributes, $parent);
        } finally {
            // Re-enable activity logging
            \App\Models\Traits\LogsActivity::enableActivityLogging();
        }
    }

    /**
     * Indicate that the expense is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Expense::STATUS_APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the expense is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Expense::STATUS_PAID,
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the expense is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Expense::STATUS_CANCELLED,
        ]);
    }
}


