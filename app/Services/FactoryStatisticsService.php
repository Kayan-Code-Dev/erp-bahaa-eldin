<?php

namespace App\Services;

use App\Models\Factory;
use App\Models\Order;
use App\Models\FactoryEvaluation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FactoryStatisticsService
 * 
 * Service for calculating and managing factory statistics and performance metrics.
 */
class FactoryStatisticsService
{
    /**
     * Recalculate statistics for a single factory
     */
    public function recalculateForFactory(Factory $factory): Factory
    {
        $factory->recalculateStatistics();
        return $factory->fresh();
    }

    /**
     * Recalculate statistics for all factories
     */
    public function recalculateAll(): int
    {
        $factories = Factory::all();
        
        foreach ($factories as $factory) {
            $factory->recalculateStatistics();
        }
        
        return $factories->count();
    }

    /**
     * Get factory performance summary
     */
    public function getFactorySummary(Factory $factory): array
    {
        $recentEvaluations = $factory->evaluations()
            ->with('order', 'evaluator')
            ->recent(5)
            ->get();

        $currentOrders = $factory->orders()
            ->whereIn('tailoring_stage', [
                Order::STAGE_SENT_TO_FACTORY,
                Order::STAGE_IN_PRODUCTION,
            ])
            ->with('client')
            ->get();

        $overdueOrders = $factory->orders()
            ->overdue()
            ->get();

        return [
            'factory' => $factory,
            'statistics' => [
                'current_orders_count' => $factory->current_orders_count,
                'total_orders_completed' => $factory->total_orders_completed,
                'average_completion_days' => $factory->average_completion_days,
                'quality_rating' => $factory->quality_rating,
                'quality_stars' => $factory->quality_stars,
                'on_time_rate' => $factory->on_time_rate,
                'total_evaluations' => $factory->total_evaluations,
                'performance_score' => $factory->performance_score,
                'is_at_capacity' => $factory->is_at_capacity,
                'available_capacity' => $factory->available_capacity,
            ],
            'current_orders' => $currentOrders,
            'overdue_orders' => $overdueOrders,
            'recent_evaluations' => $recentEvaluations,
            'stats_calculated_at' => $factory->stats_calculated_at,
        ];
    }

    /**
     * Get all factories ranked by performance
     */
    public function getFactoryRanking(?int $limit = null): array
    {
        $query = Factory::active()
            ->orderByPerformance('desc');

        if ($limit) {
            $query->limit($limit);
        }

        $factories = $query->get();

        return $factories->map(function ($factory, $index) {
            return [
                'rank' => $index + 1,
                'factory_id' => $factory->id,
                'name' => $factory->name,
                'quality_rating' => $factory->quality_rating,
                'on_time_rate' => $factory->on_time_rate,
                'performance_score' => $factory->performance_score,
                'total_orders_completed' => $factory->total_orders_completed,
                'average_completion_days' => $factory->average_completion_days,
            ];
        })->toArray();
    }

    /**
     * Recommend best factory for a new order
     */
    public function recommendFactory(?int $expectedDays = null, ?string $priority = null): ?Factory
    {
        $query = Factory::active()
            ->withCapacity()
            ->where('quality_rating', '>=', 3) // Minimum quality threshold
            ->orderByPerformance('desc');

        // If urgent, prioritize factories with fewer current orders
        if ($priority === Order::PRIORITY_URGENT || $priority === Order::PRIORITY_HIGH) {
            $query->orderByWorkload('asc');
        }

        // If expected days is tight, prioritize factories with lower avg completion
        if ($expectedDays && $expectedDays <= 7) {
            $query->where('average_completion_days', '<=', $expectedDays * 1.2);
        }

        return $query->first();
    }

    /**
     * Get factory workload distribution
     */
    public function getWorkloadDistribution(): array
    {
        return Factory::active()
            ->select('id', 'name', 'current_orders_count', 'max_capacity')
            ->orderBy('current_orders_count', 'desc')
            ->get()
            ->map(function ($factory) {
                return [
                    'factory_id' => $factory->id,
                    'name' => $factory->name,
                    'current_orders' => $factory->current_orders_count,
                    'max_capacity' => $factory->max_capacity,
                    'utilization' => $factory->max_capacity 
                        ? round(($factory->current_orders_count / $factory->max_capacity) * 100, 1)
                        : null,
                ];
            })
            ->toArray();
    }

