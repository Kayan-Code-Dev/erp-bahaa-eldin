<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Models\Branch;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Employees",
 *     description="Employee management"
 * )
 */
class EmployeeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/employees",
     *     summary="List all employees",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="department_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="job_title_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="level", in="query", description="Filter by job title level", @OA\Schema(type="string", enum={"master_manager", "branches_manager", "branch_manager", "employee"})),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="employment_type", in="query", @OA\Schema(type="string", enum={"full_time", "part_time", "contract", "intern"})),
     *     @OA\Parameter(name="employment_status", in="query", @OA\Schema(type="string", enum={"active", "on_leave", "suspended", "terminated"})),
     *     @OA\Parameter(name="manager_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="List of employees",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="roles", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Manager"),
     *                         @OA\Property(property="guard_name", type="string", example="web")
     *                     ), description="User roles assigned to the employee")
     *                 ),
     *                 @OA\Property(property="department", type="object", nullable=true),
     *                 @OA\Property(property="jobTitle", type="object", nullable=true),
     *                 @OA\Property(property="branches", type="array", @OA\Items(type="object"))
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="per_page", type="integer", example=15)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = Employee::with(['user.roles', 'department', 'jobTitle', 'manager.user', 'branches']);

        if ($request->has('department_id')) {
            $query->inDepartment($request->department_id);
        }

        if ($request->has('job_title_id')) {
            $query->where('job_title_id', $request->job_title_id);
        }

        // Filter by job title level
        if ($request->has('level')) {
            $query->whereHas('jobTitle', function ($q) use ($request) {
                $q->where('level', $request->level);
            });
        }

        if ($request->has('branch_id')) {
            $query->inBranch($request->branch_id);
        }

        if ($request->has('employment_type')) {
            $query->byType($request->employment_type);
        }

        if ($request->has('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        }

        if ($request->has('manager_id')) {
            $query->where('manager_id', $request->manager_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('employee_code', 'like', "%{$search}%");
        }

        $employees = $query->orderBy('employee_code')
                           ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($employees);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employees",
     *     summary="Create employee with user account",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "hire_date"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="department_id", type="integer"),
     *             @OA\Property(property="job_title_id", type="integer"),
     *             @OA\Property(property="manager_id", type="integer"),
     *             @OA\Property(property="employment_type", type="string"),
     *             @OA\Property(property="hire_date", type="string", format="date"),
     *             @OA\Property(property="base_salary", type="number"),
     *             @OA\Property(property="transport_allowance", type="number"),
     *             @OA\Property(property="housing_allowance", type="number"),
             *             @OA\Property(property="branch_ids", type="array", @OA\Items(type="integer"), description="Deprecated: Use entity_assignments instead"),
             *             @OA\Property(property="primary_branch_id", type="integer", description="Deprecated: Use entity_assignments instead"),
             *             @OA\Property(property="entity_assignments", type="array", @OA\Items(
             *                 type="object",
             *                 @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}, description="Type of entity"),
             *                 @OA\Property(property="entity_id", type="integer", description="ID of the entity"),
             *                 @OA\Property(property="is_primary", type="boolean", description="Whether this is the primary assignment", default=false)
             *             ), description="List of entity assignments for permission system"),
             *             @OA\Property(property="roles", type="array", @OA\Items(type="integer"), description="Role IDs to assign to the user")
             *         )
     *     ),
     *     @OA\Response(response=201, description="Employee created"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // User fields
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',

            // Employee fields
            'department_id' => 'nullable|exists:departments,id',
            'job_title_id' => 'nullable|exists:job_titles,id',
            'manager_id' => 'nullable|exists:employees,id',
            'employment_type' => 'nullable|in:' . implode(',', array_keys(Employee::EMPLOYMENT_TYPES)),
            'hire_date' => 'required|date',
            'probation_end_date' => 'nullable|date|after:hire_date',
            'base_salary' => 'nullable|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'housing_allowance' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:1',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'annual_vacation_days' => 'nullable|integer|min:0',
            'work_start_time' => 'nullable|date_format:H:i:s',
            'work_end_time' => 'nullable|date_format:H:i:s',
            'work_hours_per_day' => 'nullable|integer|min:1|max:24',
            'late_threshold_minutes' => 'nullable|integer|min:0',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_iban' => 'nullable|string|max:50',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relation' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',

            // Branch assignment (deprecated, use entity_assignments)
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
            'primary_branch_id' => 'nullable|exists:branches,id',

            // Entity assignments for permission system
            'entity_assignments' => 'nullable|array',
            'entity_assignments.*.entity_type' => 'required|string|in:branch,workshop,factory',
            'entity_assignments.*.entity_id' => 'required|integer',
            'entity_assignments.*.is_primary' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Assign roles
            if (!empty($validated['roles'])) {
                $user->syncRoles($validated['roles']);
            }

            // Create employee
            $employeeData = array_filter([
                'user_id' => $user->id,
                'employee_code' => Employee::generateEmployeeCode(),
                'department_id' => $validated['department_id'] ?? null,
                'job_title_id' => $validated['job_title_id'] ?? null,
                'manager_id' => $validated['manager_id'] ?? null,
                'employment_type' => $validated['employment_type'] ?? Employee::TYPE_FULL_TIME,
                'hire_date' => $validated['hire_date'],
                'probation_end_date' => $validated['probation_end_date'] ?? null,
                'base_salary' => $validated['base_salary'] ?? 0,
                'transport_allowance' => $validated['transport_allowance'] ?? 0,
                'housing_allowance' => $validated['housing_allowance'] ?? 0,
                'other_allowances' => $validated['other_allowances'] ?? 0,
                'overtime_rate' => $validated['overtime_rate'] ?? 1.5,
                'commission_rate' => $validated['commission_rate'] ?? 0,
                'annual_vacation_days' => $validated['annual_vacation_days'] ?? 21,
                'vacation_days_balance' => $validated['annual_vacation_days'] ?? 21,
                'work_start_time' => $validated['work_start_time'] ?? '09:00:00',
                'work_end_time' => $validated['work_end_time'] ?? '17:00:00',
                'work_hours_per_day' => $validated['work_hours_per_day'] ?? 8,
                'late_threshold_minutes' => $validated['late_threshold_minutes'] ?? 15,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_number' => $validated['bank_account_number'] ?? null,
                'bank_iban' => $validated['bank_iban'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_relation' => $validated['emergency_contact_relation'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], fn($v) => $v !== null);

            $employee = Employee::create($employeeData);

            // Assign branches (legacy support)
            if (!empty($validated['branch_ids'])) {
                $branchData = [];
                $primaryBranchId = $validated['primary_branch_id'] ?? $validated['branch_ids'][0];

                foreach ($validated['branch_ids'] as $branchId) {
                    $branchData[$branchId] = [
                        'is_primary' => $branchId == $primaryBranchId,
                        'assigned_at' => now(),
                    ];
                }

                $employee->branches()->attach($branchData);
            }

            // Assign entities for permission system
            if (!empty($validated['entity_assignments'])) {
                foreach ($validated['entity_assignments'] as $assignment) {
                    // Validate entity exists
                    $entityType = $assignment['entity_type'];
                    $entityId = $assignment['entity_id'];
                    $isPrimary = $assignment['is_primary'] ?? false;

                    // Verify entity exists based on type
                    $entityExists = false;
                    switch ($entityType) {
                        case Employee::ENTITY_TYPE_BRANCH:
                            $entityExists = \App\Models\Branch::where('id', $entityId)->exists();
                            break;
                        case Employee::ENTITY_TYPE_WORKSHOP:
                            $entityExists = \App\Models\Workshop::where('id', $entityId)->exists();
                            break;
                        case Employee::ENTITY_TYPE_FACTORY:
                            $entityExists = \App\Models\Factory::where('id', $entityId)->exists();
                            break;
                    }

                    if ($entityExists) {
                        $employee->assignToEntity($entityType, $entityId, $isPrimary);
                    }
                }
            } elseif (!empty($validated['branch_ids'])) {
                // If entity_assignments not provided but branch_ids are, sync branches to entity system
                $primaryBranchId = $validated['primary_branch_id'] ?? $validated['branch_ids'][0];
                foreach ($validated['branch_ids'] as $branchId) {
                    $isPrimary = $branchId == $primaryBranchId;
                    $employee->assignToEntity(Employee::ENTITY_TYPE_BRANCH, $branchId, $isPrimary);
                }
            }

            ActivityLog::logCreated($employee, "Created employee {$user->name}");

            return response()->json([
                'message' => 'Employee created successfully.',
                'employee' => $employee->load(['user', 'department', 'jobTitle', 'branches', 'entityAssignments.entity']),
            ], 201);
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employees/{id}",
     *     summary="Get employee details",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Employee details"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $employee = Employee::with([
            'user.roles',
            'department',
            'jobTitle',
            'manager.user',
            'subordinates.user',
            'branches',
            'entityAssignments.entity',
            'activeCustodies',
            'documents',
        ])->findOrFail($id);

        return response()->json($employee);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/employees/{id}",
     *     summary="Update employee",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="department_id", type="integer"),
     *             @OA\Property(property="job_title_id", type="integer"),
     *             @OA\Property(property="base_salary", type="number"),
             *             @OA\Property(property="employment_status", type="string"),
             *             @OA\Property(property="entity_assignments", type="array", @OA\Items(
             *                 type="object",
             *                 @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}),
             *                 @OA\Property(property="entity_id", type="integer"),
             *                 @OA\Property(property="is_primary", type="boolean", default=false)
             *             ), description="List of entity assignments (replaces existing)"),
             *             @OA\Property(property="roles", type="array", @OA\Items(type="integer"), description="Role IDs to assign to the user")
             *         )
     *     ),
     *     @OA\Response(response=200, description="Employee updated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $employee = Employee::with('user')->findOrFail($id);
        $oldValues = $employee->toArray();

        $validated = $request->validate([
            // User fields
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $employee->user_id,
            'password' => 'nullable|string|min:8',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',

            // Employee fields
            'department_id' => 'nullable|exists:departments,id',
            'job_title_id' => 'nullable|exists:job_titles,id',
            'manager_id' => 'nullable|exists:employees,id',
            'employment_type' => 'nullable|in:' . implode(',', array_keys(Employee::EMPLOYMENT_TYPES)),
            'employment_status' => 'nullable|in:' . implode(',', array_keys(Employee::EMPLOYMENT_STATUSES)),
            'termination_date' => 'nullable|date',
            'probation_end_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'housing_allowance' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|numeric|min:0',
            'overtime_rate' => 'nullable|numeric|min:1',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'annual_vacation_days' => 'nullable|integer|min:0',
            'vacation_days_balance' => 'nullable|integer|min:0',
            'work_start_time' => 'nullable|date_format:H:i:s',
            'work_end_time' => 'nullable|date_format:H:i:s',
            'work_hours_per_day' => 'nullable|integer|min:1|max:24',
            'late_threshold_minutes' => 'nullable|integer|min:0',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_iban' => 'nullable|string|max:50',
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relation' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',

            // Entity assignments for permission system
            'entity_assignments' => 'nullable|array',
            'entity_assignments.*.entity_type' => 'required|string|in:branch,workshop,factory',
            'entity_assignments.*.entity_id' => 'required|integer',
            'entity_assignments.*.is_primary' => 'nullable|boolean',
        ]);

        // Prevent circular manager reference
        if (isset($validated['manager_id']) && $validated['manager_id'] == $id) {
            return response()->json(['message' => 'Employee cannot be their own manager.'], 422);
        }

        DB::transaction(function () use ($employee, $validated, $request) {
            // Update user
            $userUpdates = array_filter([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
            ], fn($v) => $v !== null);

            if (!empty($validated['password'])) {
                $userUpdates['password'] = Hash::make($validated['password']);
            }

            if (!empty($userUpdates)) {
                $employee->user->update($userUpdates);
            }

            // Update roles
            if (isset($validated['roles'])) {
                $employee->user->syncRoles($validated['roles']);
            }

            // Update employee
            $employeeUpdates = array_filter([
                'department_id' => $validated['department_id'] ?? null,
                'job_title_id' => $validated['job_title_id'] ?? null,
                'manager_id' => $validated['manager_id'] ?? null,
                'employment_type' => $validated['employment_type'] ?? null,
                'employment_status' => $validated['employment_status'] ?? null,
                'termination_date' => $validated['termination_date'] ?? null,
                'probation_end_date' => $validated['probation_end_date'] ?? null,
                'base_salary' => $validated['base_salary'] ?? null,
                'transport_allowance' => $validated['transport_allowance'] ?? null,
                'housing_allowance' => $validated['housing_allowance'] ?? null,
                'other_allowances' => $validated['other_allowances'] ?? null,
                'overtime_rate' => $validated['overtime_rate'] ?? null,
                'commission_rate' => $validated['commission_rate'] ?? null,
                'annual_vacation_days' => $validated['annual_vacation_days'] ?? null,
                'vacation_days_balance' => $validated['vacation_days_balance'] ?? null,
                'work_start_time' => $validated['work_start_time'] ?? null,
                'work_end_time' => $validated['work_end_time'] ?? null,
                'work_hours_per_day' => $validated['work_hours_per_day'] ?? null,
                'late_threshold_minutes' => $validated['late_threshold_minutes'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_number' => $validated['bank_account_number'] ?? null,
                'bank_iban' => $validated['bank_iban'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_relation' => $validated['emergency_contact_relation'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], fn($v) => $v !== null);

            if (!empty($employeeUpdates)) {
                $employee->update($employeeUpdates);
            }

            // Sync entity assignments if provided
            if (isset($validated['entity_assignments'])) {
                // Remove all existing assignments
                $employee->entityAssignments()->update(['unassigned_at' => now()]);

                // Add new assignments
                foreach ($validated['entity_assignments'] as $assignment) {
                    $entityType = $assignment['entity_type'];
                    $entityId = $assignment['entity_id'];
                    $isPrimary = $assignment['is_primary'] ?? false;

                    // Verify entity exists
                    $entityExists = false;
                    switch ($entityType) {
                        case Employee::ENTITY_TYPE_BRANCH:
                            $entityExists = \App\Models\Branch::where('id', $entityId)->exists();
                            break;
                        case Employee::ENTITY_TYPE_WORKSHOP:
                            $entityExists = \App\Models\Workshop::where('id', $entityId)->exists();
                            break;
                        case Employee::ENTITY_TYPE_FACTORY:
                            $entityExists = \App\Models\Factory::where('id', $entityId)->exists();
                            break;
                    }

                    if ($entityExists) {
                        $employee->assignToEntity($entityType, $entityId, $isPrimary);
                    }
                }
            }
        });

        ActivityLog::logUpdated($employee, $oldValues);

        return response()->json([
            'message' => 'Employee updated.',
            'employee' => $employee->fresh(['user.roles', 'department', 'jobTitle', 'branches', 'entityAssignments.entity']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employees/{id}/assign-branches",
     *     summary="Assign branches to employee",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"branch_ids"},
     *             @OA\Property(property="branch_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="primary_branch_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Branches assigned"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function assignBranches(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'exists:branches,id',
            'primary_branch_id' => 'nullable|exists:branches,id',
        ]);

        $primaryBranchId = $validated['primary_branch_id'] ?? $validated['branch_ids'][0];

        $branchData = [];
        foreach ($validated['branch_ids'] as $branchId) {
            $branchData[$branchId] = [
                'is_primary' => $branchId == $primaryBranchId,
                'assigned_at' => now(),
            ];
        }

        $employee->branches()->sync($branchData);

        ActivityLog::log(
            ActivityLog::ACTION_UPDATED,
            $employee,
            null,
            ['branches' => $validated['branch_ids']],
            'Employee branches updated'
        );

        return response()->json([
            'message' => 'Branches assigned.',
            'employee' => $employee->fresh('branches'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employees/{id}/terminate",
     *     summary="Terminate employee",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="termination_date", type="string", format="date"),
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Employee terminated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function terminate(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        $oldValues = $employee->toArray();

        $validated = $request->validate([
            'termination_date' => 'nullable|date',
            'reason' => 'nullable|string|max:500',
        ]);

        $employee->update([
            'employment_status' => Employee::STATUS_TERMINATED,
            'termination_date' => $validated['termination_date'] ?? now(),
            'notes' => $employee->notes . ($validated['reason'] ? "\nTermination reason: " . $validated['reason'] : ''),
        ]);

        ActivityLog::logUpdated($employee, $oldValues, 'Employee terminated');

        return response()->json([
            'message' => 'Employee terminated.',
            'employee' => $employee->fresh('user'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/employees/{id}",
     *     summary="Delete employee",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Employee deleted"),
     *     @OA\Response(response=400, description="Cannot delete employee with active records"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $employee = Employee::with('user')->findOrFail($id);

        // Check for active custodies
        if ($employee->activeCustodies()->exists()) {
            return response()->json(['message' => 'Cannot delete employee with active custody items. Please return items first.'], 400);
        }

        // Check for subordinates
        if ($employee->subordinates()->exists()) {
            return response()->json(['message' => 'Cannot delete employee who manages others. Please reassign their subordinates first.'], 400);
        }

        DB::transaction(function () use ($employee) {
            ActivityLog::logDeleted($employee);

            // Detach branches
            $employee->branches()->detach();

            // Soft delete employee
            $employee->delete();

            // Soft delete user (if desired)
            // $employee->user->delete();
        });

        return response()->json(['message' => 'Employee deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employees/employment-types",
     *     summary="Get all employment types",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of types",
     *         @OA\JsonContent(
     *             @OA\Property(property="types", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", example="full_time"),
     *                 @OA\Property(property="name", type="string", example="Full Time")
     *             ))
     *         )
     *     )
     * )
     */
    public function employmentTypes()
    {
        $types = [];
        $id = 1;
        foreach (Employee::EMPLOYMENT_TYPES as $key => $name) {
            $types[] = [
                'id' => $id++,
                'key' => $key,
                'name' => $name,
            ];
        }

        return response()->json(['types' => $types]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employees/employment-statuses",
     *     summary="Get all employment statuses",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of statuses",
     *         @OA\JsonContent(
     *             @OA\Property(property="statuses", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", example="active"),
     *                 @OA\Property(property="name", type="string", example="Active")
     *             ))
     *         )
     *     )
     * )
     */
    public function employmentStatuses()
    {
        $statuses = [];
        $id = 1;
        foreach (Employee::EMPLOYMENT_STATUSES as $key => $name) {
            $statuses[] = [
                'id' => $id++,
                'key' => $key,
                'name' => $name,
            ];
        }

        return response()->json(['statuses' => $statuses]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employees/me",
     *     summary="Get current user's employee profile",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Employee profile"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee profile not found")
     * )
     */
    public function me(Request $request)
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        return response()->json($employee->load([
            'user.roles',
            'department',
            'jobTitle',
            'manager.user',
            'branches',
        ]));
    }

    // ==================== ENTITY ASSIGNMENT ENDPOINTS ====================

    /**
     * @OA\Get(
     *     path="/api/v1/employees/{id}/entities",
     *     summary="Get all entity assignments for an employee",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of entity assignments",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="assignments", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="entity_type", type="string", example="branch"),
     *                 @OA\Property(property="entity_id", type="integer", example=1),
     *                 @OA\Property(property="entity_name", type="string", example="Main Branch"),
     *                 @OA\Property(property="is_primary", type="boolean", example=true),
     *                 @OA\Property(property="assigned_at", type="string", format="date", example="2025-01-01")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Employee not found")
     * )
     */
    public function getEntities($id)
    {
        $employee = Employee::findOrFail($id);

        $assignments = $employee->entityAssignments()
            ->whereNull('unassigned_at')
            ->get()
            ->map(function ($assignment) {
                $entityName = null;
                if ($assignment->entity_type === Employee::ENTITY_TYPE_BRANCH) {
                    $entity = \App\Models\Branch::find($assignment->entity_id);
                    $entityName = $entity->name ?? null;
                } elseif ($assignment->entity_type === Employee::ENTITY_TYPE_WORKSHOP) {
                    $entity = \App\Models\Workshop::find($assignment->entity_id);
                    $entityName = $entity->name ?? null;
                } elseif ($assignment->entity_type === Employee::ENTITY_TYPE_FACTORY) {
                    $entity = \App\Models\Factory::find($assignment->entity_id);
                    $entityName = $entity->name ?? null;
                }

                return [
                    'id' => $assignment->id,
                    'entity_type' => $assignment->entity_type,
                    'entity_id' => $assignment->entity_id,
                    'entity_name' => $entityName,
                    'is_primary' => $assignment->is_primary,
                    'assigned_at' => $assignment->assigned_at,
                ];
            });

        return response()->json([
            'employee_id' => $employee->id,
            'assignments' => $assignments,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employees/{id}/entities",
     *     summary="Assign employee to an entity",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"entity_type", "entity_id"},
     *             @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch"),
     *             @OA\Property(property="entity_id", type="integer", example=1),
     *             @OA\Property(property="is_primary", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Assignment created"),
     *     @OA\Response(response=404, description="Employee or entity not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function assignEntity(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'entity_type' => 'required|string|in:branch,workshop,factory',
            'entity_id' => 'required|integer',
            'is_primary' => 'nullable|boolean',
        ]);

        // Validate entity exists
        $entityExists = false;
        if ($data['entity_type'] === Employee::ENTITY_TYPE_BRANCH) {
            $entityExists = \App\Models\Branch::where('id', $data['entity_id'])->exists();
        } elseif ($data['entity_type'] === Employee::ENTITY_TYPE_WORKSHOP) {
            $entityExists = \App\Models\Workshop::where('id', $data['entity_id'])->exists();
        } elseif ($data['entity_type'] === Employee::ENTITY_TYPE_FACTORY) {
            $entityExists = \App\Models\Factory::where('id', $data['entity_id'])->exists();
        }

        if (!$entityExists) {
            return response()->json([
                'message' => 'Entity not found',
                'errors' => ['entity_id' => ['The specified entity does not exist.']]
            ], 404);
        }

        $assignment = $employee->assignToEntity(
            $data['entity_type'],
            $data['entity_id'],
            $data['is_primary'] ?? false
        );

        ActivityLog::logUpdated($employee, "Assigned employee to {$data['entity_type']} #{$data['entity_id']}");

        return response()->json([
            'message' => 'Entity assignment created successfully.',
            'assignment' => $assignment,
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/employees/{id}/entities/{entityType}/{entityId}",
     *     summary="Remove employee from an entity",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="entityType", in="path", required=true, @OA\Schema(type="string", enum={"branch", "workshop", "factory"})),
     *     @OA\Parameter(name="entityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Assignment removed"),
     *     @OA\Response(response=404, description="Employee or assignment not found")
     * )
     */
    public function unassignEntity($id, $entityType, $entityId)
    {
        $employee = Employee::findOrFail($id);

        if (!in_array($entityType, [Employee::ENTITY_TYPE_BRANCH, Employee::ENTITY_TYPE_WORKSHOP, Employee::ENTITY_TYPE_FACTORY])) {
            return response()->json([
                'message' => 'Invalid entity type',
                'errors' => ['entity_type' => ['Must be branch, workshop, or factory.']]
            ], 422);
        }

        $removed = $employee->unassignFromEntity($entityType, (int) $entityId);

        if (!$removed) {
            return response()->json([
                'message' => 'Assignment not found',
            ], 404);
        }

        ActivityLog::logUpdated($employee, "Unassigned employee from {$entityType} #{$entityId}");

        return response()->json([
            'message' => 'Entity assignment removed successfully.',
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/employees/{id}/entities/sync",
     *     summary="Sync all entity assignments for an employee",
     *     tags={"Employees"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"assignments"},
     *             @OA\Property(property="assignments", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}),
     *                 @OA\Property(property="entity_id", type="integer"),
     *                 @OA\Property(property="is_primary", type="boolean")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Assignments synced"),
     *     @OA\Response(response=404, description="Employee not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function syncEntities(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.entity_type' => 'required|string|in:branch,workshop,factory',
            'assignments.*.entity_id' => 'required|integer',
            'assignments.*.is_primary' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($employee, $data) {
            // Mark all current assignments as unassigned
            $employee->entityAssignments()->whereNull('unassigned_at')->update(['unassigned_at' => now()]);

            // Create new assignments
            foreach ($data['assignments'] as $assignment) {
                $employee->assignToEntity(
                    $assignment['entity_type'],
                    $assignment['entity_id'],
                    $assignment['is_primary'] ?? false
                );
            }

            ActivityLog::logUpdated($employee, "Synced entity assignments");

            return response()->json([
                'message' => 'Entity assignments synced successfully.',
                'assignments' => $employee->entityAssignments()->whereNull('unassigned_at')->get(),
            ]);
        });
    }
}





