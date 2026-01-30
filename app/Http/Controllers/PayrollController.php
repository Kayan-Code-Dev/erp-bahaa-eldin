<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Cashbox;
use App\Models\ActivityLog;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Payroll",
 *     description="Payroll management endpoints"
 * )
 */
class PayrollController extends Controller
{
    protected PayrollService $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payrolls",
     *     summary="List all payrolls",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="period", in="query", description="Format: YYYY-MM", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="department_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of payrolls"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = Payroll::with(['employee.user', 'employee.department', 'employee.jobTitle']);

        if ($request->has('employee_id')) {
            $query->forEmployee($request->employee_id);
        }

        if ($request->has('period')) {
            $query->forPeriod($request->period);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        $payrolls = $query->orderBy('period', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($payrolls);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payrolls/generate",
     *     summary="Generate payroll for an employee",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "period"},
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="period", type="string", example="2026-01")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Payroll generated"),
     *     @OA\Response(response=400, description="Payroll already exists"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        try {
            $payroll = $this->payrollService->generatePayroll(
                $employee,
                $validated['period'],
                $request->user()
            );

            ActivityLog::logCreated($payroll, "Generated payroll for {$employee->name}");

            return response()->json([
                'message' => 'Payroll generated successfully.',
                'payroll' => $payroll,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payrolls/generate-bulk",
     *     summary="Generate payrolls for all active employees",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"period"},
     *             @OA\Property(property="period", type="string", example="2026-01"),
     *             @OA\Property(property="department_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Bulk payroll generation results"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function generateBulk(Request $request)
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $results = $this->payrollService->generateBulkPayrolls(
            $validated['period'],
            $request->user(),
            $validated['department_id'] ?? null
        );

        return response()->json([
            'message' => "Bulk payroll generation complete.",
            'results' => $results,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payrolls/{id}",
     *     summary="Get payroll details",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payroll details"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $payroll = Payroll::with([
            'employee.user',
            'employee.department',
            'employee.jobTitle',
            'items',
            'deductions',
            'generatedBy',
            'approvedBy',
            'paidBy',
            'cashbox',
            'transaction',
        ])->findOrFail($id);

        return response()->json($payroll);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/payrolls/{id}",
     *     summary="Update payroll (draft only)",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payroll updated"),
     *     @OA\Response(response=400, description="Cannot update non-draft payroll"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);

        if (!$payroll->can_edit) {
            return response()->json(['message' => 'Only draft payrolls can be updated.'], 400);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $oldValues = $payroll->toArray();
        $payroll->update($validated);

        ActivityLog::logUpdated($payroll, $oldValues);

        return response()->json([
            'message' => 'Payroll updated.',
            'payroll' => $payroll->fresh(['employee.user', 'items']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payrolls/{id}/recalculate",
     *     summary="Recalculate payroll (draft only)",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payroll recalculated"),
     *     @OA\Response(response=400, description="Cannot recalculate non-draft payroll"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function recalculate(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);

        try {
            $payroll = $this->payrollService->recalculatePayroll($payroll, $request->user());

            return response()->json([
                'message' => 'Payroll recalculated.',
                'payroll' => $payroll,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payrolls/{id}/submit",
     *     summary="Submit payroll for approval",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payroll submitted"),
     *     @OA\Response(response=400, description="Cannot submit payroll"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function submit(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);

        try {
            $payroll->submit($request->user()->id);

            ActivityLog::log(
                ActivityLog::ACTION_STATUS_CHANGED,
                $payroll,
                ['status' => Payroll::STATUS_DRAFT],
                ['status' => Payroll::STATUS_PENDING],
                'Payroll submitted for approval'
            );

            return response()->json([
                'message' => 'Payroll submitted for approval.',
                'payroll' => $payroll->fresh(['employee.user']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payrolls/{id}/approve",
     *     summary="Approve payroll",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payroll approved"),
     *     @OA\Response(response=400, description="Cannot approve payroll"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function approve(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);

        try {
            $payroll->approve($request->user()->id);

            ActivityLog::log(
                ActivityLog::ACTION_APPROVED,
                $payroll,
                ['status' => Payroll::STATUS_PENDING],
                ['status' => Payroll::STATUS_APPROVED],
                'Payroll approved'
            );

            return response()->json([
                'message' => 'Payroll approved.',
                'payroll' => $payroll->fresh(['employee.user', 'approvedBy']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payrolls/{id}/reject",
     *     summary="Reject payroll (return to draft)",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payroll rejected"),
     *     @OA\Response(response=400, description="Cannot reject payroll"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $payroll = Payroll::findOrFail($id);

        try {
            $payroll->reject($validated['reason'], $request->user()->id);

            ActivityLog::log(
                ActivityLog::ACTION_REJECTED,
                $payroll,
                ['status' => Payroll::STATUS_PENDING],
                ['status' => Payroll::STATUS_DRAFT, 'rejection_reason' => $validated['reason']],
                'Payroll rejected'
            );

            return response()->json([
                'message' => 'Payroll rejected and returned to draft.',
                'payroll' => $payroll->fresh(['employee.user']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payrolls/{id}/pay",
     *     summary="Process payroll payment",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cashbox_id", "payment_method"},
     *             @OA\Property(property="cashbox_id", type="integer"),
     *             @OA\Property(property="payment_method", type="string", enum={"cash", "bank_transfer", "check"}),
     *             @OA\Property(property="payment_reference", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payroll paid"),
     *     @OA\Response(response=400, description="Cannot process payment"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function pay(Request $request, $id)
    {
        $validated = $request->validate([
            'cashbox_id' => 'required|exists:cashboxes,id',
            'payment_method' => 'required|in:cash,bank_transfer,check',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $payroll = Payroll::findOrFail($id);
        $cashbox = Cashbox::findOrFail($validated['cashbox_id']);

        try {
            $payroll = $this->payrollService->processPayment(
                $payroll,
                $cashbox,
                $request->user(),
                $validated['payment_method'],
                $validated['payment_reference'] ?? null
            );

            ActivityLog::log(
                ActivityLog::ACTION_STATUS_CHANGED,
                $payroll,
                ['status' => Payroll::STATUS_APPROVED],
                ['status' => Payroll::STATUS_PAID],
                'Payroll payment processed'
            );

            return response()->json([
                'message' => 'Payroll payment processed successfully.',
                'payroll' => $payroll,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payrolls/{id}/cancel",
     *     summary="Cancel payroll",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payroll cancelled"),
     *     @OA\Response(response=400, description="Cannot cancel payroll"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function cancel(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);

        try {
            $payroll->cancel($request->user()->id, $request->reason);

            ActivityLog::logDeleted($payroll, 'Payroll cancelled');

            return response()->json([
                'message' => 'Payroll cancelled.',
                'payroll' => $payroll->fresh(['employee.user']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payrolls/summary/{period}",
     *     summary="Get payroll summary for a period",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="path", required=true, description="Format: YYYY-MM", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Period summary"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function summary($period)
    {
        $summary = $this->payrollService->getPeriodSummary($period);
        return response()->json($summary);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payrolls/statuses",
     *     summary="Get all payroll statuses",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of statuses",
     *         @OA\JsonContent(
     *             @OA\Property(property="statuses", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", example="draft"),
     *                 @OA\Property(property="name", type="string", example="Draft")
     *             ))
     *         )
     *     )
     * )
     */
    public function statuses()
    {
        $statuses = [];
        $id = 1;
        foreach (Payroll::STATUSES as $key => $name) {
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
     *     path="/api/v1/payrolls/my",
     *     summary="Get current user's payrolls",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User's payrolls"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee profile not found")
     * )
     */
    public function myPayrolls(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        $query = Payroll::forEmployee($employee->id)->with('items');

        if ($request->has('year')) {
            $query->where('period', 'like', $request->year . '-%');
        }

        $payrolls = $query->orderBy('period', 'desc')
                          ->paginate($request->get('per_page', 12));

        return response()->json($payrolls);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/payrolls/{id}",
     *     summary="Delete payroll (draft only)",
     *     tags={"Payroll"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Payroll deleted"),
     *     @OA\Response(response=400, description="Cannot delete non-draft payroll"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $payroll = Payroll::findOrFail($id);

        if ($payroll->status !== Payroll::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft payrolls can be deleted.'], 400);
        }

        // Unlink deductions
        $payroll->deductions()->update(['payroll_id' => null, 'is_applied' => false]);

        ActivityLog::logDeleted($payroll);

        $payroll->delete();

        return response()->json(['message' => 'Payroll deleted.']);
    }
}





