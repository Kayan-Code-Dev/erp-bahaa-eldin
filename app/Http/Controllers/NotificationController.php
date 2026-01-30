<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications",
     *     summary="List notifications for authenticated user",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="priority", in="query", required=false, @OA\Schema(type="string", enum={"low", "normal", "high", "urgent"})),
     *     @OA\Parameter(name="unread_only", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of notifications",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="message", type="string"),
                 *                 @OA\Property(property="priority", type="string", enum={"low", "normal", "high", "urgent"}),
     *                 @OA\Property(property="read_at", type="string", nullable=true),
     *                 @OA\Property(property="created_at", type="string")
     *             )),
     *             @OA\Property(property="unread_count", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $user = $request->user();

        $query = Notification::forUser($user->id)
            ->undismissed()
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        if ($request->filled('priority')) {
            $query->ofPriority($request->priority);
        }

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $notifications = $query->paginate($perPage);

        $response = $this->paginatedResponse($notifications);
        $response = $response->getData(true);
        $response['unread_count'] = $this->notificationService->getUnreadCount($user);

        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/unread-count",
     *     summary="Get unread notification count",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Unread count",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="unread_count", type="integer")
     *         )
     *     )
     * )
     */
    public function unreadCount(Request $request)
    {
        $count = $this->notificationService->getUnreadCount($request->user());

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/types",
     *     summary="Get notification types",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Notification types",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="types", type="object"),
     *             @OA\Property(property="priorities", type="object")
     *         )
     *     )
     * )
     */
    public function types()
    {
        return response()->json([
            'types' => Notification::getTypes(),
            'priorities' => Notification::getPriorities(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/{id}",
     *     summary="Get notification details",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notification details"),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=403, description="Not authorized to view this notification")
     * )
     */
    public function show(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        // Ensure user can only see their own notifications
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Not authorized to view this notification',
            ], 403);
        }

        return response()->json($notification);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/{id}/read",
     *     summary="Mark notification as read",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notification marked as read"),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=403, description="Not authorized")
     * )
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Not authorized',
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/{id}/unread",
     *     summary="Mark notification as unread",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notification marked as unread"),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=403, description="Not authorized")
     * )
     */
    public function markAsUnread(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Not authorized',
            ], 403);
        }

        $notification->markAsUnread();

        return response()->json([
            'message' => 'Notification marked as unread',
            'notification' => $notification,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/mark-all-read",
     *     summary="Mark all notifications as read",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="updated_count", type="integer")
     *         )
     *     )
     * )
     */
    public function markAllAsRead(Request $request)
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'message' => 'All notifications marked as read',
            'updated_count' => $count,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/{id}/dismiss",
     *     summary="Dismiss a notification",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notification dismissed"),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=403, description="Not authorized")
     * )
     */
    public function dismiss(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Not authorized',
            ], 403);
        }

        $notification->dismiss();

        return response()->json([
            'message' => 'Notification dismissed',
            'notification' => $notification,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/dismiss-all",
     *     summary="Dismiss all notifications",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All notifications dismissed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="dismissed_count", type="integer")
     *         )
     *     )
     * )
     */
    public function dismissAll(Request $request)
    {
        $count = $this->notificationService->dismissAll($request->user());

        return response()->json([
            'message' => 'All notifications dismissed',
            'dismissed_count' => $count,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/notifications/{id}",
     *     summary="Delete a notification",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Notification deleted"),
     *     @OA\Response(response=404, description="Notification not found"),
     *     @OA\Response(response=403, description="Not authorized")
     * )
     */
    public function destroy(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Not authorized',
            ], 403);
        }

        $notification->delete();

        return response()->json(null, 204);
    }

    // ==================== ADMIN ENDPOINTS ====================

    /**
     * @OA\Post(
     *     path="/api/v1/notifications/broadcast",
     *     summary="Broadcast notification to users (admin only)",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "message"},
     *             @OA\Property(property="title", type="string", example="System Maintenance"),
     *             @OA\Property(property="message", type="string", example="The system will be down for maintenance"),
             *             @OA\Property(property="priority", type="string", enum={"low", "normal", "high", "urgent"}),
     *             @OA\Property(property="role", type="string", nullable=true, description="Target role name (null for all users)"),
     *             @OA\Property(property="user_ids", type="array", @OA\Items(type="integer"), description="Specific user IDs")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Notification broadcasted"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function broadcast(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'role' => 'nullable|string|exists:roles,name',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $options = [
            'priority' => $data['priority'] ?? Notification::PRIORITY_NORMAL,
        ];

        $count = 0;

        if (!empty($data['user_ids'])) {
            // Send to specific users
            $users = \App\Models\User::whereIn('id', $data['user_ids'])->get();
            foreach ($users as $user) {
                $this->notificationService->create(
                    $user,
                    Notification::TYPE_SYSTEM,
                    $data['title'],
                    $data['message'],
                    $options
                );
                $count++;
            }
        } elseif (!empty($data['role'])) {
            // Send to users with role
            $count = $this->notificationService->createForRole(
                $data['role'],
                Notification::TYPE_SYSTEM,
                $data['title'],
                $data['message'],
                $options
            );
        } else {
            // Send to all users
            $users = \App\Models\User::all();
            foreach ($users as $user) {
                $this->notificationService->create(
                    $user,
                    Notification::TYPE_SYSTEM,
                    $data['title'],
                    $data['message'],
                    $options
                );
                $count++;
            }
        }

        return response()->json([
            'message' => 'Notification broadcasted successfully',
            'recipients_count' => $count,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/notifications/all",
     *     summary="List all notifications (admin only)",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="user_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Paginated list of all notifications")
     * )
     */
    public function all(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Notification::with('user')
            ->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        $notifications = $query->paginate($perPage);

        return $this->paginatedResponse($notifications);
    }
}





