<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transfer;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory;
use App\Models\Cloth;
use App\Models\Inventory;
use App\Models\TransferAction;
use App\Services\ClothHistoryService;
use App\Http\Controllers\Traits\FiltersByEntityAccess;
use App\Models\Employee;

class TransferController extends Controller
{
    use FiltersByEntityAccess;
    /**
     * Map enum entity_type values to model classes
     */
    private function getModelClassFromEntityType($entityType)
    {
        $map = [
            'branch' => \App\Models\Branch::class,
            'workshop' => \App\Models\Workshop::class,
            'factory' => \App\Models\Factory::class,
        ];
        return $map[$entityType] ?? null;
    }

    /**
     * Flatten pivot data in clothes collection
     */
    private function formatTransferResponse($transfer)
    {
        $transfer->load(['fromEntity', 'toEntity', 'items.cloth', 'actions.user']);

        $items = $transfer->items->map(function ($item) {
            return [
                'id' => $item->id,
                'cloth_id' => $item->cloth_id,
                'cloth_code' => $item->cloth->code ?? null,
                'cloth_name' => $item->cloth->name ?? null,
                'status' => $item->status,
            ];
        });

        return [
            'id' => $transfer->id,
            'from_entity_type' => $transfer->from_entity_type,
            'from_entity_id' => $transfer->from_entity_id,
            'from_entity_name' => $transfer->fromEntity->name ?? null,
            'to_entity_type' => $transfer->to_entity_type,
            'to_entity_id' => $transfer->to_entity_id,
            'to_entity_name' => $transfer->toEntity->name ?? null,
            'transfer_date' => $transfer->transfer_date,
            'notes' => $transfer->notes,
            'status' => $transfer->status,
            'items' => $items,
        ];
    }
    /**
     * @OA\Get(
     *     path="/api/v1/transfers",
     *     summary="List all transfers",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status. Possible values: pending, partially_pending, partially_approved, approved, rejected", @OA\Schema(type="string", enum={"pending", "partially_pending", "partially_approved", "approved", "rejected"})),
     *     @OA\Parameter(name="from_entity_type", in="query", required=false, description="Filter by source entity type. Possible values: branch, workshop, factory", @OA\Schema(type="string", enum={"branch", "workshop", "factory"})),
     *     @OA\Parameter(name="to_entity_type", in="query", required=false, description="Filter by destination entity type. Possible values: branch, workshop, factory", @OA\Schema(type="string", enum={"branch", "workshop", "factory"})),
     *     @OA\Parameter(name="action", in="query", required=false, description="Filter by action in transfer_actions. Possible values: created, updated, approved, approved_items, rejected, rejected_items, deleted", @OA\Schema(type="string", enum={"created", "updated", "approved", "approved_items", "rejected", "rejected_items", "deleted"})),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *                 @OA\Property(property="from_entity_id", type="integer", example=1),
     *                 @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="workshop (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *                 @OA\Property(property="to_entity_id", type="integer", example=2),
     *                 @OA\Property(property="transfer_date", type="string", format="date", example="2025-12-20"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
     *             @OA\Property(property="status", type="string", enum={"pending", "partially_pending", "partially_approved", "approved", "rejected"}, example="pending (allowed: pending, partially_pending, partially_approved, approved, rejected)", description="Possible values: pending, partially_pending, partially_approved, approved, rejected"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="cloth_id", type="integer", example=1),
     *                 @OA\Property(property="cloth_code", type="string", example="CL-101-001"),
     *                 @OA\Property(property="cloth_name", type="string", example="Red Dress Piece 1"),
                 *                 @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected"}, example="pending (allowed: pending, approved, rejected)")
     *             )),
     *                 @OA\Property(property="actions", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="action", type="string", enum={"created", "updated", "approved", "approved_items", "rejected", "rejected_items", "deleted"}, example="created (allowed: created, updated, approved, approved_items, rejected, rejected_items, deleted)"),
     *                     @OA\Property(property="action_date", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s"),
     *                     @OA\Property(property="user", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     )
     *                 ))
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
        $query = Transfer::with(['fromEntity', 'toEntity', 'items.cloth', 'actions.user'])
            ->orderBy('created_at', 'desc');

        // Filter by accessible entities (user can see transfers where they have access to source OR destination)
        $user = $request->user();
        if ($user && !$user->hasFullAccess()) {
            $accessService = $this->getEntityAccessService();

            $query->where(function ($q) use ($user, $accessService) {
                // Get accessible entity IDs for each type
                $branchIds = $accessService->getAccessibleEntityIds($user, Employee::ENTITY_TYPE_BRANCH);
                $workshopIds = $accessService->getAccessibleEntityIds($user, Employee::ENTITY_TYPE_WORKSHOP);
                $factoryIds = $accessService->getAccessibleEntityIds($user, Employee::ENTITY_TYPE_FACTORY);

                // Build OR conditions for source entities
                $q->where(function ($sourceQ) use ($branchIds, $workshopIds, $factoryIds) {
                    if ($branchIds !== null) {
                        $sourceQ->orWhere(function ($bq) use ($branchIds) {
                            $bq->where('from_entity_type', 'branch')->whereIn('from_entity_id', $branchIds);
                        });
                    } else {
                        $sourceQ->orWhere('from_entity_type', 'branch');
                    }

                    if ($workshopIds !== null) {
                        $sourceQ->orWhere(function ($wq) use ($workshopIds) {
                            $wq->where('from_entity_type', 'workshop')->whereIn('from_entity_id', $workshopIds);
                        });
                    } else {
                        $sourceQ->orWhere('from_entity_type', 'workshop');
                    }

                    if ($factoryIds !== null) {
                        $sourceQ->orWhere(function ($fq) use ($factoryIds) {
                            $fq->where('from_entity_type', 'factory')->whereIn('from_entity_id', $factoryIds);
                        });
                    } else {
                        $sourceQ->orWhere('from_entity_type', 'factory');
                    }
                });

                // OR conditions for destination entities
                $q->orWhere(function ($destQ) use ($branchIds, $workshopIds, $factoryIds) {
                    if ($branchIds !== null) {
                        $destQ->orWhere(function ($bq) use ($branchIds) {
                            $bq->where('to_entity_type', 'branch')->whereIn('to_entity_id', $branchIds);
                        });
                    } else {
                        $destQ->orWhere('to_entity_type', 'branch');
                    }

                    if ($workshopIds !== null) {
                        $destQ->orWhere(function ($wq) use ($workshopIds) {
                            $wq->where('to_entity_type', 'workshop')->whereIn('to_entity_id', $workshopIds);
                        });
                    } else {
                        $destQ->orWhere('to_entity_type', 'workshop');
                    }

                    if ($factoryIds !== null) {
                        $destQ->orWhere(function ($fq) use ($factoryIds) {
                            $fq->where('to_entity_type', 'factory')->whereIn('to_entity_id', $factoryIds);
                        });
                    } else {
                        $destQ->orWhere('to_entity_type', 'factory');
                    }
                });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('from_entity_type')) {
            $query->where('from_entity_type', $request->query('from_entity_type'));
        }

        if ($request->has('to_entity_type')) {
            $query->where('to_entity_type', $request->query('to_entity_type'));
        }

        if ($request->has('action')) {
            // Filter transfers that have the specified action in transfer_actions
            $query->whereHas('actions', function($q) use ($request) {
                $q->where('action', $request->query('action'));
            });
        }

        $items = $query->paginate($perPage);

        // Format each transfer
        $items->getCollection()->transform(function ($transfer) {
            return $this->formatTransferResponse($transfer);
        });

        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transfers/{id}",
     *     summary="Get a transfer by ID",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="from_entity_id", type="integer", example=1),
     *             @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="workshop (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="to_entity_id", type="integer", example=2),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2025-12-20"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
     *             @OA\Property(property="status", type="string", enum={"pending", "partially_pending", "partially_approved", "approved", "rejected"}, example="pending (allowed: pending, partially_pending, partially_approved, approved, rejected)", description="Possible values: pending, partially_pending, partially_approved, approved, rejected"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="cloth_id", type="integer", example=1),
     *                 @OA\Property(property="cloth_code", type="string", example="CL-101-001"),
     *                 @OA\Property(property="cloth_name", type="string", example="Red Dress Piece 1"),
                 *                 @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected"}, example="pending (allowed: pending, approved, rejected)")
     *             )),
     *             @OA\Property(property="actions", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="action", type="string", enum={"created", "updated", "approved", "approved_items", "rejected", "rejected_items", "deleted"}, example="created"),
     *                 @OA\Property(property="action_date", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 )
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $transfer = Transfer::findOrFail($id);
        return response()->json($this->formatTransferResponse($transfer));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transfers",
     *     summary="Create a new transfer",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_entity_type", "from_entity_id", "to_entity_type", "to_entity_id", "cloth_ids", "transfer_date"},
     *             @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="from_entity_id", type="integer", example=1),
     *             @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="workshop (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="to_entity_id", type="integer", example=2),
     *             @OA\Property(property="cloth_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}, description="Array of cloth piece IDs to transfer"),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2025-12-20"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Urgent transfer needed (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transfer created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="from_entity_id", type="integer", example=1),
     *             @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="workshop (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="to_entity_id", type="integer", example=2),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2025-12-20"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
     *             @OA\Property(property="status", type="string", enum={"pending", "partially_pending", "partially_approved", "approved", "rejected"}, example="pending (allowed: pending, partially_pending, partially_approved, approved, rejected)", description="Possible values: pending, partially_pending, partially_approved, approved, rejected"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="cloth_id", type="integer", example=1),
     *                 @OA\Property(property="cloth_code", type="string", example="CL-101-001"),
     *                 @OA\Property(property="cloth_name", type="string", example="Red Dress Piece 1"),
                 *                 @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected"}, example="pending (allowed: pending, approved, rejected)")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'from_entity_type' => 'required|string|in:branch,workshop,factory',
            'from_entity_id' => 'required|integer',
            'to_entity_type' => 'required|string|in:branch,workshop,factory',
            'to_entity_id' => 'required|integer',
            'cloth_ids' => 'required|array|min:1',
            'cloth_ids.*' => 'required|integer|exists:clothes,id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        // Map enum values to model classes
        $fromEntityTypeEnum = $data['from_entity_type'];
        $toEntityTypeEnum = $data['to_entity_type'];
        $fromModelClass = $this->getModelClassFromEntityType($fromEntityTypeEnum);
        $toModelClass = $this->getModelClassFromEntityType($toEntityTypeEnum);

        if (!$fromModelClass || !$toModelClass) {
            return response()->json([
                'message' => 'Invalid entity type',
                'errors' => ['entity_type' => ['Invalid entity type value']]
            ], 422);
        }

        // Validate that from and to entities are different
        if ($fromEntityTypeEnum === $toEntityTypeEnum &&
            $data['from_entity_id'] === $data['to_entity_id']) {
            return response()->json([
                'message' => 'Source and destination entities must be different',
                'errors' => ['to_entity_id' => ['Source and destination must be different']]
            ], 422);
        }

        // Validate entity exists
        $fromEntity = $fromModelClass::find($data['from_entity_id']);
        $toEntity = $toModelClass::find($data['to_entity_id']);

        // Store the enum values (not class names) - morphMap in Transfer model will handle conversion
        // Keep the enum values as they are

        if (!$fromEntity) {
            return response()->json([
                'message' => 'Source entity not found',
                'errors' => ['from_entity_id' => ['Source entity does not exist']]
            ], 422);
        }

        if (!$toEntity) {
            return response()->json([
                'message' => 'Destination entity not found',
                'errors' => ['to_entity_id' => ['Destination entity does not exist']]
            ], 422);
        }

        // Get inventories
        $fromInventory = $fromEntity->inventory;
        $toInventory = $toEntity->inventory;

        if (!$fromInventory) {
            return response()->json([
                'message' => 'Source entity does not have an inventory',
                'errors' => ['from_entity_id' => ['Source entity inventory not found']]
            ], 422);
        }

        if (!$toInventory) {
            return response()->json([
                'message' => 'Destination entity does not have an inventory',
                'errors' => ['to_entity_id' => ['Destination entity inventory not found']]
            ], 422);
        }

        // Validate each cloth ID in the transfer
        $clothIds = $data['cloth_ids'];
        unset($data['cloth_ids']);

        $cloths = [];
        foreach ($clothIds as $index => $clothId) {
            $cloth = Cloth::find($clothId);

            if (!$cloth) {
                return response()->json([
                    'message' => 'Cloth not found',
                    'errors' => ["cloth_ids.{$index}" => ['Cloth with ID ' . $clothId . ' does not exist']]
                ], 422);
            }

            // Check if cloth is sold - sold items cannot be transferred
            if ($cloth->status === 'sold') {
                return response()->json([
                    'message' => 'Sold cloth cannot be transferred',
                    'errors' => ["cloth_ids.{$index}" => [
                        'Cloth ID ' . $clothId . ' (' . $cloth->code . ') is sold and cannot be transferred.'
                    ]]
                ], 422);
            }

            // Check if cloth exists in source inventory
            $clothInInventory = $fromInventory->clothes()->where('clothes.id', $cloth->id)->first();

            if (!$clothInInventory) {
                return response()->json([
                    'message' => 'Cloth not found in source inventory',
                    'errors' => ["cloth_ids.{$index}" => [
                        'Cloth ID ' . $clothId . ' must be in source entity\'s inventory before it can be transferred.'
                    ]]
                ], 422);
            }

            $cloths[] = $cloth;
        }

        // Create transfer
        $data['status'] = 'pending';
        $transfer = Transfer::create($data);

        // Create transfer items (one record per cloth ID with status 'pending')
        foreach ($cloths as $cloth) {
            \App\Models\TransferItem::create([
                'transfer_id' => $transfer->id,
                'cloth_id' => $cloth->id,
                'status' => 'pending',
            ]);
        }

        // Create audit record
        TransferAction::create([
            'transfer_id' => $transfer->id,
            'user_id' => $request->user()->id,
            'action' => 'created',
            'action_date' => now(),
        ]);

        return response()->json($this->formatTransferResponse($transfer), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/transfers/{id}",
     *     summary="Update a transfer (only if pending - cannot edit approved or rejected transfers)",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="cloth_ids", type="array", @OA\Items(type="integer"), example={1, 2}, description="Optional. Array of cloth piece IDs to update in transfer"),
     *             @OA\Property(property="transfer_date", type="string", format="date", nullable=true, example="2025-12-21 (optional)"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Updated notes (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="from_entity_id", type="integer", example=1),
     *             @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="workshop (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="to_entity_id", type="integer", example=2),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2025-12-21"),
     *             @OA\Property(property="notes", type="string", nullable=true),
     *             @OA\Property(property="status", type="string", enum={"pending", "partially_pending", "partially_approved", "approved", "rejected"}, example="pending (allowed: pending, partially_pending, partially_approved, approved, rejected)", description="Possible values: pending, partially_pending, partially_approved, approved, rejected"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="cloth_id", type="integer", example=1),
     *                 @OA\Property(property="cloth_code", type="string", example="CL-101-001"),
     *                 @OA\Property(property="cloth_name", type="string", example="Red Dress Piece 1"),
                 *                 @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected"}, example="pending (allowed: pending, approved, rejected)")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=422, description="Transfer cannot be updated (not pending, approved, or rejected)")
     * )
     */
    public function update(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);

