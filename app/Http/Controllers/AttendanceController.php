<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Attendance",
 *     description="Attendance management endpoints"
 * )
 */
class AttendanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/attendance",
     *     summary="List all attendance records",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_late", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of attendance records"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $query = Attendance::with(['employee.user', 'branch']);

        if ($request->has('employee_id')) {
            $query->forEmployee($request->employee_id);
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_late')) {
            $query->where('is_late', filter_var($request->is_late, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('year') && $request->has('month')) {
            $query->forMonth($request->year, $request->month);
        }

        $attendances = $query->orderBy('date', 'desc')
                             ->orderBy('employee_id')
                             ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($attendances);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/attendance/check-in",
     *     summary="Record check-in for current user",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="branch_id", type="integer"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Check-in recorded"),
     *     @OA\Response(response=400, description="Already checked in"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee profile not found")
     * )
     */
    public function checkIn(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        // Check if already checked in today
        $existingAttendance = Attendance::forEmployee($employee->id)
                                        ->whereDate('date', today())
                                        ->first();

        if ($existingAttendance && $existingAttendance->check_in) {
            return response()->json([
                'message' => 'Already checked in today.',
                'attendance' => $existingAttendance,
            ], 400);
        }

        $branchId = $request->branch_id ?? $employee->primaryBranch()?->id;

        $attendance = Attendance::checkIn($employee, $branchId, $request->ip());

        if ($request->has('notes')) {
            $attendance->update(['notes' => $request->notes]);
        }

        ActivityLog::log(ActivityLog::ACTION_CREATED, $attendance, null, $attendance->toArray(), 'Employee checked in');

        return response()->json([
            'message' => 'Check-in recorded successfully.',
            'attendance' => $attendance->load(['employee.user', 'branch']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/attendance/check-out",
     *     summary="Record check-out for current user",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Check-out recorded"),
     *     @OA\Response(response=400, description="Not checked in"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee profile not found")
     * )
     */
    public function checkOut(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        $attendance = Attendance::checkOut($employee, $request->ip());

        if (!$attendance) {
            return response()->json(['message' => 'No check-in found for today.'], 400);
        }

        if ($request->has('notes')) {
            $currentNotes = $attendance->notes;
            $attendance->update(['notes' => $currentNotes ? $currentNotes . "\n" . $request->notes : $request->notes]);
        }

        ActivityLog::log(ActivityLog::ACTION_UPDATED, $attendance, null, $attendance->toArray(), 'Employee checked out');

        return response()->json([
            'message' => 'Check-out recorded successfully.',
            'attendance' => $attendance->load(['employee.user', 'branch']),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/my",
     *     summary="Get current user's attendance records",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User's attendance records"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee profile not found")
     * )
     */
    public function myAttendance(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        $query = Attendance::forEmployee($employee->id)->with('branch');

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to);
        } elseif ($request->has('year') && $request->has('month')) {
            $query->forMonth($request->year, $request->month);
        } else {
            // Default to current month
            $query->forMonth(now()->year, now()->month);
        }

        $attendances = $query->orderBy('date', 'desc')->paginate($request->get('per_page', 31));

        return response()->json($attendances);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/today",
     *     summary="Get current user's attendance for today",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Today's attendance"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee profile not found or no attendance today")
     * )
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        $attendance = Attendance::forEmployee($employee->id)
                                ->today()
                                ->with('branch')
                                ->first();

        if (!$attendance) {
            return response()->json([
                'message' => 'No attendance record for today.',
                'can_check_in' => true,
            ], 404);
        }

        return response()->json([
            'attendance' => $attendance,
            'can_check_in' => !$attendance->is_checked_in,
            'can_check_out' => $attendance->is_checked_in && !$attendance->is_checked_out,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/summary/{employee_id}/{period}",
     *     summary="Get attendance summary for an employee",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="employee_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="period", in="path", required=true, description="Format: YYYY-MM", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Attendance summary"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee not found")
     * )
     */
    public function summary($employeeId, $period)
    {
        $employee = Employee::findOrFail($employeeId);

        [$year, $month] = explode('-', $period);
        $summary = Attendance::getMonthlySummary($employeeId, (int)$year, (int)$month);

        // Add employee info
        $summary['employee'] = [
            'id' => $employee->id,
            'name' => $employee->name,
            'employee_code' => $employee->employee_code,
        ];
        $summary['period'] = $period;

        return response()->json($summary);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/attendance",
     *     summary="Create/update attendance record (admin)",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "date"},
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="branch_id", type="integer"),
     *             @OA\Property(property="check_in", type="string", example="09:00:00"),
     *             @OA\Property(property="check_out", type="string", example="17:00:00"),
     *             @OA\Property(property="status", type="string", enum={"present", "absent", "half_day", "holiday", "weekend", "leave"}),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Attendance record saved"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'branch_id' => 'nullable|exists:branches,id',
            'check_in' => 'nullable|date_format:H:i:s',
            'check_out' => 'nullable|date_format:H:i:s',
            'status' => 'nullable|in:' . implode(',', array_keys(Attendance::STATUSES)),
            'notes' => 'nullable|string|max:500',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        $attendance = Attendance::updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'date' => $validated['date'],
            ],
            array_merge($validated, [
                'branch_id' => $validated['branch_id'] ?? $employee->primaryBranch()?->id,
            ])
        );

        // Recalculate hours and overtime if check-in/out provided
        if ($attendance->check_in && $attendance->check_out) {
            $attendance->hours_worked = $attendance->calculateHoursWorked();
            $attendance->overtime_hours = $attendance->calculateOvertime();
            $attendance->late_minutes = $attendance->calculateLateMinutes();
            $attendance->is_late = $attendance->late_minutes > 0;
            $attendance->save();
        }

        ActivityLog::logCreated($attendance);

        return response()->json([
            'message' => 'Attendance record saved.',
            'attendance' => $attendance->load(['employee.user', 'branch']),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/{id}",
     *     summary="Get specific attendance record",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Attendance record details"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $attendance = Attendance::with(['employee.user', 'branch'])->findOrFail($id);
        return response()->json($attendance);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/attendance/{id}",
     *     summary="Update attendance record",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="check_in", type="string", example="09:00:00"),
     *             @OA\Property(property="check_out", type="string", example="17:00:00"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Attendance updated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $oldValues = $attendance->toArray();

        $validated = $request->validate([
            'check_in' => 'nullable|date_format:H:i:s',
            'check_out' => 'nullable|date_format:H:i:s',
            'status' => 'nullable|in:' . implode(',', array_keys(Attendance::STATUSES)),
            'notes' => 'nullable|string|max:500',
        ]);

        $attendance->update($validated);

        // Recalculate hours and overtime
        if ($attendance->check_in && $attendance->check_out) {
            $attendance->hours_worked = $attendance->calculateHoursWorked();
            $attendance->overtime_hours = $attendance->calculateOvertime();
            $attendance->late_minutes = $attendance->calculateLateMinutes();
            $attendance->is_late = $attendance->late_minutes > 0;
            $attendance->save();
        }

        ActivityLog::logUpdated($attendance, $oldValues);

        return response()->json([
            'message' => 'Attendance updated.',
            'attendance' => $attendance->load(['employee.user', 'branch']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/attendance/{id}",
     *     summary="Delete attendance record",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Attendance deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $attendance = Attendance::findOrFail($id);

        ActivityLog::logDeleted($attendance);

        $attendance->delete();

        return response()->json(['message' => 'Attendance record deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/statuses",
     *     summary="Get all attendance statuses",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of statuses",
     *         @OA\JsonContent(
     *             @OA\Property(property="statuses", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", example="present"),
     *                 @OA\Property(property="name", type="string", example="Present")
     *             ))
     *         )
     *     )
     * )
     */
    public function statuses()
    {
        $statuses = [];
        $id = 1;
        foreach (Attendance::STATUSES as $key => $name) {
            $statuses[] = [
                'id' => $id++,
                'key' => $key,
                'name' => $name,
            ];
        }

        return response()->json(['statuses' => $statuses]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/attendance/bulk",
     *     summary="Bulk create attendance records (for marking absences, holidays, etc.)",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_ids", "date", "status"},
     *             @OA\Property(property="employee_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="date", type="string", format="date"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Bulk records created"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'date' => 'required|date',
            'status' => 'required|in:' . implode(',', array_keys(Attendance::STATUSES)),
            'notes' => 'nullable|string|max:500',
        ]);

        $created = 0;
        $skipped = 0;

        foreach ($validated['employee_ids'] as $employeeId) {
            $existing = Attendance::where('employee_id', $employeeId)
                                  ->whereDate('date', $validated['date'])
                                  ->first();

            if ($existing) {
                $skipped++;
                continue;
            }

            Attendance::create([
                'employee_id' => $employeeId,
                'date' => $validated['date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);
            $created++;
        }

        return response()->json([
            'message' => "Bulk attendance created: {$created} records. Skipped: {$skipped} (already exist).",
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/attendance/report",
     *     summary="Get attendance report for date range",
     *     tags={"Attendance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="date_from", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="department_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Attendance report"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function report(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'department_id' => 'nullable|exists:departments,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $query = Employee::with(['user', 'department', 'jobTitle'])
                         ->active();

        if ($request->has('department_id')) {
            $query->inDepartment($request->department_id);
        }

        if ($request->has('branch_id')) {
            $query->inBranch($request->branch_id);
        }

        $employees = $query->get();

        $report = [];
        foreach ($employees as $employee) {
            $attendances = Attendance::forEmployee($employee->id)
                                     ->forDateRange($validated['date_from'], $validated['date_to'])
                                     ->get();

            $report[] = [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_code' => $employee->employee_code,
                    'department' => $employee->department?->name,
                    'job_title' => $employee->jobTitle?->name,
                ],
                'summary' => [
                    'total_days' => $attendances->count(),
                    'present_days' => $attendances->where('status', Attendance::STATUS_PRESENT)->count(),
                    'absent_days' => $attendances->where('status', Attendance::STATUS_ABSENT)->count(),
                    'half_days' => $attendances->where('status', Attendance::STATUS_HALF_DAY)->count(),
                    'leave_days' => $attendances->where('status', Attendance::STATUS_LEAVE)->count(),
                    'late_days' => $attendances->where('is_late', true)->count(),
                    'total_hours' => round($attendances->sum('hours_worked'), 2),
                    'overtime_hours' => round($attendances->sum('overtime_hours'), 2),
                ],
            ];
        }

        return response()->json([
            'period' => [
                'from' => $validated['date_from'],
                'to' => $validated['date_to'],
            ],
            'report' => $report,
        ]);
    }
}





