<?php

namespace App\Http\Controllers\Api\RoleAndPermission;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\RolesPermissions\RolePermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RolePermissionController extends Controller
{
    protected RolePermissionService $service;
    protected ?Admin $admin;

    public function __construct(RolePermissionService $service)
    {
        $this->service = $service;
        $this->admin = auth('admin-api')->user(); // خزنه مرة واحدة
    }

    private function checkPermission(string $permission)
    {
        if ($this->admin instanceof Admin) {
            if (!$this->admin->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } else {
            abort(Response::HTTP_UNAUTHORIZED, 'يجب تسجيل الدخول');
        }
    }

    public function index()
    {
        $this->checkPermission('Read-Roles');
        $roles = $this->service->indexRoles();
        return response()->json(['status' => true, 'message' => 'تم الإرسال بنجاح', 'data' => $roles]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('Create-Role');
        $validator =  Validator::make($request->all(), [
            'guard_name' => 'required|string|in:admin-api,branchManager-api,branch-api,employee-api',
            'name' => 'required|string|min:2|max:40|unique:roles,name',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();

        $role = $this->service->storeRole($data);
        return response()->json(['status' => true, 'message' => 'تم الإنشاء بنجاح', 'role' => $role], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id)
    {
        $this->checkPermission('Update-Role');
        $validator =  Validator::make($request->all(), [
            'guard_name' => 'required|string|in:admin-api,branchManager-api,branch-api,employee-api',
            'name' => 'required|string|min:2|max:40',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $role = $this->service->updateRole($id, $data);
        return response()->json(['status' => true, 'message' => 'تم التحديث بنجاح', 'role' => $role]);
    }

    public function destroy(int $id)
    {
        $this->checkPermission('Delete-Role');
        $deleted = $this->service->destroyRole($id);
        return response()->json(['status' => $deleted, 'message' => $deleted ? 'تم الحذف بنجاح' : 'فشل الحذف']);
    }

    public function indexPermissions()
    {
        $this->checkPermission('Read-Permissions');
        $permissions = $this->service->indexPermissions();
        return response()->json(['status' => true, 'message' => 'تم الإرسال بنجاح', 'data' => $permissions]);
    }

    public function show(int $id)
    {
        $this->checkPermission('Read-Roles');
        $data = $this->service->showRoleWithPermissions($id);
        return response()->json([
            'status' => true,
            'message' => 'تم الإرسال بنجاح',
            'role' => $data['role'],
            'permissions' => $data['permissions']
        ]);
    }

    public function togglePermission(Request $request)
    {
        $this->checkPermission('Update-Role');
        $validator =  Validator::make($request->all(), [
            'role_id' => 'required|integer|exists:roles,id',
            'permission_id' => 'required|integer|exists:permissions,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $result = $this->service->togglePermissionForRole($data['role_id'], $data['permission_id']);
        return response()->json([
            'status' => true,
            'message' => $result['message'],
            'granted' => $result['granted']
        ]);
    }


    public function getMyPermissions()
    {
        $guards = array_keys(config('auth.guards'));
        $guard = null;
        foreach ($guards as $guard) {
            if (auth($guard)->check()) {
                $guard = $guard;
                break;
            }
        }
        $user = auth($guard)->user();
        $permissions = $this->service->getMyPermissions($user);
        return response()->json(['status' => true, 'message' => 'تم جلب الصلاحيات الخاصة بك', 'data' => $permissions], Response::HTTP_CREATED);
    }
}
