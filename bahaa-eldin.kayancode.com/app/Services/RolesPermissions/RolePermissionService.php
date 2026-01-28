<?php

namespace App\Services\RolesPermissions;

use App\Models\Branch;
use App\Models\BranchManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionService
{
    public function indexRoles()
    {
        return Role::withCount('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions_count' => $role->permissions_count,
                'created_at' => Carbon::parse($role->created_at)->format('Y-m-d'),
            ];
        });
    }

    public function storeRole(array $data)
    {
        $role = new Role();
        $role->name = $data['name'];
        $role->guard_name = $data['guard_name'];
        $role->save();
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'created_at' => $role->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function updateRole($id, array $data)
    {
        $role = Role::findOrFail($id);
        $role->name = $data['name'];
        $role->guard_name = $data['guard_name'];
        $role->save();
        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'created_at' => $role->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function destroyRole($id)
    {
        return DB::delete('DELETE FROM roles WHERE id = ?', [$id]);
    }

    public function indexPermissions($perPage = 10)
    {
        $permissions = Permission::paginate($perPage);
        $permissions->getCollection()->transform(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => Carbon::parse($permission->created_at)->format('Y-m-d'),
            ];
        });
        return [
            'data' => $permissions->items(),
            'current_page' => $permissions->currentPage(),
            'next_page_url' => $permissions->nextPageUrl(),
            'prev_page_url' => $permissions->previousPageUrl(),
            'total' => $permissions->total(),
        ];
    }

    public function showRoleWithPermissions($id)
    {
        $role = Role::findOrFail($id);
        $rolePermissions = $role->permissions;
        $permissions = Permission::where('guard_name', '=', $role->guard_name)->get();
        foreach ($permissions as $permission) {
            $permission->setAttribute('granted', $rolePermissions->contains('id', $permission->id));
        }
        return [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'created_at' => $role->created_at->format('Y-m-d H:i:s'),
            ],
            'permissions' => $permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'granted' => $permission->granted ?? false,
                ];
            }),
        ];
    }

    public function togglePermissionForRole($roleId, $permissionId)
    {
        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);

        if ($role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);
            return ['granted' => false, 'message' => 'تم إلغاء الإذن بنجاح'];
        } else {
            $role->givePermissionTo($permission);
            return ['granted' => true, 'message' => 'تم منح الإذن بنجاح'];
        }
    }

    public function indexRolesBranchManager($branchManger)
    {
        $branchIds = BranchManager::findOrFail($branchManger->id)->manger()->pluck('id')->toArray();
        if (empty($branchIds)) {
            return collect();
        }
        return Role::whereIn('branch_id', $branchIds)->where('guard_name', '=', 'branch-api')->withCount('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'created_at' => Carbon::parse($role->created_at)->format('Y-m-d'),
            ];
        });
    }




    public function indexPermissionsBranchManager()
    {
        return Permission::where('guard_name', '=', 'branch-api')->get()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => Carbon::parse($permission->created_at)->format('Y-m-d'),
            ];
        });
    }

    public function indexRoleEmployeesBranch($branch)
    {
        if (empty($branch)) {
            return collect();
        }
        return Role::where('branch_id', $branch->id)->where('guard_name', '=', 'employee-api')->withCount('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'created_at' => Carbon::parse($role->created_at)->format('Y-m-d'),
            ];
        });
    }


    public function storeRoleEmployeesBranch(array $data, $branch)
    {
        $role = new Role();
        $role->name = $data['name'];
        $role->guard_name = $data['guard_name'];
        $role->branch_id = $branch;
        $role->save();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'created_at' => $role->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function indexPermissionsEmployeeBranch()
    {
        return Permission::where('guard_name', '=', 'employee-api')->get()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'created_at' => Carbon::parse($permission->created_at)->format('Y-m-d'),
            ];
        });
    }

    public function getMyPermissions($user)
    {
        //
        return $user->getAllPermissions()->pluck('name');
    }
}