    /**
     * Get performance trends for a factory
     */
    public function getPerformanceTrends(Factory $factory, int $months = 6): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();
        
        $evaluationsByMonth = $factory->evaluations()
            ->where('evaluated_at', '>=', $startDate)
            ->get()
            ->groupBy(function ($eval) {
                return $eval->evaluated_at->format('Y-m');
            });

        $trends = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= now()) {
            $monthKey = $currentDate->format('Y-m');
            $monthEvaluations = $evaluationsByMonth->get($monthKey, collect());
            
            $trends[] = [
                'month' => $monthKey,
                'month_label' => $currentDate->format('M Y'),
                'evaluations_count' => $monthEvaluations->count(),
                'average_quality' => $monthEvaluations->isNotEmpty() 
                    ? round($monthEvaluations->avg('quality_rating'), 2) 
                    : null,
                'on_time_rate' => $monthEvaluations->isNotEmpty()
                    ? round($monthEvaluations->where('on_time', true)->count() / $monthEvaluations->count() * 100, 1)
                    : null,
            ];

            $currentDate->addMonth();
        }

        return $trends;
    }

    /**
     * Create an evaluation for a completed order
     */
    public function createEvaluation(
        Factory $factory,
        Order $order,
        int $qualityRating,
        $user,
        array $options = []
    ): FactoryEvaluation {
        $completionDays = $order->actual_completion_days;
        $expectedDays = null;
        
        if ($order->sent_to_factory_date && $order->expected_completion_date) {
            $expectedDays = $order->sent_to_factory_date->diffInDays($order->expected_completion_date);
        }

        $onTime = true;
        if ($expectedDays !== null && $completionDays !== null) {
            $onTime = $completionDays <= $expectedDays;
        }

        $evaluation = FactoryEvaluation::create([
            'factory_id' => $factory->id,
            'order_id' => $order->id,
            'quality_rating' => $qualityRating,
            'completion_days' => $completionDays,
            'expected_days' => $expectedDays,
            'on_time' => $onTime,
            'craftsmanship_rating' => $options['craftsmanship_rating'] ?? null,
            'communication_rating' => $options['communication_rating'] ?? null,
            'packaging_rating' => $options['packaging_rating'] ?? null,
            'notes' => $options['notes'] ?? null,
            'issues_found' => $options['issues_found'] ?? null,
            'positive_feedback' => $options['positive_feedback'] ?? null,
            'evaluated_by' => $user->id,
            'evaluated_at' => now(),
        ]);

        // Recalculate factory statistics
        $factory->recalculateStatistics();

        return $evaluation;
    }

    /**
     * Get overall factory statistics
     */
    public function getOverallStatistics(): array
    {
        $factories = Factory::all();
        $activeFactories = $factories->where('factory_status', Factory::STATUS_ACTIVE);

        return [
            'total_factories' => $factories->count(),
            'active_factories' => $activeFactories->count(),
            'total_current_orders' => $activeFactories->sum('current_orders_count'),
            'total_orders_completed' => $factories->sum('total_orders_completed'),
            'average_quality_rating' => round($activeFactories->avg('quality_rating'), 2),
            'average_on_time_rate' => round($activeFactories->avg('on_time_rate'), 2),
            'total_capacity' => $activeFactories->whereNotNull('max_capacity')->sum('max_capacity'),
            'capacity_utilization' => $this->calculateOverallCapacityUtilization($activeFactories),
        ];
    }

    /**
     * Calculate overall capacity utilization
     */
    private function calculateOverallCapacityUtilization($factories): ?float
    {
        $factoriesWithCapacity = $factories->whereNotNull('max_capacity');
        
        if ($factoriesWithCapacity->isEmpty()) {
            return null;
        }

        $totalCapacity = $factoriesWithCapacity->sum('max_capacity');
        $totalOrders = $factoriesWithCapacity->sum('current_orders_count');

        return $totalCapacity > 0 
            ? round(($totalOrders / $totalCapacity) * 100, 1) 
            : 0;
    }
}