        if ($transfer->status === 'approved' || $transfer->status === 'rejected') {
            return response()->json([
                'message' => 'Approved or rejected transfers cannot be updated',
                'errors' => ['status' => ['Transfer status is ' . $transfer->status . '. Only pending or partially_pending transfers can be updated.']]
            ], 422);
        }

        $data = $request->validate([
            'cloth_ids' => 'sometimes|array|min:1',
            'cloth_ids.*' => 'required_with:cloth_ids|integer|exists:clothes,id',
            'transfer_date' => 'sometimes|required|date',
            'notes' => 'nullable|string',
        ]);

        $fromEntity = $transfer->fromEntity;
        $fromInventory = $fromEntity->inventory;

        // If cloth_ids are being updated, validate availability
        if (isset($data['cloth_ids'])) {
            $clothIds = $data['cloth_ids'];
            $cloths = [];

            foreach ($clothIds as $index => $clothId) {
                $cloth = Cloth::find($clothId);

                if (!$cloth) {
                    return response()->json([
                        'message' => 'Cloth not found',
                        'errors' => ["cloth_ids.{$index}" => ['Cloth with ID ' . $clothId . ' does not exist']]
                    ], 422);
                }

                // Check if cloth is sold - sold items cannot be transferred
                if ($cloth->status === 'sold') {
                    return response()->json([
                        'message' => 'Sold cloth cannot be transferred',
                        'errors' => ["cloth_ids.{$index}" => [
                            'Cloth ID ' . $clothId . ' (' . $cloth->code . ') is sold and cannot be transferred.'
                        ]]
                    ], 422);
                }

                // Check if cloth exists in source inventory
                $clothInInventory = $fromInventory->clothes()->where('clothes.id', $cloth->id)->first();

                if (!$clothInInventory) {
                    return response()->json([
                        'message' => 'Cloth not found in source inventory',
                        'errors' => ["cloth_ids.{$index}" => [
                            'Cloth ID ' . $clothId . ' must be in source entity\'s inventory before it can be transferred.'
                        ]]
                    ], 422);
                }

                $cloths[] = $cloth;
            }

            // Delete existing transfer items
            $transfer->items()->delete();

            // Create new transfer items (one record per cloth ID with status 'pending')
            foreach ($cloths as $cloth) {
                \App\Models\TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'cloth_id' => $cloth->id,
                    'status' => 'pending',
                ]);
            }
            unset($data['cloth_ids']);
        }

        // Update transfer fields
        if (!empty($data)) {
            $transfer->update($data);
        }

        // Create audit record
        TransferAction::create([
            'transfer_id' => $transfer->id,
            'user_id' => $request->user()->id,
            'action' => 'updated',
            'action_date' => now(),
        ]);

        return response()->json($this->formatTransferResponse($transfer->fresh()));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transfers/{id}/approve",
     *     summary="Approve a transfer and update inventory quantities. Cloth entities will be updated to destination entity.",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer approved and inventory updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="from_entity_id", type="integer", example=1),
     *             @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="workshop (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="to_entity_id", type="integer", example=2),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2025-12-20"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
     *             @OA\Property(property="status", type="string", enum={"pending", "partially_pending", "approved", "rejected"}, example="approved (allowed: pending, partially_pending, approved, rejected)", description="Possible values: pending, partially_pending, approved, rejected"),
     *             @OA\Property(property="clothes", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="CL-101"),
     *                 @OA\Property(property="name", type="string", example="Red Dress"),
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=422, description="Transfer cannot be approved")
     * )
     */
    public function approve(Request $request, $id)
    {
        $transfer = Transfer::with('items.cloth')->findOrFail($id);

        if ($transfer->status !== 'pending' && $transfer->status !== 'partially_pending' && $transfer->status !== 'partially_approved') {
            return response()->json([
                'message' => 'Only pending, partially pending, or partially approved transfers can be fully approved',
                'errors' => ['status' => ['Transfer status is ' . $transfer->status]]
            ], 422);
        }

        // Get all pending items
        $pendingItems = $transfer->items()->where('status', 'pending')->get();

        if ($pendingItems->isEmpty()) {
            return response()->json([
                'message' => 'No pending items to approve',
                'errors' => ['status' => ['All items are already approved or rejected']]
            ], 422);
        }

        $fromEntity = $transfer->fromEntity;
        $toEntity = $transfer->toEntity;
        $fromInventory = $fromEntity->inventory;
        $toInventory = $toEntity->inventory;

        // Validate all cloths are in source inventory and not sold
        foreach ($pendingItems as $item) {
            $cloth = $item->cloth;

            // Check if cloth is sold - sold items cannot be transferred
            if ($cloth->status === 'sold') {
                return response()->json([
                    'message' => 'Sold cloth cannot be transferred',
                    'errors' => ['item' => ['Cloth ' . $cloth->code . ' is sold and cannot be transferred.']]
                ], 422);
            }

            $clothInInventory = $fromInventory->clothes()->where('clothes.id', $cloth->id)->first();

            if (!$clothInInventory) {
                return response()->json([
                    'message' => 'Cloth not found in source inventory',
                    'errors' => ['item' => ['Cloth ' . $cloth->code . ' is not in source entity\'s inventory']]
                ], 422);
            }
        }

        $historyService = new ClothHistoryService();
        $user = $request->user();

        DB::transaction(function () use ($pendingItems, $fromInventory, $toInventory, $fromEntity, $toEntity, $transfer, $historyService, $user) {
            // Process each pending item
            foreach ($pendingItems as $item) {
                $cloth = $item->cloth;

                // Remove from ALL inventories first (ensures one cloth = one inventory = one entity)
                $cloth->inventories()->detach();

                // Add to destination inventory
                $toInventory->clothes()->attach($cloth->id);

                // Update item status to approved
                $item->update(['status' => 'approved']);

                // Create history record
                $historyService->recordTransferred($cloth, $fromEntity, $toEntity, $transfer, $user);
            }

            // Update transfer status based on items
            $transfer->updateStatus();
        });

        // Create audit record
        TransferAction::create([
            'transfer_id' => $transfer->id,
            'user_id' => $user->id,
            'action' => 'approved',
            'action_date' => now(),
        ]);

        return response()->json($this->formatTransferResponse($transfer->fresh()));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transfers/{id}/approve-items",
     *     summary="Approve specific items in a transfer",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"item_ids"},
     *             @OA\Property(property="item_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}, description="Array of transfer item IDs to approve")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Items approved",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", enum={"pending", "partially_pending", "partially_approved", "approved", "rejected"}, example="partially_pending (allowed: pending, partially_pending, partially_approved, approved, rejected)"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="status", type="string", example="approved")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function approveItems(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);

        $data = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'required|integer|exists:transfer_items,id',
        ]);

        // Validate all items belong to this transfer and are pending
        $itemIds = $data['item_ids'];

        // Get all items that exist with these IDs
        $allItems = \App\Models\TransferItem::whereIn('id', $itemIds)->get();
        $foundItemIds = $allItems->pluck('id')->toArray();
        $notFoundIds = array_diff($itemIds, $foundItemIds);

        // Get items that belong to this transfer
        $transferItems = $allItems->where('transfer_id', $transfer->id);
        $transferItemIds = $transferItems->pluck('id')->toArray();
        $wrongTransferIds = array_diff($foundItemIds, $transferItemIds);

        // Get items that are pending
        $pendingItems = $transferItems->where('status', 'pending');
        $pendingItemIds = $pendingItems->pluck('id')->toArray();
        $notPendingIds = array_diff($transferItemIds, $pendingItemIds);

        // Build detailed error messages
        $errors = [];
        if (!empty($notFoundIds)) {
            $errors[] = 'Item IDs not found: ' . implode(', ', $notFoundIds);
        }
        if (!empty($wrongTransferIds)) {
            $errors[] = 'Item IDs do not belong to this transfer: ' . implode(', ', $wrongTransferIds);
        }
        if (!empty($notPendingIds)) {
            $notPendingItems = $transferItems->whereIn('id', $notPendingIds);
            $statusDetails = [];
            foreach ($notPendingItems as $item) {
                $statusDetails[] = "Item ID {$item->id} is {$item->status}";
            }
            $errors[] = 'Item IDs not pending: ' . implode(', ', $notPendingIds) . ' (' . implode('; ', $statusDetails) . ')';
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Validation failed for some items',
                'errors' => ['item_ids' => $errors]
            ], 422);
        }

        $items = $pendingItems;

        $fromEntity = $transfer->fromEntity;
        $toEntity = $transfer->toEntity;
        $fromInventory = $fromEntity->inventory;
        $toInventory = $toEntity->inventory;

        // Validate all cloths are in source inventory and not sold
        foreach ($items as $item) {
            $cloth = $item->cloth;

            // Check if cloth is sold - sold items cannot be transferred
            if ($cloth->status === 'sold') {
                return response()->json([
                    'message' => 'Sold cloth cannot be transferred',
                    'errors' => ['item_ids' => ['Cloth ' . $cloth->code . ' is sold and cannot be transferred.']]
                ], 422);
            }

            $clothInInventory = $fromInventory->clothes()->where('clothes.id', $cloth->id)->first();

            if (!$clothInInventory) {
                return response()->json([
                    'message' => 'Cloth not found in source inventory',
                    'errors' => ['item_ids' => ['Cloth ' . $cloth->code . ' is not in source entity\'s inventory']]
                ], 422);
            }
        }

        $historyService = new ClothHistoryService();
        $user = $request->user();

        DB::transaction(function () use ($items, $fromInventory, $toInventory, $fromEntity, $toEntity, $transfer, $historyService, $user) {
            // Process each approved item
            foreach ($items as $item) {
                $cloth = $item->cloth;

                // Remove from ALL inventories first (ensures one cloth = one inventory = one entity)
                $cloth->inventories()->detach();

                // Add to destination inventory
                $toInventory->clothes()->attach($cloth->id);

                // Update item status to approved
                $item->update(['status' => 'approved']);

                // Create history record
                $historyService->recordTransferred($cloth, $fromEntity, $toEntity, $transfer, $user);
            }

            // Update transfer status based on items
            $transfer->updateStatus();
        });

        // Create audit record
        TransferAction::create([
            'transfer_id' => $transfer->id,
            'user_id' => $user->id,
            'action' => 'approved_items',
            'action_date' => now(),
        ]);

        return response()->json($this->formatTransferResponse($transfer->fresh()));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transfers/{id}/reject-items",
     *     summary="Reject specific items in a transfer",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"item_ids"},
     *             @OA\Property(property="item_ids", type="array", @OA\Items(type="integer"), example={1, 2}, description="Array of transfer item IDs to reject")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Items rejected",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", enum={"pending", "partially_pending", "approved", "rejected"}, example="pending (allowed: pending, partially_pending, approved, rejected)"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="status", type="string", example="rejected")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function rejectItems(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);

        $data = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'required|integer|exists:transfer_items,id',
        ]);

        // Validate all items belong to this transfer and are pending
        $itemIds = $data['item_ids'];

        // Get all items that exist with these IDs
        $allItems = \App\Models\TransferItem::whereIn('id', $itemIds)->get();
        $foundItemIds = $allItems->pluck('id')->toArray();
        $notFoundIds = array_diff($itemIds, $foundItemIds);

        // Get items that belong to this transfer
        $transferItems = $allItems->where('transfer_id', $transfer->id);
        $transferItemIds = $transferItems->pluck('id')->toArray();
        $wrongTransferIds = array_diff($foundItemIds, $transferItemIds);

        // Get items that are pending
        $pendingItems = $transferItems->where('status', 'pending');
        $pendingItemIds = $pendingItems->pluck('id')->toArray();
        $notPendingIds = array_diff($transferItemIds, $pendingItemIds);

        // Build detailed error messages
        $errors = [];
        if (!empty($notFoundIds)) {
            $errors[] = 'Item IDs not found: ' . implode(', ', $notFoundIds);
        }
        if (!empty($wrongTransferIds)) {
            $errors[] = 'Item IDs do not belong to this transfer: ' . implode(', ', $wrongTransferIds);
        }
        if (!empty($notPendingIds)) {
            $notPendingItems = $transferItems->whereIn('id', $notPendingIds);
            $statusDetails = [];
            foreach ($notPendingItems as $item) {
                $statusDetails[] = "Item ID {$item->id} is {$item->status}";
            }
            $errors[] = 'Item IDs not pending: ' . implode(', ', $notPendingIds) . ' (' . implode('; ', $statusDetails) . ')';
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Validation failed for some items',
                'errors' => ['item_ids' => $errors]
            ], 422);
        }

        $items = $pendingItems;

        DB::transaction(function () use ($items, $transfer) {
            // Update each item status to rejected
            foreach ($items as $item) {
                $item->update(['status' => 'rejected']);
            }

            // Update transfer status based on items
            $transfer->updateStatus();
        });

        // Create audit record
        TransferAction::create([
            'transfer_id' => $transfer->id,
            'user_id' => $request->user()->id,
            'action' => 'rejected_items',
            'action_date' => now(),
        ]);

        return response()->json($this->formatTransferResponse($transfer->fresh()));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transfers/{id}/reject",
     *     summary="Reject a transfer",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Transfer rejected",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="from_entity_id", type="integer", example=1),
     *             @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="workshop (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="to_entity_id", type="integer", example=2),
     *             @OA\Property(property="transfer_date", type="string", format="date", example="2025-12-20"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
     *             @OA\Property(property="status", type="string", enum={"pending", "partially_pending", "approved", "rejected"}, example="rejected (allowed: pending, partially_pending, approved, rejected)", description="Possible values: pending, partially_pending, approved, rejected"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="cloth_id", type="integer", example=1),
     *                 @OA\Property(property="cloth_code", type="string", example="CL-101-001"),
     *                 @OA\Property(property="cloth_name", type="string", example="Red Dress Piece 1"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected"}, example="rejected (allowed: pending, approved, rejected)")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=422, description="Transfer cannot be rejected")
     * )
     */
    public function reject(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);

        if ($transfer->status !== 'pending' && $transfer->status !== 'partially_pending' && $transfer->status !== 'partially_approved') {
            return response()->json([
                'message' => 'Only pending, partially pending, or partially approved transfers can be rejected',
                'errors' => ['status' => ['Transfer status is ' . $transfer->status]]
            ], 422);
        }

        // Get all pending items
        $pendingItems = $transfer->items()->where('status', 'pending')->get();

        if ($pendingItems->isEmpty()) {
            return response()->json([
                'message' => 'No pending items to reject',
                'errors' => ['status' => ['All items are already approved or rejected']]
            ], 422);
        }

        DB::transaction(function () use ($pendingItems, $transfer) {
            // Update each pending item status to rejected
            foreach ($pendingItems as $item) {
                $item->update(['status' => 'rejected']);
            }

            // Update transfer status based on items
            $transfer->updateStatus();
        });

        // Create audit record
        TransferAction::create([
            'transfer_id' => $transfer->id,
            'user_id' => $request->user()->id,
            'action' => 'rejected',
            'action_date' => now(),
        ]);

        return response()->json($this->formatTransferResponse($transfer->fresh()));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/transfers/{id}",
     *     summary="Delete a transfer (only if pending)",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Transfer deleted"),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=422, description="Transfer cannot be deleted (not pending)")
     * )
     */
    public function destroy(Request $request, $id)
    {
        $transfer = Transfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending transfers can be deleted',
                'errors' => ['status' => ['Transfer status is ' . $transfer->status]]
            ], 422);
        }

        // Create audit record before deletion
        TransferAction::create([
            'transfer_id' => $transfer->id,
            'user_id' => $request->user()->id,
            'action' => 'deleted',
            'action_date' => now(),
        ]);

        $transfer->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transfers/export",
     *     summary="Export all transfers to CSV",
     *     tags={"Transfers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="from_entity_type", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="to_entity_type", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="action", in="query", required=false, @OA\Schema(type="string")),
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
        $query = Transfer::with(['fromEntity', 'toEntity', 'items.cloth', 'actions.user'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('from_entity_type')) {
            $query->where('from_entity_type', $request->query('from_entity_type'));
        }

        if ($request->has('to_entity_type')) {
            $query->where('to_entity_type', $request->query('to_entity_type'));
        }

        if ($request->has('action')) {
            // Filter transfers that have the specified action in transfer_actions
            $query->whereHas('actions', function($q) use ($request) {
                $q->where('action', $request->query('action'));
            });
        }

        $items = $query->get();
        return $this->exportToCsv($items, \App\Exports\TransferExport::class, 'transfers_' . date('Y-m-d_His') . '.csv');
    }
}
