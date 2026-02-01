<?php

namespace App\Services;

use App\Models\SupplierOrder;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * SupplierOrderService
 *
 * Service for managing supplier orders.
 */
class SupplierOrderService
{
    /**
     * Get paginated list of supplier orders
     */
    public function list(int $perPage = 15, ?string $search = null, ?int $supplierId = null): LengthAwarePaginator
    {
        $query = SupplierOrder::with(['supplier', 'category', 'subcategory', 'branch']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get a supplier order by ID
     */
    public function find(int $id): SupplierOrder
    {
        return SupplierOrder::with(['supplier', 'category', 'subcategory', 'branch'])->findOrFail($id);
    }

    /**
     * Create a new supplier order
     */
    public function create(array $data): SupplierOrder
    {
        return SupplierOrder::create($data);
    }

    /**
     * Update an existing supplier order
     */
    public function update(int $id, array $data): SupplierOrder
    {
        $order = $this->find($id);
        $order->update($data);
        return $order->load('supplier');
    }

    /**
     * Delete a supplier order
     */
    public function delete(int $id): bool
    {
        $order = SupplierOrder::findOrFail($id);
        return $order->delete();
    }

    /**
     * Get all supplier orders (for export)
     */
    public function all(?int $supplierId = null)
    {
        $query = SupplierOrder::with(['supplier', 'category', 'subcategory', 'branch'])->orderBy('created_at', 'desc');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return $query->get();
    }

    /**
     * Generate unique order number
     */
    public function generateOrderNumber(): string
    {
        $prefix = 'SO-' . date('Ymd') . '-';
        $lastOrder = SupplierOrder::where('order_number', 'like', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $newNumber;
    }

    /**
     * Map entity type to model class (SAME AS ClothController)
     */
    protected function getModelClassFromEntityType(string $entityType): ?string
    {
        return match ($entityType) {
            'branch' => Branch::class,
            'workshop' => Workshop::class,
            'factory' => Factory::class,
            default => null,
        };
    }

    /**
     * Store supplier order with clothes
     *
     * Can be called from controller or any other service
     *
     * @param array $data Full order data including clothes
     * Expected structure:
     * [
     *     'supplier_id' => int (required),
     *     'category_id' => int|null,
     *     'subcategory_id' => int|null,
     *     'branch_id' => int|null,
     *     'order_number' => string|null (auto-generated if empty),
     *     'order_date' => string (required, Y-m-d format),
     *     'payment_amount' => float|null,
     *     'notes' => string|null,
     *     'clothes' => array (required, min 1 item) [
     *         [
     *             'code' => string (required, unique),
     *             'name' => string (required),
     *             'description' => string|null,
     *             'cloth_type_id' => int (required),
     *             'breast_size' => string|null,
     *             'waist_size' => string|null,
     *             'sleeve_size' => string|null,
     *             'notes' => string|null,
     *             'status' => string|null (default: ready_for_rent),
     *             'entity_type' => string (required: branch|workshop|factory),
     *             'entity_id' => int (required),
     *             'price' => float|null (default: 0),
     *         ],
     *         ...
     *     ]
     * ]
     *
     * @return array ['order' => SupplierOrder, 'clothes' => array]
     * @throws \Exception
     */
    public function storeWithClothes(array $data): array
    {
        // Auto-generate order number if not provided
        if (empty($data['order_number'])) {
            $data['order_number'] = $this->generateOrderNumber();
        }

        $createdClothes = [];
        $totalAmount = 0;
        $supplierOrder = null;

        DB::transaction(function () use ($data, &$createdClothes, &$totalAmount, &$supplierOrder) {
            // Create supplier order first
            $supplierOrder = SupplierOrder::create([
                'supplier_id' => $data['supplier_id'],
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'order_number' => $data['order_number'],
                'order_date' => $data['order_date'],
                'payment_amount' => $data['payment_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'status' => SupplierOrder::STATUS_PENDING,
                'total_amount' => 0,
            ]);

            $historyService = new ClothHistoryService();

            // Process each cloth - SAME LOGIC AS ClothController store
            foreach ($data['clothes'] as $clothData) {
                // Validate cloth type exists
                $clothType = ClothType::find($clothData['cloth_type_id']);
                if (!$clothType) {
                    throw new \Exception("Cloth type {$clothData['cloth_type_id']} not found");
                }

                // Map entity type and validate entity exists
                $entityTypeEnum = $clothData['entity_type'];
                $modelClass = $this->getModelClassFromEntityType($entityTypeEnum);

                if (!$modelClass) {
                    throw new \Exception("Invalid entity type: {$entityTypeEnum}");
                }

                $entity = $modelClass::find($clothData['entity_id']);
                if (!$entity) {
                    throw new \Exception("Entity {$clothData['entity_id']} not found");
                }

                // Get inventory
                $inventory = $entity->inventory;
                if (!$inventory) {
                    throw new \Exception("Entity {$entity->name} does not have an inventory");
                }

                $price = $clothData['price'] ?? 0;
                $totalAmount += $price;

                // Create cloth piece
                $cloth = Cloth::create([
                    'code' => $clothData['code'],
                    'name' => $clothData['name'],
                    'description' => $clothData['description'] ?? null,
                    'cloth_type_id' => $clothData['cloth_type_id'],
                    'breast_size' => $clothData['breast_size'] ?? null,
                    'waist_size' => $clothData['waist_size'] ?? null,
                    'sleeve_size' => $clothData['sleeve_size'] ?? null,
                    'notes' => $clothData['notes'] ?? null,
                    'status' => $clothData['status'] ?? 'ready_for_rent',
                ]);

                // Ensure cloth is not in any other inventory
                $cloth->inventories()->detach();

                // Add piece to inventory
                $inventory->clothes()->attach($cloth->id);

                // Create history record
                $historyService->recordCreated($cloth, $entity);

                // Link cloth to supplier order
                $supplierOrder->clothes()->attach($cloth->id, ['price' => $price]);

                // Build response
                $cloth->load(['clothType', 'inventories.inventoriable']);

                $createdClothes[] = [
                    'id' => $cloth->id,
                    'code' => $cloth->code,
                    'name' => $cloth->name,
                    'description' => $cloth->description,
                    'breast_size' => $cloth->breast_size,
                    'waist_size' => $cloth->waist_size,
                    'sleeve_size' => $cloth->sleeve_size,
                    'notes' => $cloth->notes,
                    'status' => $cloth->status,
                    'cloth_type_id' => $cloth->cloth_type_id,
                    'cloth_type_name' => $cloth->clothType->name ?? null,
                    'entity_type' => $entityTypeEnum,
                    'entity_id' => $entity->id,
                    'entity_name' => $entity->name,
                    'price' => $price,
                ];
            }

            // Update total amount
            $supplierOrder->total_amount = $totalAmount;
            $supplierOrder->save();
        });

        /** @var SupplierOrder $supplierOrder */
        // Load relationships
        $supplierOrder->load(['supplier', 'category', 'subcategory', 'branch', 'clothes']);

        return [
            'order' => $supplierOrder,
            'clothes' => $createdClothes,
        ];
    }
}

