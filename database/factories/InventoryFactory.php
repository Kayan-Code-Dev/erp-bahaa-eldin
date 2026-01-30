<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory as FactoryModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entityType = $this->faker->randomElement([
            Branch::class,
            Workshop::class,
            FactoryModel::class,
        ]);

        $entity = $entityType::factory()->create();

        return [
            'name' => $this->faker->company(),
            'inventoriable_type' => $entityType,
            'inventoriable_id' => $entity->id,
        ];
    }
}
