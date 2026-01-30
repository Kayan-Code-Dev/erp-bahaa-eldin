<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Departments",
 *     description="Department management"
 * )
 */
class DepartmentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/departments",
     *     summary="List all departments",
     *     tags={"Departments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="parent_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="with_children", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="List of departments"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = Department::with(['parent', 'manager']);

        if ($request->has('parent_id')) {
            if ($request->parent_id === 'null' || $request->parent_id === '') {
                $query->root();
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('with_children')) {
            $query->with('children');
        }

        $departments = $query->orderBy('name')
                             ->paginate($request->get('per_page', 50));

        return $this->paginatedResponse($departments);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/departments",
     *     summary="Create department",
     *     tags={"Departments"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name"},
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="parent_id", type="integer"),
     *             @OA\Property(property="manager_id", type="integer"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Department created"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        // Convert empty string/0 to null for optional foreign keys
        $request->merge([
            'parent_id' => $request->parent_id ?: null,
            'manager_id' => $request->manager_id ?: null,
        ]);

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:departments,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'manager_id' => 'nullable|integer|exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        $department = Department::create($validated);

        ActivityLog::logCreated($department);

        return response()->json([
            'message' => 'Department created successfully.',
            'department' => $department->load(['parent', 'manager']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/departments/{id}",
     *     summary="Get department details",
     *     tags={"Departments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Department details"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $department = Department::with(['parent', 'manager', 'children', 'jobTitles', 'employees.user'])->findOrFail($id);

        // Add computed attributes
        $department->total_employees = $department->total_employees;
        $department->hierarchy_path = $department->hierarchy_path;

        return response()->json($department);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/departments/{id}",
     *     summary="Update department",
     *     tags={"Departments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="parent_id", type="integer"),
     *             @OA\Property(property="manager_id", type="integer"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Department updated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);
        $oldValues = $department->toArray();

        // Convert empty string/0 to null for optional foreign keys
        if ($request->has('parent_id')) {
            $request->merge(['parent_id' => $request->parent_id ?: null]);
        }
        if ($request->has('manager_id')) {
            $request->merge(['manager_id' => $request->manager_id ?: null]);
        }

        $validated = $request->validate([
            'code' => 'nullable|string|max:20|unique:departments,code,' . $id,
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'manager_id' => 'nullable|integer|exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        // Prevent circular reference
        if (isset($validated['parent_id']) && $validated['parent_id'] == $id) {
            return response()->json(['message' => 'Department cannot be its own parent.'], 422);
        }

        // Only update fields that were sent in the request
        $dataToUpdate = array_intersect_key($validated, $request->all());
        $department->update($dataToUpdate);

        ActivityLog::logUpdated($department, $oldValues);

        return response()->json([
            'message' => 'Department updated.',
            'department' => $department->fresh(['parent', 'manager']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/departments/{id}",
     *     summary="Delete department",
     *     tags={"Departments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Department deleted"),
     *     @OA\Response(response=400, description="Cannot delete department with employees"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $department = Department::findOrFail($id);

        // Check if department has employees
        if ($department->employees()->exists()) {
            return response()->json(['message' => 'Cannot delete department with employees. Please reassign employees first.'], 400);
        }

        // Check if department has children
        if ($department->children()->exists()) {
            return response()->json(['message' => 'Cannot delete department with sub-departments. Please delete or reassign them first.'], 400);
        }

        ActivityLog::logDeleted($department);

        $department->delete();

        return response()->json(['message' => 'Department deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/departments/tree",
     *     summary="Get department hierarchy tree",
     *     tags={"Departments"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Department tree"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function tree()
    {
        $departments = Department::root()
                                 ->with('descendants')
                                 ->active()
                                 ->orderBy('name')
                                 ->get();

        return response()->json($departments);
    }
}





