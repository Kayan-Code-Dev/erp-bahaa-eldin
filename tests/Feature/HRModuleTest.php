<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeCustody;
use App\Models\EmployeeDocument;
use App\Models\Deduction;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Address;
use App\Models\Cashbox;
use App\Models\Role;
use App\Models\Permission;
use App\Services\PayrollService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HRModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrManager;
    protected Branch $branch;
    protected Department $department;
    protected JobTitle $jobTitle;
    protected Employee $employee;
    protected Cashbox $cashbox;

    protected function setUp(): void
    {
        parent::setUp();

        // Create city if it doesn't exist
        $city = \App\Models\City::create([
            'name' => 'Test City',
            'country_id' => \App\Models\Country::create(['name' => 'Test Country', 'code' => 'TC'])->id,
        ]);

        // Create address for branch
        $address = Address::create([
            'street' => '123 Test St',
            'building' => 'Building 1',
            'city_id' => $city->id,
        ]);

        // Create branch
        $this->branch = Branch::create([
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
            'address_id' => $address->id,
        ]);

        // Refresh to get the cashbox relationship
        $this->branch->refresh();

        // Cashbox is auto-created with branch, but if not, create it manually
        if (!$this->branch->cashbox) {
            $this->cashbox = Cashbox::create([
                'name' => "{$this->branch->name} Cashbox",
                'branch_id' => $this->branch->id,
                'initial_balance' => 100000,
                'current_balance' => 100000,
                'is_active' => true,
            ]);
        } else {
            $this->cashbox = $this->branch->cashbox;
            $this->cashbox->update(['current_balance' => 100000, 'is_active' => true]);
        }

        // Create super admin
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => User::SUPER_ADMIN_EMAIL,
            'password' => bcrypt('password'),
        ]);

        // Create HR manager role and user
        $hrRole = Role::create(['name' => 'hr_manager', 'description' => 'HR Manager']);

        // Create necessary permissions
        $permissions = [
            'hr.departments.view', 'hr.departments.manage',
            'hr.job-titles.view', 'hr.job-titles.manage',
            'hr.employees.view', 'hr.employees.create', 'hr.employees.update', 'hr.employees.delete',
            'hr.employees.manage-branches', 'hr.employees.terminate',
            'hr.attendance.view', 'hr.attendance.manage', 'hr.attendance.check-in', 'hr.attendance.reports',
            'hr.custody.view', 'hr.custody.assign', 'hr.custody.return',
            'hr.documents.view', 'hr.documents.upload', 'hr.documents.verify', 'hr.documents.delete',
            'hr.deductions.view', 'hr.deductions.create', 'hr.deductions.approve',
            'hr.payroll.view', 'hr.payroll.generate', 'hr.payroll.approve', 'hr.payroll.pay',
            'hr.activity-log.view',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreateByName($perm);
        }

        foreach ($permissions as $perm) {
            $hrRole->assignPermission($perm);
        }

        $this->hrManager = User::create([
            'name' => 'HR Manager',
            'email' => 'hr@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->hrManager->assignRole($hrRole);

        // Create department
        $this->department = Department::create([
            'code' => 'IT',
            'name' => 'Information Technology',
            'description' => 'IT Department',
            'is_active' => true,
        ]);

        // Create job title
        $this->jobTitle = JobTitle::create([
            'code' => 'DEV',
            'name' => 'Software Developer',
            'department_id' => $this->department->id,
            'level' => JobTitle::LEVEL_EMPLOYEE,
            'min_salary' => 3000,
            'max_salary' => 8000,
            'is_active' => true,
        ]);

        // Create employee user
        $employeeUser = User::create([
            'name' => 'John Employee',
            'email' => 'john@test.com',
            'password' => bcrypt('password'),
        ]);

        // Create employee
        $this->employee = Employee::create([
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP00001',
            'department_id' => $this->department->id,
            'job_title_id' => $this->jobTitle->id,
            'employment_type' => Employee::TYPE_FULL_TIME,
            'employment_status' => Employee::STATUS_ACTIVE,
            'hire_date' => now()->subMonths(6),
            'base_salary' => 5000,
            'transport_allowance' => 500,
            'housing_allowance' => 1000,
            'other_allowances' => 200,
            'overtime_rate' => 1.5,
            'commission_rate' => 2,
            'annual_vacation_days' => 21,
            'vacation_days_balance' => 21,
            'work_hours_per_day' => 8,
        ]);

        // Assign employee to branch
        $this->employee->branches()->attach($this->branch->id, [
            'is_primary' => true,
            'assigned_at' => now(),
        ]);

        Storage::fake('local');
    }

    // ==================== Department Tests ====================

    public function test_can_list_departments()
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/departments');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'code', 'name']]]);
    }

    public function test_can_create_department()
    {
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/departments', [
                'code' => 'HR',
                'name' => 'Human Resources',
                'description' => 'HR Department',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('department.code', 'HR');

        $this->assertDatabaseHas('departments', ['code' => 'HR']);
    }

    public function test_can_get_department_tree()
    {
        // Create child department
        Department::create([
            'code' => 'DEV',
            'name' => 'Development',
            'parent_id' => $this->department->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/departments/tree');

        $response->assertStatus(200);
    }

    // ==================== Job Title Tests ====================

    public function test_can_list_job_titles()
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/job-titles');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'level']]]);
    }

    public function test_can_create_job_title()
    {
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/job-titles', [
                'code' => 'MGR',
                'name' => 'IT Manager',
                'department_id' => $this->department->id,
                'level' => JobTitle::LEVEL_MANAGER,
                'min_salary' => 8000,
                'max_salary' => 15000,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('job_title.code', 'MGR');
    }

    public function test_can_get_job_title_levels()
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/job-titles/levels');

        $response->assertStatus(200)
            ->assertJsonPath('levels.1', 'Manager');
    }

    // ==================== Employee Tests ====================

    public function test_can_list_employees()
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/employees');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'employee_code', 'user']]]);
    }

    public function test_can_create_employee()
    {
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/employees', [
                'name' => 'Jane Doe',
                'email' => 'jane@test.com',
                'password' => 'password123',
                'department_id' => $this->department->id,
                'job_title_id' => $this->jobTitle->id,
                'hire_date' => now()->format('Y-m-d'),
                'base_salary' => 4500,
                'branch_ids' => [$this->branch->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('employee.user.name', 'Jane Doe');

        $this->assertDatabaseHas('users', ['email' => 'jane@test.com']);
        $this->assertDatabaseHas('employees', ['base_salary' => 4500]);
    }

    public function test_can_get_employee_details()
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/v1/employees/{$this->employee->id}");

        $response->assertStatus(200)
            ->assertJsonPath('employee_code', 'EMP00001')
            ->assertJsonStructure(['user', 'department', 'job_title']);
    }

    public function test_can_update_employee()
    {
        $response = $this->actingAs($this->hrManager)
            ->putJson("/api/v1/employees/{$this->employee->id}", [
                'base_salary' => 6000,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employees', ['id' => $this->employee->id, 'base_salary' => 6000]);
    }

    public function test_can_terminate_employee()
    {
        $response = $this->actingAs($this->hrManager)
            ->postJson("/api/v1/employees/{$this->employee->id}/terminate", [
                'termination_date' => now()->format('Y-m-d'),
                'reason' => 'Voluntary resignation',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employees', [
            'id' => $this->employee->id,
            'employment_status' => Employee::STATUS_TERMINATED,
        ]);
    }

    public function test_can_get_employment_types()
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/employees/employment-types');

        $response->assertStatus(200)
            ->assertJsonPath('types.full_time', 'Full Time');
    }

    // ==================== Attendance Tests ====================

    public function test_employee_can_check_in()
    {
        $employeeUser = $this->employee->user;

        // Give the user the check-in permission
        $role = Role::create(['name' => 'employee_role', 'description' => 'Basic Employee']);
        $perm = Permission::findOrCreateByName('hr.attendance.check-in', 'Check In');
        $role->assignPermission($perm);
        $employeeUser->assignRole($role);

        $response = $this->actingAs($employeeUser)
            ->postJson('/api/v1/attendance/check-in');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Check-in recorded successfully.');

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee->id,
            'date' => today()->format('Y-m-d 00:00:00'),
        ]);
    }

    public function test_employee_can_check_out()
    {
        $employeeUser = $this->employee->user;

        // Create check-in first
        Attendance::checkIn($this->employee, $this->branch->id);

        // Give the user the check-in permission
        $role = Role::firstOrCreate(['name' => 'employee_role'], ['description' => 'Basic Employee']);
        $perm = Permission::findOrCreateByName('hr.attendance.check-in', 'Check In');
        $role->assignPermission($perm);
        $employeeUser->assignRole($role);

        $response = $this->actingAs($employeeUser)
            ->postJson('/api/v1/attendance/check-out');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Check-out recorded successfully.');
    }

    public function test_can_get_attendance_summary()
    {
        // Create some attendance records
        Attendance::create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'date' => now()->format('Y-m-d'),
            'check_in' => '09:00:00',
            'check_out' => '17:00:00',
            'hours_worked' => 8,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $period = now()->format('Y-m');

        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/v1/attendance/summary/{$this->employee->id}/{$period}");

        $response->assertStatus(200)
            ->assertJsonStructure(['employee', 'period', 'present_days', 'absent_days']);
    }

    public function test_can_list_attendance_records()
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/attendance');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    // ==================== Employee Custody Tests ====================

    public function test_can_assign_custody_item()
    {
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/employee-custodies', [
                'employee_id' => $this->employee->id,
                'type' => EmployeeCustody::TYPE_LAPTOP,
                'name' => 'MacBook Pro',
                'serial_number' => 'ABC123',
                'value' => 2500,
                'condition_on_assignment' => 'new',
                'assigned_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('custody.name', 'MacBook Pro');

        $this->assertDatabaseHas('employee_custodies', [
            'employee_id' => $this->employee->id,
            'name' => 'MacBook Pro',
        ]);
    }

    public function test_can_return_custody_item()
    {
        $custody = EmployeeCustody::create([
            'employee_id' => $this->employee->id,
            'type' => EmployeeCustody::TYPE_LAPTOP,
            'name' => 'Dell Laptop',
            'condition_on_assignment' => 'good',
            'status' => EmployeeCustody::STATUS_ASSIGNED,
            'assigned_date' => now()->subMonths(1),
            'assigned_by' => $this->hrManager->id,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->postJson("/api/v1/employee-custodies/{$custody->id}/return", [
                'condition_on_return' => 'good',
                'return_notes' => 'Item in good condition',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employee_custodies', [
            'id' => $custody->id,
            'status' => EmployeeCustody::STATUS_RETURNED,
        ]);
    }

    public function test_can_get_custody_types()
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/employee-custodies/types');

        $response->assertStatus(200)
            ->assertJsonPath('types.laptop', 'Laptop');
    }

    // ==================== Employee Document Tests ====================

    public function test_can_upload_document()
    {
        $file = UploadedFile::fake()->create('contract.pdf', 100);

        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/employee-documents', [
                'employee_id' => $this->employee->id,
                'type' => EmployeeDocument::TYPE_CONTRACT,
                'title' => 'Employment Contract',
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('document.title', 'Employment Contract');

        $this->assertDatabaseHas('employee_documents', [
            'employee_id' => $this->employee->id,
            'title' => 'Employment Contract',
        ]);
    }

    public function test_can_verify_document()
    {
        $document = EmployeeDocument::create([
            'employee_id' => $this->employee->id,
            'type' => EmployeeDocument::TYPE_NATIONAL_ID,
            'title' => 'National ID Copy',
            'file_path' => 'test/id.pdf',
            'file_name' => 'id.pdf',
            'uploaded_by' => $this->hrManager->id,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->postJson("/api/v1/employee-documents/{$document->id}/verify");

        $response->assertStatus(200);
        $this->assertDatabaseHas('employee_documents', [
            'id' => $document->id,
            'is_verified' => true,
        ]);
    }

    public function test_can_get_document_types()
    {
        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/employee-documents/types');

        $response->assertStatus(200)
            ->assertJsonPath('types.contract', 'Contract');
    }

    // ==================== Deduction Tests ====================

    public function test_can_create_deduction()
    {
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/deductions', [
                'employee_id' => $this->employee->id,
                'type' => Deduction::TYPE_PENALTY,
                'reason' => 'Policy violation',
                'amount' => 200,
                'date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('deductions', [
            'employee_id' => $this->employee->id,
            'type' => Deduction::TYPE_PENALTY,
        ]);
    }

    public function test_can_create_absence_deduction()
    {
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/deductions/create-absence', [
                'employee_id' => $this->employee->id,
                'date' => now()->format('Y-m-d'),
                'reason' => 'Unexcused absence',
            ]);

        $response->assertStatus(201);

        // Amount should be daily rate (5000/30 = ~166.67)
        $this->assertDatabaseHas('deductions', [
            'employee_id' => $this->employee->id,
            'type' => Deduction::TYPE_ABSENCE,
        ]);
    }

    public function test_can_approve_deduction()
    {
        $deduction = Deduction::create([
            'employee_id' => $this->employee->id,
            'type' => Deduction::TYPE_PENALTY,
            'reason' => 'Test penalty',
            'amount' => 100,
            'date' => now(),
            'period' => now()->format('Y-m'),
            'created_by' => $this->hrManager->id,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->postJson("/api/v1/deductions/{$deduction->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('deductions', [
            'id' => $deduction->id,
            'approved_by' => $this->hrManager->id,
        ]);
    }

    public function test_can_get_deduction_summary()
    {
        $period = now()->format('Y-m');

        Deduction::create([
            'employee_id' => $this->employee->id,
            'type' => Deduction::TYPE_ABSENCE,
            'reason' => 'Absence',
            'amount' => 166.67,
            'date' => now(),
            'period' => $period,
            'created_by' => $this->hrManager->id,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/v1/deductions/summary/{$this->employee->id}/{$period}");

        $response->assertStatus(200)
            ->assertJsonStructure(['employee', 'period', 'total_deductions', 'by_type']);
    }

    // ==================== Payroll Tests ====================

    public function test_can_generate_payroll()
    {
        $period = now()->format('Y-m');

        // Create some attendance
        Attendance::create([
            'employee_id' => $this->employee->id,
            'date' => now()->startOfMonth(),
            'check_in' => '09:00:00',
            'check_out' => '18:00:00',
            'hours_worked' => 9,
            'overtime_hours' => 1,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/payrolls/generate', [
                'employee_id' => $this->employee->id,
                'period' => $period,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['payroll' => ['id', 'employee_id', 'period', 'base_salary', 'net_salary']]);

        $this->assertDatabaseHas('payrolls', [
            'employee_id' => $this->employee->id,
            'period' => $period,
            'status' => Payroll::STATUS_DRAFT,
        ]);
    }

    public function test_can_submit_payroll_for_approval()
    {
        $payroll = Payroll::create([
            'employee_id' => $this->employee->id,
            'period' => now()->format('Y-m'),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'base_salary' => 5000,
            'total_allowances' => 1700,
            'gross_salary' => 6700,
            'total_deductions' => 0,
            'net_salary' => 6700,
            'status' => Payroll::STATUS_DRAFT,
            'generated_by' => $this->hrManager->id,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->postJson("/api/v1/payrolls/{$payroll->id}/submit");

        $response->assertStatus(200);
        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => Payroll::STATUS_PENDING,
        ]);
    }

    public function test_can_approve_payroll()
    {
        $payroll = Payroll::create([
            'employee_id' => $this->employee->id,
            'period' => now()->format('Y-m'),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'base_salary' => 5000,
            'gross_salary' => 6700,
            'net_salary' => 6700,
            'status' => Payroll::STATUS_PENDING,
            'generated_by' => $this->hrManager->id,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->postJson("/api/v1/payrolls/{$payroll->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => Payroll::STATUS_APPROVED,
        ]);
    }

    public function test_can_process_payroll_payment()
    {
        $payroll = Payroll::create([
            'employee_id' => $this->employee->id,
            'period' => now()->format('Y-m'),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'base_salary' => 5000,
            'gross_salary' => 6700,
            'net_salary' => 6700,
            'status' => Payroll::STATUS_APPROVED,
            'generated_by' => $this->hrManager->id,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->postJson("/api/v1/payrolls/{$payroll->id}/pay", [
                'cashbox_id' => $this->cashbox->id,
                'payment_method' => 'bank_transfer',
                'payment_reference' => 'TRF123456',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => Payroll::STATUS_PAID,
        ]);

        // Check transaction was created
        $this->assertDatabaseHas('transactions', [
            'cashbox_id' => $this->cashbox->id,
            'category' => 'salary_expense',
        ]);
    }

    public function test_can_get_payroll_summary()
    {
        $period = now()->format('Y-m');

        Payroll::create([
            'employee_id' => $this->employee->id,
            'period' => $period,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'base_salary' => 5000,
            'gross_salary' => 6700,
            'net_salary' => 6700,
            'status' => Payroll::STATUS_DRAFT,
            'generated_by' => $this->hrManager->id,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/v1/payrolls/summary/{$period}");

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'total_payrolls', 'by_status', 'totals']);
    }

    // ==================== Activity Log Tests ====================

    public function test_can_list_activity_logs()
    {
        // Create an activity log
        ActivityLog::log(
            ActivityLog::ACTION_CREATED,
            $this->employee,
            null,
            $this->employee->toArray(),
            'Test activity'
        );

        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/activity-logs');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'action', 'entity_type']]]);
    }

    public function test_can_get_activity_logs_for_entity()
    {
        ActivityLog::log(
            ActivityLog::ACTION_UPDATED,
            $this->employee,
            ['base_salary' => 4000],
            ['base_salary' => 5000],
            'Salary updated'
        );

        $response = $this->actingAs($this->hrManager)
            ->getJson("/api/v1/activity-logs/entity/Employee/{$this->employee->id}");

        $response->assertStatus(200);
    }

    public function test_can_get_activity_statistics()
    {
        ActivityLog::create([
            'action' => ActivityLog::ACTION_CREATED,
            'entity_type' => Employee::class,
            'entity_id' => 1,
        ]);

        $response = $this->actingAs($this->hrManager)
            ->getJson('/api/v1/activity-logs/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure(['total_activities', 'by_action']);
    }

    // ==================== Integration Tests ====================

    public function test_full_payroll_workflow()
    {
        $period = now()->format('Y-m');

        // 1. Create attendance records
        for ($i = 1; $i <= 5; $i++) {
            Attendance::create([
                'employee_id' => $this->employee->id,
                'branch_id' => $this->branch->id,
                'date' => now()->startOfMonth()->addDays($i),
                'check_in' => '09:00:00',
                'check_out' => '17:30:00',
                'hours_worked' => 8.5,
                'overtime_hours' => 0.5,
                'status' => Attendance::STATUS_PRESENT,
            ]);
        }

        // 2. Create a deduction
        Deduction::create([
            'employee_id' => $this->employee->id,
            'type' => Deduction::TYPE_LATE,
            'reason' => 'Late arrival',
            'amount' => 50,
            'date' => now()->startOfMonth()->addDays(3),
            'period' => $period,
            'created_by' => $this->hrManager->id,
        ]);

        // 3. Generate payroll
        $response = $this->actingAs($this->hrManager)
            ->postJson('/api/v1/payrolls/generate', [
                'employee_id' => $this->employee->id,
                'period' => $period,
            ]);

        $response->assertStatus(201);
        $payrollId = $response->json('payroll.id');

        // 4. Submit for approval
        $this->actingAs($this->hrManager)
            ->postJson("/api/v1/payrolls/{$payrollId}/submit")
            ->assertStatus(200);

        // 5. Approve
        $this->actingAs($this->hrManager)
            ->postJson("/api/v1/payrolls/{$payrollId}/approve")
            ->assertStatus(200);

        // 6. Pay
        $this->actingAs($this->hrManager)
            ->postJson("/api/v1/payrolls/{$payrollId}/pay", [
                'cashbox_id' => $this->cashbox->id,
                'payment_method' => 'bank_transfer',
            ])
            ->assertStatus(200);

        // Verify final state
        $payroll = Payroll::find($payrollId);
        $this->assertEquals(Payroll::STATUS_PAID, $payroll->status);
        $this->assertNotNull($payroll->transaction_id);

        // Verify deduction was marked as applied
        $this->assertDatabaseHas('deductions', [
            'employee_id' => $this->employee->id,
            'is_applied' => true,
        ]);
    }

    public function test_employee_can_view_own_profile()
    {
        $employeeUser = $this->employee->user;

        // Give minimal permission
        $role = Role::firstOrCreate(['name' => 'basic_employee'], ['description' => 'Basic Employee']);
        $perm = Permission::findOrCreateByName('hr.attendance.check-in', 'Check In');
        $role->assignPermission($perm);
        $employeeUser->assignRole($role);

        $response = $this->actingAs($employeeUser)
            ->getJson('/api/v1/employees/me');

        $response->assertStatus(200)
            ->assertJsonPath('employee_code', 'EMP00001');
    }

    // ==================== Model Tests ====================

    public function test_employee_generates_code()
    {
        $code = Employee::generateEmployeeCode();
        $this->assertMatchesRegularExpression('/^EMP\d{5}$/', $code);
    }

    public function test_employee_calculates_total_salary()
    {
        $total = $this->employee->total_salary;
        $this->assertEquals(6700, $total); // 5000 + 500 + 1000 + 200
    }

    public function test_employee_calculates_daily_rate()
    {
        $dailyRate = $this->employee->daily_salary_rate;
        $this->assertEquals(round(5000 / 30, 2), round($dailyRate, 2));
    }

    public function test_attendance_calculates_hours_worked()
    {
        $attendance = new Attendance([
            'employee_id' => $this->employee->id,
            'date' => now(),
            'check_in' => '09:00:00',
            'check_out' => '17:30:00',
        ]);

        $hours = $attendance->calculateHoursWorked();
        $this->assertEquals(8.5, $hours);
    }

    public function test_payroll_calculates_totals()
    {
        $payroll = new Payroll([
            'employee_id' => $this->employee->id,
            'base_salary' => 5000,
            'transport_allowance' => 500,
            'housing_allowance' => 1000,
            'other_allowances' => 200,
            'absence_deductions' => 100,
            'late_deductions' => 50,
            'penalty_deductions' => 0,
            'other_deductions' => 0,
        ]);

        // Set employee relationship for calculation
        $payroll->setRelation('employee', $this->employee);

        $payroll->calculateTotals();

        $this->assertEquals(1700, $payroll->total_allowances);
        $this->assertEquals(150, $payroll->total_deductions);
        $this->assertEquals(6700, $payroll->gross_salary);
        $this->assertEquals(6550, $payroll->net_salary);
    }

    public function test_job_title_level_labels()
    {
        $this->assertEquals('Manager', JobTitle::LEVELS[JobTitle::LEVEL_MANAGER]);
        $this->assertEquals('Supervisor', JobTitle::LEVELS[JobTitle::LEVEL_SUPERVISOR]);
        $this->assertEquals('Employee', JobTitle::LEVELS[JobTitle::LEVEL_EMPLOYEE]);
    }

    public function test_department_hierarchy_path()
    {
        $parent = Department::create([
            'code' => 'TECH',
            'name' => 'Technology',
            'is_active' => true,
        ]);

        $child = Department::create([
            'code' => 'DEV',
            'name' => 'Development',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);

        $this->assertEquals('Technology > Development', $child->hierarchy_path);
    }
}


