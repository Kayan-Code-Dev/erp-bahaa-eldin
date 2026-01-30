<?php

namespace Tests\Feature\Core;

use Tests\Feature\BaseTestCase;
use App\Models\Branch;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;

/**
 * Branch CRUD Tests
 *
 * Tests all basic CRUD operations for branches according to TEST_COVERAGE.md specification
 */
class BranchTest extends BaseTestCase
{
    // ==================== LIST BRANCHES ====================

    /**
     * Test: List Branches
     * - Type: Feature Test
     * - Module: Branches
     * - Endpoint: GET /api/v1/branches
     * - Required Permission: branches.view
     * - Expected Status: 200
     * - Description: List all branches with pagination and filtering
     * - Should Pass For: general_manager, roles with branches.view
     * - Should Fail For: Users without branches.view permission (403), unauthenticated (401)
     */

    public function test_branch_list_by_general_manager_succeeds()
    {
        Branch::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/branches');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    // ==================== CREATE BRANCH ====================

    /**
     * Test: Create Branch
     * - Type: Feature Test
     * - Module: Branches
     * - Endpoint: POST /api/v1/branches
     * - Required Permission: branches.create
     * - Expected Status: 201
     * - Description: Create a new branch
     * - Should Pass For: general_manager, roles with branches.create
     * - Should Fail For: Users without branches.create (403), invalid data (422)
     */

    public function test_branch_create_with_valid_data_returns_201()
    {
        $address = Address::factory()->create();
        $this->authenticateAsSuperAdmin();

        $data = [
            'branch_code' => 'BR-001',
            'name' => 'Main Branch',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
                'notes' => $address->notes,
            ]
        ];

        $response = $this->postJson('/api/v1/branches', $data);

        $response->assertStatus(201)
            ->assertJson([
                'branch_code' => 'BR-001',
                'name' => 'Main Branch',
            ]);

        $this->assertDatabaseHas('branches', [
            'branch_code' => 'BR-001',
            'name' => 'Main Branch',
        ]);

        // Verify address was created
        $this->assertDatabaseHas('addresses', [
            'street' => $address->street,
            'building' => $address->building,
            'city_id' => $address->city_id,
        ]);

        // Verify inventory was auto-created
        $branch = Branch::where('branch_code', 'BR-001')->first();
        $this->assertNotNull($branch->inventory);
        $this->assertEquals('Main Branch Inventory', $branch->inventory->name);

        // Verify cashbox was auto-created
        $this->assertNotNull($branch->cashbox);
        $this->assertEquals('Main Branch Cashbox', $branch->cashbox->name);
    }

    // ==================== SHOW BRANCH ====================

    /**
     * Test: Show Branch
     * - Type: Feature Test
     * - Module: Branches
     * - Endpoint: GET /api/v1/branches/{id}
     * - Required Permission: branches.view
     * - Expected Status: 200
     * - Description: Get single branch details
     * - Should Pass For: general_manager, roles with branches.view
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_branch_show_by_general_manager_succeeds()
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/branches/{$branch->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $branch->id])
            ->assertJsonStructure([
                'id', 'branch_code', 'name',
                'inventory' => ['id', 'name'],
                'cashbox' => ['id', 'name'],
                'address' => ['street', 'building', 'city']
            ]);
    }

    // ==================== UPDATE BRANCH ====================

    /**
     * Test: Update Branch
     * - Type: Feature Test
     * - Module: Branches
     * - Endpoint: PUT /api/v1/branches/{id}
     * - Required Permission: branches.update
     * - Expected Status: 200
     * - Description: Update branch details
     * - Should Pass For: general_manager, roles with branches.update
     * - Should Fail For: Users without permission (403), invalid data (422)
     */

