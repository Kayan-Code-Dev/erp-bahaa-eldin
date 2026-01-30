<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Inventory;
use App\Models\Rent;
use App\Services\ClothHistoryService;
use App\Http\Controllers\Traits\FiltersByEntityAccess;

class ClothController extends Controller
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
     * Get pieces information for cloths
     * Returns total count, breakdown array, and list of pieces with entity info
     */
    private function getPiecesInfo($cloths, $entityType = null, $entityId = null)
    {
        $pieces = [];
        $breakdownMap = [];

        foreach ($cloths as $cloth) {
            // Get current location (which inventory this piece is in)
            $inventory = $cloth->inventories()->with('inventoriable')->first();

            if (!$inventory) {
                // Piece not in any inventory - still include it but with null entity info
                $pieceData = [
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
                    'entity_type' => null,
                    'entity_id' => null,
                    'entity_name' => null,
                ];
                $pieces[] = $pieceData;
                continue;
            }

            $entity = $inventory->inventoriable;
            if (!$entity) {
                // Inventory exists but no entity - include with null entity info
                $pieceData = [
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
                    'entity_type' => null,
                    'entity_id' => null,
                    'entity_name' => null,
                ];
                $pieces[] = $pieceData;
                continue;
            }

            $pieceEntityType = $this->getEntityTypeFromModel($entity);
            $pieceEntityId = $entity->id;
            $pieceEntityName = $entity->name;

            // If entity filter provided, only include pieces in that entity
            if ($entityType && $entityId) {
                if ($pieceEntityType !== $entityType || $pieceEntityId != $entityId) {
                    continue;
                }
            }

            // Build piece data with flat entity fields
            $pieceData = [
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
                'entity_type' => $pieceEntityType,
                'entity_id' => $pieceEntityId,
                'entity_name' => $pieceEntityName,
            ];

            $pieces[] = $pieceData;

            // Build breakdown map
            $key = "{$pieceEntityType}_{$pieceEntityId}";
            if (!isset($breakdownMap[$key])) {
                $breakdownMap[$key] = [
                    'entity_type' => $pieceEntityType,
                    'entity_id' => $pieceEntityId,
                    'entity_name' => $pieceEntityName,
                    'count' => 0,
                ];
            }
            $breakdownMap[$key]['count']++;
        }

        $breakdown = array_values($breakdownMap);
        $totalCount = count($pieces);

        return [
            'total_count' => $totalCount,
            'breakdown' => $breakdown,
            'pieces' => $pieces,
        ];
    }

    /**
     * Get entity type string from entity model
     */
    private function getEntityTypeFromModel($entity)
    {
        $class = get_class($entity);
        $map = [
            \App\Models\Branch::class => 'branch',
            \App\Models\Workshop::class => 'workshop',
            \App\Models\Factory::class => 'factory',
        ];
        return $map[$class] ?? null;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clothes",
     *     summary="List all clothes",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="cloth_type_id", in="query", required=false, @OA\Schema(type="integer"), description="Filter by cloth type ID"),
     *     @OA\Parameter(name="entity_type", in="query", required=false, description="Entity type", @OA\Schema(type="string", enum={"branch", "workshop", "factory"})),
     *     @OA\Parameter(name="entity_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="subcat_id", in="query", required=false, description="Filter by subcategory ID(s) (through cloth type). Can be a single ID, comma-separated IDs, or array of IDs", @OA\Schema(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string"), @OA\Schema(type="array", @OA\Items(type="integer"))})),
     *     @OA\Parameter(name="category_id", in="query", required=false, description="Filter by category ID(s) (through cloth type subcategories). Can be a single ID, comma-separated IDs, or array of IDs", @OA\Schema(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string"), @OA\Schema(type="array", @OA\Items(type="integer"))})),
     *     @OA\Parameter(name="name", in="query", required=false, @OA\Schema(type="string"), description="Filter by cloth piece name (partial match)"),
     *     @OA\Parameter(name="code", in="query", required=false, @OA\Schema(type="string"), description="Filter by cloth piece code (partial match)"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", description="List of individual pieces", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="CL-101-001"),
     *                 @OA\Property(property="name", type="string", example="Red Dress Piece 1"),
                 *                 @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model"),
                 *                 @OA\Property(property="breast_size", type="string", nullable=true, example="38"),
                 *                 @OA\Property(property="waist_size", type="string", nullable=true, example="32"),
                 *                 @OA\Property(property="sleeve_size", type="string", nullable=true, example="34"),
                 *                 @OA\Property(property="notes", type="string", nullable=true, example="Handle with care"),
                 *                 @OA\Property(property="status", type="string", enum={"damaged", "burned", "scratched", "ready_for_rent", "rented", "repairing", "die", "sold"}, example="ready_for_rent"),
     *                 @OA\Property(property="cloth_type_id", type="integer", example=5),
     *                 @OA\Property(property="cloth_type_name", type="string", example="Red Dress Model"),
     *                 @OA\Property(property="entity_type", type="string", example="branch"),
     *                 @OA\Property(property="entity_id", type="integer", example=1),
     *                 @OA\Property(property="entity_name", type="string", example="Branch 1")
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="total_pages", type="integer", example=7),
     *             @OA\Property(property="total_count", type="integer", example=10, description="Total number of pieces"),
     *             @OA\Property(property="breakdown", type="array", description="Breakdown of pieces by entity", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="entity_type", type="string", example="branch"),
     *                 @OA\Property(property="entity_id", type="integer", example=1),
     *                 @OA\Property(property="entity_name", type="string", example="Branch 1"),
     *                 @OA\Property(property="count", type="integer", example=7)
     *             )),
     *             @OA\Property(property="pieces", type="array", description="List of individual pieces (backward compatibility)", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="CL-101-001"),
     *                 @OA\Property(property="name", type="string", example="Red Dress Piece 1"),
                 *                 @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model"),
                 *                 @OA\Property(property="breast_size", type="string", nullable=true, example="38"),
                 *                 @OA\Property(property="waist_size", type="string", nullable=true, example="32"),
                 *                 @OA\Property(property="sleeve_size", type="string", nullable=true, example="34"),
                 *                 @OA\Property(property="notes", type="string", nullable=true, example="Handle with care"),
                 *                 @OA\Property(property="status", type="string", enum={"damaged", "burned", "scratched", "ready_for_rent", "rented", "repairing", "die", "sold"}, example="ready_for_rent"),
     *                 @OA\Property(property="cloth_type_id", type="integer", example=5),
     *                 @OA\Property(property="cloth_type_name", type="string", example="Red Dress Model"),
     *                 @OA\Property(property="entity_type", type="string", example="branch"),
     *                 @OA\Property(property="entity_id", type="integer", example=1),
     *                 @OA\Property(property="entity_name", type="string", example="Branch 1")
     *             ))
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $query = Cloth::with(['clothType', 'inventories.inventoriable']);

        // Filter by cloth_type_id
        if ($request->has('cloth_type_id')) {
            $query->where('cloth_type_id', $request->input('cloth_type_id'));
        }

        // Filter by entity through inventory relationship
        if ($request->filled('entity_type') && $request->filled('entity_id')) {
            $query->whereHas('inventories', function($q) use ($request) {
                $q->where('inventoriable_type', $request->input('entity_type'))
                  ->where('inventoriable_id', $request->input('entity_id'));
            });
        }
        // Filter by entity type only
        if ($request->filled('entity_type') && !$request->filled('entity_id')) {
            $entityType = $request->input('entity_type');
            $query->whereHas('inventories', function($q) use ($entityType) {
                $q->where('inventoriable_type', $entityType);
            });
        }

        // Filter by cloth type's subcategories (supports multiple values)
        if ($request->has('subcat_id')) {
            $subcatInput = $request->input('subcat_id');
            $subcatIds = is_array($subcatInput)
                ? $subcatInput
                : (is_string($subcatInput) ? explode(',', $subcatInput) : [$subcatInput]);

            // Filter out empty values and convert to integers
            $subcatIds = array_filter(array_map('intval', $subcatIds));

            if (!empty($subcatIds)) {
                $query->whereHas('clothType.subcategories', function($q) use ($subcatIds) {
                    $q->whereIn('subcategories.id', $subcatIds);
                });
            }
        }

        if ($request->has('category_id')) {
            $categoryInput = $request->input('category_id');
            $categoryIds = is_array($categoryInput)
                ? $categoryInput
                : (is_string($categoryInput) ? explode(',', $categoryInput) : [$categoryInput]);

            // Filter out empty values and convert to integers
            $categoryIds = array_filter(array_map('intval', $categoryIds));

            if (!empty($categoryIds)) {
                $query->whereHas('clothType.subcategories.category', function($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                });
            }
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->input('code') . '%');
        }

        // Filter by accessible inventories based on user's entity assignments
        $user = $request->user();
        if ($user && !$user->hasFullAccess()) {
            $accessibleInventoryIds = $user->getAccessibleInventoryIds();
            if ($accessibleInventoryIds !== null) {
                if (empty($accessibleInventoryIds)) {
                    // No accessible inventories - return empty results
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('inventories', function ($q) use ($accessibleInventoryIds) {
                        $q->whereIn('inventories.id', $accessibleInventoryIds);
                    });
                }
            }
        }

        $items = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Get entity filter
        $entityType = $request->has('entity_type') ? $request->input('entity_type') : null;
        $entityId = $request->has('entity_id') ? $request->input('entity_id') : null;

        // Get pieces info
        $piecesInfo = $this->getPiecesInfo($items->getCollection(), $entityType, $entityId);

        // Update paginator items with transformed pieces
        $items->setCollection(collect($piecesInfo['pieces']));

        // Get base paginated response
        $response = $this->paginatedResponse($items)->getData(true);

        // Add additional custom fields
        $response['total_count'] = $piecesInfo['total_count'];
        $response['breakdown'] = $piecesInfo['breakdown'];
        $response['pieces'] = $piecesInfo['pieces']; // Keep for backward compatibility

        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clothes/{id}",
     *     summary="Get a cloth by ID",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="code", type="string", example="CL-101-001"),
     *             @OA\Property(property="name", type="string", example="Red Dress Piece 1"),
     *             @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model"),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="38"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="32"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="34"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Handle with care"),
     *             @OA\Property(property="status", type="string", example="ready_for_rent"),
     *             @OA\Property(property="cloth_type_id", type="integer", example=5),
     *             @OA\Property(property="cloth_type_name", type="string", example="Red Dress Model"),
     *             @OA\Property(property="entity_type", type="string", nullable=true, example="branch (optional)"),
     *             @OA\Property(property="entity_id", type="integer", nullable=true, example="1 (optional)"),
     *             @OA\Property(property="entity_name", type="string", nullable=true, example="Branch 1 (optional)")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Cloth::with(['clothType', 'inventories.inventoriable'])->findOrFail($id);

        // Get current location
        $inventory = $item->inventories()->with('inventoriable')->first();
        $entity = $inventory ? $inventory->inventoriable : null;

        // Build response with flat structure (max depth 2)
        $response = [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'description' => $item->description,
            'breast_size' => $item->breast_size,
            'waist_size' => $item->waist_size,
            'sleeve_size' => $item->sleeve_size,
            'notes' => $item->notes,
            'status' => $item->status,
            'cloth_type_id' => $item->cloth_type_id,
            'cloth_type_name' => $item->clothType->name ?? null,
        ];

        // Add entity info as flat fields (not nested)
        if ($entity) {
            $response['entity_type'] = $this->getEntityTypeFromModel($entity);
            $response['entity_id'] = $entity->id;
            $response['entity_name'] = $entity->name;
        } else {
            $response['entity_type'] = null;
            $response['entity_id'] = null;
            $response['entity_name'] = null;
        }

        return response()->json($response);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/clothes",
     *     summary="Create a new cloth",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name", "cloth_type_id", "entity_type", "entity_id"},
     *             @OA\Property(property="code", type="string", example="CL-101-001"),
     *             @OA\Property(property="name", type="string", example="Red Dress Piece 1"),
     *             @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model"),
     *             @OA\Property(property="cloth_type_id", type="integer", example=5, description="ID of the cloth type/model"),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="38"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="32"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="34"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Handle with care"),
             *             @OA\Property(property="status", type="string", enum={"damaged", "burned", "scratched", "ready_for_rent", "rented", "repairing", "die", "sold"}, nullable=true, example="ready_for_rent"),
             *             @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch", description="Entity type"),
     *             @OA\Property(property="entity_id", type="integer", example=1, description="ID of the entity where this piece will be added")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Cloth piece created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="code", type="string", example="CL-101-001"),
     *             @OA\Property(property="name", type="string", example="Red Dress Piece 1"),
     *             @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model"),
     *             @OA\Property(property="cloth_type_id", type="integer", example=1),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="M"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="M"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="M"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Cloth notes"),
     *             @OA\Property(property="status", type="string", enum={"damaged", "burned", "scratched", "ready_for_rent", "rented", "repairing", "die", "sold"}, example="ready_for_rent"),
     *             @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}, nullable=true, example="branch"),
     *             @OA\Property(property="entity_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="entity_name", type="string", nullable=true, example="Branch 1"),
     *             @OA\Property(property="cloth_type_name", type="string", nullable=true, example="Red Dress Model")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:clothes,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'cloth_type_id' => 'required|integer|exists:cloth_types,id',
            'breast_size' => 'nullable|string',
            'waist_size' => 'nullable|string',
            'sleeve_size' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:damaged,burned,scratched,ready_for_rent,rented,repairing,die,sold',
            'entity_type' => 'required|string|in:branch,workshop,factory',
            'entity_id' => 'required|integer',
        ]);

        // Validate cloth type exists
        $clothType = ClothType::find($data['cloth_type_id']);
        if (!$clothType) {
            return response()->json([
                'message' => 'Cloth type not found',
                'errors' => ['cloth_type_id' => ['Cloth type does not exist']]
            ], 422);
        }

        // Map enum value to model class and validate entity exists
        $entityTypeEnum = $data['entity_type'];
        $modelClass = $this->getModelClassFromEntityType($entityTypeEnum);

        if (!$modelClass) {
            return response()->json([
                'message' => 'Invalid entity type',
                'errors' => ['entity_type' => ['Invalid entity type value']]
            ], 422);
        }

        $entity = $modelClass::find($data['entity_id']);
        if (!$entity) {
            return response()->json([
                'message' => 'Entity not found',
                'errors' => ['entity_id' => ['Entity does not exist']]
            ], 422);
        }

        // Get inventory
        $inventory = $entity->inventory;
        if (!$inventory) {
            return response()->json([
                'message' => 'Entity does not have an inventory',
                'errors' => ['entity_id' => ['Entity inventory not found']]
            ], 422);
        }

        // Remove entity_type/entity_id from data - do NOT store on cloth record
        unset($data['entity_type'], $data['entity_id']);

        // Create cloth piece
        $item = Cloth::create($data);

        // Ensure cloth is not in any other inventory (defensive programming)
        $item->inventories()->detach();

        // Add piece to inventory (no quantity - presence means piece is there)
        $inventory->clothes()->attach($item->id);

        // Create history record
        $historyService = new ClothHistoryService();
        $historyService->recordCreated($item, $entity);

        // Load relationships and build response
        $item->load(['clothType', 'inventories.inventoriable']);

        $response = [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'description' => $item->description,
            'breast_size' => $item->breast_size,
            'waist_size' => $item->waist_size,
            'sleeve_size' => $item->sleeve_size,
            'notes' => $item->notes,
            'status' => $item->status,
            'cloth_type_id' => $item->cloth_type_id,
            'cloth_type_name' => $item->clothType->name ?? null,
            'entity_type' => $entityTypeEnum,
            'entity_id' => $entity->id,
            'entity_name' => $entity->name,
        ];

        return response()->json($response, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/clothes/{id}",
     *     summary="Update a cloth",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", nullable=true, example="CL-101-001"),
     *             @OA\Property(property="name", type="string", nullable=true, example="Red Dress Piece 1"),
     *             @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model"),
     *             @OA\Property(property="cloth_type_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="M"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="M"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="M"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Cloth notes"),
     *             @OA\Property(property="status", type="string", enum={"damaged", "burned", "scratched", "ready_for_rent", "rented", "repairing", "die", "sold"}, nullable=true, example="ready_for_rent"),
     *             @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}, nullable=true, example="branch", description="Optional. Move piece to this entity's inventory"),
     *             @OA\Property(property="entity_id", type="integer", nullable=true, example=1, description="Optional. Move piece to this entity's inventory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cloth piece updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="code", type="string", example="CL-101-001"),
     *             @OA\Property(property="name", type="string", example="Red Dress Piece 1"),
     *             @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model"),
     *             @OA\Property(property="cloth_type_id", type="integer", example=1),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="M"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="M"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="M"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Cloth notes"),
     *             @OA\Property(property="status", type="string", enum={"damaged", "burned", "scratched", "ready_for_rent", "rented", "repairing", "die", "sold"}, example="ready_for_rent"),
     *             @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}, nullable=true, example="branch"),
     *             @OA\Property(property="entity_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="entity_name", type="string", nullable=true, example="Branch 1"),
     *             @OA\Property(property="cloth_type_name", type="string", nullable=true, example="Red Dress Model")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = Cloth::findOrFail($id);

        // Check if cloth is in any unfinished order
        $unfinishedOrders = $item->orders()
            ->whereNotIn('orders.status', ['finished', 'canceled'])
            ->exists();

        if ($unfinishedOrders) {
            return response()->json([
                'message' => 'Cannot update cloth. Cloth is currently in an unfinished order.',
                'errors' => ['order' => ['Cloth is currently in an unfinished order']]
            ], 422);
        }

        $oldStatus = $item->status;

        $data = $request->validate([
            'code' => "sometimes|required|string|unique:clothes,code,{$id}",
            'name' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'cloth_type_id' => 'sometimes|required|integer|exists:cloth_types,id',
            'breast_size' => 'nullable|string',
            'waist_size' => 'nullable|string',
            'sleeve_size' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:damaged,burned,scratched,ready_for_rent,rented,repairing,die,sold',
            'entity_type' => 'sometimes|required|string|in:branch,workshop,factory',
            'entity_id' => 'sometimes|required|integer',
        ]);

        // Validate entity exists if entity_type and entity_id are provided
        $entity = null;
        if (isset($data['entity_type']) && isset($data['entity_id'])) {
            $entityTypeEnum = $data['entity_type'];
            $modelClass = $this->getModelClassFromEntityType($entityTypeEnum);

            if (!$modelClass) {
                return response()->json([
                    'message' => 'Invalid entity type',
                    'errors' => ['entity_type' => ['Invalid entity type value']]
                ], 422);
            }

            $entity = $modelClass::find($data['entity_id']);
            if (!$entity) {
                return response()->json([
                    'message' => 'Entity not found',
                    'errors' => ['entity_id' => ['Entity does not exist']]
                ], 422);
            }

            // Get new inventory
            $newInventory = $entity->inventory;
            if (!$newInventory) {
                return response()->json([
                    'message' => 'Entity does not have an inventory',
                    'errors' => ['entity_id' => ['Entity inventory not found']]
                ], 422);
            }

            // Move piece to new inventory if different
            $currentInventory = $item->inventories()->first();
            if (!$currentInventory || $currentInventory->id !== $newInventory->id) {
                // Remove from ALL inventories first (ensures one cloth = one inventory = one entity)
                $item->inventories()->detach();

                // Add to new inventory
                $newInventory->clothes()->attach($item->id);
            }
        }

        // Remove entity_type/entity_id from data - do NOT store on cloth record
        unset($data['entity_type'], $data['entity_id']);

        // Update cloth fields if provided
        if (!empty($data)) {
            $item->update($data);
        }

        // Record status change if status was updated
        if ($request->has('status') && $oldStatus !== $item->status) {
            $historyService = new ClothHistoryService();
            $historyService->recordStatusChanged($item, $oldStatus, $item->status);
        }

        // Load relationships and build response
        $item->load(['clothType', 'inventories.inventoriable']);
        $inventory = $item->inventories()->with('inventoriable')->first();
        $currentEntity = $inventory ? $inventory->inventoriable : null;

        $response = [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'description' => $item->description,
            'breast_size' => $item->breast_size,
            'waist_size' => $item->waist_size,
            'sleeve_size' => $item->sleeve_size,
            'notes' => $item->notes,
            'status' => $item->status,
            'cloth_type_id' => $item->cloth_type_id,
            'cloth_type_name' => $item->clothType->name ?? null,
        ];

        if ($currentEntity) {
            $response['entity_type'] = $this->getEntityTypeFromModel($currentEntity);
            $response['entity_id'] = $currentEntity->id;
            $response['entity_name'] = $currentEntity->name;
        } else {
            $response['entity_type'] = null;
            $response['entity_id'] = null;
            $response['entity_name'] = null;
        }

        return response()->json($response);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/clothes/{id}",
     *     summary="Delete a cloth",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Cloth deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Cloth::findOrFail($id);

        // Check if cloth is in any unfinished order
        $unfinishedOrders = $item->orders()
            ->whereNotIn('orders.status', ['finished', 'canceled'])
            ->exists();

        if ($unfinishedOrders) {
            return response()->json([
                'message' => 'Cannot delete cloth. Cloth is currently in an unfinished order.',
                'errors' => ['order' => ['Cloth is currently in an unfinished order']]
            ], 422);
        }

        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clothes/{id}/unavailable-days",
     *     summary="Get unavailable rental days for a cloth",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Unavailable days retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="cloth_id", type="integer", example=1),
     *             @OA\Property(property="unavailable_ranges", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="start", type="string", format="date", example="2025-12-20"),
     *                 @OA\Property(property="end", type="string", format="date", example="2025-12-27")
     *             )),
     *             @OA\Property(property="unavailable_dates", type="array", @OA\Items(type="string", format="date")),
     *             @OA\Property(property="available_from", type="string", format="date", nullable=true, example="2025-12-02 (optional)")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Cloth not found")
     * )
     */
    public function unavailableDays($id)
    {
        $cloth = Cloth::findOrFail($id);
        $result = $this->calculateUnavailableDays($cloth);
        return response()->json($result);
    }

    /**
     * Calculate unavailable days for a cloth
     */
    private function calculateUnavailableDays($cloth)
    {
        // Get all rents for this cloth (excluding canceled)
        $rents = Rent::where('cloth_id', $cloth->id)
            ->where('status', '!=', 'canceled')
            ->orderBy('delivery_date')
            ->get();

        $unavailableRanges = [];
        $unavailableDates = [];

        foreach ($rents as $rent) {
            $deliveryDate = \Carbon\Carbon::parse($rent->delivery_date);
            $returnDate = \Carbon\Carbon::parse($rent->return_date);

            // 2 days before delivery_date
            $bufferStart = $deliveryDate->copy()->subDays(2);
            // 2 days after return_date
            $bufferEnd = $returnDate->copy()->addDays(2);

            // Add the entire unavailable range
            $unavailableRanges[] = [
                'start' => $bufferStart->format('Y-m-d'),
                'end' => $bufferEnd->format('Y-m-d'),
                'rent_id' => $rent->id,
                'delivery_date' => $deliveryDate->format('Y-m-d'),
                'return_date' => $returnDate->format('Y-m-d'),
            ];

            // Generate all dates in the range
            $currentDate = $bufferStart->copy();
            while ($currentDate->lte($bufferEnd)) {
                $dateStr = $currentDate->format('Y-m-d');
                if (!in_array($dateStr, $unavailableDates)) {
                    $unavailableDates[] = $dateStr;
                }
                $currentDate->addDay();
            }
        }

        // Sort dates
        sort($unavailableDates);

        // Calculate earliest available date (if any)
        $availableFrom = null;
        if (!empty($unavailableDates)) {
            $lastUnavailable = \Carbon\Carbon::parse(end($unavailableDates));
            $availableFrom = $lastUnavailable->copy()->addDay()->format('Y-m-d');
        }

        return [
            'cloth_id' => $cloth->id,
            'unavailable_ranges' => $unavailableRanges,
            'unavailable_dates' => $unavailableDates,
            'available_from' => $availableFrom,
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clothes/unavailable-days",
     *     summary="Get unavailable rental days for multiple cloths",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="cloth_ids", in="query", required=true, description="Array of cloth IDs", @OA\Schema(type="array", @OA\Items(type="integer"))),
     *     @OA\Response(
     *         response=200,
     *         description="Unavailable days retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="results", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="cloth_id", type="integer", example=1),
     *                 @OA\Property(property="unavailable_ranges", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="start", type="string", format="date", example="2025-12-20"),
     *                     @OA\Property(property="end", type="string", format="date", example="2025-12-27"),
     *                     @OA\Property(property="rent_id", type="integer", example=1),
     *                     @OA\Property(property="delivery_date", type="string", format="date", example="2025-12-22"),
     *                     @OA\Property(property="return_date", type="string", format="date", example="2025-12-25")
     *                 )),
     *                 @OA\Property(property="unavailable_dates", type="array", @OA\Items(type="string", format="date")),
     *                 @OA\Property(property="available_from", type="string", format="date", nullable=true, example="2025-12-28")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function bulkUnavailableDays(Request $request)
    {
        $data = $request->validate([
            'cloth_ids' => 'required|array|min:1',
            'cloth_ids.*' => 'required|integer|exists:clothes,id',
        ]);

        $results = [];

        foreach ($data['cloth_ids'] as $clothId) {
            $cloth = Cloth::findOrFail($clothId);
            $results[] = $this->calculateUnavailableDays($cloth);
        }

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * Check if a cloth is available for rent on a given date
     */
    private function checkClothAvailability($clothId, $deliveryDate, $daysOfRent = 1)
    {
        // Ensure daysOfRent is an integer
        $daysOfRent = (int)$daysOfRent;
        $deliveryDateCarbon = \Carbon\Carbon::parse($deliveryDate);
        $returnDateCarbon = $deliveryDateCarbon->copy()->addDays($daysOfRent);

        // Get all rents for this cloth (excluding canceled)
        $rents = Rent::where('cloth_id', $clothId)
            ->where('status', '!=', 'canceled')
            ->get();

        foreach ($rents as $rent) {
            $existingDelivery = $rent->delivery_date instanceof \Carbon\Carbon
                ? $rent->delivery_date->copy()->startOfDay()
                : \Carbon\Carbon::parse($rent->delivery_date)->startOfDay();
            $existingReturn = $rent->return_date instanceof \Carbon\Carbon
                ? $rent->return_date->copy()->startOfDay()
                : \Carbon\Carbon::parse($rent->return_date)->startOfDay();

            // Unavailable period: (delivery_date - 2 days) to (return_date + 2 days)
            $existingUnavailableStart = $existingDelivery->copy()->subDays(2);
            $existingUnavailableEnd = $existingReturn->copy()->addDays(2);

            $newDelivery = $deliveryDateCarbon->copy()->startOfDay();
            $newReturn = $returnDateCarbon->copy()->startOfDay();

            // Check if new period overlaps with existing unavailable period
            $overlaps = $newDelivery->timestamp <= $existingUnavailableEnd->timestamp &&
                       $newReturn->timestamp >= $existingUnavailableStart->timestamp;

            if ($overlaps) {
                return false;
            }
        }

        // Also check if cloth status is "repairing" or "sold" - they should be unavailable
        $cloth = Cloth::find($clothId);
        if ($cloth && in_array($cloth->status, ['repairing', 'sold'])) {
            return false;
        }

        return true;
    }

    /**
     * Find inventory by entity_type and entity_id
     */
    private function findInventoryByEntity($entityType, $entityId)
    {
        $modelClass = $this->getModelClassFromEntityType($entityType);

        if (!$modelClass) {
            return null;
        }

        // Find the entity
        $entity = $modelClass::find($entityId);

        if (!$entity) {
            return null;
        }

        // Get the entity's inventory
        return $entity->inventory;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clothes/available-for-date",
     *     summary="Get all available clothes for a given delivery date in a specific entity",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="delivery_date", in="query", required=true, description="Delivery date (Y-m-d format)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="days_of_rent", in="query", required=false, description="Days of rent (default: 1)", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="entity_type", in="query", required=true, description="Entity type. Possible values: branch, workshop, factory", @OA\Schema(type="string", enum={"branch", "workshop", "factory"})),
     *     @OA\Parameter(name="entity_id", in="query", required=true, description="Entity ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Available clothes retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="delivery_date", type="string", format="date"),
     *             @OA\Property(property="days_of_rent", type="integer"),
     *             @OA\Property(property="entity_type", type="string", example="branch"),
     *             @OA\Property(property="entity_id", type="integer", example=1),
     *             @OA\Property(property="available_clothes", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="CL-101-001"),
     *                 @OA\Property(property="name", type="string", example="Red Dress Piece 1"),
                 *                 @OA\Property(property="description", type="string", nullable=true, example="Description (optional)"),
     *                 @OA\Property(property="status", type="string", enum={"damaged", "burned", "scratched", "ready_for_rent", "rented", "repairing", "die", "sold"}, example="ready_for_rent"),
     *                 @OA\Property(property="cloth_type", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Red Dress Model")
     *                 )
     *             )),
     *             @OA\Property(property="total_available", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error - entity not found or entity does not have inventory")
     * )
     */
    public function availableForDate(Request $request)
    {
        $data = $request->validate([
            'delivery_date' => 'required|date|after_or_equal:today',
            'days_of_rent' => 'nullable|integer|min:1',
            'entity_type' => 'required|string|in:branch,workshop,factory',
            'entity_id' => 'required|integer',
        ]);

        $deliveryDate = $data['delivery_date'];
        $daysOfRent = isset($data['days_of_rent']) ? (int)$data['days_of_rent'] : 1;

        // Get inventory from entity
        $inventory = $this->findInventoryByEntity($data['entity_type'], $data['entity_id']);

        if (!$inventory) {
            return response()->json([
                'message' => 'Entity does not exist or does not have an inventory',
                'errors' => [
                    'entity_type' => ['Entity does not exist or does not have an inventory'],
                    'entity_id' => ['Entity does not exist or does not have an inventory']
                ]
            ], 422);
        }

        // Get all clothes in this entity's inventory
        $query = Cloth::query();
        $query->whereHas('inventories', function($q) use ($inventory) {
            $q->where('inventories.id', $inventory->id);
        });

        $allClothes = $query->get();
        $availableClothes = [];

        foreach ($allClothes as $cloth) {
            if ($this->checkClothAvailability($cloth->id, $deliveryDate, $daysOfRent)) {
                $availableClothes[] = [
                    'id' => $cloth->id,
                    'code' => $cloth->code,
                    'name' => $cloth->name,
                    'description' => $cloth->description,
                    'status' => $cloth->status,
                    'cloth_type' => $cloth->clothType ? [
                        'id' => $cloth->clothType->id,
                        'name' => $cloth->clothType->name,
                    ] : null,
                ];
            }
        }

        return response()->json([
            'delivery_date' => $deliveryDate,
            'days_of_rent' => $daysOfRent,
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'available_clothes' => $availableClothes,
            'total_available' => count($availableClothes),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clothes/export",
     *     summary="Export all clothes to CSV",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="cloth_type_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="entity_type", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="entity_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="subcat_id", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="name", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="code", in="query", required=false, @OA\Schema(type="string")),
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
        $query = Cloth::with(['clothType', 'inventories.inventoriable']);

        // Filter by cloth_type_id
        if ($request->has('cloth_type_id')) {
            $query->where('cloth_type_id', $request->input('cloth_type_id'));
        }

        // Filter by entity through inventory relationship
        if ($request->filled('entity_type') && $request->filled('entity_id')) {
            $query->whereHas('inventories', function($q) use ($request) {
                $q->where('inventoriable_type', $request->input('entity_type'))
                  ->where('inventoriable_id', $request->input('entity_id'));
            });
        }
        // Filter by entity type only
        if ($request->filled('entity_type') && !$request->filled('entity_id')) {
            $entityType = $request->input('entity_type');
            $query->whereHas('inventories', function($q) use ($entityType) {
                $q->where('inventoriable_type', $entityType);
            });
        }

        // Filter by cloth type's subcategories (supports multiple values)
        if ($request->has('subcat_id')) {
            $subcatInput = $request->input('subcat_id');
            $subcatIds = is_array($subcatInput)
                ? $subcatInput
                : (is_string($subcatInput) ? explode(',', $subcatInput) : [$subcatInput]);

            // Filter out empty values and convert to integers
            $subcatIds = array_filter(array_map('intval', $subcatIds));

            if (!empty($subcatIds)) {
                $query->whereHas('clothType.subcategories', function($q) use ($subcatIds) {
                    $q->whereIn('subcategories.id', $subcatIds);
                });
            }
        }

        if ($request->has('category_id')) {
            $categoryInput = $request->input('category_id');
            $categoryIds = is_array($categoryInput)
                ? $categoryInput
                : (is_string($categoryInput) ? explode(',', $categoryInput) : [$categoryInput]);

            // Filter out empty values and convert to integers
            $categoryIds = array_filter(array_map('intval', $categoryIds));

            if (!empty($categoryIds)) {
                $query->whereHas('clothType.subcategories.category', function($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                });
            }
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->input('code') . '%');
        }

        $items = $query->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\ClothExport::class, 'clothes_' . date('Y-m-d_His') . '.csv');
    }
}
