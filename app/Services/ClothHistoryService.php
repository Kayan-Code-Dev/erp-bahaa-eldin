<?php

namespace App\Services;

use App\Models\ClothHistory;
use App\Models\Cloth;
use App\Models\Transfer;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class ClothHistoryService
{
    /**
     * Record when a cloth piece is created
     */
    public function recordCreated(Cloth $cloth, $entity, $user = null)
    {
        $user = $user ?? Auth::user();

        return ClothHistory::create([
            'cloth_id' => $cloth->id,
            'action' => 'created',
            'entity_type' => $this->getEntityType($entity),
            'entity_id' => $entity->id ?? null,
            'user_id' => $user->id ?? null,
        ]);
    }

    /**
     * Record when a cloth piece is transferred
     */
    public function recordTransferred(Cloth $cloth, $fromEntity, $toEntity, Transfer $transfer, $user = null)
    {
        $user = $user ?? Auth::user();

        return ClothHistory::create([
            'cloth_id' => $cloth->id,
            'action' => 'transferred',
            'entity_type' => $this->getEntityType($toEntity),
            'entity_id' => $toEntity->id ?? null,
            'transfer_id' => $transfer->id,
            'user_id' => $user->id ?? null,
        ]);
    }

    /**
     * Record when a cloth piece is ordered
     */
    public function recordOrdered(Cloth $cloth, Order $order, $user = null)
    {
        $user = $user ?? Auth::user();

        // Get entity from order's inventory
        $entity = null;
        if ($order->inventory && $order->inventory->inventoriable) {
            $entity = $order->inventory->inventoriable;
        }

        return ClothHistory::create([
            'cloth_id' => $cloth->id,
            'action' => 'ordered',
            'entity_type' => $entity ? $this->getEntityType($entity) : null,
            'entity_id' => $entity ? $entity->id : null,
            'order_id' => $order->id,
            'user_id' => $user->id ?? null,
        ]);
    }

    /**
     * Record when a cloth piece is returned
     */
    public function recordReturned(Cloth $cloth, Order $order, $user = null)
    {
        $user = $user ?? Auth::user();

        // Get entity from order's inventory
        $entity = null;
        if ($order->inventory && $order->inventory->inventoriable) {
            $entity = $order->inventory->inventoriable;
        }

        return ClothHistory::create([
            'cloth_id' => $cloth->id,
            'action' => 'returned',
            'entity_type' => $entity ? $this->getEntityType($entity) : null,
            'entity_id' => $entity ? $entity->id : null,
            'order_id' => $order->id,
            'user_id' => $user->id ?? null,
        ]);
    }

    /**
     * Record when a cloth piece status is changed
     */
    public function recordStatusChanged(Cloth $cloth, $oldStatus, $newStatus, $user = null)
    {
        $user = $user ?? Auth::user();

        return ClothHistory::create([
            'cloth_id' => $cloth->id,
            'action' => 'status_changed',
            'status' => $newStatus,
            'notes' => "Status changed from {$oldStatus} to {$newStatus}",
            'user_id' => $user->id ?? null,
        ]);
    }

    /**
     * Get entity type string from entity object
     */
    private function getEntityType($entity)
    {
        if (!$entity) {
            return null;
        }

        $class = get_class($entity);

        $map = [
            \App\Models\Branch::class => 'branch',
            \App\Models\Workshop::class => 'workshop',
            \App\Models\Factory::class => 'factory',
        ];

        return $map[$class] ?? null;
    }
}

