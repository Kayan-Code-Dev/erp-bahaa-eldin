<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Activity Logs",
 *     description="System activity audit trail"
 * )
 */
class ActivityLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs",
     *     summary="List all activity logs",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="action", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="entity_type", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="entity_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="search", in="query", description="Search in description", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="List of activity logs"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with(['user', 'branch']);

        if ($request->has('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->has('action')) {
            $query->byAction($request->action);
        }

        if ($request->has('entity_type')) {
            $entityType = $request->entity_type;
            // Support short names or full class names
            if (!str_contains($entityType, '\\')) {
                $entityType = "App\\Models\\{$entityType}";
            }
            $query->forEntityType($entityType);
        }

        if ($request->has('entity_id') && $request->has('entity_type')) {
            $entityType = $request->entity_type;
            if (!str_contains($entityType, '\\')) {
                $entityType = "App\\Models\\{$entityType}";
            }
            $query->forEntity($entityType, $request->entity_id);
        }

        if ($request->has('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('entity_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 50));

        return $this->paginatedResponse($logs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs/{id}",
     *     summary="Get activity log details",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Activity log details"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $log = ActivityLog::with(['user', 'branch'])->findOrFail($id);
        return response()->json($log);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs/entity/{entity_type}/{entity_id}",
     *     summary="Get activity logs for a specific entity",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="entity_type", in="path", required=true, description="Entity type (e.g., Order, Payment)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="entity_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Entity activity logs"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function forEntity(Request $request, $entityType, $entityId)
    {
        // Support short names
        if (!str_contains($entityType, '\\')) {
            $entityType = "App\\Models\\{$entityType}";
        }

        $logs = ActivityLog::forEntity($entityType, $entityId)
                           ->with(['user'])
                           ->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 50));

        return response()->json($logs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs/user/{user_id}",
     *     summary="Get activity logs for a specific user",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="user_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User activity logs"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function forUser(Request $request, $userId)
    {
        $logs = ActivityLog::forUser($userId)
                           ->with(['branch'])
                           ->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 50));

        return response()->json($logs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs/my",
     *     summary="Get current user's activity logs",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="User's activity logs"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function myActivity(Request $request)
    {
        $logs = ActivityLog::forUser($request->user()->id)
                           ->with(['branch'])
                           ->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 50));

        return response()->json($logs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs/today",
     *     summary="Get today's activity logs",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Today's activity logs"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function today(Request $request)
    {
        $logs = ActivityLog::today()
                           ->with(['user', 'branch'])
                           ->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 100));

        return response()->json($logs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs/actions",
     *     summary="Get all available actions",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="List of actions")
     * )
     */
    public function actions()
    {
        return response()->json(['actions' => ActivityLog::ACTIONS]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs/statistics",
     *     summary="Get activity statistics",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Activity statistics"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function statistics(Request $request)
    {
        $query = ActivityLog::query();

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->forDateRange($request->date_from, $request->date_to);
        } else {
            // Default to last 30 days
            $query->forDateRange(now()->subDays(30), now());
        }

        $logs = $query->get();

        $statistics = [
            'total_activities' => $logs->count(),
            'by_action' => $logs->groupBy('action')->map->count(),
            'by_entity_type' => $logs->groupBy(fn($log) => class_basename($log->entity_type))->map->count(),
            'by_user' => $logs->groupBy('user_id')->map->count()->sortDesc()->take(10),
            'recent_deletions' => $logs->where('action', ActivityLog::ACTION_DELETED)->count(),
            'failed_logins' => $logs->where('action', ActivityLog::ACTION_LOGIN_FAILED)->count(),
        ];

        return response()->json($statistics);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs/deletions",
     *     summary="Get recent deletion logs",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="days", in="query", @OA\Schema(type="integer", default=7)),
     *     @OA\Response(response=200, description="Deletion logs"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function deletions(Request $request)
    {
        $days = $request->get('days', 7);

        $logs = ActivityLog::byAction(ActivityLog::ACTION_DELETED)
                           ->where('created_at', '>=', now()->subDays($days))
                           ->with(['user'])
                           ->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 50));

        return response()->json($logs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activity-logs/login-attempts",
     *     summary="Get login attempt logs",
     *     tags={"Activity Logs"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="failed_only", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Login attempt logs"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function loginAttempts(Request $request)
    {
        $query = ActivityLog::query();

        if ($request->boolean('failed_only')) {
            $query->byAction(ActivityLog::ACTION_LOGIN_FAILED);
        } else {
            $query->whereIn('action', [
                ActivityLog::ACTION_LOGIN,
                ActivityLog::ACTION_LOGOUT,
                ActivityLog::ACTION_LOGIN_FAILED,
            ]);
        }

        $logs = $query->with(['user'])
                      ->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 50));

        return response()->json($logs);
    }
}





