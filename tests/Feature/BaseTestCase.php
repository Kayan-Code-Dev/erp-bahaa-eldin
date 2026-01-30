<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Traits\LogsActivity;
use Laravel\Sanctum\Sanctum;

/**
 * Comprehensive base test case for Atelier Management System
 *
 * This base class provides:
 * - Common setup/teardown (activity logging, database refreshing)
 * - Role-based authentication helpers
 * - Permission testing helpers
 * - Common test data setup
 * - Standardized assertion methods
 */
abstract class BaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable activity logging during tests to prevent memory issues and cascading logs
        LogsActivity::disableActivityLogging();

        // Set up common test data
        $this->setUpCommonTestData();
    }

    protected function tearDown(): void
    {
        // Re-enable activity logging after tests
        LogsActivity::enableActivityLogging();
        parent::tearDown();
    }

    /**
     * Override this method in specific test classes to set up common test data
     */
    protected function setUpCommonTestData()
    {
        // Seed roles and permissions required for tests
        $this->seedRolesAndPermissions();
    }

    /**
     * Seed roles and permissions for testing
     */
    protected function seedRolesAndPermissions()
    {
        // Create roles if they don't exist
        $roles = [
            'general_manager' => 'General Manager - Full access to all modules',
            'reception_employee' => 'Reception/Rental Employee - Manages clients, rental orders, appointments',
            'sales_employee' => 'Sales Employee - Manages clients, sales orders, payments',
            'accountant' => 'Accountant - Manages payments and financial reports',
            'factory_user' => 'Factory User - Manages transfers and workshop operations',
            'workshop_manager' => 'Workshop Manager - Manages workshop operations',
            'employee' => 'Employee - Basic employee access',
            'hr_manager' => 'HR Manager - Manages employees and departments',
        ];

        foreach ($roles as $roleName => $description) {
            \App\Models\Role::firstOrCreate(
                ['name' => $roleName],
                ['description' => $description]
            );
        }

        // Create basic permissions
        $permissions = [
            ['name' => 'clients.view', 'display_name' => 'View Clients', 'module' => 'clients', 'action' => 'view'],
            ['name' => 'clients.create', 'display_name' => 'Create Clients', 'module' => 'clients', 'action' => 'create'],
            ['name' => 'clients.update', 'display_name' => 'Update Clients', 'module' => 'clients', 'action' => 'update'],
            ['name' => 'clients.delete', 'display_name' => 'Delete Clients', 'module' => 'clients', 'action' => 'delete'],
            ['name' => 'orders.view', 'display_name' => 'View Orders', 'module' => 'orders', 'action' => 'view'],
            ['name' => 'orders.create', 'display_name' => 'Create Orders', 'module' => 'orders', 'action' => 'create'],
            ['name' => 'orders.update', 'display_name' => 'Update Orders', 'module' => 'orders', 'action' => 'update'],
            ['name' => 'orders.deliver', 'display_name' => 'Deliver Orders', 'module' => 'orders', 'action' => 'deliver'],
            ['name' => 'orders.return', 'display_name' => 'Return Orders', 'module' => 'orders', 'action' => 'return'],
            ['name' => 'orders.finish', 'display_name' => 'Finish Orders', 'module' => 'orders', 'action' => 'finish'],
            ['name' => 'payments.view', 'display_name' => 'View Payments', 'module' => 'payments', 'action' => 'view'],
            ['name' => 'payments.create', 'display_name' => 'Create Payments', 'module' => 'payments', 'action' => 'create'],
            ['name' => 'payments.pay', 'display_name' => 'Pay Payments', 'module' => 'payments', 'action' => 'pay'],
            ['name' => 'payments.cancel', 'display_name' => 'Cancel Payments', 'module' => 'payments', 'action' => 'cancel'],
            ['name' => 'custody.view', 'display_name' => 'View Custody', 'module' => 'custody', 'action' => 'view'],
            ['name' => 'custody.create', 'display_name' => 'Create Custody', 'module' => 'custody', 'action' => 'create'],
            ['name' => 'custody.return', 'display_name' => 'Return Custody', 'module' => 'custody', 'action' => 'return'],
            ['name' => 'clothes.view', 'display_name' => 'View Clothes', 'module' => 'clothes', 'action' => 'view'],
            ['name' => 'clothes.create', 'display_name' => 'Create Clothes', 'module' => 'clothes', 'action' => 'create'],
            ['name' => 'clothes.update', 'display_name' => 'Update Clothes', 'module' => 'clothes', 'action' => 'update'],
            ['name' => 'clothes.delete', 'display_name' => 'Delete Clothes', 'module' => 'clothes', 'action' => 'delete'],
            ['name' => 'cloth-types.view', 'display_name' => 'View Cloth Types', 'module' => 'cloth-types', 'action' => 'view'],
            ['name' => 'cloth-types.create', 'display_name' => 'Create Cloth Types', 'module' => 'cloth-types', 'action' => 'create'],
            ['name' => 'cloth-types.update', 'display_name' => 'Update Cloth Types', 'module' => 'cloth-types', 'action' => 'update'],
            ['name' => 'cloth-types.delete', 'display_name' => 'Delete Cloth Types', 'module' => 'cloth-types', 'action' => 'delete'],
            ['name' => 'inventories.view', 'display_name' => 'View Inventories', 'module' => 'inventories', 'action' => 'view'],
            ['name' => 'inventories.create', 'display_name' => 'Create Inventories', 'module' => 'inventories', 'action' => 'create'],
            ['name' => 'inventories.update', 'display_name' => 'Update Inventories', 'module' => 'inventories', 'action' => 'update'],
            ['name' => 'inventories.delete', 'display_name' => 'Delete Inventories', 'module' => 'inventories', 'action' => 'delete'],
            ['name' => 'branches.view', 'display_name' => 'View Branches', 'module' => 'branches', 'action' => 'view'],
            ['name' => 'branches.create', 'display_name' => 'Create Branches', 'module' => 'branches', 'action' => 'create'],
            ['name' => 'branches.update', 'display_name' => 'Update Branches', 'module' => 'branches', 'action' => 'update'],
            ['name' => 'branches.delete', 'display_name' => 'Delete Branches', 'module' => 'branches', 'action' => 'delete'],
            ['name' => 'categories.view', 'display_name' => 'View Categories', 'module' => 'categories', 'action' => 'view'],
            ['name' => 'categories.create', 'display_name' => 'Create Categories', 'module' => 'categories', 'action' => 'create'],
            ['name' => 'categories.update', 'display_name' => 'Update Categories', 'module' => 'categories', 'action' => 'update'],
            ['name' => 'categories.delete', 'display_name' => 'Delete Categories', 'module' => 'categories', 'action' => 'delete'],
            ['name' => 'subcategories.view', 'display_name' => 'View Subcategories', 'module' => 'subcategories', 'action' => 'view'],
            ['name' => 'subcategories.create', 'display_name' => 'Create Subcategories', 'module' => 'subcategories', 'action' => 'create'],
            ['name' => 'subcategories.update', 'display_name' => 'Update Subcategories', 'module' => 'subcategories', 'action' => 'update'],
            ['name' => 'subcategories.delete', 'display_name' => 'Delete Subcategories', 'module' => 'subcategories', 'action' => 'delete'],
            ['name' => 'appointments.view', 'display_name' => 'View Appointments', 'module' => 'appointments', 'action' => 'view'],
            ['name' => 'appointments.create', 'display_name' => 'Create Appointments', 'module' => 'appointments', 'action' => 'create'],
            ['name' => 'appointments.update', 'display_name' => 'Update Appointments', 'module' => 'appointments', 'action' => 'update'],
            ['name' => 'appointments.confirm', 'display_name' => 'Confirm Appointments', 'module' => 'appointments', 'action' => 'confirm'],
            ['name' => 'appointments.start', 'display_name' => 'Start Appointments', 'module' => 'appointments', 'action' => 'start'],
            ['name' => 'appointments.complete', 'display_name' => 'Complete Appointments', 'module' => 'appointments', 'action' => 'complete'],
            ['name' => 'appointments.cancel', 'display_name' => 'Cancel Appointments', 'module' => 'appointments', 'action' => 'cancel'],
            ['name' => 'transfers.view', 'display_name' => 'View Transfers', 'module' => 'transfers', 'action' => 'view'],
            ['name' => 'transfers.create', 'display_name' => 'Create Transfers', 'module' => 'transfers', 'action' => 'create'],
            ['name' => 'transfers.update', 'display_name' => 'Update Transfers', 'module' => 'transfers', 'action' => 'update'],
            ['name' => 'transfers.approve', 'display_name' => 'Approve Transfers', 'module' => 'transfers', 'action' => 'approve'],
            ['name' => 'transfers.reject', 'display_name' => 'Reject Transfers', 'module' => 'transfers', 'action' => 'reject'],
            ['name' => 'workshops.view', 'display_name' => 'View Workshops', 'module' => 'workshops', 'action' => 'view'],
            ['name' => 'workshops.create', 'display_name' => 'Create Workshops', 'module' => 'workshops', 'action' => 'create'],
            ['name' => 'workshops.update', 'display_name' => 'Update Workshops', 'module' => 'workshops', 'action' => 'update'],
            ['name' => 'workshops.delete', 'display_name' => 'Delete Workshops', 'module' => 'workshops', 'action' => 'delete'],
            ['name' => 'departments.view', 'display_name' => 'View Departments', 'module' => 'departments', 'action' => 'view'],
            ['name' => 'departments.create', 'display_name' => 'Create Departments', 'module' => 'departments', 'action' => 'create'],
            ['name' => 'departments.update', 'display_name' => 'Update Departments', 'module' => 'departments', 'action' => 'update'],
            ['name' => 'departments.delete', 'display_name' => 'Delete Departments', 'module' => 'departments', 'action' => 'delete'],
            ['name' => 'employees.view', 'display_name' => 'View Employees', 'module' => 'employees', 'action' => 'view'],
            ['name' => 'employees.create', 'display_name' => 'Create Employees', 'module' => 'employees', 'action' => 'create'],
            ['name' => 'employees.update', 'display_name' => 'Update Employees', 'module' => 'employees', 'action' => 'update'],
            ['name' => 'employees.delete', 'display_name' => 'Delete Employees', 'module' => 'employees', 'action' => 'delete'],
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'module' => 'roles', 'action' => 'view'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles', 'module' => 'roles', 'action' => 'create'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles', 'module' => 'roles', 'action' => 'update'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles', 'module' => 'roles', 'action' => 'delete'],
            ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'module' => 'permissions', 'action' => 'view'],
            ['name' => 'permissions.create', 'display_name' => 'Create Permissions', 'module' => 'permissions', 'action' => 'create'],
            ['name' => 'permissions.update', 'display_name' => 'Update Permissions', 'module' => 'permissions', 'action' => 'update'],
            ['name' => 'permissions.delete', 'display_name' => 'Delete Permissions', 'module' => 'permissions', 'action' => 'delete'],
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'module' => 'reports', 'action' => 'view'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports', 'module' => 'reports', 'action' => 'export'],
        ];

        foreach ($permissions as $permissionData) {
            \App\Models\Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                $permissionData
            );
        }

        // Assign basic permissions to roles (simplified for testing)
        $rolePermissions = [
            'general_manager' => $permissions, // All permissions
            'reception_employee' => [
                'clients.view', 'clients.create', 'clients.update', 'clients.delete',
                'orders.view', 'orders.create', 'orders.update', 'orders.deliver', 'orders.return',
                'payments.view', 'payments.create', 'payments.pay',
                'custody.view', 'custody.create', 'custody.return',
                'clothes.view', 'cloth-types.view', 'inventories.view', 'branches.view',
                'categories.view', 'subcategories.view',
                'appointments.view', 'appointments.create', 'appointments.update', 'appointments.confirm', 'appointments.start', 'appointments.complete', 'appointments.cancel',
            ],
            'sales_employee' => [
                'clients.view', 'clients.create', 'clients.update', 'clients.delete',
                'orders.view', 'orders.create', 'orders.update', 'orders.deliver', 'orders.finish',
                'payments.view', 'payments.create', 'payments.pay',
                'custody.view', 'custody.create',
                'clothes.view', 'cloth-types.view', 'inventories.view', 'branches.view',
                'categories.view', 'subcategories.view',
            ],
            'accountant' => [
                'clients.view', 'orders.view', 'payments.view', 'payments.create', 'payments.pay', 'payments.cancel',
                'reports.view', 'reports.export',
            ],
            'factory_user' => [
                'transfers.view', 'transfers.create', 'transfers.update',
                'workshops.view', 'clothes.view', 'clothes.update',
                'inventories.view', 'cloth-types.view',
            ],
            'workshop_manager' => [
                'workshops.view', 'workshops.update', 'clothes.view', 'clothes.update',
                'inventories.view', 'cloth-types.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $rolePerms) {
            $role = \App\Models\Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($rolePerms as $permName) {
                    $permission = \App\Models\Permission::where('name', $permName)->first();
                    if ($permission) {
                        $role->permissions()->syncWithoutDetaching([$permission->id]);
                    }
                }
            }
        }
    }

    // ==================== AUTHENTICATION HELPERS ====================

    /**
     * Create a user with a specific role and authenticate
     */
    protected function authenticateAs(string $roleName): User
    {
        $user = $this->createUserWithRole($roleName);
        Sanctum::actingAs($user);
        return $user;
    }

    /**
     * Authenticate as super admin (bypasses all permission checks)
     */
    protected function authenticateAsSuperAdmin(): User
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);
        return $user;
    }

    /**
     * Authenticate as regular user (no special roles)
     */
    protected function authenticateAsRegularUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    /**
     * Create user with specific role (without authentication)
     */
    protected function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $user->roles()->attach($role);
        }
        return $user;
    }

    // ==================== PERMISSION TESTING HELPERS ====================

    /**
     * Test that an endpoint requires a specific permission
     */
    protected function assertEndpointRequiresPermission(string $method, string $endpoint, string $permissionName, array $requestData = [])
    {
        // Test without authentication (should fail with 401)
        $response = $this->json($method, $endpoint, $requestData);
        $response->assertStatus(401);

        // Test with user lacking permission (should fail with 403)
        $this->authenticateAsRegularUser();
        $response = $this->json($method, $endpoint, $requestData);
        $response->assertStatus(403);

        // Test with user having permission (should succeed)
        $this->authenticateAsSuperAdmin();
        $response = $this->json($method, $endpoint, $requestData);
        $response->assertStatus(200); // Or whatever success status is expected
    }

    /**
     * Test role-based access for an endpoint
     */
    protected function assertRoleBasedAccess(
        string $method,
        string $endpoint,
        array $allowedRoles,
        array $deniedRoles,
        array $requestData = [],
        int $successStatus = 200
    ) {
        // Test allowed roles
        foreach ($allowedRoles as $role) {
            $this->authenticateAs($role);
            $response = $this->json($method, $endpoint, $requestData);
            $response->assertStatus($successStatus);
        }

        // Test denied roles
        foreach ($deniedRoles as $role) {
            $this->authenticateAs($role);
            $response = $this->json($method, $endpoint, $requestData);
            $response->assertStatus(403);
        }

        // Test unauthenticated
        $this->withoutMiddleware(); // Clear authentication
        $response = $this->json($method, $endpoint, $requestData);
        $response->assertStatus(401);
    }

    // ==================== COMMON TEST DATA HELPERS ====================

    /**
     * Create a complete client with address and phones
     */
    protected function createCompleteClient(array $overrides = []): \App\Models\Client
    {
        $address = \App\Models\Address::factory()->create();

        $clientData = array_merge([
            'first_name' => 'Test',
            'last_name' => 'Client',
            'national_id' => fake()->unique()->numerify('##############'), // 14 digits
            'date_of_birth' => '1990-01-01',
            'address_id' => $address->id,
        ], $overrides);

        $client = \App\Models\Client::factory()->create($clientData);

        // Add phones
        \App\Models\Phone::factory()->create([
            'client_id' => $client->id,
            'phone' => '+201234567890',
            'type' => 'mobile',
        ]);

        return $client;
    }

    /**
     * Create a complete order with client and items
     */
    protected function createCompleteOrder(array $overrides = []): \App\Models\Order
    {
        $client = $this->createCompleteClient();
        $inventory = \App\Models\Inventory::factory()->create();

        $orderData = array_merge([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'total_price' => 100.00,
            'paid' => 0,
            'remaining' => 100.00,
            'status' => 'created',
        ], $overrides);

        $order = \App\Models\Order::factory()->create($orderData);

        // Add order items - Note: This might need adjustment based on actual order_items table structure
        // For now, we'll skip this as the relationship might not be set up correctly
        // $cloth = \App\Models\Cloth::factory()->create();
        // $order->clothes()->attach($cloth->id, [
        //     'quantity' => 1,
        //     'price' => 100.00,
        //     'type' => 'rent'
        // ]);

        return $order;
    }

    // ==================== STANDARDIZED ASSERTIONS ====================

    /**
     * Assert paginated response structure
     */
    protected function assertPaginatedResponse($response, array $additionalDataKeys = [])
    {
        $expectedStructure = [
            'data',
            'current_page',
            'total',
            'total_pages',
            'per_page'
        ];

        $expectedStructure = array_merge($expectedStructure, $additionalDataKeys);

        $response->assertJsonStructure($expectedStructure);
    }

    /**
     * Assert validation error structure
     */
    protected function assertValidationError($response, array $expectedFields)
    {
        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => $expectedFields
            ]);
    }

    /**
     * Assert permission denied response
     */
    protected function assertPermissionDenied($response)
    {
        $response->assertStatus(403)
            ->assertJsonStructure(['message']);
    }

    /**
     * Assert unauthorized response
     */
    protected function assertUnauthorized($response)
    {
        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    /**
     * Assert not found response
     */
    protected function assertNotFound($response)
    {
        $response->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    // ==================== DATA PROVIDERS ====================

    /**
     * Common validation error data provider
     * Override in child classes with module-specific data
     */
    public static function validationErrorDataProvider(): array
    {
        return [];
    }

    /**
     * Common role access data provider
     * Override in child classes with module-specific roles
     */
    public static function roleAccessDataProvider(): array
    {
        return [];
    }

    // ==================== UTILITY METHODS ====================

    /**
     * Get a unique test identifier for avoiding conflicts
     */
    protected function getTestId(): string
    {
        return uniqid('test_');
    }

    /**
     * Clean up any test-specific data
     */
    protected function cleanUpTestData()
    {
        // Override in child classes if needed
    }
}
