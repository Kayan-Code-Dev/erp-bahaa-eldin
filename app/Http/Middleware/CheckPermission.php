<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * Checks if the authenticated user has the required permission.
     * Super admin (admin@admin.com) bypasses all permission checks.
     *
     * Usage in routes:
     *   Route::middleware(['auth:sanctum', 'permission:orders.create'])->...
     *   Route::middleware(['auth:sanctum', 'permission:orders.create,orders.update'])->... (any of these)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permissions  Comma-separated permission names (user needs ANY of them)
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Super admin bypasses all permission checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Parse permissions (comma-separated)
        $permissionList = array_map('trim', explode(',', $permissions));

        // Check if user has any of the required permissions
        if (!$user->hasAnyPermission($permissionList)) {
            return response()->json([
                'message' => 'Forbidden. You do not have the required permission.',
                'required_permissions' => $permissionList,
            ], 403);
        }

        return $next($request);
    }
}