    public function test_branch_update_with_valid_data_succeeds()
    {
        $branch = Branch::factory()->create();
        $newAddress = Address::factory()->create();
        $this->authenticateAsSuperAdmin();

        $updateData = [
            'branch_code' => 'BR-UPDATED',
            'name' => 'Updated Branch',
            'address' => [
                'street' => $newAddress->street,
                'building' => $newAddress->building,
                'city_id' => $newAddress->city_id,
            ]
        ];

        $response = $this->putJson("/api/v1/branches/{$branch->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'branch_code' => 'BR-UPDATED',
            'name' => 'Updated Branch',
        ]);
    }

    // ==================== DELETE BRANCH ====================

    /**
     * Test: Delete Branch
     * - Type: Feature Test
     * - Module: Branches
     * - Endpoint: DELETE /api/v1/branches/{id}
     * - Required Permission: branches.delete
     * - Expected Status: 200/204
     * - Description: Delete a branch
     * - Should Pass For: general_manager, roles with branches.delete
     * - Should Fail For: Users without permission (403), branch with orders (409/422)
     */

    public function test_branch_delete_without_orders_succeeds()
    {
        $branch = Branch::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->deleteJson("/api/v1/branches/{$branch->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($branch);
    }

    // ==================== DELETE BRANCH WITH ORDERS (SHOULD FAIL) ====================

    /**
     * Test: Delete Branch with Orders (Should Fail)
     * - Type: Feature Test
     * - Module: Branches
     * - Endpoint: DELETE /api/v1/branches/{id}
     * - Expected Status: 422/409
     * - Description: Cannot delete branch that has orders
     */

    public function test_branch_delete_with_orders_fails()
    {
        $branch = Branch::factory()->create();
        // Create an order for this branch
        \App\Models\Order::factory()->create(['entity_type' => 'branch', 'entity_id' => $branch->id]);
        $this->authenticateAsSuperAdmin();

        $response = $this->deleteJson("/api/v1/branches/{$branch->id}");

        $response->assertStatus(422); // Or 409 depending on implementation
    }

    // ==================== EXPORT BRANCHES ====================

    /**
     * Test: Export Branches
     * - Type: Feature Test
     * - Module: Branches
     * - Endpoint: GET /api/v1/branches/export
     * - Required Permission: branches.export
     * - Expected Status: 200
     * - Description: Export branches to file
     * - Should Pass For: general_manager, roles with branches.export
     * - Should Fail For: Users without permission (403)
     */

    public function test_branch_export_by_general_manager_succeeds()
    {
        Branch::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->get('/api/v1/branches/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
    }

    // ==================== VALIDATION TESTS ====================

    public function test_branch_create_without_required_fields_fails_422()
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/api/v1/branches', []);

        $this->assertValidationError($response, ['branch_code', 'name', 'address']);
    }

    public function test_branch_create_with_duplicate_branch_code_fails_422()
    {
        Branch::factory()->create(['branch_code' => 'BR-001']);

        $this->authenticateAsSuperAdmin();

        $address = Address::factory()->create();

        $data = [
            'branch_code' => 'BR-001', // Duplicate
            'name' => 'Different Branch',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ]
        ];

        $response = $this->postJson('/api/v1/branches', $data);

        $this->assertValidationError($response, ['branch_code']);
    }

    public function test_branch_create_with_invalid_branch_code_format_fails_422()
    {
        $this->authenticateAsSuperAdmin();

        $address = Address::factory()->create();

        $data = [
            'branch_code' => 'INVALID-CODE', // Invalid format
            'name' => 'Test Branch',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ]
        ];

        $response = $this->postJson('/api/v1/branches', $data);

        $this->assertValidationError($response, ['branch_code']);
    }

    public function test_branch_create_with_name_too_long_fails_422()
    {
        $this->authenticateAsSuperAdmin();

        $address = Address::factory()->create();

        $data = [
            'branch_code' => 'BR-001',
            'name' => str_repeat('A', 256), // Too long
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ]
        ];

        $response = $this->postJson('/api/v1/branches', $data);

        $this->assertValidationError($response, ['name']);
    }

    // ==================== BRANCH AUTO-CREATION TESTS ====================

    public function test_branch_creation_auto_creates_inventory()
    {
        $address = Address::factory()->create();
        $this->authenticateAsSuperAdmin();

        $data = [
            'branch_code' => 'BR-AUTO',
            'name' => 'Auto Branch',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ]
        ];

        $this->postJson('/api/v1/branches', $data);

        $branch = Branch::where('branch_code', 'BR-AUTO')->first();
        $this->assertNotNull($branch->inventory);
        $this->assertEquals('Auto Branch Inventory', $branch->inventory->name);
    }

    public function test_branch_creation_auto_creates_cashbox()
    {
        $address = Address::factory()->create();
        $this->authenticateAsSuperAdmin();

        $data = [
            'branch_code' => 'BR-AUTO',
            'name' => 'Auto Branch',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ]
        ];

        $this->postJson('/api/v1/branches', $data);

        $branch = Branch::where('branch_code', 'BR-AUTO')->first();
        $this->assertNotNull($branch->cashbox);
        $this->assertEquals('Auto Branch Cashbox', $branch->cashbox->name);
        $this->assertEquals(0, $branch->cashbox->initial_balance);
        $this->assertEquals(0, $branch->cashbox->current_balance);
        $this->assertTrue($branch->cashbox->is_active);
    }
}
