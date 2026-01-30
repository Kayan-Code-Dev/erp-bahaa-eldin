<?php

namespace App\Http\Controllers;

use App\Models\Deduction;
use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Deductions",
 *     description="Employee deduction management"
 * )
 */
class DeductionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/deductions",
     *     summary="List all deductions",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="period", in="query", description="Format: YYYY-MM", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_applied", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="is_approved", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="List of deductions"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = Deduction::with(['employee.user', 'createdBy', 'approvedBy', 'payroll']);

        if ($request->has('employee_id')) {
            $query->forEmployee($request->employee_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('period')) {
            $query->forPeriod($request->period);
        }

        if ($request->has('is_applied')) {
            $applied = filter_var($request->is_applied, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_applied', $applied);
        }

        if ($request->has('is_approved')) {
            $approved = filter_var($request->is_approved, FILTER_VALIDATE_BOOLEAN);
            if ($approved) {
                $query->approved();
            } else {
                $query->pendingApproval();
            }
        }

        $deductions = $query->orderBy('date', 'desc')
                            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($deductions);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/deductions",
     *     summary="Create deduction",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "type", "reason", "amount", "date"},
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="type", type="string", enum={"absence", "late", "penalty", "loan_repayment", "advance_repayment", "other"}),
     *             @OA\Property(property="reason", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="amount", type="number"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Deduction created"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:' . implode(',', array_keys(Deduction::TYPES)),
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['period'] = date('Y-m', strtotime($validated['date']));
        $validated['created_by'] = $request->user()->id;

        $deduction = Deduction::create($validated);

        ActivityLog::logCreated($deduction);

        return response()->json([
            'message' => 'Deduction created successfully.',
            'deduction' => $deduction->load(['employee.user', 'createdBy']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/deductions/{id}",
     *     summary="Get deduction details",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deduction details"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $deduction = Deduction::with(['employee.user', 'createdBy', 'approvedBy', 'payroll'])->findOrFail($id);
        return response()->json($deduction);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/deductions/{id}",
     *     summary="Update deduction",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="amount", type="number"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Deduction updated"),
     *     @OA\Response(response=400, description="Cannot update applied deduction"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $deduction = Deduction::findOrFail($id);

        if ($deduction->is_applied) {
            return response()->json(['message' => 'Cannot update a deduction that has been applied to a payroll.'], 400);
        }

        $oldValues = $deduction->toArray();

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'nullable|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        $deduction->update(array_filter($validated, fn($v) => $v !== null));

        ActivityLog::logUpdated($deduction, $oldValues);

        return response()->json([
            'message' => 'Deduction updated.',
            'deduction' => $deduction->fresh(['employee.user']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/deductions/{id}/approve",
     *     summary="Approve deduction",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deduction approved"),
     *     @OA\Response(response=400, description="Already approved"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function approve(Request $request, $id)
    {
        $deduction = Deduction::findOrFail($id);

        if ($deduction->is_approved) {
            return response()->json(['message' => 'Deduction is already approved.'], 400);
        }

        $oldValues = $deduction->toArray();

        $deduction->approve($request->user()->id);

        ActivityLog::log(
            ActivityLog::ACTION_APPROVED,
            $deduction,
            $oldValues,
            $deduction->toArray(),
            'Deduction approved'
        );

        return response()->json([
            'message' => 'Deduction approved.',
            'deduction' => $deduction->fresh(['employee.user', 'approvedBy']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/deductions/{id}",
     *     summary="Delete deduction",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deduction deleted"),
     *     @OA\Response(response=400, description="Cannot delete applied deduction"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $deduction = Deduction::findOrFail($id);

        if ($deduction->is_applied) {
            return response()->json(['message' => 'Cannot delete a deduction that has been applied to a payroll.'], 400);
        }

        ActivityLog::logDeleted($deduction);

        $deduction->delete();

        return response()->json(['message' => 'Deduction deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/deductions/types",
     *     summary="Get all deduction types",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of types",
     *         @OA\JsonContent(
     *             @OA\Property(property="types", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", example="absence"),
     *                 @OA\Property(property="name", type="string", example="Absence")
     *             ))
     *         )
     *     )
     * )
     */
    public function types()
    {
        $types = [];
        $id = 1;
        foreach (Deduction::TYPES as $key => $name) {
            $types[] = [
                'id' => $id++,
                'key' => $key,
                'name' => $name,
            ];
        }

        return response()->json(['types' => $types]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/deductions/create-absence",
     *     summary="Create absence deduction automatically",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "date"},
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Absence deduction created"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createAbsence(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'reason' => 'nullable|string|max:255',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        $deduction = Deduction::createAbsenceDeduction(
            $employee,
            $validated['date'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        ActivityLog::logCreated($deduction, 'Absence deduction created');

        return response()->json([
            'message' => 'Absence deduction created.',
            'deduction' => $deduction->load(['employee.user']),
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/deductions/create-late",
     *     summary="Create late deduction automatically",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "date", "late_minutes"},
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="late_minutes", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Late deduction created"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createLate(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'late_minutes' => 'required|integer|min:1',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        $deduction = Deduction::createLateDeduction(
            $employee,
            $validated['date'],
            $validated['late_minutes'],
            $request->user()->id
        );

        ActivityLog::logCreated($deduction, 'Late deduction created');

        return response()->json([
            'message' => 'Late deduction created.',
            'deduction' => $deduction->load(['employee.user']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/deductions/summary/{employee_id}/{period}",
     *     summary="Get deduction summary for employee",
     *     tags={"Deductions"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="employee_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="period", in="path", required=true, description="Format: YYYY-MM", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Deduction summary"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee not found")
     * )
     */
    public function summary($employeeId, $period)
    {
        $employee = Employee::findOrFail($employeeId);

        $deductions = Deduction::forEmployee($employeeId)
                               ->forPeriod($period)
                               ->get();

        $summary = [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_code' => $employee->employee_code,
            ],
            'period' => $period,
            'total_deductions' => $deductions->sum('amount'),
            'by_type' => [
                'absence' => $deductions->where('type', Deduction::TYPE_ABSENCE)->sum('amount'),
                'late' => $deductions->where('type', Deduction::TYPE_LATE)->sum('amount'),
                'penalty' => $deductions->where('type', Deduction::TYPE_PENALTY)->sum('amount'),
                'other' => $deductions->whereNotIn('type', [
                    Deduction::TYPE_ABSENCE,
                    Deduction::TYPE_LATE,
                    Deduction::TYPE_PENALTY,
                ])->sum('amount'),
            ],
            'counts' => [
                'total' => $deductions->count(),
                'pending_approval' => $deductions->whereNull('approved_at')->count(),
                'applied' => $deductions->where('is_applied', true)->count(),
            ],
        ];

        return response()->json($summary);
    }
}





