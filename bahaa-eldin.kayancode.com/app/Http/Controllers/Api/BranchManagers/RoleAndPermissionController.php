<?php

namespace App\Http\Controllers\Api\BranchManagers;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Services\RolesPermissions\RolePermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RoleAndPermissionController extends Controller
{

    protected RolePermissionService $service;
    protected ?Admin $admin;
    protected ?BranchManager $branchManager;
    protected ?Branch $branch;

    public function __construct(RolePermissionService $service)
    {
        $this->service = $service;
        $this->admin = auth('admin-api')->user();
        $this->branchManager = auth('branchManager-api')->user();
        $this->branch = auth('branch-api')->user();
    }

    private function checkPermission(string $permission)
    {
        if ($this->admin instanceof Admin) {
            if (!$this->admin->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } elseif ($this->branchManager instanceof BranchManager) {
            if (!$this->branchManager->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } elseif ($this->branch instanceof Branch) {
            if (!$this->branch->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } else {
            abort(Response::HTTP_UNAUTHORIZED, 'يجب تسجيل الدخول');
        }
    }

    public function getPermissionsBranchManager()
    {
        $this->checkPermission('Read-Permissions');
        $permissions = $this->service->indexPermissionsBranchManager();
        return response()->json(['status' => true, 'message' => 'تم جلب الصلاحيات الخاصة بك', 'data' => $permissions], Response::HTTP_OK);
    }



    public function getMyRoles()
    {
        $this->checkPermission('Read-Roles');
        $roles = $this->service->indexRolesBranchManager(auth('branchManager-api')->user());
        return response()->json(['status' => true, 'message' => 'تم جلب الادوار الخاصة بك', 'data' => $roles], Response::HTTP_OK);
    }


    public function togglePermission(Request $request)
    {
        // $this->checkPermission('Create-Permission');
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

    public function getMyRolesEmployees()
    {

        $this->checkPermission('Read-Roles');
        $roles = $this->service->indexRoleEmployeesBranch(auth('branch-api')->user());
        return response()->json(['status' => true, 'message' => 'تم جلب الادوار الخاصة بلموظقين', 'data' => $roles], Response::HTTP_OK);
    }

    public function createRoleEmployeeBranch(Request $request)
    {
        $this->checkPermission('Create-Role');
        $branchId = auth('branch-api')->user()->id;
        $validator =  Validator::make($request->all(), [
            'guard_name' => 'required|string|in:employee-api',
            'name' => ['required', 'string', 'min:2', 'max:40',  Rule::unique('roles', 'name')->where(function ($query) use ($branchId) {
                return $query->where('branch_id', $branchId);
            }),],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $role = $this->service->storeRoleEmployeesBranch($data, $branchId);
        return response()->json(['status' => true, 'message' => 'تم الإنشاء بنجاح', 'role' => $role], Response::HTTP_CREATED);
    }


    public function getPermissionsEmployeeBranch()
    {
        $this->checkPermission('Read-Permissions');
        $permissions = $this->service->indexPermissionsEmployeeBranch();
        return response()->json(['status' => true, 'message' => 'تم جلب الصلاحيات الخاصة بك', 'data' => $permissions], Response::HTTP_OK);
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
}
