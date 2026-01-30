<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Order;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\Receivable;
use App\Models\Cloth;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * DashboardService
 * 
 * Aggregates and calculates dashboard metrics from ActivityLog and other sources.
 * Provides comprehensive analytics for activity, business, and HR metrics.
 */
class DashboardService
{
    /**
     * Parse period string or dates into Carbon dates
     */
    protected function parsePeriod($period, $dateFrom = null, $dateTo = null): array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            case 'last_week':
                return [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()];
            case 'last_month':
                return [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()];
            default:
                if ($dateFrom && $dateTo) {
                    return [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()];
                }
                // Default to current month
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }
    }

    // ==================== Activity Analytics Methods ====================

    /**
     * Get activity summary
     */
    public function getActivitySummary($dateFrom = null, $dateTo = null, $branchId = null, $period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period, $dateFrom, $dateTo);

        $query = ActivityLog::whereBetween('created_at', [$start, $end]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $totalActivities = $query->count();

        $byAction = (clone $query)
            ->select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        $byEntityType = (clone $query)
            ->whereNotNull('entity_type')
            ->select('entity_type', DB::raw('count(*) as count'))
            ->groupBy('entity_type')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->mapWithKeys(function ($item) {
                return [class_basename($item->entity_type) => $item->count];
            })
            ->toArray();

        return [
            'total_activities' => $totalActivities,
            'by_action' => $byAction,
            'by_entity_type' => $byEntityType,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get user activity stats
     */
    public function getUserActivityStats($userId, $period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $activities = ActivityLog::where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $byAction = $activities->groupBy('action')->map->count();
        $byEntityType = $activities->whereNotNull('entity_type')
            ->groupBy(function ($log) {
                return class_basename($log->entity_type);
            })
            ->map->count();

        return [
            'total_activities' => $activities->count(),
            'by_action' => $byAction->toArray(),
            'by_entity_type' => $byEntityType->toArray(),
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get entity activity trends
     */
    public function getEntityActivityTrends($entityType, $period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $fullEntityType = str_contains($entityType, '\\') ? $entityType : "App\\Models\\{$entityType}";

        $trends = ActivityLog::where('entity_type', $fullEntityType)
            ->whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('DATE(created_at) as date'),
                'action',
                DB::raw('count(*) as count')
            )
            ->groupBy('date', 'action')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($day) {
                return $day->pluck('count', 'action')->toArray();
            })
            ->toArray();

        return [
            'entity_type' => class_basename($fullEntityType),
            'trends' => $trends,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get most active users
     */
    public function getMostActiveUsers($limit = 10, $period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $users = ActivityLog::whereBetween('created_at', [$start, $end])
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as activity_count'))
            ->groupBy('user_id')
            ->orderByDesc('activity_count')
            ->limit($limit)
            ->with('user:id,name,email')
            ->get()
            ->map(function ($log) {
                return [
                    'user_id' => $log->user_id,
                    'user_name' => $log->user->name ?? 'Unknown',
                    'user_email' => $log->user->email ?? null,
                    'activity_count' => $log->activity_count,
                ];
            })
            ->toArray();

        return [
            'users' => $users,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get most modified entities
     */
    public function getMostModifiedEntities($entityType, $limit = 10, $period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $fullEntityType = str_contains($entityType, '\\') ? $entityType : "App\\Models\\{$entityType}";

        $entities = ActivityLog::where('entity_type', $fullEntityType)
            ->whereBetween('created_at', [$start, $end])
            ->select('entity_id', DB::raw('count(*) as modification_count'))
            ->groupBy('entity_id')
            ->orderByDesc('modification_count')
            ->limit($limit)
            ->get()
            ->map(function ($log) use ($fullEntityType) {
                try {
                    if (class_exists($fullEntityType)) {
                        $entity = $fullEntityType::find($log->entity_id);
                        $entityName = $entity ? ($entity->name ?? $entity->title ?? "#{$log->entity_id}") : "Deleted #{$log->entity_id}";
                    } else {
                        $entityName = "Unknown #{$log->entity_id}";
                    }
                } catch (\Exception $e) {
                    $entityName = "Deleted #{$log->entity_id}";
                }
                
                return [
                    'entity_id' => $log->entity_id,
                    'entity_name' => $entityName,
                    'modification_count' => $log->modification_count,
                ];
            })
            ->toArray();

        return [
            'entity_type' => class_basename($fullEntityType),
            'entities' => $entities,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get activity by hour of day
     */
    public function getActivityByHour($date = null, $branchId = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();

        $query = ActivityLog::whereDate('created_at', $targetDate);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Use database-agnostic date extraction
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $hourExpression = DB::raw("CAST(strftime('%H', created_at) AS INTEGER) as hour");
        } else {
            $hourExpression = DB::raw('HOUR(created_at) as hour');
        }

        $activities = $query
            ->select($hourExpression, DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        // Fill in missing hours with 0
        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            // Check both integer and string keys
            $result[$hour] = $activities[$i] ?? $activities[$hour] ?? 0;
        }

        return [
            'date' => $targetDate->toDateString(),
            'hourly_activity' => $result,
        ];
    }

    /**
     * Get activity by day
     */
    public function getActivityByDay($startDate, $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $activities = ActivityLog::whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        return [
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'by_day' => $activities,
        ];
    }

    // ==================== Business Metrics Methods ====================

    /**
     * Get sales metrics
     */
    public function getSalesMetrics($period = 'month', $branchId = null): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $query = Order::whereBetween('created_at', [$start, $end]);

        if ($branchId) {
            $inventoryIds = Inventory::where('inventoriable_type', Branch::class)
                ->where('inventoriable_id', $branchId)
                ->pluck('id');
            $query->whereIn('inventory_id', $inventoryIds);
        }

        $totalRevenue = $query->sum('total_price');
        $orderCount = $query->count();
        $averageOrderValue = $orderCount > 0 ? round($totalRevenue / $orderCount, 2) : 0;

        $byStatus = (clone $query)
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total_price) as revenue'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => [
                    'count' => $item->count,
                    'revenue' => (float) $item->revenue,
                ]];
            })
            ->toArray();

        return [
            'total_revenue' => (float) $totalRevenue,
            'order_count' => $orderCount,
            'average_order_value' => $averageOrderValue,
            'by_status' => $byStatus,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get client metrics
     */
    public function getClientMetrics($period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $newClients = Client::whereBetween('created_at', [$start, $end])->count();
        $totalClients = Client::count();
        $activeClients = Client::whereHas('orders', function ($q) use ($start, $end) {
            $q->whereBetween('created_at', [$start, $end]);
        })->count();

        $previousStart = $start->copy()->subMonth()->startOfMonth();
        $previousEnd = $start->copy()->subMonth()->endOfMonth();
        $previousNewClients = Client::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $growthRate = $previousNewClients > 0 
            ? round((($newClients - $previousNewClients) / $previousNewClients) * 100, 2)
            : ($newClients > 0 ? 100 : 0);

        return [
            'new_clients' => $newClients,
            'total_clients' => $totalClients,
            'active_clients' => $activeClients,
            'growth_rate' => $growthRate,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get payment metrics
     */
    public function getPaymentMetrics($period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $payments = Payment::whereBetween('payment_date', [$start, $end])
            ->where('status', 'paid');

        $totalPayments = $payments->sum('amount');
        $paymentCount = $payments->count();

        $byMethod = (clone $payments)
            ->select('payment_type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
            ->groupBy('payment_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->payment_type => [
                    'count' => $item->count,
                    'total' => (float) $item->total,
                ]];
            })
            ->toArray();

        return [
            'total_payments' => (float) $totalPayments,
            'payment_count' => $paymentCount,
            'by_method' => $byMethod,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get inventory metrics
     */
    public function getInventoryMetrics($branchId = null): array
    {
        $query = Cloth::query();

        if ($branchId) {
            $query->whereHas('inventories', function ($q) use ($branchId) {
                $q->where('inventoriable_type', 'App\\Models\\Branch')
                  ->where('inventoriable_id', $branchId);
            });
        }

        $available = (clone $query)->where('status', 'ready_for_rent')->count();
        $outOfBranch = (clone $query)->where('status', 'rented')->count();
        $total = $query->count();

        return [
            'total_items' => $total,
            'available' => $available,
            'out_of_branch' => $outOfBranch,
            'utilization_rate' => $total > 0 ? round(($outOfBranch / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get financial metrics
     */
    public function getFinancialMetrics($period = 'month', $branchId = null): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $incomeQuery = Transaction::where('type', Transaction::TYPE_INCOME)
            ->whereBetween('created_at', [$start, $end]);

        $expenseQuery = Transaction::where('type', Transaction::TYPE_EXPENSE)
            ->whereBetween('created_at', [$start, $end]);

        if ($branchId) {
            $cashboxIds = Cashbox::where('branch_id', $branchId)->pluck('id');
            $incomeQuery->whereIn('cashbox_id', $cashboxIds);
            $expenseQuery->whereIn('cashbox_id', $cashboxIds);
        }

        $totalIncome = $incomeQuery->sum('amount');
        $totalExpenses = $expenseQuery->sum('amount');
        $profit = $totalIncome - $totalExpenses;

        $cashboxBalances = Cashbox::when($branchId, function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        })
        ->where('is_active', true)
        ->get()
        ->map(function ($cashbox) {
            return [
                'cashbox_id' => $cashbox->id,
                'name' => $cashbox->name,
                'balance' => (float) $cashbox->current_balance,
            ];
        })
        ->toArray();

        return [
            'total_income' => (float) $totalIncome,
            'total_expenses' => (float) $totalExpenses,
            'profit' => (float) $profit,
            'profit_margin' => $totalIncome > 0 ? round(($profit / $totalIncome) * 100, 2) : 0,
            'cashbox_balances' => $cashboxBalances,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    // ==================== HR Metrics Methods ====================

    /**
     * Get attendance metrics
     */
    public function getAttendanceMetrics($period = 'month', $departmentId = null): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $query = Attendance::whereBetween('date', [$start, $end]);

        if ($departmentId) {
            $query->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        $totalRecords = $query->count();
        $presentDays = (clone $query)->where('status', Attendance::STATUS_PRESENT)->count();
        $absentDays = (clone $query)->where('status', Attendance::STATUS_ABSENT)->count();
        $lateArrivals = (clone $query)->where('is_late', true)->count();
        $leaveDays = (clone $query)->where('status', Attendance::STATUS_LEAVE)->count();

        $attendanceRate = $totalRecords > 0 ? round(($presentDays / $totalRecords) * 100, 2) : 0;

        return [
            'total_records' => $totalRecords,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'late_arrivals' => $lateArrivals,
            'leave_days' => $leaveDays,
            'attendance_rate' => $attendanceRate,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get payroll metrics
     */
    public function getPayrollMetrics($period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $payrolls = Payroll::whereBetween('period_start', [$start, $end]);

        $totalPayroll = $payrolls->sum('net_salary');
        $payrollCount = $payrolls->count();
        $averageSalary = $payrollCount > 0 ? round($totalPayroll / $payrollCount, 2) : 0;

        $byStatus = (clone $payrolls)
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(net_salary) as total'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => [
                    'count' => $item->count,
                    'total' => (float) $item->total,
                ]];
            })
            ->toArray();

        return [
            'total_payroll' => (float) $totalPayroll,
            'payroll_count' => $payrollCount,
            'average_salary' => $averageSalary,
            'by_status' => $byStatus,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get employee activity metrics
     */
    public function getEmployeeActivityMetrics($period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $employeeActivities = ActivityLog::whereBetween('created_at', [$start, $end])
            ->whereHas('user', function ($q) {
                $q->whereHas('employee');
            })
            ->select('user_id', DB::raw('count(*) as activity_count'))
            ->groupBy('user_id')
            ->orderByDesc('activity_count')
            ->limit(10)
            ->with('user.employee')
            ->get()
            ->map(function ($log) {
                return [
                    'user_id' => $log->user_id,
                    'employee_id' => $log->user->employee->id ?? null,
                    'employee_name' => $log->user->employee->user->name ?? $log->user->name ?? 'Unknown',
                    'activity_count' => $log->activity_count,
                ];
            })
            ->toArray();

        return [
            'most_active_employees' => $employeeActivities,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }

    /**
     * Get HR trends
     */
    public function getHRTrends($period = 'month'): array
    {
        [$start, $end] = $this->parsePeriod($period);

        $attendanceTrends = Attendance::whereBetween('date', [$start, $end])
            ->select(
                DB::raw('DATE(date) as date'),
                DB::raw('count(*) as total'),
                DB::raw('sum(case when status = "present" then 1 else 0 end) as present')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total' => $item->total,
                    'present' => $item->present,
                    'attendance_rate' => $item->total > 0 ? round(($item->present / $item->total) * 100, 2) : 0,
                ];
            })
            ->toArray();

        $payrollTrends = Payroll::whereBetween('period_start', [$start, $end])
            ->select(
                DB::raw('DATE(period_start) as date'),
                DB::raw('sum(net_salary) as total_payroll'),
                DB::raw('count(*) as payroll_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'total_payroll' => (float) $item->total_payroll,
                    'payroll_count' => $item->payroll_count,
                ];
            })
            ->toArray();

        return [
            'attendance_trends' => $attendanceTrends,
            'payroll_trends' => $payrollTrends,
            'period' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ];
    }
}

