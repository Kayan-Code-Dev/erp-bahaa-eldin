<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Dashboard",
 *     description="Comprehensive dashboard analytics and metrics"
 * )
 */
class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/overview",
     *     summary="Get complete dashboard overview",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", description="Period: today, week, month, year, last_week, last_month", @OA\Schema(type="string", default="month")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="department_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Complete dashboard overview",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="activity", type="object"),
     *             @OA\Property(property="business", type="object"),
     *             @OA\Property(property="hr", type="object")
     *         )
     *     )
     * )
     */
    public function overview(Request $request)
    {
        $period = $request->get('period', 'month');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $branchId = $request->get('branch_id');
        $departmentId = $request->get('department_id');

        return response()->json([
            'activity' => $this->dashboardService->getActivitySummary($dateFrom, $dateTo, $branchId, $period),
            'business' => [
                'sales' => $this->dashboardService->getSalesMetrics($period, $branchId),
                'clients' => $this->dashboardService->getClientMetrics($period),
                'payments' => $this->dashboardService->getPaymentMetrics($period),
                'inventory' => $this->dashboardService->getInventoryMetrics($branchId),
                'financial' => $this->dashboardService->getFinancialMetrics($period, $branchId),
            ],
            'hr' => [
                'attendance' => $this->dashboardService->getAttendanceMetrics($period, $departmentId),
                'payroll' => $this->dashboardService->getPayrollMetrics($period),
                'employee_activity' => $this->dashboardService->getEmployeeActivityMetrics($period),
                'trends' => $this->dashboardService->getHRTrends($period),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/summary",
     *     summary="Get quick dashboard summary (key metrics only)",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Dashboard summary")
     * )
     */
    public function summary(Request $request)
    {
        $period = $request->get('period', 'month');
        $branchId = $request->get('branch_id');

        $activity = $this->dashboardService->getActivitySummary(null, null, $branchId, $period);
        $sales = $this->dashboardService->getSalesMetrics($period, $branchId);
        $financial = $this->dashboardService->getFinancialMetrics($period, $branchId);
        $attendance = $this->dashboardService->getAttendanceMetrics($period);

        return response()->json([
            'key_metrics' => [
                'total_activities' => $activity['total_activities'],
                'total_revenue' => $sales['total_revenue'],
                'total_orders' => $sales['order_count'],
                'total_income' => $financial['total_income'],
                'total_expenses' => $financial['total_expenses'],
                'profit' => $financial['profit'],
                'attendance_rate' => $attendance['attendance_rate'],
            ],
            'period' => $activity['period'],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    // ==================== Activity Analytics Endpoints ====================

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/activity/summary",
     *     summary="Get activity summary",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Activity summary")
     * )
     */
    public function activitySummary(Request $request)
    {
        $result = $this->dashboardService->getActivitySummary(
            $request->get('date_from'),
            $request->get('date_to'),
            $request->get('branch_id'),
            $request->get('period', 'month')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/activity/trends",
     *     summary="Get activity trends over time",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="entity_type", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="Activity trends")
     * )
     */
    public function activityTrends(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|string',
        ]);

        $result = $this->dashboardService->getEntityActivityTrends(
            $request->get('entity_type'),
            $request->get('period', 'month')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/activity/users",
     *     summary="Get user activity stats",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="user_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="User activity stats")
     * )
     */
    public function userActivity(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $result = $this->dashboardService->getUserActivityStats(
            $request->get('user_id'),
            $request->get('period', 'month')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/activity/entities",
     *     summary="Get entity activity breakdown",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Entity activity breakdown")
     * )
     */
    public function entityActivity(Request $request)
    {
        $result = $this->dashboardService->getActivitySummary(
            null,
            null,
            $request->get('branch_id'),
            $request->get('period', 'month')
        );

        return response()->json([
            'by_entity_type' => $result['by_entity_type'],
            'period' => $result['period'],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/activity/top-users",
     *     summary="Get most active users",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="Most active users")
     * )
     */
    public function topUsers(Request $request)
    {
        $result = $this->dashboardService->getMostActiveUsers(
            $request->get('limit', 10),
            $request->get('period', 'month')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/activity/top-entities",
     *     summary="Get most modified entities",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="entity_type", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="Most modified entities")
     * )
     */
    public function topEntities(Request $request)
    {
        $request->validate([
            'entity_type' => 'required|string',
        ]);

        $result = $this->dashboardService->getMostModifiedEntities(
            $request->get('entity_type'),
            $request->get('limit', 10),
            $request->get('period', 'month')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/activity/timeline",
     *     summary="Get activity timeline (hourly/daily)",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="date", in="query", description="For hourly view", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_from", in="query", description="For daily view", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", description="For daily view", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Activity timeline")
     * )
     */
    public function activityTimeline(Request $request)
    {
        if ($request->has('date')) {
            $result = $this->dashboardService->getActivityByHour($request->get('date'));
        } else {
            $request->validate([
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
            ]);

            $result = $this->dashboardService->getActivityByDay(
                $request->get('date_from'),
                $request->get('date_to')
            );
        }

        return response()->json($result);
    }

    // ==================== Business Metrics Endpoints ====================

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/business/sales",
     *     summary="Get sales metrics",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Sales metrics")
     * )
     */
    public function salesMetrics(Request $request)
    {
        $result = $this->dashboardService->getSalesMetrics(
            $request->get('period', 'month'),
            $request->get('branch_id')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/business/clients",
     *     summary="Get client metrics",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="Client metrics")
     * )
     */
    public function clientMetrics(Request $request)
    {
        $result = $this->dashboardService->getClientMetrics(
            $request->get('period', 'month')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/business/payments",
     *     summary="Get payment metrics",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="Payment metrics")
     * )
     */
    public function paymentMetrics(Request $request)
    {
        $result = $this->dashboardService->getPaymentMetrics(
            $request->get('period', 'month')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/business/inventory",
     *     summary="Get inventory metrics",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Inventory metrics")
     * )
     */
    public function inventoryMetrics(Request $request)
    {
        $result = $this->dashboardService->getInventoryMetrics(
            $request->get('branch_id')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/business/financial",
     *     summary="Get financial overview",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Financial overview")
     * )
     */
    public function financialMetrics(Request $request)
    {
        $result = $this->dashboardService->getFinancialMetrics(
            $request->get('period', 'month'),
            $request->get('branch_id')
        );

        return response()->json($result);
    }

    // ==================== HR Metrics Endpoints ====================

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/hr/attendance",
     *     summary="Get attendance metrics",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Parameter(name="department_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Attendance metrics")
     * )
     */
    public function attendanceMetrics(Request $request)
    {
        $result = $this->dashboardService->getAttendanceMetrics(
            $request->get('period', 'month'),
            $request->get('department_id')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/hr/payroll",
     *     summary="Get payroll metrics",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="Payroll metrics")
     * )
     */
    public function payrollMetrics(Request $request)
    {
        $result = $this->dashboardService->getPayrollMetrics(
            $request->get('period', 'month')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/hr/employees",
     *     summary="Get employee activity metrics",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="Employee activity metrics")
     * )
     */
    public function employeeActivityMetrics(Request $request)
    {
        $result = $this->dashboardService->getEmployeeActivityMetrics(
            $request->get('period', 'month')
        );

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard/hr/trends",
     *     summary="Get HR trends",
     *     tags={"Dashboard"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="period", in="query", @OA\Schema(type="string", default="month")),
     *     @OA\Response(response=200, description="HR trends")
     * )
     */
    public function hrTrends(Request $request)
    {
        $result = $this->dashboardService->getHRTrends(
            $request->get('period', 'month')
        );

        return response()->json($result);
    }
}


