<?php

namespace Tests\Feature\Client;

use Tests\Feature\BaseTestCase;
use App\Models\Client;
use App\Models\Address;
use App\Models\Phone;
use App\Models\Order;

/**
 * Client CRUD Operations Tests
 *
 * Tests all basic CRUD operations for clients according to TEST_COVERAGE.md specification
 */
class ClientCrudTest extends BaseTestCase
{
    // ==================== LIST CLIENTS ====================

    /**
     * Test: List Clients
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: GET /api/v1/clients
     * - Required Permission: clients.view
     * - Expected Status: 200
     * - Description: List all clients with pagination and filtering
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_client_list_by_general_manager_succeeds()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(3, 'data');
    }

    public function test_client_list_by_reception_employee_succeeds()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_client_list_by_sales_employee_succeeds()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAs('sales_employee');

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_client_list_by_accountant_succeeds()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAs('accountant');

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_client_list_by_factory_user_fails_403()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAs('factory_user');

        $response = $this->getJson('/api/v1/clients');

        $this->assertPermissionDenied($response);
    }

    public function test_client_list_by_workshop_manager_fails_403()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAs('workshop_manager');

        $response = $this->getJson('/api/v1/clients');

        $this->assertPermissionDenied($response);
    }

    public function test_client_list_by_employee_fails_403()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAs('employee');

        $response = $this->getJson('/api/v1/clients');

        $this->assertPermissionDenied($response);
    }

    public function test_client_list_by_hr_manager_fails_403()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAs('hr_manager');

        $response = $this->getJson('/api/v1/clients');

        $this->assertPermissionDenied($response);
    }

    public function test_client_list_by_unauthenticated_fails_401()
    {
        Client::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/clients');

        $this->assertUnauthorized($response);
    }

    public function test_client_list_with_search_filter_works()
    {
        $client1 = Client::factory()->create(['first_name' => 'John']);
        $client2 = Client::factory()->create(['first_name' => 'Jane']);
        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/clients?search=John');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $client1->id]);
        $response->assertJsonMissing(['id' => $client2->id]);
    }

    // ==================== CREATE CLIENT ====================

    /**
     * Test: Create Client
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: POST /api/v1/clients
     * - Required Permission: clients.create
     * - Expected Status: 201
     * - Description: Create a new client with all required fields
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: accountant, factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_client_create_with_valid_data_returns_201()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'date_of_birth' => '1990-01-01',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
                'notes' => $address->notes,
            ],
            'phones' => [
                [
                    'phone_number' => '+201234567890',
                    'phone_type' => 'mobile',
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $response->assertStatus(201)
            ->assertJson([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'national_id' => '12345678901234',
            ]);

        $this->assertDatabaseHas('clients', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
        ]);

        // Verify address was created
        $this->assertDatabaseHas('addresses', [
            'street' => $address->street,
            'building' => $address->building,
            'city_id' => $address->city_id,
        ]);

        // Verify phone was created
        $this->assertDatabaseHas('phones', [
            'phone_number' => '+201234567890',
            'phone_type' => 'mobile',
        ]);
    }

    public function test_client_create_by_general_manager_succeeds()
    {
        $this->authenticateAsSuperAdmin();

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

        $response->assertStatus(201);
    }

    public function test_client_create_by_accountant_fails_403()
    {
        $this->authenticateAs('accountant');

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

        $this->assertPermissionDenied($response);
    }

    public function test_client_create_by_factory_user_fails_403()
    {
        $this->authenticateAs('factory_user');

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

        $this->assertPermissionDenied($response);
    }

    public function test_client_create_by_unauthenticated_fails_401()
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

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertUnauthorized($response);
    }

    // ==================== SHOW CLIENT ====================

    /**
     * Test: Show Client
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: GET /api/v1/clients/{id}
     * - Required Permission: clients.view
     * - Expected Status: 200
     * - Description: Get single client details with relationships
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_client_show_by_general_manager_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $client->id])
            ->assertJsonStructure([
                'id', 'first_name', 'last_name', 'national_id',
                'phones', 'address'
            ]);
    }

    public function test_client_show_by_reception_employee_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(200);
    }

    public function test_client_show_by_accountant_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('accountant');

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(200);
    }

    public function test_client_show_by_factory_user_fails_403()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('factory_user');

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $this->assertPermissionDenied($response);
    }

    public function test_client_show_nonexistent_client_fails_404()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/clients/99999');

        $this->assertNotFound($response);
    }

    public function test_client_show_by_unauthenticated_fails_401()
    {
        $client = $this->createCompleteClient();

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $this->assertUnauthorized($response);
    }

    // ==================== UPDATE CLIENT ====================

    /**
     * Test: Update Client
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: PUT /api/v1/clients/{id}
     * - Required Permission: clients.update
     * - Expected Status: 200
     * - Description: Update client details
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: accountant, factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_client_update_with_valid_data_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        $updateData = [
            'first_name' => 'Updated',
            'last_name' => $client->last_name,
            'national_id' => $client->national_id,
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'first_name' => 'Updated',
        ]);
    }

    public function test_client_update_by_general_manager_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAsSuperAdmin();

        $updateData = [
            'first_name' => 'Updated',
            'last_name' => $client->last_name,
            'national_id' => $client->national_id,
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}", $updateData);

        $response->assertStatus(200);
    }

    public function test_client_update_by_accountant_fails_403()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('accountant');

        $updateData = [
            'first_name' => 'Updated',
            'last_name' => $client->last_name,
            'national_id' => $client->national_id,
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}", $updateData);

        $this->assertPermissionDenied($response);
    }

    public function test_client_update_nonexistent_client_fails_404()
    {
        $this->authenticateAs('reception_employee');

        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'Test',
            'national_id' => '12345678901234',
        ];

        $response = $this->putJson('/api/v1/clients/99999', $updateData);

        $this->assertNotFound($response);
    }

    public function test_client_update_by_unauthenticated_fails_401()
    {
        $client = $this->createCompleteClient();

        $updateData = [
            'first_name' => 'Updated',
            'last_name' => $client->last_name,
            'national_id' => $client->national_id,
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}", $updateData);

        $this->assertUnauthorized($response);
    }

    // ==================== DELETE CLIENT ====================

    /**
     * Test: Delete Client
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: DELETE /api/v1/clients/{id}
     * - Required Permission: clients.delete
     * - Expected Status: 200/204
     * - Description: Soft delete a client
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: accountant, factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_client_delete_without_orders_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($client);
    }

    public function test_client_delete_with_orders_fails()
    {
        $client = $this->createCompleteClient();
        // Create an order for this client
        Order::factory()->create(['client_id' => $client->id]);
        $this->authenticateAs('reception_employee');

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        // Depending on your business rules, this might return 422 or succeed
        // For now, we'll assume it's prevented
        $response->assertStatus(422);
    }

    public function test_client_delete_by_accountant_fails_403()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('accountant');

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        $this->assertPermissionDenied($response);
    }

    public function test_client_delete_nonexistent_client_fails_404()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->deleteJson('/api/v1/clients/99999');

        $this->assertNotFound($response);
    }

    public function test_client_delete_by_unauthenticated_fails_401()
    {
        $client = $this->createCompleteClient();

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        $this->assertUnauthorized($response);
    }

    // ==================== EXPORT CLIENTS ====================

    /**
     * Test: Export Clients
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: GET /api/v1/clients/export
     * - Required Permission: clients.export
     * - Expected Status: 200
     * - Description: Export clients to file
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: accountant, factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_client_export_by_reception_employee_succeeds()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAs('reception_employee');

        $response = $this->get('/api/v1/clients/export');

        $response->assertStatus(200);
        // Note: This would typically return a file download, so we check headers
        $response->assertHeader('Content-Type', 'text/csv');
    }

    public function test_client_export_by_accountant_fails_403()
    {
        Client::factory()->count(3)->create();
        $this->authenticateAs('accountant');

        $response = $this->get('/api/v1/clients/export');

        $this->assertPermissionDenied($response);
    }
}
