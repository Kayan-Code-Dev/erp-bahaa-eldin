<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory as FactoryModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entityTypes = ['branch', 'workshop', 'factory'];
        $fromEntityType = $this->faker->randomElement($entityTypes);
        
        // Get a random entity of the selected type
        $fromEntity = match($fromEntityType) {
            'branch' => Branch::inRandomOrder()->first() ?? Branch::factory()->create(),
            'workshop' => Workshop::inRandomOrder()->first() ?? Workshop::factory()->create(),
            'factory' => FactoryModel::inRandomOrder()->first() ?? FactoryModel::factory()->create(),
            default => Branch::factory()->create()
        };
        
        // Get a different entity for destination
        $toEntityType = $this->faker->randomElement($entityTypes);
        $toEntity = match($toEntityType) {
            'branch' => Branch::where('id', '!=', $fromEntity->id)->inRandomOrder()->first() ?? Branch::factory()->create(),
            'workshop' => Workshop::where('id', '!=', $fromEntity->id)->inRandomOrder()->first() ?? Workshop::factory()->create(),
            'factory' => FactoryModel::where('id', '!=', $fromEntity->id)->inRandomOrder()->first() ?? FactoryModel::factory()->create(),
            default => Branch::factory()->create()
        };
        
        return [
            'from_entity_type' => $fromEntityType,
            'from_entity_id' => $fromEntity->id,
            'to_entity_type' => $toEntityType,
            'to_entity_id' => $toEntity->id,
            'transfer_date' => $this->faker->date(),
            'notes' => $this->faker->optional()->sentence(),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
        ];
    }
}

