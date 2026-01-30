<?php

namespace Tests\Feature\Client;

use Tests\Feature\BaseTestCase;
use App\Models\Client;
use App\Models\Address;

/**
 * Client Permission Tests
 *
 * Tests role-based permissions for client operations according to TEST_COVERAGE.md specification
 */
class ClientPermissionTest extends BaseTestCase
{
    /**
     * Test: Client Create Permission Requirements
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify that clients.create permission is required for creating clients
     */
    public function test_client_create_requires_clients_create_permission()
    {
        $address = Address::factory()->create();

        $data = [
            'first_name' => 'Test',
            'last_name' => 'Client',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $this->assertEndpointRequiresPermission('POST', '/api/v1/clients', 'clients.create', $data);
    }

    /**
     * Test: Client View Permission Requirements
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify that clients.view permission is required for viewing clients
     */
    public function test_client_view_requires_clients_view_permission()
    {
        $client = Client::factory()->create();

        $this->assertEndpointRequiresPermission('GET', "/api/v1/clients/{$client->id}", 'clients.view');
    }

    /**
     * Test: Client Update Permission Requirements
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify that clients.update permission is required for updating clients
     */
    public function test_client_update_requires_clients_update_permission()
    {
        $client = Client::factory()->create();

        $data = [
            'first_name' => 'Updated',
            'last_name' => $client->last_name,
            'national_id' => $client->national_id,
        ];

        $this->assertEndpointRequiresPermission('PUT', "/api/v1/clients/{$client->id}", 'clients.update', $data);
    }

    /**
     * Test: Client Delete Permission Requirements
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify that clients.delete permission is required for deleting clients
     */
    public function test_client_delete_requires_clients_delete_permission()
    {
        $client = Client::factory()->create();

        $this->assertEndpointRequiresPermission('DELETE', "/api/v1/clients/{$client->id}", 'clients.delete');
    }

    /**
     * Test: Client Export Permission Requirements
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify that clients.export permission is required for exporting clients
     */
    public function test_client_export_requires_clients_export_permission()
    {
        Client::factory()->count(3)->create();

        $this->assertEndpointRequiresPermission('GET', '/api/v1/clients/export', 'clients.export');
    }

    /**
     * Test: Super Admin Bypasses Client Permissions
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify that superadmin@example.com can access all client endpoints regardless of roles
     */
    public function test_super_admin_can_access_all_client_endpoints()
    {
        $client = Client::factory()->create();
        $address = Address::factory()->create();

        // Test create
        $createData = [
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $this->authenticateAsSuperAdmin();
        $response = $this->postJson('/api/v1/clients', $createData);
        $response->assertStatus(201);

        // Test list
        $response = $this->getJson('/api/v1/clients');
        $response->assertStatus(200);

        // Test show
        $response = $this->getJson("/api/v1/clients/{$client->id}");
        $response->assertStatus(200);

        // Test update
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => $client->last_name,
            'national_id' => $client->national_id,
        ];
        $response = $this->putJson("/api/v1/clients/{$client->id}", $updateData);
        $response->assertStatus(200);

        // Test delete
        $response = $this->deleteJson("/api/v1/clients/{$client->id}");
        $response->assertStatus(200);

        // Test export
        $response = $this->get('/api/v1/clients/export');
        $response->assertStatus(200);
    }

    /**
     * Test: Role-Based Access Matrix for Clients
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Comprehensive test of role-based access for all client endpoints
     */
    public function test_client_role_based_access_matrix()
    {
        $client = Client::factory()->create();
        $address = Address::factory()->create();

        $testData = [
            'create' => [
                'method' => 'POST',
                'endpoint' => '/api/v1/clients',
                'data' => [
                    'first_name' => 'Test',
                    'last_name' => 'Client',
                    'national_id' => '12345678901234',
                    'address' => [
                        'street' => $address->street,
                        'building' => $address->building,
                        'city_id' => $address->city_id,
                    ],
                    'phones' => [
                        ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
                    ]
                ],
                'allowed_roles' => ['general_manager', 'reception_employee', 'sales_employee'],
                'denied_roles' => ['accountant', 'factory_user', 'workshop_manager', 'employee', 'hr_manager'],
                'success_status' => 201
            ],
            'list' => [
                'method' => 'GET',
                'endpoint' => '/api/v1/clients',
                'data' => [],
                'allowed_roles' => ['general_manager', 'reception_employee', 'sales_employee', 'accountant'],
                'denied_roles' => ['factory_user', 'workshop_manager', 'employee', 'hr_manager'],
                'success_status' => 200
            ],
            'show' => [
                'method' => 'GET',
                'endpoint' => "/api/v1/clients/{$client->id}",
                'data' => [],
                'allowed_roles' => ['general_manager', 'reception_employee', 'sales_employee', 'accountant'],
                'denied_roles' => ['factory_user', 'workshop_manager', 'employee', 'hr_manager'],
                'success_status' => 200
            ],
            'update' => [
                'method' => 'PUT',
                'endpoint' => "/api/v1/clients/{$client->id}",
                'data' => [
                    'first_name' => 'Updated',
                    'last_name' => $client->last_name,
                    'national_id' => $client->national_id,
                ],
                'allowed_roles' => ['general_manager', 'reception_employee', 'sales_employee'],
                'denied_roles' => ['accountant', 'factory_user', 'workshop_manager', 'employee', 'hr_manager'],
                'success_status' => 200
            ],
            'delete' => [
                'method' => 'DELETE',
                'endpoint' => "/api/v1/clients/{$client->id}",
                'data' => [],
                'allowed_roles' => ['general_manager', 'reception_employee', 'sales_employee'],
                'denied_roles' => ['accountant', 'factory_user', 'workshop_manager', 'employee', 'hr_manager'],
                'success_status' => 200
            ],
            'export' => [
                'method' => 'GET',
                'endpoint' => '/api/v1/clients/export',
                'data' => [],
                'allowed_roles' => ['general_manager', 'reception_employee', 'sales_employee'],
                'denied_roles' => ['accountant', 'factory_user', 'workshop_manager', 'employee', 'hr_manager'],
                'success_status' => 200
            ]
        ];

        foreach ($testData as $operation => $config) {
            // Test allowed roles
            foreach ($config['allowed_roles'] as $role) {
                $this->authenticateAs($role);
                $response = $this->json($config['method'], $config['endpoint'], $config['data']);
                $response->assertStatus($config['success_status']);
            }

            // Test denied roles
            foreach ($config['denied_roles'] as $role) {
                $this->authenticateAs($role);
                $response = $this->json($config['method'], $config['endpoint'], $config['data']);
                $response->assertStatus(403);
            }

            // Test unauthenticated (except for export which might be handled differently)
            if ($operation !== 'export') {
                $this->withoutMiddleware();
                $response = $this->json($config['method'], $config['endpoint'], $config['data']);
                $response->assertStatus(401);
            }
        }
    }

    /**
     * Test: User with Multiple Roles Has Combined Permissions
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify that users with multiple roles have combined permissions
     */
    public function test_user_with_multiple_roles_has_combined_permissions()
    {
        // This test would require creating a user with multiple roles
        // Implementation depends on your role assignment system
        $this->markTestSkipped('Multiple role assignment test - implement based on your role system');
    }

    /**
     * Test: Permission Inheritance Works Correctly
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify that users inherit permissions from their assigned roles
     */
    public function test_permission_inheritance_works_correctly()
    {
        // Create a role with client permissions
        $role = \App\Models\Role::factory()->create(['name' => 'test_client_role']);
        $role->permissions()->attach(\App\Models\Permission::where('name', 'clients.view')->first());

        // Create user with this role
        $user = $this->createUserWithRole('test_client_role');
        Sanctum::actingAs($user);

        // Test that user can view clients
        Client::factory()->count(3)->create();
        $response = $this->getJson('/api/v1/clients');
        $response->assertStatus(200);

        // Test that user cannot create clients (permission not assigned)
        $address = Address::factory()->create();
        $data = [
            'first_name' => 'Test',
            'last_name' => 'Client',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);
        $response->assertStatus(403);
    }
}
