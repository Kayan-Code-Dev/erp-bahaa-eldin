<?php

namespace App\Http\Controllers\Api\Department;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmployeeLogin;
use App\Services\Department\DepartmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DepartmentController extends Controller
{
    //
    protected DepartmentService $departmentservice;
    protected ?Branch $branch;
    protected ?EmployeeLogin $employeeLogin;

    public function __construct(DepartmentService $departmentservice)
    {
        $this->departmentservice = $departmentservice;
        $this->branch = auth('branch-api')->user(); // فرع عنده صلاحيات
        $this->employeeLogin = auth('employee-api')->user(); // موظف عنده صلاحيات
    }

    private function checkPermission(string $permission)
    {
        if ($this->branch instanceof Branch) {
            if (!$this->branch->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } elseif ($this->employeeLogin instanceof EmployeeLogin) {
            if (!$this->employeeLogin->can($permission)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة');
            }
        } else {
            abort(Response::HTTP_UNAUTHORIZED, 'يجب تسجيل الدخول');
        }
    }


    public function index($perPage = 10)
    {
        $permissionCheck = $this->checkPermission('Read-Departments');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $cities = $this->departmentservice->index($perPage);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الاقسام بنجاح',
            'data' => $cities
        ], Response::HTTP_OK);
    }


    // Store
    public function store(Request $request)
    {
        $permissionCheck = $this->checkPermission('Create-Department');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $idBranch = $branchUser  ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:45',
            'code' => ['required', 'string', 'max:20', Rule::unique('departments')->where(function ($query) use ($idBranch) {
                return $query->where('branch_id', $idBranch);
            }),],
            'description' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $data['branch_id'] = $idBranch;
        $department = $this->departmentservice->createDepartment($data);
        $data = [
            'id' => $department->id ?? '',
            'name' => $department->name ?? '',
            'code' => $department->code ?? '',
            'description' => $department->description ?? '',
            'active' => $department->active ? true : false ?? '',
            'created_at' => $department->created_at ? $department->created_at->format('d-m-Y') : '',
        ];
        return response()->json(['status' => true, 'message' => 'تم إنشاء القسم بنجاح', 'data' => $data], Response::HTTP_CREATED);
    }


    // Update
    public function update(Request $request, string $id)
    {
        $permissionCheck = $this->checkPermission('Update-Department');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $branchUser = auth('branch-api')->user();
        $employeeUser = auth('employee-api')->user();
        $idBranch = $branchUser  ? $branchUser->id : ($employeeUser ? $employeeUser->employee->branch->id : null);
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:45',
            'code' => ['required', 'string', 'max:20', Rule::unique('departments')->where(function ($query) use ($idBranch) {
                return $query->where('branch_id', $idBranch);
            })->ignore($id),],
            'description' => 'required|string',
            'active' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $department = $this->departmentservice->updateDepartment($id, $validator->validated());
        if (!$department) {
            return response()->json(['status' => false, 'message' => 'القسم غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $data = [
            'id' => $department->id ?? '',
            'name' => $department->name ?? '',
            'code' => $department->code ?? '',
            'description' => $department->description ?? '',
            'active' => $department->active ? true : false ?? '',
            'created_at' => $department->created_at ? $department->created_at->format('d-m-Y') : '',
        ];
        return response()->json([
            'status' => true,
            'message' => 'تم تعديل بيانات القسم بنجاح',
            'data' => $data
        ], Response::HTTP_OK);
    }

    // // Delete
    public function destroy(string $id)
    {
        $permissionCheck = $this->checkPermission('Delete-Department');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $deleted = $this->departmentservice->deleteDepartment($id);
        if (!$deleted) {
            return response()->json(['status' => false,  'message' => 'القسم غير موجود'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف القسم بنجاح'], Response::HTTP_OK);
    }
}
