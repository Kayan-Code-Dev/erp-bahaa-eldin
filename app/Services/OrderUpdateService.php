<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Cloth;
use App\Models\Rent;
use Illuminate\Support\Facades\DB;

class OrderUpdateService
{
    protected ClothHistoryService $clothHistoryService;
    protected OrderHistoryService $orderHistoryService;

    public function __construct()
    {
        $this->clothHistoryService = new ClothHistoryService();
        $this->orderHistoryService = new OrderHistoryService();
    }

    /**
     * Validate if order can be updated
     *
     * @param Order $order
     * @return array ['valid' => bool, 'error' => array|null]
     */
    public function validateOrderCanBeUpdated(Order $order): array
    {
        // Check if order has any sold items
        $hasSoldItems = $order->items()->where('clothes.status', 'sold')->exists();
        if ($hasSoldItems) {
            return [
                'valid' => false,
                'error' => [
                    'message' => 'لا يمكن تعديل طلب يحتوي على قطع مباعة',
                    'errors' => ['order' => ['This order contains sold items and cannot be modified.']]
                ]
            ];
        }

        // Check order status
        if (in_array($order->status, ['finished', 'canceled'])) {
            return [
                'valid' => false,
                'error' => [
                    'message' => 'لا يمكن تعديل طلب منتهي أو ملغي',
                    'errors' => ['order' => ['Cannot modify orders that are finished or canceled.']]
                ]
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Update visit datetime
     *
     * @param Order $order
     * @param string|null $visitDatetime
     * @param mixed $user
     * @return void
     */
    public function updateVisitDatetime(Order $order, ?string $visitDatetime, $user): void
    {
        $oldVisitDatetime = $order->delivery_date;
        $order->delivery_date = $visitDatetime;
        $order->save();

        if ($oldVisitDatetime != $visitDatetime) {
            $this->orderHistoryService->logUpdated(
                $order,
                'delivery_date',
                $oldVisitDatetime,
                $visitDatetime,
                null,
                $user
            );
        }
    }

    /**
     * Replace cloths in order
     *
     * @param Order $order
     * @param array $replacements
     * @param mixed $user
     * @return array ['success' => bool, 'error' => array|null]
     */
    public function replaceClothes(Order $order, array $replacements, $user): array
    {
        $inventory = $order->inventory;

        foreach ($replacements as $index => $replacement) {
            $result = $this->replaceSingleCloth(
                $order,
                $inventory,
                $replacement['old_cloth_id'],
                $replacement['new_cloth_id'],
                $index,
                $user
            );

            if (!$result['success']) {
                return $result;
            }
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * Replace a single cloth in order
     *
     * @param Order $order
     * @param mixed $inventory
     * @param int $oldClothId
     * @param int $newClothId
     * @param int $index
     * @param mixed $user
     * @return array ['success' => bool, 'error' => array|null]
     */
    protected function replaceSingleCloth(Order $order, $inventory, int $oldClothId, int $newClothId, int $index, $user): array
    {
        // Validate old cloth belongs to this order
        $existingItem = $order->items()->where('clothes.id', $oldClothId)->first();
        if (!$existingItem) {
            return [
                'success' => false,
                'error' => [
                    'message' => 'القطعة القديمة غير موجودة في هذا الطلب',
                    'errors' => ["replace_items.{$index}.old_cloth_id" => ["Cloth {$oldClothId} is not part of this order"]]
                ]
            ];
        }

        // Get the new cloth
        $newCloth = Cloth::find($newClothId);
        if (!$newCloth) {
            return [
                'success' => false,
                'error' => [
                    'message' => 'القطعة الجديدة غير موجودة',
                    'errors' => ["replace_items.{$index}.new_cloth_id" => ["Cloth {$newClothId} does not exist"]]
                ]
            ];
        }

        // Validate new cloth is in the order's inventory
        if ($inventory) {
            $clothInInventory = $inventory->clothes()->where('clothes.id', $newClothId)->first();
            if (!$clothInInventory) {
                return [
                    'success' => false,
                    'error' => [
                        'message' => 'القطعة الجديدة غير موجودة في المخزن',
                        'errors' => ["replace_items.{$index}.new_cloth_id" => ["Cloth {$newCloth->code} is not in the order's inventory"]]
                    ]
                ];
            }
        }

        // Check if new cloth is already sold
        if ($newCloth->status === 'sold') {
            return [
                'success' => false,
                'error' => [
                    'message' => 'القطعة الجديدة مباعة بالفعل',
                    'errors' => ["replace_items.{$index}.new_cloth_id" => ["Cloth {$newCloth->code} is already sold"]]
                ]
            ];
        }

        // Check if new cloth is already in another active order
        $newClothInOtherOrder = DB::table('cloth_order')
            ->join('orders', 'orders.id', '=', 'cloth_order.order_id')
            ->where('cloth_order.cloth_id', $newClothId)
            ->where('cloth_order.order_id', '!=', $order->id)
            ->whereNotIn('orders.status', ['finished', 'canceled'])
            ->exists();

        if ($newClothInOtherOrder) {
            return [
                'success' => false,
                'error' => [
                    'message' => 'القطعة الجديدة موجودة في طلب آخر نشط',
                    'errors' => ["replace_items.{$index}.new_cloth_id" => ["Cloth {$newCloth->code} is already in another active order"]]
                ]
            ];
        }

        // Perform the replacement
        $this->performClothReplacement($order, $existingItem, $newCloth, $oldClothId, $user);

        return ['success' => true, 'error' => null];
    }

    /**
     * Perform the actual cloth replacement
     *
     * @param Order $order
     * @param mixed $existingItem
     * @param Cloth $newCloth
     * @param int $oldClothId
     * @param mixed $user
     * @return void
     */
    protected function performClothReplacement(Order $order, $existingItem, Cloth $newCloth, int $oldClothId, $user): void
    {
        // Get the pivot data from old cloth
        $pivotData = $this->extractPivotData($existingItem);

        // Return old cloth to ready_for_rent
        $oldCloth = Cloth::find($oldClothId);
        $oldCloth->status = 'ready_for_rent';
        $oldCloth->save();

        // Cancel any rent records for old cloth
        $this->cancelRentRecords($order->id, $oldClothId);

        // Detach old cloth and attach new cloth
        $order->items()->detach($oldClothId);
        $order->items()->attach($newCloth->id, $pivotData);

        // Update new cloth status based on type
        $this->updateClothStatusByType($newCloth, $pivotData['type']);

        // Log the replacement
        $this->orderHistoryService->logItemRemoved($order, $oldClothId, $oldCloth->code, $user);
        $this->orderHistoryService->logItemAdded($order, $newCloth->id, $newCloth->code, $user);
        $this->clothHistoryService->recordOrdered($newCloth, $order, $user);
    }

    /**
     * Extract pivot data from existing item
     *
     * @param mixed $existingItem
     * @return array
     */
    protected function extractPivotData($existingItem): array
    {
        return [
            'price' => $existingItem->pivot->price,
            'type' => $existingItem->pivot->type,
            'quantity' => $existingItem->pivot->quantity ?? 1,
            'paid' => $existingItem->pivot->paid ?? 0,
            'remaining' => $existingItem->pivot->remaining ?? 0,
            'days_of_rent' => $existingItem->pivot->days_of_rent,
            'occasion_datetime' => $existingItem->pivot->occasion_datetime,
            'delivery_date' => $existingItem->pivot->delivery_date,
            'status' => $existingItem->pivot->status,
            'notes' => $existingItem->pivot->notes,
            'discount_type' => $existingItem->pivot->discount_type,
            'discount_value' => $existingItem->pivot->discount_value,
            'returnable' => $existingItem->pivot->returnable,
            'sleeve_length' => $existingItem->pivot->sleeve_length,
            'forearm' => $existingItem->pivot->forearm,
            'shoulder_width' => $existingItem->pivot->shoulder_width,
            'cuffs' => $existingItem->pivot->cuffs,
            'waist' => $existingItem->pivot->waist,
            'chest_length' => $existingItem->pivot->chest_length,
            'total_length' => $existingItem->pivot->total_length,
            'hinch' => $existingItem->pivot->hinch,
            'dress_size' => $existingItem->pivot->dress_size,
        ];
    }

    /**
     * Cancel rent records for a cloth
     *
     * @param int $orderId
     * @param int $clothId
     * @return void
     */
    protected function cancelRentRecords(int $orderId, int $clothId): void
    {
        $clothOrderId = DB::table('cloth_order')
            ->where('order_id', $orderId)
            ->where('cloth_id', $clothId)
            ->value('id');

        if ($clothOrderId) {
            Rent::where('cloth_order_id', $clothOrderId)->update(['status' => 'canceled']);
        }
    }

    /**
     * Update cloth status based on order type
     *
     * @param Cloth $cloth
     * @param string $type
     * @return void
     */
    protected function updateClothStatusByType(Cloth $cloth, string $type): void
    {
        $statusMap = [
            'rent' => 'rented',
            'buy' => 'sold',
            'tailoring' => 'repairing',
        ];

        if (isset($statusMap[$type])) {
            $cloth->status = $statusMap[$type];
            $cloth->save();
        }
    }
}

