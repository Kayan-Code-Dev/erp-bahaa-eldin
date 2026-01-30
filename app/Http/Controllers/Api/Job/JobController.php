<?php

namespace App\Http\Controllers\Api\Job;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmployeeLogin;
use App\Services\Job\JobService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class JobController extends Controller
{
    //
    protected JobService $jobervice;
    protected ?Branch $branch;
    protected ?EmployeeLogin $employeeLogin;

    public function __construct(JobService $jobervice)
    {
        $this->jobervice = $jobervice;
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
        $permissionCheck = $this->checkPermission('Read-BranchJobs');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $job = $this->jobervice->index($perPage);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الوظائف بنجاح',
            'data' => $job
        ], Response::HTTP_OK);
    }

    public function getDepartment()
    {
        $permissionCheck = $this->checkPermission('Create-BranchJob');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $departments = $this->jobervice->getDepartment()->map(function ($department) {
            return [
                'id' => $department->id,
                'name' => $department->name,
            ];
        });
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الاقسام بنجاح',
            'data' => $departments
        ], Response::HTTP_OK);
    }

    // Store
    public function store(Request $request)
    {
        $permissionCheck = $this->checkPermission('Create-BranchJob');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $idBranch = $this->branch ? $this->branch->id : ($this->employeeLogin->employee->branch->id ?? null);
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|integer|exists:departments,id',
            'name' => 'required|string|max:45',
            'code' => ['required', 'string', 'max:20', Rule::unique('branch_jobs')->where(function ($query) use ($idBranch) {
                return $query->where('branch_id', $idBranch);
            }),],
            'description' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $data['branch_id'] = $idBranch;
        $job = $this->jobervice->createJob($data);
        $data = [
            'id' => $job->id ?? '',
            'department' => $job->department->name ?? '',
            'name' => $job->name ?? '',
            'code' => $job->code ?? '',
            'description' => $job->description ?? '',
            'active' => true,
            'created_at' => $job->created_at ? $job->created_at->format('d-m-Y') : '',
        ];
        return response()->json(['status' => true, 'message' => 'تم إنشاء الوظيفة بنجاح', 'data' => $data], Response::HTTP_CREATED);
    }


    // Update
    public function update(Request $request, string $id)
    {
        $permissionCheck = $this->checkPermission('Update-BranchJob');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|integer|exists:departments,id',
            'name' => 'required|string|max:45',
            'code' => ['required', 'string', 'max:20', Rule::unique('branch_jobs')->where(function ($query) use ($id) {
                return $query->where('branch_id', $id);
            })->ignore($id)],
            'description' => 'required|string',
            'active' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $job = $this->jobervice->updateJob($id, $validator->validated());
        if (!$job) {
            return response()->json(['status' => false, 'message' => 'الوظيفة غير موجود'], Response::HTTP_NOT_FOUND);
        }
        $data = [
            'id' => $job->id ?? '',
            'department' => $job->department->name ?? '',
            'name' => $job->name ?? '',
            'code' => $job->code ?? '',
            'description' => $job->description ?? '',
            'active' => $job->active,
            'created_at' => $job->created_at ? $job->created_at->format('d-m-Y') : '',
        ];
        return response()->json([
            'status' => true,
            'message' => 'تم تعديل بيانات الوظيفة بنجاح',
            'data' => $data
        ], Response::HTTP_OK);
    }

    // // Delete
    public function destroy(string $id)
    {
        $permissionCheck = $this->checkPermission('Delete-BranchJob');
        if ($permissionCheck instanceof JsonResponse) {
            return $permissionCheck;
        }
        $deleted = $this->jobervice->deleteJob($id);
        if (!$deleted) {
            return response()->json(['status' => false,  'message' => 'الوظيفة غير موجود'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['status' => true, 'message' => 'تم حذف الوظيفة بنجاح'], Response::HTTP_OK);
    }
}
