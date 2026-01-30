<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Inventory;
use App\Models\Address;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Static counter for generating unique branch codes
     */
    protected static int $counter = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static::$counter++;
        return [
            'branch_code' => 'BR-' . str_pad((string)static::$counter, 6, '0', STR_PAD_LEFT),
            'name' => $this->faker->company(),
            'address_id' => Address::factory(),
        ];
    }
}
