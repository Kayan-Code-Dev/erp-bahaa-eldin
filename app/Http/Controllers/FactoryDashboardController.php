<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FactoryDashboardController extends Controller
{
    /**
     * Get factory ID for the authenticated user
     */
    private function getFactoryId(): ?int
    {
        $user = auth()->user();
        return $user->getFactoryId();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factory/dashboard",
     *     summary="Get factory dashboard statistics",
     *     tags={"Factory Dashboard"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index()
    {
        $factoryId = $this->getFactoryId();
        if (!$factoryId) {
            return response()->json(['message' => 'User is not assigned to a factory'], 403);
        }

        // Get orders assigned to factory
        $orders = Order::forFactory($factoryId)
            ->tailoringOrders()
            ->with('items')
            ->get();

        // Count new orders (items with pending_factory_approval or new status)
        $newOrdersCount = 0;
        $inProgressCount = 0;
        $overdueCount = 0;
        $totalCompletionDays = 0;
        $completedCount = 0;

        foreach ($orders as $order) {
            $tailoringItems = $order->items()->wherePivot('type', 'tailoring')->get();
            
            foreach ($tailoringItems as $item) {
                $status = $item->pivot->factory_status;
                
                if ($status === 'pending_factory_approval' || $status === 'new' || $status === null) {
                    $newOrdersCount++;
                }
                
                if ($status === 'in_progress' || $status === 'accepted') {
                    $inProgressCount++;
                }
                
                // Check if overdue
                if ($item->pivot->factory_expected_delivery_date) {
                    $expectedDate = Carbon::parse($item->pivot->factory_expected_delivery_date);
                    if ($expectedDate->isPast() && $status !== 'delivered_to_atelier' && $status !== 'closed') {
                        $overdueCount++;
                    }
                }
                
                // Calculate completion time for delivered items
                if ($status === 'delivered_to_atelier' && $item->pivot->factory_accepted_at && $item->pivot->factory_delivered_at) {
                    $acceptedAt = Carbon::parse($item->pivot->factory_accepted_at);
                    $deliveredAt = Carbon::parse($item->pivot->factory_delivered_at);
                    $totalCompletionDays += $acceptedAt->diffInDays($deliveredAt);
                    $completedCount++;
                }
            }
        }

        $averageCompletionDays = $completedCount > 0 ? round($totalCompletionDays / $completedCount, 2) : 0;

        return response()->json([
            'new_orders_count' => $newOrdersCount,
            'in_progress_count' => $inProgressCount,
            'overdue_count' => $overdueCount,
            'average_completion_days' => $averageCompletionDays,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factory/statistics",
     *     summary="Get detailed factory statistics",
     *     tags={"Factory Dashboard"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Success"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function statistics(Request $request)
    {
        $factoryId = $this->getFactoryId();
        if (!$factoryId) {
            return response()->json(['message' => 'User is not assigned to a factory'], 403);
        }

        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : Carbon::now()->startOfMonth();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : Carbon::now()->endOfMonth();

        $orders = Order::forFactory($factoryId)
            ->tailoringOrders()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('items')
            ->get();

        $totalItems = 0;
        $acceptedItems = 0;
        $rejectedItems = 0;
        $deliveredItems = 0;
        $totalCompletionDays = 0;
        $completedCount = 0;
        $onTimeDeliveries = 0;
        $totalDeliveries = 0;

        foreach ($orders as $order) {
            $tailoringItems = $order->items()->wherePivot('type', 'tailoring')->get();
            
            foreach ($tailoringItems as $item) {
                $totalItems++;
                $status = $item->pivot->factory_status;
                
                if ($status === 'accepted' || $status === 'in_progress' || $status === 'ready_for_delivery' || 
                    $status === 'delivered_to_atelier' || $status === 'closed') {
                    $acceptedItems++;
                }
                
                if ($status === 'rejected') {
                    $rejectedItems++;
                }
                
                if ($status === 'delivered_to_atelier' || $status === 'closed') {
                    $deliveredItems++;
                    $totalDeliveries++;
                    
                    // Check if on time
                    if ($item->pivot->factory_expected_delivery_date && $item->pivot->factory_delivered_at) {
                        $expectedDate = Carbon::parse($item->pivot->factory_expected_delivery_date);
                        $deliveredDate = Carbon::parse($item->pivot->factory_delivered_at);
                        if ($deliveredDate->lte($expectedDate)) {
                            $onTimeDeliveries++;
                        }
                    }
                    
                    // Calculate completion time
                    if ($item->pivot->factory_accepted_at && $item->pivot->factory_delivered_at) {
                        $acceptedAt = Carbon::parse($item->pivot->factory_accepted_at);
                        $deliveredAt = Carbon::parse($item->pivot->factory_delivered_at);
                        $totalCompletionDays += $acceptedAt->diffInDays($deliveredAt);
                        $completedCount++;
                    }
                }
            }
        }

        $averageCompletionDays = $completedCount > 0 ? round($totalCompletionDays / $completedCount, 2) : 0;
        $onTimeRate = $totalDeliveries > 0 ? round(($onTimeDeliveries / $totalDeliveries) * 100, 2) : 0;

        return response()->json([
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'total_items' => $totalItems,
            'accepted_items' => $acceptedItems,
            'rejected_items' => $rejectedItems,
            'delivered_items' => $deliveredItems,
            'average_completion_days' => $averageCompletionDays,
            'on_time_rate' => $onTimeRate,
            'rejection_rate' => $totalItems > 0 ? round(($rejectedItems / $totalItems) * 100, 2) : 0,
        ], 200);
    }
}
