<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Role;

class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/roles",
     *     summary="List all roles",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
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
        $items = Role::with('users')->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{id}",
     *     summary="Get a role by ID",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Role::with('users')->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/roles",
     *     summary="Create a new role",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Admin"),
     *             @OA\Property(property="description", type="string", example="System Administrator")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Role created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $item = Role::create($data);
        return response()->json($item, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/roles/{id}",
     *     summary="Update a role",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Super Admin"),
     *             @OA\Property(property="description", type="string", example="Updated Description")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Role updated"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = Role::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string',
            'description' => 'nullable|string',
        ]);
        $item->update($data);
        return response()->json($item);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/roles/{id}",
     *     summary="Delete a role",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Role deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Role::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/export",
     *     summary="Export all roles to CSV",
     *     tags={"Roles"},
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
        $items = Role::with('users')->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\RoleExport::class, 'roles_' . date('Y-m-d_His') . '.csv');
    }

    // ==================== ENTITY TYPE RESTRICTIONS ====================

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{id}/entity-types",
     *     summary="Get entity types this role is restricted to",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of entity types",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="role_id", type="integer", example=1),
     *             @OA\Property(property="is_universal", type="boolean", example=false, description="If true, role applies to all entity types"),
     *             @OA\Property(property="entity_types", type="array", @OA\Items(type="string", example="branch"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role not found")
     * )
     */
    public function getEntityTypes($id)
    {
        $role = Role::findOrFail($id);

        return response()->json([
            'role_id' => $role->id,
            'is_universal' => $role->isUniversal(),
            'entity_types' => $role->entityTypes()->pluck('entity_type'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/roles/{id}/entity-types",
     *     summary="Set entity types this role is restricted to",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"entity_types"},
     *             @OA\Property(
     *                 property="entity_types",
     *                 type="array",
     *                 description="Array of entity types. Empty array means role applies to all types.",
     *                 @OA\Items(type="string", enum={"branch", "workshop", "factory"})
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Entity types set"),
     *     @OA\Response(response=404, description="Role not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function setEntityTypes(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'entity_types' => 'required|array',
            'entity_types.*' => 'string|in:branch,workshop,factory',
        ]);

        $role->setEntityTypes($data['entity_types']);

        return response()->json([
            'message' => 'Entity types updated successfully.',
            'role_id' => $role->id,
            'is_universal' => $role->isUniversal(),
            'entity_types' => $role->entityTypes()->pluck('entity_type'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/roles/{id}/entity-types",
     *     summary="Remove all entity type restrictions (make role universal)",
     *     tags={"Roles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Entity type restrictions removed"),
     *     @OA\Response(response=404, description="Role not found")
     * )
     */
    public function clearEntityTypes($id)
    {
        $role = Role::findOrFail($id);

        $role->entityTypes()->delete();

        return response()->json([
            'message' => 'Entity type restrictions removed. Role is now universal.',
            'role_id' => $role->id,
            'is_universal' => true,
        ]);
    }
}
