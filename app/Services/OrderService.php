<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Cloth;
use App\Models\Rent;
use App\Models\Inventory;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class OrderService
{
    protected ClothHistoryService $clothHistoryService;
    protected OrderHistoryService $orderHistoryService;

    public function __construct()
    {
        $this->clothHistoryService = new ClothHistoryService();
        $this->orderHistoryService = new OrderHistoryService();
    }

    /**
     * Find inventory by entity type and ID
     */
    public function findInventoryByEntity(string $entityType, int $entityId): ?Inventory
    {
        $morphMap = [
            'branch' => \App\Models\Branch::class,
            'workshop' => \App\Models\Workshop::class,
            'factory' => \App\Models\Factory::class,
        ];

        $modelClass = $morphMap[$entityType] ?? null;
        if (!$modelClass) {
            return null;
        }

        return Inventory::where('inventoriable_type', $entityType)
            ->where('inventoriable_id', $entityId)
            ->first();
    }


    public function checkRentalAvailability(int $clothId, string $deliveryDate, int $daysOfRent): array
    {
        $startDate = \Carbon\Carbon::parse($deliveryDate);
        $endDate = $startDate->copy()->addDays($daysOfRent);

        $conflicts = Rent::where('cloth_id', $clothId)
            ->where('status', '!=', 'canceled')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('delivery_date', [$startDate, $endDate])
                    ->orWhereBetween('return_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('delivery_date', '<=', $startDate)
                            ->where('return_date', '>=', $endDate);
                    });
            })
            ->get();

        return [
            'available' => $conflicts->isEmpty(),
            'conflicts' => $conflicts->map(fn($rent) => "Order #{$rent->order_id}: {$rent->delivery_date} - {$rent->return_date}")->toArray(),
        ];
    }

    /**
     * Calculate totals from items with discounts and quantity
     */
    public function calculateTotals(array $items, ?string $orderDiscountType = null, float $orderDiscountValue = 0): array
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $itemPrice = $item['price'];
            $quantity = $item['quantity'] ?? 1;
            $itemDiscountType = $item['discount_type'] ?? null;
            $itemDiscountValue = $item['discount_value'] ?? 0;

            if ($itemDiscountType === 'percentage') {
                $itemPrice = $itemPrice * (1 - $itemDiscountValue / 100);
            } elseif ($itemDiscountType === 'fixed') {
                $itemPrice = max(0, $itemPrice - $itemDiscountValue);
            }
            $subtotal += ($itemPrice * $quantity);
        }

        $totalPrice = $subtotal;
        if ($orderDiscountType === 'percentage') {
            $totalPrice = $subtotal * (1 - $orderDiscountValue / 100);
        } elseif ($orderDiscountType === 'fixed') {
            $totalPrice = max(0, $subtotal - $orderDiscountValue);
        }

        return [
            'subtotal' => $subtotal,
            'total_price' => $totalPrice,
        ];
    }

    /**
     * Store a new order with items and measurements
     *
     * Order Flow:
     * 1. Each cloth item has: price, quantity, paid, discount (item level)
     * 2. Order subtotal = sum of all item totals (after item discounts)
     * 3. Apply order-level discount to get final total_price
     * 4. Order paid = sum of all items' paid
     * 5. Order remaining = total_price - order paid
     *
     * @param array $data Validated request data
     * @param Inventory $inventory The inventory for the order
     * @param mixed $user Current authenticated user
     * @return array ['order' => Order, 'errors' => array|null]
     */
    public function store(array $data, Inventory $inventory, $user): array
    {
        // Validate buy order constraints
        $buyItems = collect($data['items'])->where('type', 'buy');
        if ($buyItems->isNotEmpty() && count($data['items']) > 1) {
            return [
                'order' => null,
                'errors' => [
                    'message' => 'Buy orders must have exactly 1 item. Cannot mix buy with other items.',
                    'details' => ['items' => ['A buy order can only contain a single buy item.']]
                ]
            ];
        }

        $order = null;
        $itemErrors = null;

        DB::transaction(function () use ($data, $inventory, $user, &$order, &$itemErrors) {
            // First, calculate all items totals and sum paid
            $itemsSubtotal = 0;
            $totalPaid = 0;
            $processedItems = [];

            foreach ($data['items'] as $index => $itemData) {
                // Calculate item total (price * quantity - item discount)
                $quantity = $itemData['quantity'] ?? 1;
                $itemPrice = $itemData['price'];
                $itemDiscountType = $itemData['discount_type'] ?? null;
                $itemDiscountValue = $itemData['discount_value'] ?? 0;

                if ($itemDiscountType === 'percentage') {
                    $itemPrice = $itemPrice * (1 - $itemDiscountValue / 100);
                } elseif ($itemDiscountType === 'fixed') {
                    $itemPrice = max(0, $itemPrice - $itemDiscountValue);
                }
                $itemTotal = $itemPrice * $quantity;
                $itemsSubtotal += $itemTotal;

                // Sum item paid amounts
                $itemPaid = $itemData['paid'] ?? 0;
                $totalPaid += $itemPaid;

                $processedItems[] = [
                    'index' => $index,
                    'data' => $itemData,
                    'item_total' => $itemTotal,
                    'item_paid' => $itemPaid,
                ];
            }

            // Apply order-level discount
            $orderDiscountType = $data['discount_type'] ?? null;
            $orderDiscountValue = $data['discount_value'] ?? 0;
            $totalPrice = $itemsSubtotal;

            if ($orderDiscountType === 'percentage') {
                $totalPrice = $itemsSubtotal * (1 - $orderDiscountValue / 100);
            } elseif ($orderDiscountType === 'fixed') {
                $totalPrice = max(0, $itemsSubtotal - $orderDiscountValue);
            }

            // Calculate order remaining
            $orderRemaining = max(0, $totalPrice - $totalPaid);

            // Create order with calculated totals
            $order = Order::create([
                'client_id' => $data['client_id'],
                'inventory_id' => $inventory->id,
                'total_price' => $totalPrice,
                'status' => 'created',
                'paid' => $totalPaid,
                'remaining' => $orderRemaining,
                'visit_datetime' => now(),
                'delivery_date' => $data['delivery_date'] ?? null,
                'days_of_rent' => $data['days_of_rent'] ?? null,
                'occasion_datetime' => $data['occasion_datetime'] ?? null,
                'order_notes' => $data['order_notes'] ?? null,
                'discount_type' => $orderDiscountType,
                'discount_value' => $orderDiscountValue,
            ]);

            $this->orderHistoryService->logCreated($order, $user);

            // Create payment record if total paid > 0
            if ($totalPaid > 0) {
                Payment::create([
                    'order_id' => $order->id,
                    'amount' => $totalPaid,
                    'status' => 'paid',
                    'payment_type' => 'initial',
                    'payment_date' => now(),
                    'created_by' => $user?->id,
                ]);
            }

            // Process each item
            foreach ($processedItems as $processed) {
                $result = $this->processOrderItem(
                    $order,
                    $inventory,
                    $processed['data'],
                    $processed['index'],
                    $user
                );
                if ($result['error']) {
                    $itemErrors = $result['error'];
                    throw new \Exception('Item validation failed');
                }
            }
        });

        if ($itemErrors) {
            return ['order' => null, 'errors' => $itemErrors];
        }

        return ['order' => $order, 'errors' => null];
    }

    /**
     * Process a single order item
     */
    protected function processOrderItem(Order $order, Inventory $inventory, array $itemData, int $index, $user): array
    {
        $cloth = Cloth::find($itemData['cloth_id']);

        if (!$cloth) {
            return [
                'error' => [
                    'message' => 'Cloth not found',
                    'details' => ["items.{$index}.cloth_id" => ['Cloth with id ' . $itemData['cloth_id'] . ' does not exist']]
                ]
            ];
        }

        // Validate cloth is in inventory
        $clothInInventory = $inventory->clothes()->where('clothes.id', $cloth->id)->first();
        if (!$clothInInventory) {
            return [
                'error' => [
                    'message' => 'Cloth not in inventory',
                    'details' => ["items.{$index}.cloth_id" => ['Cloth ' . $cloth->code . ' is not in the order\'s inventory']]
                ]
            ];
        }

        // Check rental availability using order-level fields
        if ($itemData['type'] === 'rent' && $order->delivery_date && $order->days_of_rent) {
            $availability = $this->checkRentalAvailability(
                $cloth->id,
                $order->delivery_date,
                $order->days_of_rent
            );
            if (!$availability['available']) {
                return [
                    'error' => [
                        'message' => 'Cloth is not available for rent on the requested dates',
                        'details' => ['delivery_date' => ['Conflicts: ' . implode(', ', $availability['conflicts'])]]
                    ]
                ];
            }
        }

        // Check buy constraints
        if ($itemData['type'] === 'buy') {
            $hasPendingRents = Rent::where('cloth_id', $cloth->id)
                ->where('status', '!=', 'canceled')
                ->where('return_date', '>=', today())
                ->exists();

            if ($hasPendingRents) {
                return [
                    'error' => [
                        'message' => 'Cannot sell cloth with pending rent reservations',
                        'details' => ["items.{$index}.cloth_id" => ['Cloth ' . $cloth->code . ' has pending rent reservations']]
                    ]
                ];
            }

            if ($cloth->status === 'sold') {
                return [
                    'error' => [
                        'message' => 'Cannot sell cloth that is already sold',
                        'details' => ["items.{$index}.cloth_id" => ['Cloth ' . $cloth->code . ' is already sold']]
                    ]
                ];
            }
        }

        // Calculate item total with discounts
        $quantity = $itemData['quantity'] ?? 1;
        $itemPrice = $itemData['price'];
        $itemDiscountType = $itemData['discount_type'] ?? null;
        $itemDiscountValue = $itemData['discount_value'] ?? 0;

        if ($itemDiscountType === 'percentage') {
            $itemPrice = $itemPrice * (1 - $itemDiscountValue / 100);
        } elseif ($itemDiscountType === 'fixed') {
            $itemPrice = max(0, $itemPrice - $itemDiscountValue);
        }
        $itemTotal = $itemPrice * $quantity;

        // Get paid amount for this item
        $paid = $itemData['paid'] ?? 0;
        $remaining = max(0, $itemTotal - $paid);

        // Build pivot data with quantity, paid, remaining, and measurements
        $pivot = [
            'price' => $itemData['price'],
            'type' => $itemData['type'],
            'quantity' => $quantity,
            'paid' => $paid, // المبلغ المدفوع
            'remaining' => $remaining, // المبلغ المتبقي
            'status' => 'created',
            'notes' => $itemData['notes'] ?? null,
            'discount_type' => $itemData['discount_type'] ?? null,
            'discount_value' => $itemData['discount_value'] ?? null,
            'returnable' => ($itemData['type'] === 'rent'),
            // Measurements (مقاسات)
            'sleeve_length' => $itemData['sleeve_length'] ?? null,
            'forearm' => $itemData['forearm'] ?? null,
            'shoulder_width' => $itemData['shoulder_width'] ?? null,
            'cuffs' => $itemData['cuffs'] ?? null,
            'waist' => $itemData['waist'] ?? null,
            'chest_length' => $itemData['chest_length'] ?? null,
            'total_length' => $itemData['total_length'] ?? null,
            'hinch' => $itemData['hinch'] ?? null,
            'dress_size' => $itemData['dress_size'] ?? null,
        ];

        $order->items()->attach($cloth->id, $pivot);

        // Update inventory based on order type
        $this->updateInventoryForOrderItem($cloth, $inventory, $itemData['type'], $user);

        // Record history
        $this->clothHistoryService->recordOrdered($cloth, $order, $user);
        $this->orderHistoryService->logItemAdded($order, $cloth->id, $cloth->code, $user);

        return ['error' => null];
    }

    /**
     * Update inventory based on order item type
     *
     * INVENTORY FLOW:
     * ================
     *
     * BUY (شراء):
     * - Cloth status → 'sold'
     * - Remove from inventory (cloth leaves permanently)
     * - Customer owns the cloth
     *
     * RENT (إيجار):
     * - Cloth status → 'rented'
     * - Stays in inventory but marked as unavailable
     * - After rental period: status → 'ready_for_rent'
     * - Cloth returns to inventory
     *
     * TAILORING (تفصيل):
     * - Cloth status → 'repairing' (being customized)
     * - May be transferred to factory inventory
     * - After completion: delivered to customer or returned
     */
    protected function updateInventoryForOrderItem(Cloth $cloth, Inventory $inventory, string $type, $user): void
    {
        switch ($type) {
            case 'buy':
                // Mark cloth as sold and remove from inventory
                $cloth->update(['status' => 'sold']);
                // Detach from inventory (sold items leave inventory)
                $cloth->inventories()->detach($inventory->id);
                break;

            case 'rent':
                // Mark cloth as rented (reserved)
                $cloth->update(['status' => 'rented']);
                // Cloth stays in inventory but is unavailable for other rentals
                break;

            case 'tailoring':
                // Mark cloth as being tailored/repaired
                $cloth->update(['status' => 'repairing']);
                // Cloth may stay or be transferred to factory
                break;
        }
    }

    /**
     * Return cloth to inventory after rental period ends
     */
    public function returnClothFromRental(Cloth $cloth, Inventory $inventory): void
    {
        $cloth->update(['status' => 'ready_for_rent']);

        // Ensure cloth is in inventory
        if (!$cloth->inventories()->where('inventory_id', $inventory->id)->exists()) {
            $cloth->inventories()->attach($inventory->id);
        }
    }

    /**
     * Complete tailoring and mark cloth ready
     */
    public function completeTailoring(Cloth $cloth, string $newStatus = 'ready_for_rent'): void
    {
        $cloth->update(['status' => $newStatus]);
    }
}

