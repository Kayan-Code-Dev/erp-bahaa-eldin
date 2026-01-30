<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Workshop;
use App\Models\WorkshopLog;
use App\Models\Inventory;
use App\Models\Address;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\TransferAction;
use App\Models\Cloth;
use App\Models\Notification;
use App\Models\User;
use App\Services\ClothHistoryService;
use Illuminate\Support\Facades\DB;

class WorkshopController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/workshops",
     *     summary="List all workshops",
     *     tags={"Workshops"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="workshop_code", type="string", example="WS-001"),
     *                 @OA\Property(property="name", type="string", example="Main Workshop"),
     *                 @OA\Property(property="address_id", type="integer", example=1),
     *                 @OA\Property(property="address", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="street", type="string", example="Industrial Zone"),
     *                     @OA\Property(property="building", type="string", example="Block 5"),
     *                     @OA\Property(property="notes", type="string", nullable=true),
     *                     @OA\Property(property="city_id", type="integer", example=1),
     *                     @OA\Property(property="city", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Cairo"),
     *                         @OA\Property(property="country_id", type="integer", example=1),
     *                         @OA\Property(property="country", type="object", nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Egypt")
     *                         )
     *                     )
     *                 )
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="total_pages", type="integer", example=7),
     *             @OA\Property(property="per_page", type="integer", example=15)
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = Workshop::with(['inventory', 'address.city.country'])->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/workshops/{id}",
     *     summary="Get a workshop by ID",
     *     tags={"Workshops"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="workshop_code", type="string", example="WS-001"),
     *             @OA\Property(property="name", type="string", example="Main Workshop"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Industrial Zone"),
     *                 @OA\Property(property="building", type="string", example="Block 5"),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="city", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Cairo"),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="country", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Egypt")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Workshop::with(['inventory', 'address.city.country'])->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/workshops",
     *     summary="Create a new workshop",
     *     tags={"Workshops"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"workshop_code", "name", "address"},
     *             @OA\Property(property="workshop_code", type="string", example="WS-001"),
     *             @OA\Property(property="name", type="string", example="Main Workshop"),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 required={"street", "building", "city_id"},
     *                 @OA\Property(property="street", type="string", example="Industrial Zone"),
     *                 @OA\Property(property="building", type="string", example="Block 5"),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="notes", type="string", example="Near main entrance")
     *             ),
     *             @OA\Property(property="inventory_name", type="string", example="Main Workshop Inventory", description="Optional: name for the automatically created inventory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Workshop created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="workshop_code", type="string", example="WS-001"),
     *             @OA\Property(property="name", type="string", example="Main Workshop"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Industrial Zone"),
     *                 @OA\Property(property="building", type="string", example="Block 5"),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="city", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Cairo"),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="country", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Egypt")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'workshop_code' => 'required|string|unique:workshops,workshop_code',
            'name' => 'required|string',
            'address' => 'required|array',
            'address.street' => 'required|string',
            'address.building' => 'required|string',
            'address.city_id' => 'required|exists:cities,id',
            'address.notes' => 'nullable|string',
            'inventory_name' => 'nullable|string',
        ]);

        $inventoryName = $data['inventory_name'] ?? $data['name'] . ' Inventory';
        unset($data['inventory_name']);

        // Create address first
        $address = Address::create($data['address']);
        $data['address_id'] = $address->id;
        unset($data['address']);

        $workshop = Workshop::create($data);

        // Automatically create inventory for the workshop
        $workshop->inventory()->create([
            'name' => $inventoryName,
        ]);

        return response()->json($workshop->load(['inventory', 'address.city.country']), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/workshops/{id}",
     *     summary="Update a workshop",
     *     tags={"Workshops"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="workshop_code", type="string", example="WS-001-UPDATED"),
     *             @OA\Property(property="name", type="string", example="Updated Workshop"),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 required={"street", "building", "city_id"},
     *                 @OA\Property(property="street", type="string", example="Industrial Zone"),
     *                 @OA\Property(property="building", type="string", example="Block 5"),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="notes", type="string", example="Near main entrance")
     *             ),
     *             @OA\Property(property="inventory_name", type="string", example="Updated Workshop Inventory", description="Optional: name for the inventory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workshop updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="workshop_code", type="string", example="WS-001-UPDATED"),
     *             @OA\Property(property="name", type="string", example="Updated Workshop"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Industrial Zone"),
     *                 @OA\Property(property="building", type="string", example="Block 5"),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="city", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Cairo"),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="country", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Egypt")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = Workshop::findOrFail($id);

        $validationRules = [
            'workshop_code' => "sometimes|required|string|unique:workshops,workshop_code,{$id}",
            'name' => 'sometimes|required|string',
            'address' => 'sometimes|required|array',
            'address.street' => 'required_with:address|string',
            'address.building' => 'required_with:address|string',
            'address.city_id' => 'required_with:address|exists:cities,id',
            'address.notes' => 'nullable|string',
            'inventory_name' => 'nullable|string',
        ];

        $data = $request->validate($validationRules);

        // Extract address from data if provided
        $address = null;
        if ($request->has('address')) {
            $address = $data['address'];
            unset($data['address']);
        }

        // Extract inventory_name from data if provided
        $inventoryName = null;
        if ($request->has('inventory_name')) {
            $inventoryName = $data['inventory_name'];
            unset($data['inventory_name']);
        }

        // Update or create address if provided
        if ($address !== null) {
            if ($item->address_id) {
                // Update existing address
                $item->address->update($address);
            } else {
                // Create new address
                $addressModel = Address::create($address);
                $data['address_id'] = $addressModel->id;
            }
        }

        // Update workshop data
        if (!empty($data)) {
            $item->update($data);
        }

        // Update inventory name if provided and inventory exists
        if ($inventoryName !== null && $item->inventory) {
            $item->inventory->update(['name' => $inventoryName]);
        }

        return response()->json($item->load(['inventory', 'address.city.country']));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/workshops/{id}",
     *     summary="Delete a workshop",
     *     tags={"Workshops"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Workshop deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Workshop::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    // ==================== WORKSHOP CLOTH MANAGEMENT ====================

    /**
     * @OA\Get(
     *     path="/api/v1/workshops/{id}/clothes",
     *     summary="List all clothes currently in the workshop with their status",
     *     tags={"Workshop Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by workshop status", @OA\Schema(type="string", enum={"received", "processing", "ready_for_delivery"})),
     *     @OA\Response(
     *         response=200,
     *         description="List of clothes in workshop",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="CL-001"),
     *                 @OA\Property(property="name", type="string", example="Wedding Dress"),
     *                 @OA\Property(property="workshop_status", type="string", enum={"received", "processing", "ready_for_delivery"}, example="processing"),
     *                 @OA\Property(property="workshop_notes", type="string", nullable=true, example="Needs pressing"),
     *                 @OA\Property(property="received_at", type="string", format="datetime", nullable=true)
     *             )),
     *             @OA\Property(property="total", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Workshop not found")
     * )
     */
    public function clothes(Request $request, $id)
    {
        $workshop = Workshop::findOrFail($id);
        $statusFilter = $request->query('status');
        $perPage = (int) $request->query('per_page', 15);

        $clothes = $workshop->clothesWithStatus();

        if ($statusFilter) {
            $clothes = $clothes->filter(fn($cloth) => $cloth->workshop_status === $statusFilter);
        }

        // Convert collection to paginated response
        $currentPage = (int) $request->query('page', 1);
        $items = $clothes->values();
        $total = $items->count();
        $offset = ($currentPage - 1) * $perPage;
        $itemsForPage = $items->slice($offset, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $itemsForPage,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->paginatedResponse($paginator);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/workshops/{id}/pending-transfers",
     *     summary="List pending incoming transfers to this workshop",
     *     tags={"Workshop Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of pending transfers",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="from_entity_type", type="string", example="branch"),
     *                 @OA\Property(property="from_entity_id", type="integer", example=1),
     *                 @OA\Property(property="from_entity_name", type="string", example="Main Branch"),
     *                 @OA\Property(property="transfer_date", type="string", format="date"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="items", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="cloth_id", type="integer"),
     *                     @OA\Property(property="cloth_code", type="string"),
     *                     @OA\Property(property="cloth_name", type="string"),
     *                     @OA\Property(property="status", type="string")
     *                 ))
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Workshop not found")
     * )
     */
    public function pendingTransfers(Request $request, $id)
    {
        $workshop = Workshop::findOrFail($id);
        $perPage = (int) $request->query('per_page', 15);

        $query = $workshop->pendingIncomingTransfers();

        $transfers = $query->paginate($perPage);

        $transfers->getCollection()->transform(function ($transfer) {
            return [
                'id' => $transfer->id,
                'from_entity_type' => $transfer->from_entity_type,
                'from_entity_id' => $transfer->from_entity_id,
                'from_entity_name' => $transfer->fromEntity->name ?? null,
                'transfer_date' => $transfer->transfer_date,
                'status' => $transfer->status,
                'notes' => $transfer->notes,
                'items' => $transfer->items->map(fn($item) => [
                    'id' => $item->id,
                    'cloth_id' => $item->cloth_id,
                    'cloth_code' => $item->cloth->code ?? null,
                    'cloth_name' => $item->cloth->name ?? null,
                    'status' => $item->status,
                ]),
            ];
        });

        return $this->paginatedResponse($transfers);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/workshops/{id}/approve-transfer/{transfer_id}",
     *     summary="Approve an incoming transfer and receive clothes into workshop",
     *     tags={"Workshop Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Workshop ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="transfer_id", in="path", required=true, description="Transfer ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="item_ids", type="array", @OA\Items(type="integer"), description="Optional: specific item IDs to approve. If not provided, all pending items are approved.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer approved, clothes received",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Transfer approved successfully"),
     *             @OA\Property(property="transfer", type="object"),
     *             @OA\Property(property="clothes_received", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Workshop or transfer not found"),
     *     @OA\Response(response=422, description="Transfer not destined for this workshop")
     * )
     */
    public function approveTransfer(Request $request, $id, $transferId)
    {
        $workshop = Workshop::findOrFail($id);
        $transfer = Transfer::with('items.cloth')->findOrFail($transferId);

        // Validate transfer is destined for this workshop
        if ($transfer->to_entity_type !== 'workshop' || $transfer->to_entity_id !== $workshop->id) {
            return response()->json([
                'message' => 'This transfer is not destined for this workshop',
                'errors' => ['transfer_id' => ['Transfer destination mismatch']]
            ], 422);
        }

        // Validate transfer status
        if (!in_array($transfer->status, ['pending', 'partially_pending'])) {
            return response()->json([
                'message' => 'Transfer cannot be approved',
                'errors' => ['status' => ['Transfer status is ' . $transfer->status]]
            ], 422);
        }

        $itemIds = $request->input('item_ids');
        $user = $request->user();
        $historyService = new ClothHistoryService();

        $clothesReceived = 0;

        DB::transaction(function () use ($transfer, $workshop, $itemIds, $user, $historyService, &$clothesReceived) {
            // Get items to approve
            $query = $transfer->items()->where('status', 'pending');
            if ($itemIds) {
                $query->whereIn('id', $itemIds);
            }
            $pendingItems = $query->get();

            $fromEntity = $transfer->fromEntity;
            $fromInventory = $fromEntity->inventory;
            $toInventory = $workshop->inventory;

            foreach ($pendingItems as $item) {
                $cloth = $item->cloth;

                // Move cloth to workshop inventory
                $cloth->inventories()->detach();
                $toInventory->clothes()->attach($cloth->id);

                // Update item status
                $item->update(['status' => 'approved']);

                // Create cloth history record
                $historyService->recordTransferred($cloth, $fromEntity, $workshop, $transfer, $user);

                // Create workshop log (received)
                WorkshopLog::create([
                    'workshop_id' => $workshop->id,
                    'cloth_id' => $cloth->id,
                    'transfer_id' => $transfer->id,
                    'action' => 'received',
                    'cloth_status' => 'received',
                    'received_at' => now(),
                    'user_id' => $user->id,
                ]);

                $clothesReceived++;
            }

            // Update transfer status
            $transfer->updateStatus();

            // Create transfer action
            TransferAction::create([
                'transfer_id' => $transfer->id,
                'user_id' => $user->id,
                'action' => $itemIds ? 'approved_items' : 'approved',
                'action_date' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Transfer approved successfully',
            'transfer' => $transfer->fresh()->load('items.cloth'),
            'clothes_received' => $clothesReceived,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/workshops/{id}/update-cloth-status",
     *     summary="Update cloth status and add notes in workshop",
     *     tags={"Workshop Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cloth_id", "status"},
     *             @OA\Property(property="cloth_id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", enum={"received", "processing", "ready_for_delivery"}, example="processing"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Needs minor repairs before pressing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cloth status updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Cloth status updated successfully"),
     *             @OA\Property(property="log", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="cloth_id", type="integer"),
     *                 @OA\Property(property="action", type="string", example="status_changed"),
     *                 @OA\Property(property="cloth_status", type="string"),
     *                 @OA\Property(property="notes", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Workshop or cloth not found"),
     *     @OA\Response(response=422, description="Cloth not in this workshop")
     * )
     */
    public function updateClothStatus(Request $request, $id)
    {
        $workshop = Workshop::findOrFail($id);

        $data = $request->validate([
            'cloth_id' => 'required|integer|exists:clothes,id',
            'status' => 'required|string|in:received,processing,ready_for_delivery',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cloth = Cloth::findOrFail($data['cloth_id']);

        // Verify cloth is in this workshop's inventory
        $clothInWorkshop = $workshop->inventory->clothes()
            ->where('clothes.id', $cloth->id)
            ->exists();

        if (!$clothInWorkshop) {
            return response()->json([
                'message' => 'Cloth is not in this workshop',
                'errors' => ['cloth_id' => ['Cloth must be in workshop inventory']]
            ], 422);
        }

        // Create workshop log
        $log = WorkshopLog::create([
            'workshop_id' => $workshop->id,
            'cloth_id' => $cloth->id,
            'action' => 'status_changed',
            'cloth_status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        // If marked as ready for delivery, notify branch staff
        if ($data['status'] === 'ready_for_delivery' && $workshop->branch) {
            $this->notifyBranchClothReady($workshop, $cloth);
        }

        return response()->json([
            'message' => 'Cloth status updated successfully',
            'log' => $log->load(['cloth', 'user']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/workshops/{id}/return-cloth",
     *     summary="Create a return transfer to send cloth back to branch",
     *     tags={"Workshop Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cloth_id"},
     *             @OA\Property(property="cloth_id", type="integer", example=1),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Cloth cleaned and pressed, ready for delivery")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Return transfer created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Return transfer created successfully"),
     *             @OA\Property(property="transfer", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Workshop or cloth not found"),
     *     @OA\Response(response=422, description="Validation error or cloth not in workshop")
     * )
     */
    public function returnCloth(Request $request, $id)
    {
        $workshop = Workshop::with('branch')->findOrFail($id);

        $data = $request->validate([
            'cloth_id' => 'required|integer|exists:clothes,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cloth = Cloth::findOrFail($data['cloth_id']);

        // Verify workshop has a branch
        if (!$workshop->branch) {
            return response()->json([
                'message' => 'Workshop has no associated branch',
                'errors' => ['workshop' => ['Workshop must be linked to a branch']]
            ], 422);
        }

        // Verify cloth is in this workshop's inventory
        $clothInWorkshop = $workshop->inventory->clothes()
            ->where('clothes.id', $cloth->id)
            ->exists();

        if (!$clothInWorkshop) {
            return response()->json([
                'message' => 'Cloth is not in this workshop',
                'errors' => ['cloth_id' => ['Cloth must be in workshop inventory']]
            ], 422);
        }

        // Check if a pending return transfer already exists
        $existingTransfer = Transfer::where('from_entity_type', 'workshop')
            ->where('from_entity_id', $workshop->id)
            ->where('to_entity_type', 'branch')
            ->where('to_entity_id', $workshop->branch->id)
            ->whereIn('status', ['pending', 'partially_pending'])
            ->whereHas('items', function ($query) use ($cloth) {
                $query->where('cloth_id', $cloth->id);
            })
            ->first();

        if ($existingTransfer) {
            return response()->json([
                'message' => 'A return transfer already exists for this cloth',
                'errors' => ['cloth_id' => ['Transfer #' . $existingTransfer->id . ' already pending']]
            ], 422);
        }

        $user = $request->user();
        $transfer = null;

        DB::transaction(function () use ($workshop, $cloth, $data, $user, &$transfer) {
            // Create the return transfer
            $transfer = Transfer::create([
                'from_entity_type' => 'workshop',
                'from_entity_id' => $workshop->id,
                'to_entity_type' => 'branch',
                'to_entity_id' => $workshop->branch->id,
                'transfer_date' => now()->format('Y-m-d'),
                'notes' => $data['notes'] ?? 'Return from workshop after processing',
                'status' => 'pending',
            ]);

            // Create transfer item
            TransferItem::create([
                'transfer_id' => $transfer->id,
                'cloth_id' => $cloth->id,
                'status' => 'pending',
            ]);

            // Create transfer action
            TransferAction::create([
                'transfer_id' => $transfer->id,
                'user_id' => $user->id,
                'action' => 'created',
                'action_date' => now(),
            ]);

            // Create workshop log (marking as returned)
            WorkshopLog::create([
                'workshop_id' => $workshop->id,
                'cloth_id' => $cloth->id,
                'transfer_id' => $transfer->id,
                'action' => 'returned',
                'cloth_status' => 'ready_for_delivery',
                'notes' => $data['notes'] ?? null,
                'returned_at' => now(),
                'user_id' => $user->id,
            ]);
        });

        // Notify branch about incoming cloth
        $this->notifyBranchIncomingCloth($workshop->branch, $transfer, $cloth);

        return response()->json([
            'message' => 'Return transfer created successfully',
            'transfer' => $transfer->load('items.cloth'),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/workshops/{id}/logs",
     *     summary="Get workshop operation logs",
     *     tags={"Workshop Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="cloth_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="action", in="query", required=false, @OA\Schema(type="string", enum={"received", "status_changed", "returned"})),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"received", "processing", "ready_for_delivery"})),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Workshop logs",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="workshop_id", type="integer"),
     *                 @OA\Property(property="cloth_id", type="integer"),
     *                 @OA\Property(property="cloth", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="code", type="string"),
     *                     @OA\Property(property="name", type="string")
     *                 ),
     *                 @OA\Property(property="transfer_id", type="integer", nullable=true),
     *                 @OA\Property(property="action", type="string"),
     *                 @OA\Property(property="action_label", type="string"),
     *                 @OA\Property(property="cloth_status", type="string"),
     *                 @OA\Property(property="status_label", type="string"),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="received_at", type="string", format="datetime", nullable=true),
     *                 @OA\Property(property="returned_at", type="string", format="datetime", nullable=true),
     *                 @OA\Property(property="user", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="datetime")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Workshop not found")
     * )
     */
    public function logs(Request $request, $id)
    {
        $workshop = Workshop::findOrFail($id);
        $perPage = (int) $request->query('per_page', 15);

        $query = WorkshopLog::forWorkshop($workshop->id)
            ->with(['cloth', 'transfer', 'user'])
            ->orderBy('created_at', 'desc');

        if ($request->has('cloth_id')) {
            $query->forCloth($request->query('cloth_id'));
        }

        if ($request->has('action')) {
            $query->byAction($request->query('action'));
        }

        if ($request->has('status')) {
            $query->byStatus($request->query('status'));
        }

        $logs = $query->paginate($perPage);

        // Add computed attributes
        $logs->getCollection()->transform(function ($log) {
            $log->action_label = $log->action_label;
            $log->status_label = $log->status_label;
            return $log;
        });

        return $this->paginatedResponse($logs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/workshops/{id}/cloth-history/{cloth_id}",
     *     summary="Get complete history of a cloth in this workshop",
     *     tags={"Workshop Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="cloth_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Cloth workshop history",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="cloth", type="object"),
     *             @OA\Property(property="current_status", type="string", nullable=true),
     *             @OA\Property(property="is_in_workshop", type="boolean"),
     *             @OA\Property(property="history", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Workshop or cloth not found")
     * )
     */
    public function clothHistory($id, $clothId)
    {
        $workshop = Workshop::findOrFail($id);
        $cloth = Cloth::findOrFail($clothId);

        $history = WorkshopLog::forWorkshop($workshop->id)
            ->forCloth($cloth->id)
            ->with(['transfer', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        $latestLog = $history->first();
        $isInWorkshop = $workshop->inventory->clothes()
            ->where('clothes.id', $cloth->id)
            ->exists();

        return response()->json([
            'cloth' => $cloth,
            'current_status' => $latestLog?->cloth_status,
            'is_in_workshop' => $isInWorkshop,
            'history' => $history,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/workshops/statuses",
     *     summary="Get available cloth statuses for workshops",
     *     tags={"Workshop Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of available statuses",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="received", type="string", example="Received"),
     *                 @OA\Property(property="processing", type="string", example="Processing"),
     *                 @OA\Property(property="ready_for_delivery", type="string", example="Ready for Delivery")
     *             )
     *         )
     *     )
     * )
     */
    public function statuses()
    {
        return response()->json([
            'data' => Workshop::CLOTH_STATUSES,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/workshops/actions",
     *     summary="Get available log action types for workshops",
     *     tags={"Workshop Management"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of available actions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="received", type="string", example="Cloth Received"),
     *                 @OA\Property(property="status_changed", type="string", example="Status Changed"),
     *                 @OA\Property(property="returned", type="string", example="Cloth Returned")
     *             )
     *         )
     *     )
     * )
     */
    public function actions()
    {
        return response()->json([
            'data' => WorkshopLog::ACTIONS,
        ]);
    }

    // ==================== NOTIFICATION HELPERS ====================

    /**
     * Notify branch staff that cloth is ready for pickup
     */
    protected function notifyBranchClothReady(Workshop $workshop, Cloth $cloth): void
    {
        if (!$workshop->branch) {
            return;
        }

        // Find users who can manage the branch (using roles relationship)
        $branchStaff = User::whereHas('roles.permissions', function ($query) {
            $query->whereIn('name', ['branches.view', 'transfers.approve']);
        })->orWhereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->get();

        foreach ($branchStaff as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'workshop_cloth_ready',
                'title' => 'Cloth Ready for Delivery',
                'message' => "Cloth '{$cloth->code}' is ready for delivery at workshop '{$workshop->name}'.",
                'reference_type' => Cloth::class,
                'reference_id' => $cloth->id,
                'priority' => 'normal',
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * Notify branch about incoming cloth transfer
     */
    protected function notifyBranchIncomingCloth($branch, Transfer $transfer, Cloth $cloth): void
    {
        $branchStaff = User::whereHas('roles.permissions', function ($query) {
            $query->whereIn('name', ['branches.view', 'transfers.approve']);
        })->orWhereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->get();

        foreach ($branchStaff as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'transfer_incoming',
                'title' => 'Cloth Return from Workshop',
                'message' => "Cloth '{$cloth->code}' is being returned from workshop. Transfer #{$transfer->id} pending approval.",
                'reference_type' => Transfer::class,
                'reference_id' => $transfer->id,
                'priority' => 'normal',
                'sent_at' => now(),
            ]);
        }
    }

    // ==================== EXPORT ====================

    /**
     * @OA\Get(
     *     path="/api/v1/workshops/export",
     *     summary="Export all workshops to CSV",
     *     tags={"Workshops"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="CSV file download",
     *         @OA\MediaType(
     *             mediaType="text/csv"
     *         )
     *     )
     * )
     */
    public function export(Request $request)
    {
        $items = Workshop::with(['address.city.country', 'inventory'])->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\WorkshopExport::class, 'workshops_' . date('Y-m-d_His') . '.csv');
    }
}
