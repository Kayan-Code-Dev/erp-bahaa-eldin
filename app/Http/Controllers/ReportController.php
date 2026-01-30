<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cloth;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\Receivable;
use App\Models\Factory;
use App\Models\FactoryEvaluation;
use App\Models\User;
use App\Models\Cashbox;
use App\Models\Transaction;
use App\Models\Rent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/reports/available-dresses",
     *     summary="Get currently available dresses/clothes",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="cloth_type_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Available dresses report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_available", type="integer"),
     *             @OA\Property(property="by_status", type="object"),
     *             @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function availableDresses(Request $request)
    {
        $query = Cloth::with(['clothType', 'inventories.inventoriable'])
            ->where('status', 'ready_for_rent');

        if ($request->filled('branch_id')) {
            $query->whereHas('inventories', function ($q) use ($request) {
                $q->where('inventoriable_type', 'App\\Models\\Branch')
                  ->where('inventoriable_id', $request->branch_id);
            });
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        if ($request->filled('cloth_type_id')) {
            $query->where('cloth_type_id', $request->cloth_type_id);
        }

        $items = $query->get();

        // Count by status for context
        $allStatuses = Cloth::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'total_available' => $items->count(),
            'by_status' => $allStatuses,
            'items' => $items,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/out-of-branch",
     *     summary="Get clothes currently rented out (not in branch)",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Out of branch report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_out", type="integer"),
     *             @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function outOfBranch(Request $request)
    {
        $query = Cloth::with(['clothType', 'orders' => function ($q) {
            $q->whereIn('orders.status', ['pending', 'delivered'])
              ->latest('orders.created_at')
              ->limit(1);
        }, 'orders.client'])
            ->where('clothes.status', 'rented');

        $items = $query->get()->map(function ($cloth) {
            $lastOrder = $cloth->orders->first();
            return [
                'cloth_id' => $cloth->id,
                'cloth_code' => $cloth->code,
                'cloth_name' => $cloth->name,
                'status' => $cloth->status,
                'client' => $lastOrder?->client ? [
                    'id' => $lastOrder->client->id,
                    'name' => $lastOrder->client->first_name . ' ' . $lastOrder->client->last_name,
                ] : null,
                'order_id' => $lastOrder?->id,
                'order_date' => $lastOrder?->created_at,
            ];
        });

        return response()->json([
            'total_out' => $items->count(),
            'items' => $items,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/overdue-returns",
     *     summary="Get overdue rental returns",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="days_overdue", in="query", required=false, @OA\Schema(type="integer", default=0)),
     *     @OA\Response(
     *         response=200,
     *         description="Overdue returns report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_overdue", type="integer"),
     *             @OA\Property(property="total_value_at_risk", type="number"),
     *             @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function overdueReturns(Request $request)
    {
        $daysOverdue = (int) $request->query('days_overdue', 0);
        $cutoffDate = now()->subDays($daysOverdue);

        $overdueRents = Rent::with(['cloth', 'order.client', 'client'])
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
            ->whereIn('appointment_type', ['rental_return', 'rental_delivery'])
            ->where('return_date', '<', $cutoffDate)
            ->get();

        $items = $overdueRents->map(function ($rent) {
            $daysLate = Carbon::parse($rent->return_date)->diffInDays(now());
            return [
                'rent_id' => $rent->id,
                'cloth' => $rent->cloth ? [
                    'id' => $rent->cloth->id,
                    'code' => $rent->cloth->code,
                    'name' => $rent->cloth->name,
                ] : null,
                'client' => $rent->client ? [
                    'id' => $rent->client->id,
                    'name' => $rent->client->first_name . ' ' . $rent->client->last_name,
                    'phone' => $rent->client->phone,
                ] : null,
                'return_date' => $rent->return_date,
                'days_late' => $daysLate,
                'order_id' => $rent->order_id,
            ];
        });

        return response()->json([
            'total_overdue' => $items->count(),
            'items' => $items->sortByDesc('days_late')->values(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/most-rented",
     *     summary="Get most rented dresses",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Most rented report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="period", type="object"),
     *             @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function mostRented(Request $request)
    {
        $startDate = $request->query('start_date', now()->subMonths(6)->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $limit = (int) $request->query('limit', 20);

        $mostRented = DB::table('cloth_order')
            ->join('clothes', 'cloth_order.cloth_id', '=', 'clothes.id')
            ->where('cloth_order.type', 'rent')
            ->whereBetween('cloth_order.created_at', [$startDate, $endDate])
            ->select('clothes.id', 'clothes.code', 'clothes.name', DB::raw('count(*) as rental_count'), DB::raw('sum(cloth_order.price) as total_revenue'))
            ->groupBy('clothes.id', 'clothes.code', 'clothes.name')
            ->orderByDesc('rental_count')
            ->limit($limit)
            ->get();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'total_items' => $mostRented->count(),
            'items' => $mostRented,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/most-sold",
     *     summary="Get most sold items (tailoring)",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Most sold report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="period", type="object"),
     *             @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function mostSold(Request $request)
    {
        $startDate = $request->query('start_date', now()->subMonths(6)->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $limit = (int) $request->query('limit', 20);

        $mostSold = DB::table('cloth_order')
            ->join('clothes', 'cloth_order.cloth_id', '=', 'clothes.id')
            ->where('cloth_order.type', 'tailoring')
            ->whereBetween('cloth_order.created_at', [$startDate, $endDate])
            ->select('clothes.id', 'clothes.code', 'clothes.name', DB::raw('count(*) as sales_count'), DB::raw('sum(cloth_order.price) as total_revenue'))
            ->groupBy('clothes.id', 'clothes.code', 'clothes.name')
            ->orderByDesc('sales_count')
            ->limit($limit)
            ->get();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'total_items' => $mostSold->count(),
            'items' => $mostSold,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/rental-profits",
     *     summary="Get rental revenue/profits report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="group_by", in="query", required=false, @OA\Schema(type="string", enum={"day", "week", "month"}, default="month")),
     *     @OA\Response(
     *         response=200,
     *         description="Rental profits report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="summary", type="object"),
     *             @OA\Property(property="breakdown", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function rentalProfits(Request $request)
    {
        $startDate = $request->query('start_date', now()->subMonths(6)->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $groupBy = $request->query('group_by', 'month');

        // Get raw data and group in PHP for database compatibility
        $rentalData = DB::table('cloth_order')
            ->join('orders', 'cloth_order.order_id', '=', 'orders.id')
            ->where('cloth_order.type', 'rent')
            ->whereBetween('cloth_order.created_at', [$startDate, $endDate])
            ->select('cloth_order.created_at', 'cloth_order.price', 'cloth_order.discount_type', 'cloth_order.discount_value')
            ->get();

        // Group in PHP for database-agnostic behavior
        $grouped = $rentalData->groupBy(function ($item) use ($groupBy) {
            $date = Carbon::parse($item->created_at);
            return match($groupBy) {
                'day' => $date->format('Y-m-d'),
                'week' => $date->format('Y-W'),
                'month' => $date->format('Y-m'),
                default => $date->format('Y-m'),
            };
        });

        $breakdown = $grouped->map(function ($items, $period) {
            $discounts = $items->sum(function ($item) {
                if ($item->discount_type === 'percentage') {
                    return $item->price * ($item->discount_value ?? 0) / 100;
                } elseif ($item->discount_type === 'fixed') {
                    return $item->discount_value ?? 0;
                }
                return 0;
            });

            $netRevenue = $items->sum(function ($item) {
                if ($item->discount_type === 'percentage') {
                    return $item->price * (1 - ($item->discount_value ?? 0) / 100);
                } elseif ($item->discount_type === 'fixed') {
                    return $item->price - ($item->discount_value ?? 0);
                }
                return $item->price;
            });

            return [
                'period' => $period,
                'rental_count' => $items->count(),
                'gross_revenue' => $items->sum('price'),
                'discounts' => $discounts,
                'net_revenue' => $netRevenue,
            ];
        })->sortKeys()->values();

        $totals = [
            'total_rentals' => $breakdown->sum('rental_count'),
            'gross_revenue' => $breakdown->sum('gross_revenue'),
            'total_discounts' => $breakdown->sum('discounts'),
            'net_revenue' => $breakdown->sum('net_revenue'),
        ];

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'grouped_by' => $groupBy,
            ],
            'summary' => $totals,
            'breakdown' => $breakdown,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/tailoring-profits",
     *     summary="Get tailoring revenue/profits report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="group_by", in="query", required=false, @OA\Schema(type="string", enum={"day", "week", "month"}, default="month")),
     *     @OA\Response(
     *         response=200,
     *         description="Tailoring profits report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="summary", type="object"),
     *             @OA\Property(property="breakdown", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function tailoringProfits(Request $request)
    {
        $startDate = $request->query('start_date', now()->subMonths(6)->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());
        $groupBy = $request->query('group_by', 'month');

        // Get raw data and group in PHP for database compatibility
        $tailoringData = DB::table('cloth_order')
            ->join('orders', 'cloth_order.order_id', '=', 'orders.id')
            ->where('cloth_order.type', 'tailoring')
            ->whereBetween('cloth_order.created_at', [$startDate, $endDate])
            ->select('cloth_order.created_at', 'cloth_order.price', 'cloth_order.discount_type', 'cloth_order.discount_value')
            ->get();

        // Group in PHP for database-agnostic behavior
        $grouped = $tailoringData->groupBy(function ($item) use ($groupBy) {
            $date = Carbon::parse($item->created_at);
            return match($groupBy) {
                'day' => $date->format('Y-m-d'),
                'week' => $date->format('Y-W'),
                'month' => $date->format('Y-m'),
                default => $date->format('Y-m'),
            };
        });

        $breakdown = $grouped->map(function ($items, $period) {
            $discounts = $items->sum(function ($item) {
                if ($item->discount_type === 'percentage') {
                    return $item->price * ($item->discount_value ?? 0) / 100;
                } elseif ($item->discount_type === 'fixed') {
                    return $item->discount_value ?? 0;
                }
                return 0;
            });

            $netRevenue = $items->sum(function ($item) {
                if ($item->discount_type === 'percentage') {
                    return $item->price * (1 - ($item->discount_value ?? 0) / 100);
                } elseif ($item->discount_type === 'fixed') {
                    return $item->price - ($item->discount_value ?? 0);
                }
                return $item->price;
            });

            return [
                'period' => $period,
                'order_count' => $items->count(),
                'gross_revenue' => $items->sum('price'),
                'discounts' => $discounts,
                'net_revenue' => $netRevenue,
            ];
        })->sortKeys()->values();

        $totals = [
            'total_orders' => $breakdown->sum('order_count'),
            'gross_revenue' => $breakdown->sum('gross_revenue'),
            'total_discounts' => $breakdown->sum('discounts'),
            'net_revenue' => $breakdown->sum('net_revenue'),
        ];

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'grouped_by' => $groupBy,
            ],
            'summary' => $totals,
            'breakdown' => $breakdown,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/factory-evaluations",
     *     summary="Get factory performance evaluations report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="factory_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Factory evaluations report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="summary", type="object"),
     *             @OA\Property(property="factories", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function factoryEvaluations(Request $request)
    {
        $startDate = $request->query('start_date', now()->subMonths(6)->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());

        $query = Factory::with(['evaluations' => function ($q) use ($startDate, $endDate) {
            $q->whereBetween('evaluated_at', [$startDate, $endDate]);
        }]);

        if ($request->filled('factory_id')) {
            $query->where('id', $request->factory_id);
        }

        $factories = $query->get()->map(function ($factory) {
            $evaluations = $factory->evaluations;
            return [
                'factory_id' => $factory->id,
                'factory_name' => $factory->name,
                'total_evaluations' => $evaluations->count(),
                'average_quality' => $evaluations->count() > 0 ? round($evaluations->avg('quality_rating'), 2) : null,
                'average_completion_days' => $evaluations->count() > 0 ? round($evaluations->avg('completion_days'), 1) : null,
                'on_time_rate' => $evaluations->count() > 0 
                    ? round($evaluations->where('on_time', true)->count() / $evaluations->count() * 100, 1) 
                    : null,
                'current_orders' => $factory->current_orders_count,
                'overall_quality_rating' => $factory->quality_rating,
            ];
        });

        $overallStats = [
            'total_evaluations' => $factories->sum('total_evaluations'),
            'average_quality' => $factories->whereNotNull('average_quality')->avg('average_quality'),
            'average_on_time_rate' => $factories->whereNotNull('on_time_rate')->avg('on_time_rate'),
        ];

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => $overallStats,
            'factories' => $factories->sortByDesc('average_quality')->values(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/employee-orders",
     *     summary="Get orders per employee report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Employee orders report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="summary", type="object"),
     *             @OA\Property(property="employees", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function employeeOrders(Request $request)
    {
        $startDate = $request->query('start_date', now()->subMonths(1)->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());

        // Get payments created by users (as a proxy for who created orders/served customers)
        $employeeStats = Payment::with('createdBy')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('created_by')
            ->get()
            ->groupBy('created_by')
            ->map(function ($payments, $userId) {
                $user = $payments->first()->createdBy;
                return [
                    'user_id' => $userId,
                    'user_name' => $user ? $user->name : 'Unknown',
                    'total_payments' => $payments->count(),
                    'total_amount' => $payments->sum('amount'),
                    'paid_amount' => $payments->where('status', 'paid')->sum('amount'),
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_employees' => $employeeStats->count(),
                'total_payments' => $employeeStats->sum('total_payments'),
                'total_revenue' => $employeeStats->sum('paid_amount'),
            ],
            'employees' => $employeeStats,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/daily-cashbox",
     *     summary="Get daily cashbox summary",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Daily cashbox report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="date", type="string"),
     *             @OA\Property(property="cashboxes", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function dailyCashbox(Request $request)
    {
        $date = $request->query('date', now()->toDateString());
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        $query = Cashbox::with('branch');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $cashboxes = $query->get()->map(function ($cashbox) use ($startOfDay, $endOfDay) {
            $dayTransactions = Transaction::where('cashbox_id', $cashbox->id)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->get();

            $income = $dayTransactions->where('type', 'income')->sum('amount');
            $expense = $dayTransactions->where('type', 'expense')->sum('amount');

            // Get opening balance (balance at start of day)
            $lastTransactionBefore = Transaction::where('cashbox_id', $cashbox->id)
                ->where('created_at', '<', $startOfDay)
                ->orderBy('created_at', 'desc')
                ->first();

            $openingBalance = $lastTransactionBefore ? $lastTransactionBefore->balance_after : $cashbox->opening_balance;

            return [
                'cashbox_id' => $cashbox->id,
                'branch' => $cashbox->branch ? [
                    'id' => $cashbox->branch->id,
                    'name' => $cashbox->branch->name,
                ] : null,
                'opening_balance' => $openingBalance,
                'total_income' => $income,
                'total_expense' => $expense,
                'net_change' => $income - $expense,
                'closing_balance' => $openingBalance + $income - $expense,
                'transaction_count' => $dayTransactions->count(),
            ];
        });

        return response()->json([
            'date' => $date,
            'summary' => [
                'total_income' => $cashboxes->sum('total_income'),
                'total_expense' => $cashboxes->sum('total_expense'),
                'net_change' => $cashboxes->sum('net_change'),
            ],
            'cashboxes' => $cashboxes,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/monthly-financial",
     *     summary="Get monthly financial overview",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="year", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Monthly financial report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="period", type="object"),
     *             @OA\Property(property="revenue", type="object"),
     *             @OA\Property(property="expenses", type="object"),
     *             @OA\Property(property="cashflow", type="object")
     *         )
     *     )
     * )
     */
    public function monthlyFinancial(Request $request)
    {
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Revenue from orders
        $orderRevenue = DB::table('cloth_order')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("SUM(CASE WHEN type = 'rent' THEN price ELSE 0 END) as rental_revenue"),
                DB::raw("SUM(CASE WHEN type = 'tailoring' THEN price ELSE 0 END) as tailoring_revenue"),
                DB::raw("SUM(price) as total_revenue")
            )
            ->first();

        // Payments received
        $paymentsReceived = Payment::whereBetween('paid_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('amount');

        // Custody forfeited
        $custodyForfeited = Custody::whereBetween('forfeited_at', [$startDate, $endDate])
            ->where('status', 'forfeited')
            ->sum('amount');

        // Expenses
        $expensesByCategory = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->pluck('total', 'category');

        $totalExpenses = $expensesByCategory->sum();

        // Transaction summary
        $transactionSummary = Transaction::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
                DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense")
            )
            ->first();

        return response()->json([
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_name' => $startDate->format('F'),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'revenue' => [
                'rental_revenue' => $orderRevenue->rental_revenue ?? 0,
                'tailoring_revenue' => $orderRevenue->tailoring_revenue ?? 0,
                'total_order_revenue' => $orderRevenue->total_revenue ?? 0,
                'payments_received' => $paymentsReceived,
                'custody_forfeited' => $custodyForfeited,
            ],
            'expenses' => [
                'by_category' => $expensesByCategory,
                'total_expenses' => $totalExpenses,
            ],
            'cashflow' => [
                'total_income' => $transactionSummary->total_income ?? 0,
                'total_expense' => $transactionSummary->total_expense ?? 0,
                'net_cashflow' => ($transactionSummary->total_income ?? 0) - ($transactionSummary->total_expense ?? 0),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/expenses",
     *     summary="Get expense breakdown report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Expense breakdown report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="summary", type="object"),
     *             @OA\Property(property="by_category", type="object"),
     *             @OA\Property(property="by_branch", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function expenses(Request $request)
    {
        $startDate = $request->query('start_date', now()->subMonths(1)->toDateString());
        $endDate = $request->query('end_date', now()->toDateString());

        $query = Expense::with('branch')
            ->whereBetween('expense_date', [$startDate, $endDate]);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $expenses = $query->get();

        // By category
        $byCategory = $expenses->groupBy('category')->map(function ($items, $category) {
            return [
                'category' => $category,
                'count' => $items->count(),
                'total' => $items->sum('amount'),
                'pending' => $items->where('status', 'pending')->sum('amount'),
                'approved' => $items->where('status', 'approved')->sum('amount'),
                'paid' => $items->where('status', 'paid')->sum('amount'),
            ];
        })->values();

        // By branch
        $byBranch = $expenses->groupBy('branch_id')->map(function ($items, $branchId) {
            $branch = $items->first()->branch;
            return [
                'branch_id' => $branchId,
                'branch_name' => $branch ? $branch->name : 'Unknown',
                'count' => $items->count(),
                'total' => $items->sum('amount'),
            ];
        })->values();

        // By status
        $byStatus = $expenses->groupBy('status')->map(function ($items, $status) {
            return [
                'status' => $status,
                'count' => $items->count(),
                'total' => $items->sum('amount'),
            ];
        })->values();

        return response()->json([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_count' => $expenses->count(),
                'total_amount' => $expenses->sum('amount'),
                'paid_amount' => $expenses->where('status', 'paid')->sum('amount'),
            ],
            'by_category' => $byCategory,
            'by_branch' => $byBranch,
            'by_status' => $byStatus,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/deposits",
     *     summary="Get custody/deposit status report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"held", "returned", "forfeited", "partial"})),
     *     @OA\Response(
     *         response=200,
     *         description="Deposits/custody report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="summary", type="object"),
     *             @OA\Property(property="by_status", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function deposits(Request $request)
    {
        $query = Custody::with(['order.client']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $custodies = $query->get();

        // By status summary
        $byStatus = $custodies->groupBy('status')->map(function ($items, $status) {
            return [
                'status' => $status,
                'count' => $items->count(),
                'total_amount' => $items->sum('value'),
                'pending_amount' => $items->where('status', 'pending')->sum('value'),
            ];
        })->values();

        // Currently pending deposits (held)
        $pendingDeposits = $custodies->where('status', 'pending');

        return response()->json([
            'summary' => [
                'total_custodies' => $custodies->count(),
                'total_amount' => $custodies->sum('value'),
                'currently_held' => $pendingDeposits->sum('value'),
                'total_returned' => $custodies->where('status', 'returned')->sum('value'),
                'total_forfeited' => $custodies->where('status', 'forfeited')->sum('value'),
            ],
            'by_status' => $byStatus,
            'held_deposits' => $pendingDeposits->map(function ($custody) {
                return [
                    'custody_id' => $custody->id,
                    'order_id' => $custody->order_id,
                    'client' => $custody->order?->client ? [
                        'id' => $custody->order->client->id,
                        'name' => $custody->order->client->first_name . ' ' . $custody->order->client->last_name,
                    ] : null,
                    'amount' => $custody->value,
                    'created_at' => $custody->created_at,
                ];
            })->values(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/debts",
     *     summary="Get outstanding receivables/debts report",
     *     tags={"Reports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending", "partial", "paid", "overdue", "written_off"})),
     *     @OA\Parameter(name="overdue_only", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Outstanding debts report",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="summary", type="object"),
     *             @OA\Property(property="by_status", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="aging", type="object")
     *         )
     *     )
     * )
     */
    public function debts(Request $request)
    {
        $query = Receivable::with(['client', 'order']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('overdue_only')) {
            $query->where('status', 'overdue');
        }

        $receivables = $query->get();

        // By status
        $byStatus = $receivables->groupBy('status')->map(function ($items, $status) {
            return [
                'status' => $status,
                'count' => $items->count(),
                'total_amount' => $items->sum('original_amount'),
                'paid_amount' => $items->sum('paid_amount'),
                'remaining_amount' => $items->sum('remaining_amount'),
            ];
        })->values();

        // Aging analysis (for non-paid)
        $outstandingReceivables = $receivables->whereIn('status', ['pending', 'partial', 'overdue']);
        
        $aging = [
            'current' => ['count' => 0, 'amount' => 0],        // Not yet due
            '1_30_days' => ['count' => 0, 'amount' => 0],      // 1-30 days overdue
            '31_60_days' => ['count' => 0, 'amount' => 0],     // 31-60 days overdue
            '61_90_days' => ['count' => 0, 'amount' => 0],     // 61-90 days overdue
            'over_90_days' => ['count' => 0, 'amount' => 0],   // 90+ days overdue
        ];

        foreach ($outstandingReceivables as $receivable) {
            if (!$receivable->due_date) continue;
            
            $daysOverdue = Carbon::parse($receivable->due_date)->diffInDays(now(), false);
            $remaining = $receivable->remaining_amount;

            if ($daysOverdue <= 0) {
                $aging['current']['count']++;
                $aging['current']['amount'] += $remaining;
            } elseif ($daysOverdue <= 30) {
                $aging['1_30_days']['count']++;
                $aging['1_30_days']['amount'] += $remaining;
            } elseif ($daysOverdue <= 60) {
                $aging['31_60_days']['count']++;
                $aging['31_60_days']['amount'] += $remaining;
            } elseif ($daysOverdue <= 90) {
                $aging['61_90_days']['count']++;
                $aging['61_90_days']['amount'] += $remaining;
            } else {
                $aging['over_90_days']['count']++;
                $aging['over_90_days']['amount'] += $remaining;
            }
        }

        // Top debtors
        $topDebtors = $outstandingReceivables
            ->groupBy('client_id')
            ->map(function ($items, $clientId) {
                $client = $items->first()->client;
                return [
                    'client_id' => $clientId,
                    'client_name' => $client ? $client->first_name . ' ' . $client->last_name : 'Unknown',
                    'total_owed' => $items->sum('remaining_amount'),
                    'receivables_count' => $items->count(),
                ];
            })
            ->sortByDesc('total_owed')
            ->take(10)
            ->values();

        return response()->json([
            'summary' => [
                'total_receivables' => $receivables->count(),
                'total_amount' => $receivables->sum('original_amount'),
                'total_paid' => $receivables->sum('paid_amount'),
                'total_outstanding' => $outstandingReceivables->sum('remaining_amount'),
                'overdue_amount' => $receivables->where('status', 'overdue')->sum('remaining_amount'),
            ],
            'by_status' => $byStatus,
            'aging' => $aging,
            'top_debtors' => $topDebtors,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}

