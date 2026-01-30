<?php

namespace App\Services\Employees;

use App\Helpers\OtpGenerator;
use App\Mail\EmployeePasswordMail;
use App\Mail\WelcomeEmployeeMail;
use App\Models\Branch;
use App\Models\BranchJob;
use App\Models\BranchManager;
use App\Models\City;
use App\Models\Country;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContact;
use App\Models\EmployeeEducation;
use App\Models\EmployeeInfo;
use App\Models\EmployeeJob;
use App\Models\EmployeeLogin;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class EmployeeService
{
    /**
     * جلب كل الموظفين مع pagination
     */
    public function getAllEmployees($perPage = 10)
    {
        $mangerId = auth('branchManager-api')->user()->id;
        $myBranches = Branch::where('branch_manager_id', '=', $mangerId)->get()->pluck('id');
        $employees = Employee::whereIn('branch_id', $myBranches)->paginate($perPage);
        $mapped = $employees->getCollection()->map(fn($employee) => $this->formatEmployee($employee));
        return [
            'data' => $mapped,
            'current_page' => $employees->currentPage(),
            'next_page_url' => $employees->nextPageUrl(),
            'prev_page_url' => $employees->previousPageUrl(),
            'total' => $employees->total(),
        ];
    }


    public function getEmployee($uuid)
    {
        //
        $employee = Employee::where('uuid', $uuid)->first();
        if (!$employee) {
            return null;
        }
        $data = [
            'uuid' => $employee->uuid,
            'full_name' => $employee->full_name,
            'branch_id' => $employee->branch_id,
            'phone' => $employee->phone,
            'department_id' => $employee->department_id,
            'country_id' => $employee->city->country->id,
            'city_id' => $employee->city_id,
            'national_id' => $employee->national_id,
            'branch_job_id' => $employee->branch_job_id,
            // ********************* بيانات تسجيل الدخول (من employee_logins) ***************************
            'role_id' => optional(optional($employee->login)->roles)->first()->id ?? null,
            'username' => optional($employee->login)->username,
            'email' => optional($employee->login)->email,
            'mobile' => optional($employee->login)->mobile,
            // ********************* بيانات التوظيف (من employee_jobs) ***************************
            'salary' => optional($employee->job)->salary,
            'hire_date' => optional($employee->job)->hire_date,
            'commission' => optional($employee->job)->commission,
            'contract_end_date' => optional($employee->job)->contract_end_date,
            'fingerprint_device_number' => optional($employee->job)->fingerprint_device_number,
            'work_from' => optional($employee->job)->work_from,
            'work_to' => optional($employee->job)->work_to,
        ];
        return $data;
    }


    public function getMyEmployees($perPage = 10)
    {
        $myBranches = Branch::where('id', '=', auth('branch-api')->user()->id)->get()->pluck('id');
        $employees = Employee::whereIn('branch_id', $myBranches)->paginate($perPage);
        $mapped = $employees->getCollection()->map(fn($employee) => $this->formatEmployee($employee));
        return [
            'data' => $mapped,
            'current_page' => $employees->currentPage(),
            'next_page_url' => $employees->nextPageUrl(),
            'prev_page_url' => $employees->previousPageUrl(),
            'total' => $employees->total(),
        ];
    }

    //************************************** */
    public function getMyBranches(BranchManager $branchManager)
    {
        $branches = Branch::query()->where('branch_manager_id', $branchManager->id)->get();
        return $branches->map(function ($branch) {
            return [
                'id' => $branch->id,
                'name' => $branch->name
            ];
        });
    }
    //************************************** */

    public function getBranchesDepartment(Branch $branch)
    {
        $departments = Department::query()->where('branch_id', $branch->id)->get();
        return $departments->map(function ($branch) {
            return [
                'id' => $branch->id,
                'name' => $branch->name
            ];
        });
    }


    public function getMyBranchesDepartment()
    {
        $departments = Department::query()->where('branch_id', auth('branch-api')->user()->id)->get();
        return $departments->map(function ($branch) {
            return [
                'id' => $branch->id,
                'name' => $branch->name
            ];
        });
    }
    //************************************** */


    public function getBranchesJob(Department $department)
    {
        $branchJobs = BranchJob::query()->where('department_id', $department->id)->get();
        return $branchJobs->map(function ($branch) {
            return [
                'id' => $branch->id,
                'name' => $branch->name
            ];
        });
    }


    public function getMyBranchesJob(Department $department)
    {
        $branchJobs = BranchJob::query()->where('department_id', $department->id)->get();
        return $branchJobs->map(function ($branch) {
            return [
                'id' => $branch->id,
                'name' => $branch->name
            ];
        });
    }
    //************************************** */


    public function getCountries()
    {
        return Country::where('active', '=', true)->get(['id', 'name']);
    }


    public function getCitiesByCountry(Country $country)
    {
        return City::where('active', '=', true)->where('country_id', '=', $country->id)->get(['id', 'name']);
    }

    public function createMyEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            //*************** البيانات الأساسية **************//
            $employee = Employee::create([
                'full_name'     => $data['full_name'],
                'branch_id'     => auth('branch-api')->user()->id,
                'phone'         => $data['phone'],
                'department_id' => $data['department_id'],
                'city_id'       => $data['city_id'],
                'national_id'   => $data['national_id'],
                'branch_job_id' => $data['branch_job_id'] ?? null,
                'ip_address'    => request()->ip(),
            ]);
            //*************** بيانات تسجيل الدخول **************//
            $otp = OtpGenerator::generateNumeric(6);
            $employeeLogin = EmployeeLogin::create([
                'employee_id'      => $employee->id,
                'username'         => $data['username'],
                'email'            => $data['email'],
                'mobile'           => $data['mobile'],
                'otp_code'         => Hash::make($otp),
                'code_expires_at'  => now()->addMinutes(3),
            ]);
            $this->giveTheRoleoEmployee($employeeLogin, $data['role_id']);
            $this->sendWelcomeMessage($employeeLogin, $otp);
            //*************** بيانات الوظيفة **************//
            EmployeeJob::create([
                'employee_id'            => $employee->id,
                'salary'                 => $data['salary'] ?? null,
                'hire_date'              => $data['hire_date'] ?? null,
                'commission'             => $data['commission'] ?? null,
                'contract_end_date'      => $data['contract_end_date'] ?? null,
                'fingerprint_device_number' => $data['fingerprint_device_number'] ?? null,
                'work_from'              => $data['work_from'] ?? null,
                'work_to'                => $data['work_to'] ?? null,
            ]);
            return $employee;
        });
    }

    public function createEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            //*************** البيانات الأساسية **************//
            $employee = Employee::create([
                'full_name'     => $data['full_name'],
                'branch_id'     => $data['branch_id'],
                'phone'         => $data['phone'],
                'department_id' => $data['department_id'],
                'city_id'       => $data['city_id'],
                'national_id'   => $data['national_id'],
                'branch_job_id' => $data['branch_job_id'] ?? null,
                'ip_address'    => request()->ip(),
            ]);
            //*************** بيانات تسجيل الدخول **************//
            $otp = OtpGenerator::generateNumeric(6);
            $employeeLogin = EmployeeLogin::create([
                'employee_id'      => $employee->id,
                'username'         => $data['username'],
                'email'            => $data['email'],
                'mobile'           => $data['mobile'],
                'otp_code'         => Hash::make($otp),
                'code_expires_at'  => now()->addMinutes(3),
            ]);
            $this->giveTheRoleoEmployee($employeeLogin, $data['role_id']);
            $this->sendWelcomeMessage($employeeLogin, $otp);
            //*************** بيانات الوظيفة **************//
            EmployeeJob::create([
                'employee_id'            => $employee->id,
                'salary'                 => $data['salary'] ?? null,
                'hire_date'              => $data['hire_date'] ?? null,
                'commission'             => $data['commission'] ?? null,
                'contract_end_date'      => $data['contract_end_date'] ?? null,
                'fingerprint_device_number' => $data['fingerprint_device_number'] ?? null,
                'work_from'              => $data['work_from'] ?? null,
                'work_to'                => $data['work_to'] ?? null,
            ]);
            return $employee;
        });
    }

    public function updateEmployee(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            //*************** البيانات الأساسية **************//
            $employee->update([
                'full_name'     => $data['full_name'],
                'branch_id'     => $data['branch_id'],
                'phone'         => $data['phone'],
                'department_id' => $data['department_id'],
                'city_id'       => $data['city_id'],
                'national_id'   => $data['national_id'],
                'branch_job_id' => $data['branch_job_id'] ?? $employee->branch_job_id,
            ]);

            //*************** بيانات تسجيل الدخول **************//
            $employeeLogin = $employee->login;
            $employeeLogin->update([
                'username' => $data['username'],
                'email'    => $data['email'],
                'mobile'   => $data['mobile'],
            ]);
            $this->giveTheRoleoEmployee($employeeLogin, $data['role_id']);

            //*************** بيانات الوظيفة **************//
            $employeeJob = $employee->job;
            $employeeJob->update([
                'salary'                   => $data['salary'] ?? $employeeJob->salary,
                'hire_date'                => $data['hire_date'] ?? $employeeJob->hire_date,
                'commission'               => $data['commission'] ?? $employeeJob->commission,
                'contract_end_date'        => $data['contract_end_date'] ?? $employeeJob->contract_end_date,
                'fingerprint_device_number' => $data['fingerprint_device_number'] ?? $employeeJob->fingerprint_device_number,
                'work_from'                => $data['work_from'] ?? $employeeJob->work_from,
                'work_to'                  => $data['work_to'] ?? $employeeJob->work_to,
            ]);
            return $employee;
        });
    }

    public function updateMyEmployee(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            //*************** البيانات الأساسية **************//
            $employee->update([
                'full_name'     => $data['full_name'],
                'branch_id'     => auth('branch-api')->user()->id,
                'phone'         => $data['phone'],
                'department_id' => $data['department_id'],
                'city_id'       => $data['city_id'],
                'national_id'   => $data['national_id'],
                'branch_job_id' => $data['branch_job_id'] ?? $employee->branch_job_id,
            ]);

            //*************** بيانات تسجيل الدخول **************//
            $employeeLogin = $employee->login;
            $employeeLogin->update([
                'username' => $data['username'],
                'email'    => $data['email'],
                'mobile'   => $data['mobile'],
            ]);
            $this->giveTheRoleoEmployee($employeeLogin, $data['role_id']);

            //*************** بيانات الوظيفة **************//
            $employeeJob = $employee->job;
            $employeeJob->update([
                'salary'                   => $data['salary'] ?? $employeeJob->salary,
                'hire_date'                => $data['hire_date'] ?? $employeeJob->hire_date,
                'commission'               => $data['commission'] ?? $employeeJob->commission,
                'contract_end_date'        => $data['contract_end_date'] ?? $employeeJob->contract_end_date,
                'fingerprint_device_number' => $data['fingerprint_device_number'] ?? $employeeJob->fingerprint_device_number,
                'work_from'                => $data['work_from'] ?? $employeeJob->work_from,
                'work_to'                  => $data['work_to'] ?? $employeeJob->work_to,
            ]);
            return $employee;
        });
    }

    public function deleteEmployee(Employee $employee): bool
    {
        return DB::transaction(function () use ($employee) {
            if ($employee->login) {
                $employee->login->delete();
            }
            if ($employee->job) {
                $employee->job->delete(); // soft delete
            }
            if ($employee->contact) {
                $employee->contact->delete(); // soft delete
            }
            if ($employee->educations()->exists()) {
                $employee->educations()->delete(); // soft delete
            }
            return $employee->delete();
        });
    }

    public function getDeletedEmployees($perPage = 10)
    {
        $mangerId = auth('branchManager-api')->user()->id;
        $myBranches = Branch::where('branch_manager_id', '=', $mangerId)->get()->pluck('id');
        $employees = Employee::onlyTrashed()
            ->with(['login' => fn($q) => $q->withTrashed(), 'job' => fn($q) => $q->withTrashed(), 'contact', 'educations'])->whereIn('branch_id', $myBranches)
            ->paginate($perPage);
        $mapped = $employees->getCollection()->map(fn($employee) => $this->formatEmployee($employee));
        $employees->setCollection($mapped);
        return [
            'data' => $employees->items(),
            'current_page' => $employees->currentPage(),
            'next_page_url' => $employees->nextPageUrl(),
            'prev_page_url' => $employees->previousPageUrl(),
            'total' => $employees->total(),
        ];
    }

    public function getDeletedMyEmployees($perPage = 10)
    {
        $employees = Employee::onlyTrashed()
            ->with(['login' => fn($q) => $q->withTrashed(), 'job' => fn($q) => $q->withTrashed(), 'contact', 'educations'])->where('branch_id', auth('branch-api')->user()->id)
            ->paginate($perPage);
        $mapped = $employees->getCollection()->map(fn($employee) => $this->formatEmployee($employee));
        $employees->setCollection($mapped);
        return [
            'data' => $employees->items(),
            'current_page' => $employees->currentPage(),
            'next_page_url' => $employees->nextPageUrl(),
            'prev_page_url' => $employees->previousPageUrl(),
            'total' => $employees->total(),
        ];
    }




    public function restoreEmployee($employee)
    {

        DB::transaction(function () use ($employee) {
            // استرجاع الموظف الأساسي
            $employee->restore();
            // استرجاع بيانات تسجيل الدخول
            if ($employee->login()->withTrashed()->exists()) {
                $employee->login()->withTrashed()->restore();
            }
            // استرجاع بيانات الوظيفة
            if ($employee->job()->withTrashed()->exists()) {
                $employee->job()->withTrashed()->restore();
            }
            // استرجاع بيانات التواصل
            if ($employee->contact()->withTrashed()->exists()) {
                $employee->contact()->withTrashed()->restore();
            }
            // استرجاع بيانات الشهادات
            if ($employee->educations()->withTrashed()->exists()) {
                $employee->educations()->withTrashed()->restore();
            }
        });
        return $this->formatEmployee($employee);
    }


    public function forceDeleteEmployee(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            EmployeeLogin::withTrashed()->where('employee_id', $employee->id)->forceDelete();
            EmployeeJob::withTrashed()->where('employee_id', $employee->id)->forceDelete();
            EmployeeContact::withTrashed()->where('employee_id', $employee->id)->forceDelete();
            EmployeeEducation::withTrashed()->where('employee_id', $employee->id)->forceDelete();
            $employee->forceDelete();
        });
    }


    public function toggleBlockEmployee(Employee $employee): string
    {
        return DB::transaction(function () use ($employee) {
            if ($employee->status === 'terminated') {
                $employee->status = 'active';
                $blocked = false;
                $message = 'تم تفعيل الموظف بنجاح.';
            } else {
                $employee->status = 'terminated';
                $blocked = true;
                $message = 'تم حظر الموظف بنجاح.';
            }
            $employee->save();
            EmployeeLogin::where('employee_id', $employee->id)->update([
                'blocked' => $blocked
            ]);
            return $message;
        });
    }

    public function indexRoleBranch($branch)
    {
        return Role::where('branch_id', $branch)->where('guard_name', '=', 'employee-api')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
                'created_at' => Carbon::parse($role->created_at)->format('Y-m-d'),
            ];
        });
    }

    public function indexMyRoleBranch()
    {
        return Role::where('branch_id', auth('branch-api')->user()->id)->where('guard_name', '=', 'employee-api')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
            ];
        });
    }



    public function verifyEmail(array $data, EmployeeLogin $employeeLogin): void
    {
        if ($employeeLogin->code_expires_at && now()->greaterThan($employeeLogin->code_expires_at)) {
            throw new \Exception('رمز التفعيل منتهي الصلاحية.');
        }
        if (! Hash::check($data['otp'], $employeeLogin->otp_code)) {
            throw new \Exception('رمز التفعيل غير صحيح.');
        }
        $employee = $employeeLogin->employee;
        $employee->status = 'active';
        $employee->save();
        // إعادة تعيين الرمز وكلمة المرور
        $employeeLogin->otp_code = null;
        $employeeLogin->code_expires_at = null;
        $password = OtpGenerator::generateAlphanumeric(6);
        $employeeLogin->password = Hash::make($password);
        $employeeLogin->save();
        try {
            $loginUrl = env('APP_URL_LOGIN');
            Mail::to($employeeLogin->email)->send(
                new EmployeePasswordMail($employeeLogin, $password, $loginUrl)
            );
        } catch (\Exception $e) {
            Log::error("خطأ عند إرسال البريد للموظف {$employeeLogin->id}: " . $e->getMessage());
        }
    }


    protected function sendWelcomeMessage(EmployeeLogin $employeeLogin, string $otp): void
    {
        $activationUrl = url("/cms/employees/activate/{$employeeLogin->employee->uuid}?otp={$otp}");
        Mail::to($employeeLogin->email)->send(new WelcomeEmployeeMail($employeeLogin, $otp, $activationUrl));
    }

    protected function giveTheRoleoEmployee(EmployeeLogin $employeeLogin, $roleId)
    {
        $employeeLogin->assignRole(Role::findOrFail($roleId));
    }

    public function formatEmployee(Employee $employee): array
    {
        return [
            'uuid' => $employee->uuid ?? '',
            'full_name' => $employee->login->username ?? '',
            'branch_name' => $employee->branch->name ?? '',
            'Job' => $employee->branchJob->name ?? '',
            'tel' => $employee->phone ?? '',
            'email' => $employee->login->email ?? '',
            'employment_history' => $employee->job->hire_date ?? '',
            'status' => $employee->status ?? '',
            'blocked' => $employee->login->blocked ?? '',
        ];
    }
}
