<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Cloth;
use App\Models\Inventory;
use App\Models\ClothReturnPhoto;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory;
use Illuminate\Support\Facades\DB;

class ReturnClothService
{
    protected ClothHistoryService $clothHistoryService;

    public function __construct()
    {
        $this->clothHistoryService = new ClothHistoryService();
    }

    /**
     * Validate cloth can be returned
     *
     * @param Order $order
     * @param Cloth $cloth
     * @return array ['valid' => bool, 'error' => array|null]
     */
    public function validateClothCanBeReturned(Order $order, Cloth $cloth): array
    {
        // Check if cloth belongs to the order and is rent type and returnable
        $clothOrder = DB::table('cloth_order')
            ->where('order_id', $order->id)
            ->where('cloth_id', $cloth->id)
            ->where('type', 'rent')
            ->where('returnable', true)
            ->first();

        if (!$clothOrder) {
            return [
                'valid' => false,
                'error' => [
                    'message' => 'القطعة ليست جزءاً من هذا الطلب كعنصر قابل للإرجاع أو تم إرجاعها بالفعل',
                    'errors' => ['cloth_id' => ['Cloth is not part of this order as a rentable item or has already been returned']]
                ]
            ];
        }

        // Check order status
        if (in_array($order->status, ['finished', 'canceled'])) {
            return [
                'valid' => false,
                'error' => [
                    'message' => 'لا يمكن إرجاع قطعة من طلب منتهي أو ملغي',
                    'errors' => ['order' => ['Cannot return cloth from order in current status']]
                ]
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Get entity class from type
     *
     * @param string $type
     * @return string
     */
    public function getEntityClassFromType(string $type): string
    {
        return match($type) {
            'branch' => Branch::class,
            'workshop' => Workshop::class,
            'factory' => Factory::class,
        };
    }

    /**
     * Get or create destination inventory
     *
     * @param string $entityType
     * @param int $entityId
     * @return Inventory
     */
    public function getOrCreateDestinationInventory(string $entityType, int $entityId): Inventory
    {
        $entityClass = $this->getEntityClassFromType($entityType);
        $entity = $entityClass::findOrFail($entityId);

        // Try relationship first
        $inventory = null;
        if (method_exists($entity, 'inventory')) {
            $inventory = $entity->inventory;
        }

        // Query directly if relationship returns null
        if (!$inventory) {
            $inventory = Inventory::where('inventoriable_type', $entityClass)
                ->where('inventoriable_id', $entityId)
                ->first();
        }

        // Create if still no inventory
        if (!$inventory) {
            $inventory = $entity->inventory()->create(['name' => $entity->name . ' Inventory']);
        }

        return $inventory;
    }

    /**
     * Handle photo uploads
     *
     * @param array $photos
     * @param int $orderId
     * @param int $clothId
     * @return array
     */
    public function handlePhotoUploads(array $photos, int $orderId, int $clothId): array
    {
        $uploadedPaths = [];

        foreach ($photos as $photo) {
            $timestamp = now()->format('Ymd_His');
            $random = strtoupper(substr(md5(uniqid()), 0, 6));
            $extension = $photo->getClientOriginalExtension();
            $filename = "cloth-return_{$orderId}_{$clothId}_{$timestamp}_{$random}.{$extension}";

            $path = $photo->storeAs('cloth-return-photos', $filename, 'local');
            $uploadedPaths[] = $path;
        }

        return $uploadedPaths;
    }

    /**
     * Create photo records in database
     *
     * @param array $photoPaths
     * @param int $orderId
     * @param int $clothId
     * @return void
     */
    public function createPhotoRecords(array $photoPaths, int $orderId, int $clothId): void
    {
        foreach ($photoPaths as $photoPath) {
            ClothReturnPhoto::create([
                'order_id' => $orderId,
                'cloth_id' => $clothId,
                'photo_path' => $photoPath,
                'photo_type' => 'return_photo',
            ]);
        }
    }

    /**
     * Mark cloth as returned (not returnable)
     *
     * @param int $orderId
     * @param int $clothId
     * @return void
     */
    public function markClothAsReturned(int $orderId, int $clothId): void
    {
        DB::table('cloth_order')
            ->where('order_id', $orderId)
            ->where('cloth_id', $clothId)
            ->update(['returnable' => false]);
    }

    /**
     * Update cloth status to repairing
     *
     * @param Cloth $cloth
     * @return void
     */
    public function updateClothStatus(Cloth $cloth): void
    {
        $cloth->update(['status' => 'repairing']);
    }

    /**
     * Transfer cloth to destination inventory
     *
     * @param Cloth $cloth
     * @param Inventory $destinationInventory
     * @return void
     */
    public function transferClothToInventory(Cloth $cloth, Inventory $destinationInventory): void
    {
        DB::table('cloth_inventory')
            ->where('cloth_id', $cloth->id)
            ->delete();

        DB::table('cloth_inventory')->insert([
            'cloth_id' => $cloth->id,
            'inventory_id' => $destinationInventory->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clear relationship cache
        $cloth->load('inventories');
        $destinationInventory->load('clothes');
    }

    /**
     * Record cloth return history
     *
     * @param Cloth $cloth
     * @param Order $order
     * @param mixed $user
     * @return void
     */
    public function recordHistory(Cloth $cloth, Order $order, $user): void
    {
        $this->clothHistoryService->recordReturned($cloth, $order, $user);
    }

    /**
     * Process complete cloth return
     *
     * @param Order $order
     * @param Cloth $cloth
     * @param array $data
     * @param mixed $user
     * @return array ['success' => bool, 'cloth' => Cloth|null, 'error' => array|null]
     */
    public function processReturn(Order $order, Cloth $cloth, array $data, $user): array
    {
        // Validate
        $validation = $this->validateClothCanBeReturned($order, $cloth);
        if (!$validation['valid']) {
            return ['success' => false, 'cloth' => null, 'error' => $validation['error']];
        }

        // Get destination inventory
        $destinationInventory = $this->getOrCreateDestinationInventory(
            $data['entity_type'],
            $data['entity_id']
        );

        // Handle photos
        $photoPaths = $this->handlePhotoUploads($data['photos'], $order->id, $cloth->id);
        $this->createPhotoRecords($photoPaths, $order->id, $cloth->id);

        // Update records
        $this->markClothAsReturned($order->id, $cloth->id);
        $this->updateClothStatus($cloth);
        $this->transferClothToInventory($cloth, $destinationInventory);

        // Record history
        $this->recordHistory($cloth, $order, $user);

        return [
            'success' => true,
            'cloth' => $cloth->fresh(),
            'error' => null
        ];
    }
}

