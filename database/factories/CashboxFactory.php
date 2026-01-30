<?php

namespace Database\Factories;

use App\Models\Cashbox;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cashbox>
 */
class CashboxFactory extends Factory
{
    protected $model = Cashbox::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true) . ' Cashbox',
            'branch_id' => null, // Will be set in create() method
            'initial_balance' => fake()->randomFloat(2, 0, 10000),
            'current_balance' => fake()->randomFloat(2, 0, 10000),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Create a new model instance.
     * Override to handle Branch auto-cashbox creation.
     */
    public function create($attributes = [], ?\Illuminate\Database\Eloquent\Model $parent = null)
    {
        // If branch_id is not explicitly provided, create a branch without events
        // to prevent the auto-cashbox creation from Branch boot method
        if (!isset($attributes['branch_id']) || $attributes['branch_id'] === null) {
            $branch = Branch::withoutEvents(function () {
                return Branch::factory()->create();
            });
            $attributes['branch_id'] = $branch->id;
        }
        
        return parent::create($attributes, $parent);
    }

    /**
     * Indicate that the cashbox is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
