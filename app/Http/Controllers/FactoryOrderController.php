<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\FactoryItemStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FactoryOrderController extends Controller
{
    // Factory item status constants
    const STATUS_NEW = 'new';
    const STATUS_PENDING_FACTORY_APPROVAL = 'pending_factory_approval';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    const STATUS_DELIVERED_TO_ATELIER = 'delivered_to_atelier';
    const STATUS_CLOSED = 'closed';

    /**
     * Get factory ID for the authenticated user
     */
    private function getFactoryId(): ?int
    {
        $user = auth()->user();
        return $user->getFactoryId();
    }

    /**
     * Check if user belongs to factory assigned to order
     */
    private function checkFactoryAccess(Order $order): bool
    {
        $factoryId = $this->getFactoryId();
        if (!$factoryId) {
            return false;
        }
        return $order->assigned_factory_id == $factoryId;
    }

    /**
     * Get cloth_order pivot ID
     */
    private function getClothOrderId(int $orderId, int $clothId): ?int
    {
        return DB::table('cloth_order')
            ->where('order_id', $orderId)
            ->where('cloth_id', $clothId)
            ->value('id');
    }

    /**
     * Get valid next statuses
     */
    private function getValidNextStatuses(?string $currentStatus): array
    {
        $transitions = [
            null => [self::STATUS_PENDING_FACTORY_APPROVAL],
            self::STATUS_NEW => [self::STATUS_PENDING_FACTORY_APPROVAL],
            self::STATUS_PENDING_FACTORY_APPROVAL => [self::STATUS_ACCEPTED, self::STATUS_REJECTED],
            self::STATUS_ACCEPTED => [self::STATUS_IN_PROGRESS],
            self::STATUS_IN_PROGRESS => [self::STATUS_READY_FOR_DELIVERY],
            self::STATUS_READY_FOR_DELIVERY => [self::STATUS_DELIVERED_TO_ATELIER],
            self::STATUS_DELIVERED_TO_ATELIER => [self::STATUS_CLOSED],
            self::STATUS_REJECTED => [],
            self::STATUS_CLOSED => [],
        ];

        return $transitions[$currentStatus] ?? [];
    }

    /**
     * Filter order response to hide prices/payments for factory users
     */
    private function filterOrderResponse(Order $order): array
    {
        $orderArray = $order->toArray();

        // Remove pricing information
        unset($orderArray['total_price']);
        unset($orderArray['paid']);
        unset($orderArray['remaining']);

        // Remove payments if loaded
        if (isset($orderArray['payments'])) {
            unset($orderArray['payments']);
        }

        // Filter items - remove price information
        if (isset($orderArray['items'])) {
            $orderArray['items'] = array_map(function ($item) {
                if (isset($item['pivot'])) {
                    unset($item['pivot']['price']);
                    unset($item['pivot']['discount_type']);
                    unset($item['pivot']['discount_value']);
                }
                return $item;
            }, $orderArray['items']);
        }

        // Only show minimal client info
        if (isset($orderArray['client'])) {
            $orderArray['client'] = [
                'id' => $orderArray['client']['id'] ?? null,
                'first_name' => $orderArray['client']['first_name'] ?? null,
                'last_name' => $orderArray['client']['last_name'] ?? null,
            ];
        }

        return $orderArray;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factory/orders",
     *     summary="List orders assigned to factory",
     *     tags={"Factory Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="factory_status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $factoryId = $this->getFactoryId();
        if (!$factoryId) {
            return response()->json(['message' => 'User is not assigned to a factory'], 403);
        }

        $query = Order::with(['client:id,first_name,last_name', 'items.clothType'])
            ->forFactory($factoryId)
            ->tailoringOrders();

        // Filter by factory status if provided
        if ($request->has('factory_status')) {
            $factoryStatus = $request->query('factory_status');
            $query->whereHas('items', function ($q) use ($factoryStatus) {
                $q->where('cloth_order.type', 'tailoring')
                  ->where('cloth_order.factory_status', $factoryStatus);
            });
        }

        // Filter by order status if provided
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Filter responses
        $orders->getCollection()->transform(function ($order) {
            return $this->filterOrderResponse($order);
        });

        return $this->paginatedResponse($orders);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factory/orders/{id}",
     *     summary="Get order details",
     *     tags={"Factory Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $order = Order::with(['client:id,first_name,last_name', 'items.clothType'])
            ->findOrFail($id);

        if (!$this->checkFactoryAccess($order)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        // Only show tailoring items
        $order->setRelation('items', $order->items()->wherePivot('type', 'tailoring')->get());

        return response()->json($this->filterOrderResponse($order));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/factory/orders/{orderId}/items/{itemId}/accept",
     *     summary="Accept a tailoring item",
     *     tags={"Factory Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="itemId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="expected_delivery_date", type="string", format="date"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Item accepted"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function acceptItem(Request $request, $orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);

        if (!$this->checkFactoryAccess($order)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $item = $order->items()->wherePivot('type', 'tailoring')->findOrFail($itemId);
        $currentStatus = $item->pivot->factory_status;

        // Validate status transition
        $validNextStatuses = $this->getValidNextStatuses($currentStatus);
        if (!in_array(self::STATUS_ACCEPTED, $validNextStatuses)) {
            return response()->json([
                'message' => 'Invalid status transition',
                'errors' => ['status' => ['Cannot accept item with current status: ' . ($currentStatus ?? 'new')]]
            ], 422);
        }

        $validated = $request->validate([
            'expected_delivery_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        $clothOrderId = $this->getClothOrderId($orderId, $itemId);
        if (!$clothOrderId) {
            return response()->json(['message' => 'Item not found in order'], 404);
        }

        DB::transaction(function () use ($order, $item, $validated, $clothOrderId, $currentStatus) {
            // Save old status before update
            $oldStatus = $currentStatus;

            // Update pivot
            $order->items()->updateExistingPivot($item->id, [
                'factory_status' => self::STATUS_ACCEPTED,
                'factory_accepted_at' => now(),
                'factory_expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'factory_notes' => $validated['notes'] ?? $item->pivot->factory_notes,
            ]);

            // Log status change
            FactoryItemStatusLog::create([
                'cloth_order_id' => $clothOrderId,
                'from_status' => $oldStatus,
                'to_status' => self::STATUS_ACCEPTED,
                'changed_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
                'metadata' => [
                    'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                ],
            ]);
        });

        return response()->json(['message' => 'Item accepted successfully'], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/factory/orders/{orderId}/items/{itemId}/reject",
     *     summary="Reject a tailoring item",
     *     tags={"Factory Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="itemId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rejection_reason"},
     *             @OA\Property(property="rejection_reason", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Item rejected"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function rejectItem(Request $request, $orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);

        if (!$this->checkFactoryAccess($order)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $item = $order->items()->wherePivot('type', 'tailoring')->findOrFail($itemId);
        $currentStatus = $item->pivot->factory_status;

        // Validate status transition
        $validNextStatuses = $this->getValidNextStatuses($currentStatus);
        if (!in_array(self::STATUS_REJECTED, $validNextStatuses)) {
            return response()->json([
                'message' => 'Invalid status transition',
                'errors' => ['status' => ['Cannot reject item with current status: ' . ($currentStatus ?? 'new')]]
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $clothOrderId = $this->getClothOrderId($orderId, $itemId);
        if (!$clothOrderId) {
            return response()->json(['message' => 'Item not found in order'], 404);
        }

        DB::transaction(function () use ($order, $item, $validated, $clothOrderId, $currentStatus) {
            // Save old status before update
            $oldStatus = $currentStatus;

            // Update pivot
            $order->items()->updateExistingPivot($item->id, [
                'factory_status' => self::STATUS_REJECTED,
                'factory_rejected_at' => now(),
                'factory_rejection_reason' => $validated['rejection_reason'],
                'factory_notes' => $validated['notes'] ?? $item->pivot->factory_notes,
            ]);

            // Log status change
            FactoryItemStatusLog::create([
                'cloth_order_id' => $clothOrderId,
                'from_status' => $oldStatus,
                'to_status' => self::STATUS_REJECTED,
                'changed_by' => auth()->id(),
                'rejection_reason' => $validated['rejection_reason'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return response()->json(['message' => 'Item rejected successfully'], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/factory/orders/{orderId}/items/{itemId}/status",
     *     summary="Update item status",
     *     tags={"Factory Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="itemId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"in_progress", "ready_for_delivery"}),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status updated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateItemStatus(Request $request, $orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);

        if (!$this->checkFactoryAccess($order)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $item = $order->items()->wherePivot('type', 'tailoring')->findOrFail($itemId);
        $currentStatus = $item->pivot->factory_status;

        // Cannot modify after delivery
        if ($currentStatus === self::STATUS_DELIVERED_TO_ATELIER || $currentStatus === self::STATUS_CLOSED) {
            return response()->json([
                'message' => 'Cannot modify item after delivery',
                'errors' => ['status' => ['Item has already been delivered']]
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:in_progress,ready_for_delivery',
            'notes' => 'nullable|string|max:1000',
        ]);

        $newStatus = $validated['status'];

        // Validate status transition
        $validNextStatuses = $this->getValidNextStatuses($currentStatus);
        if (!in_array($newStatus, $validNextStatuses)) {
            return response()->json([
                'message' => 'Invalid status transition',
                'errors' => ['status' => ['Cannot transition from ' . ($currentStatus ?? 'new') . ' to ' . $newStatus]]
            ], 422);
        }

        $clothOrderId = $this->getClothOrderId($orderId, $itemId);
        if (!$clothOrderId) {
            return response()->json(['message' => 'Item not found in order'], 404);
        }

        DB::transaction(function () use ($order, $item, $validated, $newStatus, $clothOrderId, $currentStatus) {
            // Update pivot
            $updateData = [
                'factory_status' => $newStatus,
            ];

            if ($validated['notes'] ?? null) {
                $updateData['factory_notes'] = ($item->pivot->factory_notes ?? '') . "\n" . $validated['notes'];
            }

            $order->items()->updateExistingPivot($item->id, $updateData);

            // Log status change
            FactoryItemStatusLog::create([
                'cloth_order_id' => $clothOrderId,
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
                'changed_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return response()->json(['message' => 'Status updated successfully'], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/factory/orders/{orderId}/items/{itemId}/notes",
     *     summary="Update item notes",
     *     tags={"Factory Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="itemId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"notes"},
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Notes updated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateItemNotes(Request $request, $orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);

        if (!$this->checkFactoryAccess($order)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $item = $order->items()->wherePivot('type', 'tailoring')->findOrFail($itemId);

        $currentStatus = $item->pivot->factory_status;
        if ($currentStatus === self::STATUS_DELIVERED_TO_ATELIER || $currentStatus === self::STATUS_CLOSED) {
            return response()->json(['message' => 'Cannot modify item after delivery'], 422);
        }

        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $order->items()->updateExistingPivot($itemId, [
            'factory_notes' => $validated['notes'],
        ]);

        return response()->json(['message' => 'Notes updated successfully'], 200);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/factory/orders/{orderId}/items/{itemId}/delivery-date",
     *     summary="Set expected delivery date",
     *     tags={"Factory Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="itemId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"expected_delivery_date"},
     *             @OA\Property(property="expected_delivery_date", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Delivery date set"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function setDeliveryDate(Request $request, $orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);

        if (!$this->checkFactoryAccess($order)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $item = $order->items()->wherePivot('type', 'tailoring')->findOrFail($itemId);

        $currentStatus = $item->pivot->factory_status;
        if ($currentStatus === self::STATUS_DELIVERED_TO_ATELIER || $currentStatus === self::STATUS_CLOSED) {
            return response()->json(['message' => 'Cannot modify item after delivery'], 422);
        }

        $validated = $request->validate([
            'expected_delivery_date' => 'required|date|after:today',
        ]);

        $order->items()->updateExistingPivot($itemId, [
            'factory_expected_delivery_date' => $validated['expected_delivery_date'],
        ]);

        return response()->json(['message' => 'Delivery date set successfully'], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/factory/orders/{orderId}/items/{itemId}/deliver",
     *     summary="Confirm item delivery",
     *     tags={"Factory Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="itemId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Item delivered"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function deliverItem(Request $request, $orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);

        if (!$this->checkFactoryAccess($order)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $item = $order->items()->wherePivot('type', 'tailoring')->findOrFail($itemId);
        $currentStatus = $item->pivot->factory_status;

        // Must be ready_for_delivery to deliver
        if ($currentStatus !== self::STATUS_READY_FOR_DELIVERY) {
            return response()->json([
                'message' => 'Invalid status for delivery',
                'errors' => ['status' => ['Item must be ready_for_delivery to deliver']]
            ], 422);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $clothOrderId = $this->getClothOrderId($orderId, $itemId);
        if (!$clothOrderId) {
            return response()->json(['message' => 'Item not found in order'], 404);
        }

        DB::transaction(function () use ($order, $item, $validated, $clothOrderId, $currentStatus) {
            // Save old status before update
            $oldStatus = $currentStatus;

            // Update pivot
            $updateData = [
                'factory_status' => self::STATUS_DELIVERED_TO_ATELIER,
                'factory_delivered_at' => now(),
            ];

            if ($validated['notes'] ?? null) {
                $updateData['factory_notes'] = ($item->pivot->factory_notes ?? '') . "\n" . $validated['notes'];
            }

            $order->items()->updateExistingPivot($item->id, $updateData);

            // Log status change
            FactoryItemStatusLog::create([
                'cloth_order_id' => $clothOrderId,
                'from_status' => $oldStatus,
                'to_status' => self::STATUS_DELIVERED_TO_ATELIER,
                'changed_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        return response()->json(['message' => 'Item delivered successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factory/orders/{orderId}/items/{itemId}/history",
     *     summary="Get item status history",
     *     tags={"Factory Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="orderId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="itemId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function getItemStatusHistory(Request $request, $orderId, $itemId)
    {
        $order = Order::findOrFail($orderId);

        if (!$this->checkFactoryAccess($order)) {
            return response()->json(['message' => 'Access denied'], 403);
        }

        $item = $order->items()->wherePivot('type', 'tailoring')->findOrFail($itemId);

        $clothOrderId = $this->getClothOrderId($orderId, $itemId);
        if (!$clothOrderId) {
            return response()->json(['message' => 'Item not found in order'], 404);
        }

        $perPage = (int) $request->query('per_page', 15);

        $logs = FactoryItemStatusLog::with('changedBy:id,name,email')
            ->forItem($clothOrderId)
            ->latest()
            ->paginate($perPage);

        return $this->paginatedResponse($logs);
    }
}
