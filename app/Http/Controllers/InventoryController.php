<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;

class InventoryController extends Controller
{
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
     * @OA\Get(
     *     path="/api/v1/inventories",
     *     summary="List all inventories",
     *     tags={"Inventories"},
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
     *                 @OA\Property(property="name", type="string", example="Main Warehouse"),
     *                 @OA\Property(property="inventoriable_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *                 @OA\Property(property="inventoriable_id", type="integer", example=1),
     *                 @OA\Property(property="inventoriable", type="object", nullable=true, description="Polymorphic relationship - can be Branch, Workshop, or Factory, each with their own address.city.country structure")
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
        $items = Inventory::with(['inventoriable'])->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/inventories/{id}",
     *     summary="Get an inventory by ID",
     *     tags={"Inventories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Main Warehouse"),
     *             @OA\Property(property="inventoriable_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="inventoriable_id", type="integer", example=1),
     *             @OA\Property(property="inventoriable", type="object", nullable=true, description="Polymorphic relationship - can be Branch, Workshop, or Factory, each with their own address.city.country structure")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Inventory::with(['inventoriable'])->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/inventories",
     *     summary="Create a new inventory (Note: Inventories are usually created automatically with branches/workshops/factories)",
     *     tags={"Inventories"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "inventoriable_type", "inventoriable_id"},
     *             @OA\Property(property="name", type="string", example="Main Warehouse"),
     *             @OA\Property(property="inventoriable_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="inventoriable_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Inventory created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Main Warehouse"),
     *             @OA\Property(property="inventoriable_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="inventoriable_id", type="integer", example=1),
     *             @OA\Property(property="inventoriable", type="object", nullable=true, description="Polymorphic relationship - can be Branch, Workshop, or Factory, each with their own address.city.country structure")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'inventoriable_type' => 'required|string|in:branch,workshop,factory',
            'inventoriable_id' => 'required|integer',
        ]);

        // Map enum value to model class
        $entityTypeEnum = $data['inventoriable_type'];
        $modelClass = $this->getModelClassFromEntityType($entityTypeEnum);

        if (!$modelClass) {
            return response()->json([
                'message' => 'Invalid entity type',
                'errors' => ['inventoriable_type' => ['Invalid entity type value']]
            ], 422);
        }

        // Validate entity exists
        $entity = $modelClass::find($data['inventoriable_id']);

        // Store the full class name in data
        $data['inventoriable_type'] = $modelClass;
        if (!$entity) {
            return response()->json([
                'message' => 'Entity not found',
                'errors' => ['inventoriable_id' => ['Entity does not exist']]
            ], 422);
        }

        // Check if inventory already exists
        if ($entity->inventory) {
            return response()->json([
                'message' => 'Entity already has an inventory',
                'errors' => ['inventoriable_id' => ['This entity already has an inventory']]
            ], 422);
        }

        $item = Inventory::create($data);
        return response()->json($item->load('inventoriable'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/inventories/{id}",
     *     summary="Update an inventory",
     *     tags={"Inventories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Warehouse")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inventory updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated Warehouse"),
     *             @OA\Property(property="inventoriable_type", type="string", enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)", description="Possible values: branch, workshop, factory"),
     *             @OA\Property(property="inventoriable_id", type="integer", example=1),
     *             @OA\Property(property="inventoriable", type="object", nullable=true, description="Polymorphic relationship - can be Branch, Workshop, or Factory, each with their own address.city.country structure")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = Inventory::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string',
        ]);
        $item->update($data);
        return response()->json($item->load('inventoriable'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/inventories/{id}",
     *     summary="Delete an inventory",
     *     tags={"Inventories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Inventory deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Inventory::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/inventories/{id}/clothes",
     *     summary="Get all clothes in an inventory",
     *     tags={"Inventories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="CL-101"),
     *                 @OA\Property(property="name", type="string", example="Red Dress"),
     *                 @OA\Property(property="description", type="string", nullable=true),
     *                 @OA\Property(property="breast_size", type="string", nullable=true),
     *                 @OA\Property(property="waist_size", type="string", nullable=true),
     *                 @OA\Property(property="sleeve_size", type="string", nullable=true),
                 *                 @OA\Property(property="status", type="string", nullable=true, enum={"damaged", "burned", "scratched", "ready_for_rent", "rented", "repairing", "die"}, example="ready_for_rent (allowed: damaged, burned, scratched, ready_for_rent, rented, repairing, die, sold)"),
                 *                 @OA\Property(property="notes", type="string", nullable=true),
                 *                 @OA\Property(property="entity_type", type="string", nullable=true, enum={"branch", "workshop", "factory"}, example="branch (allowed: branch, workshop, factory)"),
     *                 @OA\Property(property="entity_id", type="integer", nullable=true, example=1),
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="total_pages", type="integer", example=7),
     *             @OA\Property(property="per_page", type="integer", example=15)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function getClothes(Request $request, $id)
    {
        $inventory = Inventory::findOrFail($id);
        $perPage = (int) $request->query('per_page', 15);

        $clothes = $inventory->clothes()->paginate($perPage);

        // Remove pivot data
        $clothes->getCollection()->transform(function ($cloth) {
            unset($cloth->pivot);
            return $cloth;
        });

        return $this->paginatedResponse($clothes);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/inventories/export",
     *     summary="Export all inventories to CSV",
     *     tags={"Inventories"},
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
        $items = Inventory::with(['inventoriable', 'clothes', 'orders'])->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\InventoryExport::class, 'inventories_' . date('Y-m-d_His') . '.csv');
    }
}
