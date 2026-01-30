<?php

namespace App\Http\Controllers\Api\Employees;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Models\Country;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLogin;
use App\Services\Employees\EmployeeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    protected $employeeService;
    protected ?BranchManager $branchManager;
    protected ?Branch $branch;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
        $this->branchManager = auth('branchManager-api')->user();
        $this->branch = auth('branch-api')->user();
    }

    private function checkPermission(string $permission)
    {
        if ($this->branchManager instanceof BranchManager) {
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

    public function getEmployees($perPage = 10)
    {
        $this->checkPermission('Read-Employees');
        $employees = $this->employeeService->getAllEmployees($perPage);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الموظفين بنجاح',
            'data' => $employees
        ], Response::HTTP_OK);
    }


    public function getEmployee($uuid)
    {
        $this->checkPermission('Create-Employee');
        $employees = $this->employeeService->getEmployee($uuid);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الموظف بنجاح',
            'data' => $employees
        ], Response::HTTP_OK);
    }

    public function getMyEmployees($perPage = 10)
    {
        $this->checkPermission('Read-Employees');
        $employees = $this->employeeService->getMyEmployees($perPage);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الموظفين بنجاح',
            'data' => $employees
        ], Response::HTTP_OK);
    }

    public function getBranches()
    {
        $this->checkPermission('Create-Employee');
        $branchManager = auth('branchManager-api')->user();
        $branches = $this->employeeService->getMyBranches($branchManager);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الفرع بنجاح',
            'data' => $branches
        ], Response::HTTP_OK);
    }

    public function getBranchesDepartment(Branch $branch)
    {
        $this->checkPermission('Create-Employee');
        $departments = $this->employeeService->getBranchesDepartment($branch);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الاقسام بنجاح',
            'data' => $departments
        ], Response::HTTP_OK);
    }

    public function getMyBranchesDepartment()
    {
        $this->checkPermission('Create-Employee');
        $departments = $this->employeeService->getMyBranchesDepartment();
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الاقسام بنجاح',
            'data' => $departments
        ], Response::HTTP_OK);
    }


    public function getBranchesJob(Department $department)
    {
        $this->checkPermission('Create-Employee');
        $Jobs = $this->employeeService->getBranchesJob($department);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الوظائف بنجاح',
            'data' => $Jobs
        ], Response::HTTP_OK);
    }


    public function getMyBranchesJob(Department $department)
    {
        $this->checkPermission('Create-Employee');
        $Jobs = $this->employeeService->getMyBranchesJob($department);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الوظائف بنجاح',
            'data' => $Jobs
        ], Response::HTTP_OK);
    }

    public function getCountries()
    {
        $this->checkPermission('Create-Employee');
        $countries = $this->employeeService->getCountries();
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الدول بنجاح',
            'data' => $countries
        ], Response::HTTP_OK);
    }

    public function getCitiesByCountry(Country $country)
    {
        $this->checkPermission('Create-Employee');
        $countries = $this->employeeService->getCitiesByCountry($country);
        return response()->json([
            'status' => true,
            'message' => 'تم جلب المدينة بنجاح',
            'data' => $countries
        ], Response::HTTP_OK);
    }

    public function getRoleBranch($branchId)
    {
        $this->checkPermission('Create-Employee');
        $roles = $this->employeeService->indexRoleBranch($branchId);
        return response()->json(['status' => true, 'message' => 'تم جلب الادوار الخاصة بك', 'data' => $roles], Response::HTTP_OK);
    }

    public function getMyRoleBranch()
    {
        //
        $this->checkPermission('Create-Employee');
        $roles = $this->employeeService->indexMyRoleBranch();
        return response()->json(['status' => true, 'message' => 'تم جلب الادوار الخاصة بك', 'data' => $roles], Response::HTTP_OK);
    }


    public function createEmployees(Request $request)
    {
        $this->checkPermission('Create-Employee');
        $validator = Validator::make($request->all(), [
            //***************البيانات الاساسية************** */
            'full_name'                => 'required|string|max:200',
            'branch_id'                 => 'required|numeric|exists:branches,id',
            'phone'                     => 'required|string|unique:employees,phone|max:20',
            'department_id'             => 'required|numeric|exists:departments,id',
            'country_id'                => 'required|numeric|exists:countries,id',
            'city_id'                   => 'required|numeric|exists:cities,id',
            'national_id'               => 'required|string|unique:employees,national_id|max:50',
            'branch_job_id'             => 'required|exists:branch_jobs,id',
            //***************بيانات تسجيل الدخول************** */
            'role_id'                   => 'required|numeric|exists:roles,id',
            'username'                  => 'required|string|max:50|unique:employee_logins,username',
            'email'                     => 'required|email|unique:employee_logins,email|max:100',
            'mobile'                    => 'required|string|unique:employee_logins,mobile|max:20',
            //********************* بيانات التوظيف ***************************
            'salary'                    => 'required|numeric|min:0',                       // الراتب
            'hire_date'                 => 'required|date|after_or_equal:today', // تاريخ التعيين من اليوم فصاعداً
            'commission'                => 'required|numeric|min:0',                       // العمولة
            'contract_end_date'         => 'required|date|after_or_equal:hire_date',       // تاريخ إنهاء الخدمة
            'fingerprint_device_number' => 'required|string|max:50',                  // رقم جهاز البصمة
            'work_from'                 => 'required|date_format:H:i',                    // العمل من
            'work_to'                   => 'required|date_format:H:i|after:work_from',   // العمل الى
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $employee = $this->employeeService->createEmployee($data);
        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الموظف بنجاح',
            'data' => $this->employeeService->formatEmployee($employee)

        ], Response::HTTP_CREATED);
    }


    public function createMyEmployees(Request $request)
    {
        $this->checkPermission('Create-Employee');
        $validator = Validator::make($request->all(), [
            //***************البيانات الاساسية************** */
            'full_name'                => 'required|string|max:200',
            'phone'                     => 'required|string|unique:employees,phone|max:20',
            'department_id'             => 'required|numeric|exists:departments,id',
            'country_id'                => 'required|numeric|exists:countries,id',
            'city_id'                   => 'required|numeric|exists:cities,id',
            'national_id'               => 'required|string|unique:employees,national_id|max:50',
            'branch_job_id'             => 'required|exists:branch_jobs,id',
            //***************بيانات تسجيل الدخول************** */
            'role_id'                   => 'required|numeric|exists:roles,id',
            'username'                  => 'required|string|max:50|unique:employee_logins,username',
            'email'                     => 'required|email|unique:employee_logins,email|max:100',
            'mobile'                    => 'required|string|unique:employee_logins,mobile|max:20',
            //********************* بيانات التوظيف ***************************
            'salary'                    => 'required|numeric|min:0',                       // الراتب
            'hire_date'                 => 'required|date|after_or_equal:today', // تاريخ التعيين من اليوم فصاعداً
            'commission'                => 'required|numeric|min:0',                       // العمولة
            'contract_end_date'         => 'required|date|after_or_equal:hire_date',       // تاريخ إنهاء الخدمة
            'fingerprint_device_number' => 'required|string|max:50',                  // رقم جهاز البصمة
            'work_from'                 => 'required|date_format:H:i',                    // العمل من
            'work_to'                   => 'required|date_format:H:i|after:work_from',   // العمل الى
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $data = $validator->validated();
        $employee = $this->employeeService->createMyEmployee($data);
        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الموظف بنجاح',
            'data' => $this->employeeService->formatEmployee($employee)

        ], Response::HTTP_CREATED);
    }


    public function showVerifyEmail(string $uuid)
    {
        $employee = Employee::where('uuid', $uuid)->first();
        if (! $employee) {
            return abort(Response::HTTP_BAD_REQUEST, ' الفرع غير موجود.');
        }
        return view('Employee.activate', ['employee' => $employee]);
    }



    public function verifyEmail(Request $request, string $uuid)
    {
        $employee = Employee::where('uuid', $uuid)->first();
        if (! $employee) {
            return response()->json(['status' => false,  'message' => 'الفرع غير موجود.'], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 400);
        }
        try {
            $employeeLogin = EmployeeLogin::where('employee_id', $employee->id)->firstOrFail();
            $this->employeeService->verifyEmail(['otp' => $request->otp], $employeeLogin);
            return response()->view('Employee.activation_success');
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function updateMyEmployee(Request $request, $uuid)
    {
        $this->checkPermission('Update-Employee');
        $employee = Employee::where('uuid', '=', $uuid)->first(); // جلب الموظف الحالي
        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], Response::HTTP_BAD_REQUEST);
        }
        $validator = Validator::make($request->all(), [
            //*************** البيانات الأساسية **************//
            'full_name'    => 'required|string|max:200',
            'phone'        => 'required|string|max:20|unique:employees,phone,' . $employee->id,
            'department_id' => 'required|numeric|exists:departments,id',
            'country_id'   => 'required|numeric|exists:countries,id',
            'city_id'      => 'required|numeric|exists:cities,id',
            'national_id'  => 'required|string|max:50|unique:employees,national_id,' . $employee->id,
            'branch_job_id' => 'required|exists:branch_jobs,id',

            //*************** بيانات تسجيل الدخول **************//
            'role_id'                   => 'required|numeric|exists:roles,id',
            'username' => 'required|string|max:50|unique:employee_logins,username,' . $employee->login->id,
            'email'    => 'required|email|max:100|unique:employee_logins,email,' . $employee->login->id,
            'mobile'   => 'required|string|max:20|unique:employee_logins,mobile,' . $employee->login->id,

            //*************** بيانات التوظيف **************//
            'salary'                    => 'required|numeric|min:0',
            'hire_date'                 => 'required|date|after_or_equal:today',
            'commission'                => 'required|numeric|min:0',
            'contract_end_date'         => 'required|date|after_or_equal:hire_date',
            'fingerprint_device_number' => 'required|string|max:50',
            'work_from'                 => 'required|date_format:H:i',
            'work_to'                   => 'required|date_format:H:i|after:work_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();
        $employee = $this->employeeService->updateMyEmployee($employee, $data);

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث بيانات الموظف بنجاح',
            'data'    => $this->employeeService->formatEmployee($employee)
        ], Response::HTTP_OK);
    }


    public function updateEmployee(Request $request, $uuid)
    {
        $this->checkPermission('Update-Employee');
        $employee = Employee::where('uuid', '=', $uuid)->first(); // جلب الموظف الحالي
        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], Response::HTTP_BAD_REQUEST);
        }
        $validator = Validator::make($request->all(), [
            //*************** البيانات الأساسية **************//
            'full_name'    => 'required|string|max:200',
            'branch_id'    => 'required|numeric|exists:branches,id',
            'phone'        => 'required|string|max:20|unique:employees,phone,' . $employee->id,
            'department_id' => 'required|numeric|exists:departments,id',
            'country_id'   => 'required|numeric|exists:countries,id',
            'city_id'      => 'required|numeric|exists:cities,id',
            'national_id'  => 'required|string|max:50|unique:employees,national_id,' . $employee->id,
            'branch_job_id' => 'required|exists:branch_jobs,id',

            //*************** بيانات تسجيل الدخول **************//
            'role_id'                   => 'required|numeric|exists:roles,id',
            'username' => 'required|string|max:50|unique:employee_logins,username,' . $employee->login->id,
            'email'    => 'required|email|max:100|unique:employee_logins,email,' . $employee->login->id,
            'mobile'   => 'required|string|max:20|unique:employee_logins,mobile,' . $employee->login->id,

            //*************** بيانات التوظيف **************//
            'salary'                    => 'required|numeric|min:0',
            'hire_date'                 => 'required|date|after_or_equal:today',
            'commission'                => 'required|numeric|min:0',
            'contract_end_date'         => 'required|date|after_or_equal:hire_date',
            'fingerprint_device_number' => 'required|string|max:50',
            'work_from'                 => 'required|date_format:H:i',
            'work_to'                   => 'required|date_format:H:i|after:work_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = $validator->validated();

        $employee = $this->employeeService->updateEmployee($employee, $data);

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث بيانات الموظف بنجاح',
            'data'    => $this->employeeService->formatEmployee($employee)
        ], Response::HTTP_OK);
    }

    public function deleteEmployee(Request $request, string $uuid)
    {
        $this->checkPermission('Delete-Employee');
        $employee = Employee::where('uuid', '=', $uuid)->first(); // جلب الموظف الحالي
        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], Response::HTTP_BAD_REQUEST);
        }
        $employee = $this->employeeService->deleteEmployee($employee);
        return response()->json([
            'status'  => true,
            'message' => 'تم حذف الموظف بنجاح',
        ], Response::HTTP_OK);
    }

    public function deleteMyEmployee(Request $request, string $uuid)
    {
        $this->checkPermission('Delete-Employee');
        $employee = Employee::where('uuid', '=', $uuid)->first(); // جلب الموظف الحالي
        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], Response::HTTP_BAD_REQUEST);
        }
        $employee = $this->employeeService->deleteEmployee($employee);
        return response()->json([
            'status'  => true,
            'message' => 'تم حذف الموظف بنجاح',
        ], Response::HTTP_OK);
    }

    public function getDeletedEmployees()
    {
        $this->checkPermission('Read-DeletedEmployees');
        $deletedEmployees = $this->employeeService->getDeletedEmployees();
        return response()->json(['status' => true, 'message' => 'تم عرض جميع الموظفين المحذوفين', 'data'   => $deletedEmployees]);
    }

    public function getDeletedMyEmployees()
    {
        $this->checkPermission('Read-DeletedEmployees');
        $deletedEmployees = $this->employeeService->getDeletedMyEmployees();
        return response()->json(['status' => true, 'message' => 'تم عرض جميع الموظفين المحذوفين', 'data'   => $deletedEmployees]);
    }

    public function restoreEmployee(string $uuid)
    {
        $this->checkPermission('Restore-Employee');

        $employee = Employee::onlyTrashed()->where('uuid', '=', $uuid)->first(); // جلب الموظف الحالي
        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], Response::HTTP_BAD_REQUEST);
        }
        $employee =  $this->employeeService->restoreEmployee($employee);
        return response()->json(['status' => true, 'message' => 'تم استرجاع الموظف وجميع بياناته بنجاح.', 'data' => $employee], Response::HTTP_OK);
    }

    public function restoreMyEmployee(string $uuid)
    {
        $this->checkPermission('Restore-Employee');
        $employee = Employee::onlyTrashed()->where('uuid', '=', $uuid)->first(); // جلب الموظف الحالي
        if (!$employee) {
            return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], Response::HTTP_BAD_REQUEST);
        }
        $employee =  $this->employeeService->restoreEmployee($employee);
        return response()->json(['status' => true, 'message' => 'تم استرجاع الموظف وجميع بياناته بنجاح.', 'data' => $employee], Response::HTTP_OK);
    }


    public function forceDeleteEmployee(string $uuid)
    {

        $this->checkPermission('Force-Employee');
        $employee = Employee::withTrashed()->where('uuid', $uuid)->first();
        if (!$employee) {
            return response()->json(['status' => false,  'message' => 'الموظف غير موجود'], Response::HTTP_BAD_REQUEST);
        }
        $this->employeeService->forceDeleteEmployee($employee);
        return response()->json([
            'status' => true,
            'message' => 'تم حذف الموظف وجميع بياناته نهائيًا بنجاح.'
        ], Response::HTTP_OK);
    }

    public function forceDeleteMyEmployee(string $uuid)
    {
        $this->checkPermission('Force-Employee');
        $employee = Employee::withTrashed()->where('uuid', $uuid)->first();
        if (!$employee) {
            return response()->json(['status' => false,  'message' => 'الموظف غير موجود'], Response::HTTP_BAD_REQUEST);
        }
        $this->employeeService->forceDeleteEmployee($employee);
        return response()->json([
            'status' => true,
            'message' => 'تم حذف الموظف وجميع بياناته نهائيًا بنجاح.'
        ], Response::HTTP_OK);
    }


    public function blockEmployee(string $uuid)
    {

        $this->checkPermission('Blocked-Employee');
        $employee = Employee::where('uuid', $uuid)->first();
        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'الموظف غير موجود'
            ], Response::HTTP_BAD_REQUEST);
        }
        $message = $this->employeeService->toggleBlockEmployee($employee);
        return response()->json(['status' => true, 'message' => $message], Response::HTTP_OK);
    }

    public function blockMyEmployee(string $uuid)
    {
        $this->checkPermission('Blocked-Employee');
        $employee = Employee::where('uuid', $uuid)->first();
        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'الموظف غير موجود'
            ], Response::HTTP_BAD_REQUEST);
        }
        $message = $this->employeeService->toggleBlockEmployee($employee);
        return response()->json(['status' => true, 'message' => $message], Response::HTTP_OK);
    }
}
