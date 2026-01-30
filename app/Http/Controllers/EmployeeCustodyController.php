<?php

namespace App\Http\Controllers;

use App\Models\EmployeeCustody;
use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Employee Custody",
 *     description="Employee equipment custody management"
 * )
 */
class EmployeeCustodyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/employee-custodies",
     *     summary="List all employee custodies",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of custodies"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = EmployeeCustody::with(['employee.user', 'assignedBy', 'returnedTo']);

        if ($request->has('employee_id')) {
            $query->forEmployee($request->employee_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $custodies = $query->orderBy('assigned_date', 'desc')
                           ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($custodies);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employee-custodies",
     *     summary="Assign custody item to employee",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "type", "name", "condition_on_assignment", "assigned_date"},
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="type", type="string", enum={"laptop", "phone", "tablet", "keys", "tools", "uniform", "vehicle", "other"}),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="serial_number", type="string"),
     *             @OA\Property(property="asset_tag", type="string"),
     *             @OA\Property(property="value", type="number"),
     *             @OA\Property(property="condition_on_assignment", type="string", enum={"new", "good", "fair", "poor"}),
     *             @OA\Property(property="assigned_date", type="string", format="date"),
     *             @OA\Property(property="expected_return_date", type="string", format="date"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Custody assigned"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:' . implode(',', array_keys(EmployeeCustody::TYPES)),
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'serial_number' => 'nullable|string|max:100',
            'asset_tag' => 'nullable|string|max:50',
            'value' => 'nullable|numeric|min:0',
            'condition_on_assignment' => 'required|in:new,good,fair,poor',
            'assigned_date' => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:assigned_date',
            'notes' => 'nullable|string|max:500',
        ]);

        $validated['assigned_by'] = $request->user()->id;
        $validated['status'] = EmployeeCustody::STATUS_ASSIGNED;

        $custody = EmployeeCustody::create($validated);

        ActivityLog::logCreated($custody);

        return response()->json([
            'message' => 'Custody item assigned successfully.',
            'custody' => $custody->load(['employee.user', 'assignedBy']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employee-custodies/{id}",
     *     summary="Get custody details",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Custody details"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $custody = EmployeeCustody::with(['employee.user', 'assignedBy', 'returnedTo'])->findOrFail($id);
        return response()->json($custody);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/employee-custodies/{id}",
     *     summary="Update custody item",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="serial_number", type="string"),
     *             @OA\Property(property="value", type="number"),
     *             @OA\Property(property="expected_return_date", type="string", format="date"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Custody updated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $custody = EmployeeCustody::findOrFail($id);
        $oldValues = $custody->toArray();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'serial_number' => 'nullable|string|max:100',
            'asset_tag' => 'nullable|string|max:50',
            'value' => 'nullable|numeric|min:0',
            'expected_return_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $custody->update(array_filter($validated, fn($v) => $v !== null));

        ActivityLog::logUpdated($custody, $oldValues);

        return response()->json([
            'message' => 'Custody updated.',
            'custody' => $custody->fresh(['employee.user']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employee-custodies/{id}/return",
     *     summary="Mark custody item as returned",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"condition_on_return"},
     *             @OA\Property(property="condition_on_return", type="string", enum={"new", "good", "fair", "poor", "damaged"}),
     *             @OA\Property(property="return_notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Custody returned"),
     *     @OA\Response(response=400, description="Item not assigned"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function return(Request $request, $id)
    {
        $custody = EmployeeCustody::findOrFail($id);

        if ($custody->status !== EmployeeCustody::STATUS_ASSIGNED) {
            return response()->json(['message' => 'Item is not currently assigned.'], 400);
        }

        $validated = $request->validate([
            'condition_on_return' => 'required|in:new,good,fair,poor,damaged',
            'return_notes' => 'nullable|string|max:500',
        ]);

        $oldValues = $custody->toArray();

        $custody->markAsReturned(
            $validated['condition_on_return'],
            $request->user()->id,
            $validated['return_notes'] ?? null
        );

        ActivityLog::logUpdated($custody, $oldValues, 'Custody item returned');

        return response()->json([
            'message' => 'Item returned successfully.',
            'custody' => $custody->fresh(['employee.user', 'returnedTo']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employee-custodies/{id}/mark-damaged",
     *     summary="Mark custody item as damaged",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Marked as damaged"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function markDamaged(Request $request, $id)
    {
        $custody = EmployeeCustody::findOrFail($id);
        $oldValues = $custody->toArray();

        $custody->markAsDamaged($request->notes);

        ActivityLog::logUpdated($custody, $oldValues, 'Custody item marked as damaged');

        return response()->json([
            'message' => 'Item marked as damaged.',
            'custody' => $custody->fresh(['employee.user']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employee-custodies/{id}/mark-lost",
     *     summary="Mark custody item as lost",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Marked as lost"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function markLost(Request $request, $id)
    {
        $custody = EmployeeCustody::findOrFail($id);
        $oldValues = $custody->toArray();

        $custody->markAsLost($request->notes);

        ActivityLog::logUpdated($custody, $oldValues, 'Custody item marked as lost');

        return response()->json([
            'message' => 'Item marked as lost.',
            'custody' => $custody->fresh(['employee.user']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/employee-custodies/{id}",
     *     summary="Delete custody record",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Custody deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $custody = EmployeeCustody::findOrFail($id);

        ActivityLog::logDeleted($custody);

        $custody->delete();

        return response()->json(['message' => 'Custody record deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employee-custodies/types",
     *     summary="Get all custody types",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of types",
     *         @OA\JsonContent(
     *             @OA\Property(property="types", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", example="equipment"),
     *                 @OA\Property(property="name", type="string", example="Equipment")
     *             ))
     *         )
     *     )
     * )
     */
    public function types()
    {
        $types = [];
        $id = 1;
        foreach (EmployeeCustody::TYPES as $key => $name) {
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
     *     path="/api/v1/employee-custodies/statuses",
     *     summary="Get all custody statuses",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of statuses",
     *         @OA\JsonContent(
     *             @OA\Property(property="statuses", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", example="assigned"),
     *                 @OA\Property(property="name", type="string", example="Assigned")
     *             ))
     *         )
     *     )
     * )
     */
    public function statuses()
    {
        $statuses = [];
        $id = 1;
        foreach (EmployeeCustody::STATUSES as $key => $name) {
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
     *     path="/api/v1/employee-custodies/overdue",
     *     summary="Get overdue custody items",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="List of overdue items"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function overdue(Request $request)
    {
        $custodies = EmployeeCustody::with(['employee.user'])
                                    ->overdue()
                                    ->orderBy('expected_return_date')
                                    ->paginate($request->get('per_page', 15));

        return response()->json($custodies);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employee-custodies/my",
     *     summary="Get current user's custody items",
     *     tags={"Employee Custody"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="User's custody items"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee profile not found")
     * )
     */
    public function myCustodies(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        $custodies = EmployeeCustody::forEmployee($employee->id)
                                    ->with(['assignedBy', 'returnedTo'])
                                    ->orderBy('assigned_date', 'desc')
                                    ->paginate($request->get('per_page', 15));

        return response()->json($custodies);
    }
}





