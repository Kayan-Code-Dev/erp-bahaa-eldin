<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    /**
     * Define all permissions for the system
     * Format: 'module.action' => 'Display Name'
     */
    protected array $permissions = [
        // Client permissions
        'clients.view' => 'View Clients',
        'clients.create' => 'Create Clients',
        'clients.update' => 'Update Clients',
        'clients.delete' => 'Delete Clients',
        'clients.export' => 'Export Clients',
        'clients.measurements.view' => 'View Client Measurements',
        'clients.measurements.update' => 'Update Client Measurements',

        // Order permissions
        'orders.view' => 'View Orders',
        'orders.create' => 'Create Orders',
        'orders.update' => 'Update Orders',
        'orders.delete' => 'Delete Orders',
        'orders.export' => 'Export Orders',
        'orders.deliver' => 'Deliver Orders',
        'orders.finish' => 'Finish Orders',
        'orders.cancel' => 'Cancel Orders',
        'orders.return' => 'Return Order Items',

        // Payment permissions
        'payments.view' => 'View Payments',
        'payments.create' => 'Create Payments',
        'payments.pay' => 'Mark Payments as Paid',
        'payments.cancel' => 'Cancel Payments',
        'payments.export' => 'Export Payments',

        // Custody permissions
        'custody.view' => 'View Custody',
        'custody.create' => 'Create Custody',
        'custody.update' => 'Update Custody',
        'custody.return' => 'Return Custody',
        'custody.export' => 'Export Custody',

        // Inventory permissions
        'inventories.view' => 'View Inventories',
        'inventories.create' => 'Create Inventories',
        'inventories.update' => 'Update Inventories',
        'inventories.delete' => 'Delete Inventories',
        'inventories.export' => 'Export Inventories',

        // Clothes permissions
        'clothes.view' => 'View Clothes',
        'clothes.create' => 'Create Clothes',
        'clothes.update' => 'Update Clothes',
        'clothes.delete' => 'Delete Clothes',
        'clothes.export' => 'Export Clothes',

        // Cloth types permissions
        'cloth-types.view' => 'View Cloth Types',
        'cloth-types.create' => 'Create Cloth Types',
        'cloth-types.update' => 'Update Cloth Types',
        'cloth-types.delete' => 'Delete Cloth Types',
        'cloth-types.export' => 'Export Cloth Types',

        // Branch permissions
        'branches.view' => 'View Branches',
        'branches.create' => 'Create Branches',
        'branches.update' => 'Update Branches',
        'branches.delete' => 'Delete Branches',
        'branches.export' => 'Export Branches',

        // Workshop permissions
        'workshops.view' => 'View Workshops',
        'workshops.create' => 'Create Workshops',
        'workshops.update' => 'Update Workshops',
        'workshops.delete' => 'Delete Workshops',
        'workshops.export' => 'Export Workshops',
        
        // Workshop cloth management permissions
        'workshops.manage-clothes' => 'Manage Clothes in Workshop',
        'workshops.approve-transfers' => 'Approve Incoming Transfers to Workshop',
        'workshops.update-status' => 'Update Cloth Status in Workshop',
        'workshops.return-cloth' => 'Create Return Transfers from Workshop',
        'workshops.view-logs' => 'View Workshop Logs',

        // Factory permissions
        'factories.view' => 'View Factories',
        'factories.create' => 'Create Factories',
        'factories.update' => 'Update Factories',
        'factories.delete' => 'Delete Factories',
        'factories.export' => 'Export Factories',
        'factories.manage' => 'Manage Factory Settings and Statistics',
        
        // Factory User permissions (for factory users to manage their orders)
        'factories.orders.view' => 'View Factory Orders',
        'factories.orders.accept' => 'Accept Tailoring Items',
        'factories.orders.reject' => 'Reject Tailoring Items',
        'factories.orders.update-status' => 'Update Item Status',
        'factories.orders.add-notes' => 'Add Notes to Items',
        'factories.orders.set-delivery-date' => 'Set Expected Delivery Date',
        'factories.orders.deliver' => 'Confirm Item Delivery',
        'factories.reports.view' => 'View Factory Statistics',
        'factories.dashboard.view' => 'View Factory Dashboard',

        // Transfer permissions
        'transfers.view' => 'View Transfers',
        'transfers.create' => 'Create Transfers',
        'transfers.update' => 'Update Transfers',
        'transfers.delete' => 'Delete Transfers',
        'transfers.approve' => 'Approve Transfers',
        'transfers.reject' => 'Reject Transfers',
        'transfers.export' => 'Export Transfers',

        // Category permissions
        'categories.view' => 'View Categories',
        'categories.create' => 'Create Categories',
        'categories.update' => 'Update Categories',
        'categories.delete' => 'Delete Categories',
        'categories.export' => 'Export Categories',

        // Subcategory permissions
        'subcategories.view' => 'View Subcategories',
        'subcategories.create' => 'Create Subcategories',
        'subcategories.update' => 'Update Subcategories',
        'subcategories.delete' => 'Delete Subcategories',
        'subcategories.export' => 'Export Subcategories',

        // User management permissions
        'users.view' => 'View Users',
        'users.create' => 'Create Users',
        'users.update' => 'Update Users',
        'users.delete' => 'Delete Users',
        'users.export' => 'Export Users',

        // Role management permissions
        'roles.view' => 'View Roles',
        'roles.create' => 'Create Roles',
        'roles.update' => 'Update Roles',
        'roles.delete' => 'Delete Roles',
        'roles.export' => 'Export Roles',
        'roles.assign-permissions' => 'Assign Permissions to Roles',

        // Address/Location permissions
        'addresses.view' => 'View Addresses',
        'addresses.create' => 'Create Addresses',
        'addresses.update' => 'Update Addresses',
        'addresses.delete' => 'Delete Addresses',
        'addresses.export' => 'Export Addresses',

        'cities.view' => 'View Cities',
        'cities.create' => 'Create Cities',
        'cities.update' => 'Update Cities',
        'cities.delete' => 'Delete Cities',
        'cities.export' => 'Export Cities',

        'countries.view' => 'View Countries',
        'countries.create' => 'Create Countries',
        'countries.update' => 'Update Countries',
        'countries.delete' => 'Delete Countries',
        'countries.export' => 'Export Countries',

        'phones.view' => 'View Phones',
        'phones.create' => 'Create Phones',
        'phones.update' => 'Update Phones',
        'phones.delete' => 'Delete Phones',
        'phones.export' => 'Export Phones',

        // Accounting module permissions - Cashbox
        'cashbox.view' => 'View Cashbox',
        'cashbox.manage' => 'Manage Cashbox',
        'cashbox.recalculate' => 'Recalculate Cashbox Balance',

        // Accounting module permissions - Transactions (IMMUTABLE - no create/update/delete)
        'transactions.view' => 'View Transactions',
        'transactions.reverse' => 'Reverse Transactions',

        // Accounting module permissions - Expenses
        'expenses.view' => 'View Expenses',
        'expenses.create' => 'Create Expenses',
        'expenses.update' => 'Update Expenses',
        'expenses.delete' => 'Delete Expenses',
        'expenses.approve' => 'Approve Expenses',
        'expenses.pay' => 'Pay Expenses',
        'expenses.export' => 'Export Expenses',

        // Accounting module permissions - Receivables
        'receivables.view' => 'View Receivables',
        'receivables.manage' => 'Manage Receivables',
        'receivables.export' => 'Export Receivables',

        // Appointment permissions
        'appointments.view' => 'View Appointments',
        'appointments.create' => 'Create Appointments',
        'appointments.update' => 'Update Appointments',
        'appointments.delete' => 'Delete Appointments',
        'appointments.manage' => 'Manage Appointments (confirm, complete, cancel)',
        'appointments.export' => 'Export Appointments',

        // Tailoring Stage permissions
        'tailoring.view' => 'View Tailoring Orders',
        'tailoring.manage' => 'Manage Tailoring Stages',
        'tailoring.assign-factory' => 'Assign Factory to Orders',

        // Factory Evaluation permissions
        'evaluations.view' => 'View Factory Evaluations',
        'evaluations.create' => 'Create Factory Evaluations',
        'evaluations.manage' => 'Manage Factory Evaluations',

        // Factory Management permissions (extends existing)
        'factories.manage' => 'Manage Factory Settings and Statistics',

        // Reports permissions
        'reports.view' => 'View Reports',
        'reports.financial' => 'View Financial Reports',
        'reports.inventory' => 'View Inventory Reports',
        'reports.performance' => 'View Performance Reports',

        // Notification permissions
        'notifications.view' => 'View Notifications',
        'notifications.manage' => 'Manage Notifications (broadcast, view all)',

        // HR Module - Departments
        'hr.departments.view' => 'View Departments',
        'hr.departments.manage' => 'Manage Departments',

        // HR Module - Job Titles
        'hr.job-titles.view' => 'View Job Titles',
        'hr.job-titles.manage' => 'Manage Job Titles',

        // HR Module - Employees
        'hr.employees.view' => 'View Employees',
        'hr.employees.create' => 'Create Employees',
        'hr.employees.update' => 'Update Employees',
        'hr.employees.delete' => 'Delete Employees',
        'hr.employees.manage-branches' => 'Manage Employee Branch Assignments',
        'hr.employees.manage-entities' => 'Manage Employee Entity Assignments (Branches, Workshops, Factories)',
        'hr.employees.terminate' => 'Terminate Employees',

        // HR Module - Attendance
        'hr.attendance.view' => 'View Attendance Records',
        'hr.attendance.manage' => 'Manage Attendance Records',
        'hr.attendance.check-in' => 'Check In/Out Attendance',
        'hr.attendance.reports' => 'View Attendance Reports',

        // HR Module - Employee Custody (Equipment)
        'hr.custody.view' => 'View Employee Custody Items',
        'hr.custody.assign' => 'Assign Custody to Employees',
        'hr.custody.return' => 'Process Custody Returns',

        // HR Module - Employee Documents
        'hr.documents.view' => 'View Employee Documents',
        'hr.documents.upload' => 'Upload Employee Documents',
        'hr.documents.verify' => 'Verify Employee Documents',
        'hr.documents.delete' => 'Delete Employee Documents',

        // HR Module - Deductions
        'hr.deductions.view' => 'View Deductions',
        'hr.deductions.create' => 'Create Deductions',
        'hr.deductions.approve' => 'Approve Deductions',

        // HR Module - Payroll
        'hr.payroll.view' => 'View Payrolls',
        'hr.payroll.generate' => 'Generate Payrolls',
        'hr.payroll.approve' => 'Approve Payrolls',
        'hr.payroll.pay' => 'Process Payroll Payments',

        // HR Module - Activity Logs
        'hr.activity-log.view' => 'View Activity Logs',

        // Dashboard Module
        'dashboard.view' => 'View Dashboard',
        'dashboard.activity.view' => 'View Activity Analytics',
        'dashboard.business.view' => 'View Business Metrics',
        'dashboard.hr.view' => 'View HR Metrics',
    ];

    /**
     * Define default roles and their permissions
     */
    protected array $roles = [
        'general_manager' => [
            'description' => 'General Manager - Full access to all modules',
            'permissions' => '*', // All permissions
        ],
        'reception_employee' => [
            'description' => 'Reception/Rental Employee - Manages clients, rental orders, appointments',
            'permissions' => [
                'clients.*',
                'orders.view', 'orders.create', 'orders.update', 'orders.deliver', 'orders.return',
                'payments.view', 'payments.create', 'payments.pay',
                'custody.view', 'custody.create', 'custody.return',
                'clothes.view',
                'cloth-types.view',
                'inventories.view',
                'branches.view',
                'categories.view',
                'subcategories.view',
                'appointments.*',
            ],
        ],
        'sales_employee' => [
            'description' => 'Sales Employee - Manages clients, sales orders, payments',
            'permissions' => [
                'clients.*',
                'orders.view', 'orders.create', 'orders.update', 'orders.deliver', 'orders.finish',
                'payments.view', 'payments.create', 'payments.pay',
                'custody.view', 'custody.create',
                'clothes.view',
                'cloth-types.view',
                'inventories.view',
                'branches.view',
                'categories.view',
                'subcategories.view',
            ],
        ],
        'factory_manager' => [
            'description' => 'Factory Manager - Manages tailoring orders, factories, transfers',
            'permissions' => [
                'orders.view', 'orders.update',
                'factories.*',
                'workshops.*',
                'transfers.*',
                'clothes.view', 'clothes.update',
                'cloth-types.view',
                'inventories.view',
                'tailoring.*',
                'evaluations.*',
            ],
        ],
        'workshop_manager' => [
            'description' => 'Workshop Manager - Manages workshop cloth processing and returns',
            'permissions' => [
                'workshops.view',
                'workshops.manage-clothes',
                'workshops.approve-transfers',
                'workshops.update-status',
                'workshops.return-cloth',
                'workshops.view-logs',
                'transfers.view',
                'transfers.approve',
                'clothes.view',
                'inventories.view',
                'notifications.view',
            ],
        ],
        'accountant' => [
            'description' => 'Accountant - Manages payments, custody, financial reports',
            'permissions' => [
                'payments.*',
                'custody.*',
                'cashbox.*',
                'transactions.*',
                'expenses.*',
                'receivables.*',
                'reports.financial',
                'orders.view',
                'clients.view',
                'hr.payroll.view',
            ],
        ],
        'hr_manager' => [
            'description' => 'HR Manager - Full access to HR module',
            'permissions' => [
                'hr.departments.*',
                'hr.job-titles.*',
                'hr.employees.*',
                'hr.attendance.*',
                'hr.custody.*',
                'hr.documents.*',
                'hr.deductions.*',
                'hr.payroll.*',
                'hr.activity-log.*',
                'dashboard.view',
                'dashboard.activity.view',
                'dashboard.hr.view',
                'users.view', 'users.create', 'users.update',
                'roles.view',
            ],
        ],
        'employee' => [
            'description' => 'Basic Employee - View own profile and check attendance',
            'permissions' => [
                'hr.attendance.check-in',
                'notifications.view',
            ],
        ],
        'factory_user' => [
            'description' => 'Factory User - Manage tailoring orders assigned to factory',
            'permissions' => [
                'factories.orders.view',
                'factories.orders.accept',
                'factories.orders.reject',
                'factories.orders.update-status',
                'factories.orders.add-notes',
                'factories.orders.set-delivery-date',
                'factories.orders.deliver',
                'factories.reports.view',
                'factories.dashboard.view',
                'notifications.view',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating permissions...');
        
        // Create all permissions
        foreach ($this->permissions as $name => $displayName) {
            $parsed = Permission::parseName($name);
            Permission::firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $displayName,
                    'description' => "Allows user to {$displayName}",
                    'module' => $parsed['module'],
                    'action' => $parsed['action'],
                ]
            );
        }
        
        $this->command->info('Created ' . count($this->permissions) . ' permissions.');

        // Create roles and assign permissions
        $this->command->info('Creating roles and assigning permissions...');
        
        foreach ($this->roles as $roleName => $roleConfig) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['description' => $roleConfig['description']]
            );

            $permissionsToAssign = $this->resolvePermissions($roleConfig['permissions']);
            $role->syncPermissions($permissionsToAssign);
            
            $this->command->info("  - Created role '{$roleName}' with " . count($permissionsToAssign) . " permissions.");
        }

        // Ensure super admin user exists and has general_manager role
        $this->ensureSuperAdmin();

        $this->command->info('Permission seeding completed!');
    }

    /**
     * Resolve permission patterns to actual permission names
     * Supports:
     *   - '*' = all permissions
     *   - 'module.*' = all permissions for a module
     *   - 'module.action' = specific permission
     */
    protected function resolvePermissions($permissions): array
    {
        if ($permissions === '*') {
            return array_keys($this->permissions);
        }

        $resolved = [];
        
        foreach ($permissions as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                // Module wildcard: 'clients.*' matches 'clients.view', 'clients.create', etc.
                $module = substr($pattern, 0, -2);
                foreach (array_keys($this->permissions) as $permName) {
                    if (str_starts_with($permName, $module . '.')) {
                        $resolved[] = $permName;
                    }
                }
            } else {
                // Exact match
                if (isset($this->permissions[$pattern])) {
                    $resolved[] = $pattern;
                }
            }
        }

        return array_unique($resolved);
    }

    /**
     * Ensure super admin user exists
     */
    protected function ensureSuperAdmin(): void
    {
        $superAdminEmail = User::SUPER_ADMIN_EMAIL;
        
        $user = User::where('email', $superAdminEmail)->first();
        
        if ($user) {
            // Ensure super admin has general_manager role
            $generalManagerRole = Role::where('name', 'general_manager')->first();
            if ($generalManagerRole && !$user->hasRole('general_manager')) {
                $user->assignRole($generalManagerRole);
                $this->command->info("Assigned general_manager role to super admin ({$superAdminEmail}).");
            }
        } else {
            // Create super admin user
            $user = User::create([
                'name' => 'Super Admin',
                'email' => $superAdminEmail,
                'password' => bcrypt('admin123'), // Default password - CHANGE IN PRODUCTION!
            ]);
            
            $generalManagerRole = Role::where('name', 'general_manager')->first();
            if ($generalManagerRole) {
                $user->assignRole($generalManagerRole);
            }
            
            $this->command->warn("Created super admin user ({$superAdminEmail}) with default password 'admin123'. CHANGE THIS IN PRODUCTION!");
        }
    }
}

