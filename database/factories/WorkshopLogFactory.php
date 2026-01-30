<?php

namespace Database\Factories;

use App\Models\WorkshopLog;
use App\Models\Workshop;
use App\Models\Cloth;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkshopLogFactory extends Factory
{
    protected $model = WorkshopLog::class;

    public function definition(): array
    {
        $action = $this->faker->randomElement(['received', 'status_changed', 'returned']);
        
        return [
            'workshop_id' => Workshop::factory(),
            'cloth_id' => Cloth::factory(),
            'transfer_id' => null,
            'action' => $action,
            'cloth_status' => $this->faker->randomElement(['received', 'processing', 'ready_for_delivery']),
            'notes' => $this->faker->optional()->sentence(),
            'received_at' => $action === 'received' ? now() : null,
            'returned_at' => $action === 'returned' ? now() : null,
            'user_id' => User::factory(),
        ];
    }

    /**
     * Configure as a "received" action
     */
    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'received',
            'cloth_status' => 'received',
            'received_at' => now(),
            'returned_at' => null,
        ]);
    }

    /**
     * Configure as a "status_changed" action
     */
    public function statusChanged(string $status = 'processing'): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'status_changed',
            'cloth_status' => $status,
        ]);
    }

    /**
     * Configure as a "returned" action
     */
    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'returned',
            'cloth_status' => 'ready_for_delivery',
            'returned_at' => now(),
        ]);
    }

    /**
     * Associate with a transfer
     */
    public function withTransfer(Transfer $transfer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'transfer_id' => $transfer?->id ?? Transfer::factory(),
        ]);
    }
}





