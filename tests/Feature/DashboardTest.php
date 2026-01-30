<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Cashbox;
use App\Models\Branch;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\Department;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Inventory;
use Carbon\Carbon;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected Branch $branch;
    protected Cashbox $cashbox;
    protected Department $department;
    protected Employee $employee;
    protected Inventory $inventory;

    protected Address $clientAddress;

    protected function setUp(): void
    {
        parent::setUp();

        // Create country and city
        $country = Country::create(['name' => 'Test Country', 'code' => 'TC']);
        $city = City::create(['name' => 'Test City', 'country_id' => $country->id]);
        $address = Address::create([
            'street' => '123 Test St',
            'building' => 'Building 1',
            'city_id' => $city->id
        ]);
        $this->clientAddress = $address;

        // Create branch and cashbox
        $this->branch = Branch::create([
            'branch_code' => 'BR001',
            'name' => 'Main Branch',
            'address_id' => $address->id,
        ]);
        $this->branch->refresh();
        $this->cashbox = $this->branch->cashbox;
        $this->cashbox->update(['current_balance' => 100000, 'is_active' => true]);

        // Create super admin
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => User::SUPER_ADMIN_EMAIL,
            'password' => bcrypt('password'),
        ]);

        // Create manager with dashboard permissions
        $this->manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@test.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'manager', 'description' => 'Manager']);
        $permissions = [
            'dashboard.view',
            'dashboard.activity.view',
            'dashboard.business.view',
            'dashboard.hr.view',
        ];

        foreach ($permissions as $perm) {
            $parsed = Permission::parseName($perm);
            Permission::firstOrCreate(
                ['name' => $perm],
                [
                    'display_name' => ucfirst($perm),
                    'module' => $parsed['module'],
                    'action' => $parsed['action'],
                ]
            );
            $role->assignPermission($perm);
        }

        $this->manager->assignRole($role);

        // Create department and employee
        $this->department = Department::create([
            'code' => 'IT',
            'name' => 'IT Department',
            'is_active' => true,
        ]);

        $employeeUser = User::create([
            'name' => 'John Employee',
            'email' => 'john@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->employee = Employee::create([
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP001',
            'department_id' => $this->department->id,
            'hire_date' => now()->subMonths(6),
            'base_salary' => 5000,
            'employment_status' => Employee::STATUS_ACTIVE,
        ]);

        // Create inventory for branch
        $this->inventory = Inventory::create([
            'name' => 'Branch Inventory',
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $this->branch->id,
        ]);
    }

    // ==================== Dashboard Overview Tests ====================

    public function test_can_get_dashboard_overview()
    {
        // Create some test data
        $client = Client::create([
            'first_name' => 'Test',
            'middle_name' => 'Middle',
            'last_name' => 'Client',
            'date_of_birth' => '1990-01-01',
            'national_id' => '1234567890' . rand(1000, 9999),
            'address_id' => $this->clientAddress->id,
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $this->inventory->id,
            'total_price' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/overview?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'activity',
                'business' => ['sales', 'clients', 'payments', 'inventory', 'financial'],
                'hr' => ['attendance', 'payroll', 'employee_activity', 'trends'],
                'generated_at',
            ]);
    }

    public function test_can_get_dashboard_summary()
    {
        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/summary?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'key_metrics',
                'period',
                'generated_at',
            ]);
    }

    public function test_dashboard_requires_permission()
    {
        $user = User::create([
            'name' => 'No Permission',
            'email' => 'noperm@test.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/dashboard/overview');

        $response->assertStatus(403);
    }

    // ==================== Activity Analytics Tests ====================

    public function test_can_get_activity_summary()
    {
        // Create some activity logs
        ActivityLog::create([
            'user_id' => $this->manager->id,
            'action' => ActivityLog::ACTION_CREATED,
            'entity_type' => Order::class,
            'entity_id' => 1,
            'description' => 'Test activity',
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/activity/summary?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_activities',
                'by_action',
                'by_entity_type',
                'period',
            ]);
    }

    public function test_can_get_activity_trends()
    {
        ActivityLog::create([
            'user_id' => $this->manager->id,
            'action' => ActivityLog::ACTION_CREATED,
            'entity_type' => Order::class,
            'entity_id' => 1,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/activity/trends?entity_type=Order&period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'entity_type',
                'trends',
                'period',
            ]);
    }

    public function test_can_get_user_activity_stats()
    {
        ActivityLog::create([
            'user_id' => $this->manager->id,
            'action' => ActivityLog::ACTION_CREATED,
            'entity_type' => Order::class,
            'entity_id' => 1,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/v1/dashboard/activity/users?user_id={$this->manager->id}&period=month");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_activities',
                'by_action',
                'by_entity_type',
                'period',
            ]);
    }

    public function test_can_get_top_active_users()
    {
        ActivityLog::create([
            'user_id' => $this->manager->id,
            'action' => ActivityLog::ACTION_CREATED,
            'entity_type' => Order::class,
            'entity_id' => 1,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/activity/top-users?limit=10&period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'users',
                'period',
            ]);
    }

    public function test_can_get_activity_timeline()
    {
        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/activity/timeline?date=' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'hourly_activity',
            ]);
    }

    // ==================== Business Metrics Tests ====================

    public function test_can_get_sales_metrics()
    {
        $client = Client::create([
            'first_name' => 'Test',
            'middle_name' => 'Middle',
            'last_name' => 'Client',
            'date_of_birth' => '1990-01-01',
            'national_id' => '1234567890' . rand(1000, 9999),
            'address_id' => $this->clientAddress->id,
        ]);

        Order::create([
            'client_id' => $client->id,
            'inventory_id' => $this->inventory->id,
            'total_price' => 1000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/business/sales?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_revenue',
                'order_count',
                'average_order_value',
                'by_status',
                'period',
            ]);
    }

    public function test_can_get_client_metrics()
    {
        Client::create([
            'first_name' => 'Test',
            'middle_name' => 'Middle',
            'last_name' => 'Client',
            'date_of_birth' => '1990-01-01',
            'national_id' => '1234567890' . rand(1000, 9999),
            'address_id' => $this->clientAddress->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/business/clients?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'new_clients',
                'total_clients',
                'active_clients',
                'growth_rate',
                'period',
            ]);
    }

    public function test_can_get_payment_metrics()
    {
        $client = Client::create([
            'first_name' => 'Test',
            'middle_name' => 'Middle',
            'last_name' => 'Client',
            'date_of_birth' => '1990-01-01',
            'national_id' => '1234567890' . rand(1000, 9999),
            'address_id' => $this->clientAddress->id,
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $this->inventory->id,
            'total_price' => 1000,
            'status' => 'pending',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'amount' => 500,
            'status' => 'paid',
            'payment_type' => 'normal',
            'payment_date' => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/business/payments?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_payments',
                'payment_count',
                'by_method',
                'period',
            ]);
    }

    public function test_can_get_inventory_metrics()
    {
        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/business/inventory');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_items',
                'available',
                'out_of_branch',
                'utilization_rate',
            ]);
    }

    public function test_can_get_financial_metrics()
    {
        Transaction::create([
            'cashbox_id' => $this->cashbox->id,
            'type' => Transaction::TYPE_INCOME,
            'amount' => 1000,
            'balance_after' => 101000,
            'category' => 'sales',
            'description' => 'Test income',
            'created_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/business/financial?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_income',
                'total_expenses',
                'profit',
                'profit_margin',
                'cashbox_balances',
                'period',
            ]);
    }

    // ==================== HR Metrics Tests ====================

    public function test_can_get_attendance_metrics()
    {
        Attendance::create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'date' => now(),
            'check_in' => '09:00:00',
            'check_out' => '17:00:00',
            'hours_worked' => 8,
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/hr/attendance?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_records',
                'present_days',
                'absent_days',
                'late_arrivals',
                'leave_days',
                'attendance_rate',
                'period',
            ]);
    }

    public function test_can_get_payroll_metrics()
    {
        Payroll::create([
            'employee_id' => $this->employee->id,
            'period' => now()->format('Y-m'),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'base_salary' => 5000,
            'gross_salary' => 5000,
            'net_salary' => 5000,
            'status' => Payroll::STATUS_PAID,
            'generated_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/hr/payroll?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_payroll',
                'payroll_count',
                'average_salary',
                'by_status',
                'period',
            ]);
    }

    public function test_can_get_employee_activity_metrics()
    {
        ActivityLog::create([
            'user_id' => $this->employee->user_id,
            'action' => ActivityLog::ACTION_CREATED,
            'entity_type' => Order::class,
            'entity_id' => 1,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/hr/employees?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'most_active_employees',
                'period',
            ]);
    }

    public function test_can_get_hr_trends()
    {
        Attendance::create([
            'employee_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'date' => now(),
            'status' => Attendance::STATUS_PRESENT,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/hr/trends?period=month');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'attendance_trends',
                'payroll_trends',
                'period',
            ]);
    }

    // ==================== Filtering Tests ====================

    public function test_dashboard_respects_date_range()
    {
        $response = $this->actingAs($this->manager)
            ->getJson('/api/v1/dashboard/overview?date_from=2026-01-01&date_to=2026-01-31');

        $response->assertStatus(200)
            ->assertJsonPath('activity.period.from', '2026-01-01')
            ->assertJsonPath('activity.period.to', '2026-01-31');
    }

    public function test_dashboard_respects_branch_filter()
    {
        $response = $this->actingAs($this->manager)
            ->getJson("/api/v1/dashboard/overview?branch_id={$this->branch->id}");

        $response->assertStatus(200);
    }

    public function test_dashboard_respects_department_filter()
    {
        $response = $this->actingAs($this->manager)
            ->getJson("/api/v1/dashboard/hr/attendance?department_id={$this->department->id}");

        $response->assertStatus(200);
    }

    // ==================== Period Tests ====================

    public function test_dashboard_supports_different_periods()
    {
        $periods = ['today', 'week', 'month', 'year', 'last_week', 'last_month'];

        foreach ($periods as $period) {
            $response = $this->actingAs($this->manager)
                ->getJson("/api/v1/dashboard/summary?period={$period}");

            $response->assertStatus(200);
        }
    }
}


