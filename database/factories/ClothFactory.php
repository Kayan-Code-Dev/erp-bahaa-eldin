<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ClothType;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cloth>
 */
class ClothFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('CL-###'),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'cloth_type_id' => ClothType::factory(),
            'breast_size' => (string)$this->faker->numberBetween(30, 100),
            'waist_size' => (string)$this->faker->numberBetween(30, 100),
            'sleeve_size' => (string)$this->faker->numberBetween(30, 100),
            'notes' => $this->faker->sentence(),
        ];
    }
}
