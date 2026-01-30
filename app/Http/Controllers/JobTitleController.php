<?php

namespace App\Http\Controllers;

use App\Models\JobTitle;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Job Titles",
 *     description="Job title management"
 * )
 */
class JobTitleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/job-titles",
     *     summary="List all job titles",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="department_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="level", in="query", @OA\Schema(type="string", enum={"master_manager", "branches_manager", "branch_manager", "employee"})),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="List of job titles"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = JobTitle::with(['department', 'roles']);

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('level')) {
            $query->byLevel($request->level);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $jobTitles = $query->orderByRaw("FIELD(level, 'master_manager', 'branches_manager', 'branch_manager', 'employee')")
                           ->orderBy('name')
                           ->paginate($request->get('per_page', 50));

        return $this->paginatedResponse($jobTitles);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/job-titles",
     *     summary="Create job title",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name"},
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="department_id", type="integer"),
     *             @OA\Property(property="level", type="string", enum={"master_manager", "branches_manager", "branch_manager", "employee"}, description="Job title level"),
     *             @OA\Property(property="min_salary", type="number"),
     *             @OA\Property(property="max_salary", type="number"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="role_ids", type="array", @OA\Items(type="integer"), description="Role IDs to assign to this job title")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Job title created"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:job_titles,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'department_id' => 'nullable|exists:departments,id',
            'level' => 'nullable|string|in:master_manager,branches_manager,branch_manager,employee',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'is_active' => 'nullable|boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $roleIds = $validated['role_ids'] ?? [];
        unset($validated['role_ids']);

        $jobTitle = JobTitle::create($validated);

        // Attach roles if provided
        if (!empty($roleIds)) {
            $jobTitle->roles()->attach($roleIds);
        }

        ActivityLog::logCreated($jobTitle);

        return response()->json([
            'message' => 'Job title created successfully.',
            'job_title' => $jobTitle->load(['department', 'roles']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/job-titles/{id}",
     *     summary="Get job title details",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Job title details"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $jobTitle = JobTitle::with(['department', 'employees.user', 'roles.permissions'])->findOrFail($id);
        return response()->json($jobTitle);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/job-titles/{id}",
     *     summary="Update job title",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="department_id", type="integer"),
     *             @OA\Property(property="level", type="string", enum={"master_manager", "branches_manager", "branch_manager", "employee"}),
     *             @OA\Property(property="min_salary", type="number"),
     *             @OA\Property(property="max_salary", type="number"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="role_ids", type="array", @OA\Items(type="integer"), description="Role IDs to assign (replaces existing)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Job title updated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $jobTitle = JobTitle::findOrFail($id);
        $oldValues = $jobTitle->toArray();

        $validated = $request->validate([
            'code' => 'nullable|string|max:20|unique:job_titles,code,' . $id,
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'department_id' => 'nullable|exists:departments,id',
            'level' => 'nullable|string|in:master_manager,branches_manager,branch_manager,employee',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $roleIds = $validated['role_ids'] ?? null;
        unset($validated['role_ids']);

        $jobTitle->update(array_filter($validated, fn($v) => $v !== null));

        // Sync roles if provided
        if ($roleIds !== null) {
            $jobTitle->roles()->sync($roleIds);
        }

        ActivityLog::logUpdated($jobTitle, $oldValues);

        return response()->json([
            'message' => 'Job title updated.',
            'job_title' => $jobTitle->fresh(['department', 'roles']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/job-titles/{id}",
     *     summary="Delete job title",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Job title deleted"),
     *     @OA\Response(response=400, description="Cannot delete job title with employees"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $jobTitle = JobTitle::findOrFail($id);

        if ($jobTitle->employees()->exists()) {
            return response()->json(['message' => 'Cannot delete job title with employees. Please reassign employees first.'], 400);
        }

        ActivityLog::logDeleted($jobTitle);

        $jobTitle->delete();

        return response()->json(['message' => 'Job title deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/job-titles/levels",
     *     summary="Get all job title levels",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of levels",
     *         @OA\JsonContent(
     *             @OA\Property(property="levels", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", example="master_manager"),
     *                 @OA\Property(property="name", type="string", example="Master Manager")
     *             ))
     *         )
     *     )
     * )
     */
    public function levels()
    {
        $levels = [];
        foreach (JobTitle::LEVELS as $key => $name) {
            $levels[] = [
                'id' => JobTitle::LEVEL_HIERARCHY[$key],
                'key' => $key,
                'name' => $name,
            ];
        }

        return response()->json([
            'levels' => $levels,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/job-titles/{id}/roles",
     *     summary="Get roles assigned to a job title",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of roles"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function roles($id)
    {
        $jobTitle = JobTitle::findOrFail($id);
        return response()->json([
            'job_title_id' => $jobTitle->id,
            'job_title_name' => $jobTitle->name,
            'roles' => $jobTitle->roles()->with('permissions')->get(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/job-titles/{id}/roles",
     *     summary="Assign roles to a job title",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"role_ids"},
     *             @OA\Property(property="role_ids", type="array", @OA\Items(type="integer"), description="Role IDs to assign")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Roles assigned"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function assignRoles(Request $request, $id)
    {
        $jobTitle = JobTitle::findOrFail($id);

        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $jobTitle->roles()->syncWithoutDetaching($validated['role_ids']);

        ActivityLog::log('roles_assigned', $jobTitle, [
            'role_ids' => $validated['role_ids'],
        ]);

        return response()->json([
            'message' => 'Roles assigned successfully.',
            'job_title' => $jobTitle->load('roles'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/job-titles/{id}/roles",
     *     summary="Remove roles from a job title",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"role_ids"},
     *             @OA\Property(property="role_ids", type="array", @OA\Items(type="integer"), description="Role IDs to remove")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Roles removed"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function removeRoles(Request $request, $id)
    {
        $jobTitle = JobTitle::findOrFail($id);

        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $jobTitle->roles()->detach($validated['role_ids']);

        ActivityLog::log('roles_removed', $jobTitle, [
            'role_ids' => $validated['role_ids'],
        ]);

        return response()->json([
            'message' => 'Roles removed successfully.',
            'job_title' => $jobTitle->load('roles'),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/job-titles/{id}/roles/sync",
     *     summary="Sync roles for a job title (replace all existing roles)",
     *     tags={"Job Titles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"role_ids"},
     *             @OA\Property(property="role_ids", type="array", @OA\Items(type="integer"), description="Role IDs to set (replaces all existing)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Roles synced"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function syncRoles(Request $request, $id)
    {
        $jobTitle = JobTitle::findOrFail($id);

        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $oldRoles = $jobTitle->roles()->pluck('roles.id')->toArray();
        $jobTitle->roles()->sync($validated['role_ids']);

        ActivityLog::log('roles_synced', $jobTitle, [
            'old_role_ids' => $oldRoles,
            'new_role_ids' => $validated['role_ids'],
        ]);

        return response()->json([
            'message' => 'Roles synced successfully.',
            'job_title' => $jobTitle->load('roles'),
        ]);
    }
}





