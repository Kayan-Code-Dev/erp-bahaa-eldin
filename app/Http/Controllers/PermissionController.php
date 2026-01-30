<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/permissions",
     *     summary="List all permissions",
     *     description="Get a list of all permissions in the system. Can be filtered by module.",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="module", in="query", required=false, description="Filter by module name", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="List of permissions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="orders.view"),
     *                 @OA\Property(property="display_name", type="string", example="View Orders"),
     *                 @OA\Property(property="description", type="string", example="Allows user to view orders"),
     *                 @OA\Property(property="module", type="string", example="orders"),
     *                 @OA\Property(property="action", type="string", example="view")
     *             )),
     *             @OA\Property(property="modules", type="array", @OA\Items(type="string"), example={"clients", "orders", "payments"})
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Permission::query();

        if ($request->has('module') && $request->module) {
            $query->forModule($request->module);
        }

        $permissions = $query->orderBy('module')->orderBy('action')->get();

        // Get unique modules for filter dropdown
        $modules = Permission::distinct()->pluck('module')->sort()->values();

        return response()->json([
            'data' => $permissions,
            'modules' => $modules,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/permissions/{id}",
     *     summary="Get a permission by ID",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Permission details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="orders.view"),
     *             @OA\Property(property="display_name", type="string", example="View Orders"),
     *             @OA\Property(property="description", type="string", example="Allows user to view orders"),
     *             @OA\Property(property="module", type="string", example="orders"),
     *             @OA\Property(property="action", type="string", example="view"),
     *             @OA\Property(property="roles", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="general_manager")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Permission not found")
     * )
     */
    public function show($id)
    {
        $permission = Permission::with('roles')->findOrFail($id);
        return response()->json($permission);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{id}/permissions",
     *     summary="Get permissions assigned to a role",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of permissions for the role",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="role", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="general_manager"),
     *                 @OA\Property(property="description", type="string", example="General Manager - Full access")
     *             ),
     *             @OA\Property(property="permissions", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="orders.view"),
     *                 @OA\Property(property="display_name", type="string", example="View Orders")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role not found")
     * )
     */
    public function rolePermissions($roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
            ],
            'permissions' => $role->permissions->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'display_name' => $p->display_name,
                    'module' => $p->module,
                    'action' => $p->action,
                ];
            }),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/roles/{id}/permissions",
     *     summary="Assign permissions to a role",
     *     description="Sync permissions for a role. Replaces all existing permissions with the provided list.",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permissions"},
     *             @OA\Property(property="permissions", type="array", description="Array of permission names to assign", @OA\Items(type="string"), example={"orders.view", "orders.create", "clients.view"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Permissions updated successfully"),
     *             @OA\Property(property="role", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="sales_employee")
     *             ),
     *             @OA\Property(property="permissions_count", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function assignPermissions(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $data = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->syncPermissions($data['permissions']);

        return response()->json([
            'message' => 'Permissions updated successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
            'permissions_count' => count($data['permissions']),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}/permissions",
     *     summary="Get all permissions for a user (through their roles)",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="List of permissions for the user",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="is_super_admin", type="boolean", example=false)
     *             ),
     *             @OA\Property(property="roles", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="sales_employee")
     *             )),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"orders.view", "clients.view"})
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function userPermissions($userId)
    {
        $user = \App\Models\User::with('roles')->findOrFail($userId);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->isSuperAdmin(),
            ],
            'roles' => $user->roles->map(function ($r) {
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                ];
            }),
            'permissions' => $user->getAllPermissions(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/me/permissions",
     *     summary="Get permissions for the currently authenticated user",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current user's permissions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="is_super_admin", type="boolean", example=false)
     *             ),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"sales_employee"}),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"orders.view", "clients.view"})
     *         )
     *     )
     * )
     */
    public function myPermissions(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->isSuperAdmin(),
            ],
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/roles/{id}/permissions/toggle",
     *     summary="Toggle a permission for a role",
     *     description="If the permission is assigned to the role, it will be removed. If it's not assigned, it will be added.",
     *     tags={"Permissions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Role ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permission"},
     *             @OA\Property(property="permission", type="string", description="Permission name to toggle", example="orders.view")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission toggled successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Permission assigned successfully"),
     *             @OA\Property(property="action", type="string", enum={"assigned", "revoked"}, example="assigned"),
     *             @OA\Property(property="role", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="sales_employee")
     *             ),
     *             @OA\Property(property="permission", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="orders.view"),
     *                 @OA\Property(property="display_name", type="string", example="View Orders")
     *             ),
     *             @OA\Property(property="is_assigned", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Role or permission not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function togglePermission(Request $request, $roleId)
    {
        $role = Role::findOrFail($roleId);

        $data = $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        $permission = Permission::where('name', $data['permission'])->firstOrFail();
        $wasAssigned = $role->hasPermission($permission->name);

        if ($wasAssigned) {
            $role->revokePermission($permission);
            $action = 'revoked';
            $message = 'Permission revoked successfully';
        } else {
            $role->assignPermission($permission);
            $action = 'assigned';
            $message = 'Permission assigned successfully';
        }

        return response()->json([
            'message' => $message,
            'action' => $action,
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
            ],
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
            ],
            'is_assigned' => !$wasAssigned,
        ]);
    }
}






