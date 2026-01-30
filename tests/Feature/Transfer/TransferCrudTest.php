<?php

namespace Tests\Feature\Transfer;

use Tests\Feature\BaseTestCase;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Cloth;
use App\Models\Inventory;

/**
 * Transfer CRUD Tests
 *
 * Tests all basic CRUD operations for transfers according to TEST_COVERAGE.md specification
 */
class TransferCrudTest extends BaseTestCase
{
    // ==================== LIST TRANSFERS ====================

    /**
     * Test: List Transfers
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: GET /api/v1/transfers
     * - Required Permission: transfers.view
     * - Expected Status: 200
     * - Description: List all transfers with pagination and filtering
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     * - Should Fail For: factory_user, reception_employee, sales_employee, accountant, hr_manager, employee
     */

    public function test_transfer_list_by_general_manager_succeeds()
    {
        Transfer::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/transfers');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_transfer_list_by_factory_manager_succeeds()
    {
        Transfer::factory()->count(3)->create();
        $this->authenticateAs('factory_manager');

        $response = $this->getJson('/api/v1/transfers');

        $response->assertStatus(200);
    }

    public function test_transfer_list_by_reception_employee_fails_403()
    {
        Transfer::factory()->count(3)->create();
        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/transfers');

        $this->assertPermissionDenied($response);
    }

    public function test_transfer_list_with_filters_succeeds()
    {
        // Create transfers with different statuses and types
        Transfer::factory()->create(['status' => 'pending']);
        Transfer::factory()->create(['status' => 'approved']);
        Transfer::factory()->create(['status' => 'rejected']);

        $this->authenticateAs('factory_manager');

        // Test status filter
        $response = $this->getJson('/api/v1/transfers?status=pending');
        $response->assertStatus(200);

        // Test action filter (created, approved, rejected)
        $response = $this->getJson('/api/v1/transfers?action=created');
        $response->assertStatus(200);
    }

    // ==================== CREATE TRANSFER ====================

    /**
     * Test: Create Transfer (Branch to Workshop)
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: POST /api/v1/transfers
     * - Required Permission: transfers.create
     * - Expected Status: 201
     * - Description: Create a transfer from branch to workshop
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     * - Should Fail For: Users without permission (403), invalid data (422), cloth not in source inventory (422)
     */

    public function test_transfer_create_branch_to_workshop_succeeds()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();

        // Add cloth to branch inventory
        $cloth = Cloth::factory()->create();
        $branch->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        $this->authenticateAs('factory_manager');

        $data = [
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'cloth_ids' => [$cloth->id],
            'notes' => 'Test transfer',
        ];

        $response = $this->postJson('/api/v1/transfers', $data);

        $response->assertStatus(201)
            ->assertJson([
                'from_entity_type' => 'branch',
                'to_entity_type' => 'workshop',
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('transfers', [
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'status' => 'pending',
        ]);

        // Verify transfer items were created
        $transfer = Transfer::where('from_entity_id', $branch->id)->first();
        $this->assertDatabaseHas('transfer_items', [
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth->id,
            'status' => 'pending',
        ]);
    }

    // ==================== CREATE TRANSFER WITH SAME SOURCE AND DESTINATION ====================

    /**
     * Test: Create Transfer with Same Source and Destination (Should Fail)
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: POST /api/v1/transfers
     * - Expected Status: 422
     * - Description: Cannot create transfer with same source and destination entity
     */

    public function test_transfer_create_same_source_destination_fails_422()
    {
        $branch = Branch::factory()->create();
        $cloth = Cloth::factory()->create();
        $branch->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        $this->authenticateAs('factory_manager');

        $data = [
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'branch', // Same type
            'to_entity_id' => $branch->id, // Same entity
            'cloth_ids' => [$cloth->id],
        ];

        $response = $this->postJson('/api/v1/transfers', $data);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ==================== CREATE TRANSFER WITH CLOTH NOT IN SOURCE INVENTORY ====================

    /**
     * Test: Create Transfer with Cloth Not in Source Inventory (Should Fail)
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: POST /api/v1/transfers
     * - Expected Status: 422
     * - Description: Cannot transfer cloth that is not in source entity's inventory
     */

    public function test_transfer_create_cloth_not_in_inventory_fails_422()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();
        $cloth = Cloth::factory()->create();
        // Note: cloth is NOT added to branch inventory

        $this->authenticateAs('factory_manager');

        $data = [
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'cloth_ids' => [$cloth->id], // Cloth not in inventory
        ];

        $response = $this->postJson('/api/v1/transfers', $data);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ==================== SHOW TRANSFER ====================

    /**
     * Test: Show Transfer
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: GET /api/v1/transfers/{id}
     * - Required Permission: transfers.view
     * - Expected Status: 200
     * - Description: Get single transfer details with items and actions
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_transfer_show_by_factory_manager_succeeds()
    {
        $transfer = Transfer::factory()->create();
        TransferItem::factory()->count(2)->create(['transfer_id' => $transfer->id]);

        $this->authenticateAs('factory_manager');

        $response = $this->getJson("/api/v1/transfers/{$transfer->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $transfer->id])
            ->assertJsonStructure([
                'id', 'from_entity_type', 'from_entity_id', 'to_entity_type', 'to_entity_id',
                'status', 'transfer_date', 'notes',
                'items' => [
                    '*' => [
                        'id', 'cloth_id', 'status',
                        'cloth' => ['id', 'code', 'name']
                    ]
                ],
                'actions' => [
                    '*' => ['id', 'action', 'created_at', 'user']
                ]
            ]);
    }

    // ==================== UPDATE TRANSFER ====================

    /**
     * Test: Update Transfer
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: PUT /api/v1/transfers/{id}
     * - Required Permission: transfers.update
     * - Expected Status: 200
     * - Description: Update transfer details (notes, transfer_date)
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     * - Should Fail For: Users without permission (403), transfer already approved/rejected (422)
     */

    public function test_transfer_update_pending_transfer_succeeds()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);

        $this->authenticateAs('factory_manager');

        $updateData = [
            'notes' => 'Updated transfer notes',
        ];

        $response = $this->putJson("/api/v1/transfers/{$transfer->id}", $updateData);

        $response->assertStatus(200);

        $transfer->refresh();
        $this->assertEquals('Updated transfer notes', $transfer->notes);
    }

    public function test_transfer_update_approved_transfer_fails_422()
    {
        $transfer = Transfer::factory()->create(['status' => 'approved']);

        $this->authenticateAs('factory_manager');

        $updateData = [
            'notes' => 'Should not update',
        ];

        $response = $this->putJson("/api/v1/transfers/{$transfer->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ==================== DELETE TRANSFER ====================

    /**
     * Test: Delete Transfer
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: DELETE /api/v1/transfers/{id}
     * - Required Permission: transfers.delete
     * - Expected Status: 200/204
     * - Description: Delete a transfer (only if pending)
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     * - Should Fail For: Users without permission (403), transfer already approved/rejected
     */

    public function test_transfer_delete_pending_transfer_succeeds()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);

        $this->authenticateAs('factory_manager');

        $response = $this->deleteJson("/api/v1/transfers/{$transfer->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($transfer);
    }

    public function test_transfer_delete_approved_transfer_fails_422()
    {
        $transfer = Transfer::factory()->create(['status' => 'approved']);

        $this->authenticateAs('factory_manager');

        $response = $this->deleteJson("/api/v1/transfers/{$transfer->id}");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ==================== EXPORT TRANSFERS ====================

    /**
     * Test: Export Transfers
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: GET /api/v1/transfers/export
     * - Required Permission: transfers.export
     * - Expected Status: 200
     * - Description: Export transfers to file
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     * - Should Fail For: Users without permission (403)
     */

    public function test_transfer_export_by_factory_manager_succeeds()
    {
        Transfer::factory()->count(3)->create();
        $this->authenticateAs('factory_manager');

        $response = $this->get('/api/v1/transfers/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
    }

    // ==================== VALIDATION TESTS ====================

    public function test_transfer_create_without_required_fields_fails_422()
    {
        $this->authenticateAs('factory_manager');

        $response = $this->postJson('/api/v1/transfers', []);

        $this->assertValidationError($response, [
            'from_entity_type', 'from_entity_id', 'to_entity_type', 'to_entity_id', 'cloth_ids'
        ]);
    }

    public function test_transfer_create_with_invalid_entity_type_fails_422()
    {
        $this->authenticateAs('factory_manager');

        $data = [
            'from_entity_type' => 'invalid_type',
            'from_entity_id' => 1,
            'to_entity_type' => 'branch',
            'to_entity_id' => 2,
            'cloth_ids' => [1],
        ];

        $response = $this->postJson('/api/v1/transfers', $data);

        $this->assertValidationError($response, ['from_entity_type']);
    }

    public function test_transfer_create_with_nonexistent_entity_fails_422()
    {
        $this->authenticateAs('factory_manager');

        $data = [
            'from_entity_type' => 'branch',
            'from_entity_id' => 99999, // Non-existent
            'to_entity_type' => 'workshop',
            'to_entity_id' => 1,
            'cloth_ids' => [1],
        ];

        $response = $this->postJson('/api/v1/transfers', $data);

        $this->assertValidationError($response, ['from_entity_id']);
    }

    public function test_transfer_create_with_empty_cloth_ids_fails_422()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();

        $this->authenticateAs('factory_manager');

        $data = [
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'cloth_ids' => [], // Empty
        ];

        $response = $this->postJson('/api/v1/transfers', $data);

        $this->assertValidationError($response, ['cloth_ids']);
    }

    // ==================== PERMISSION TESTS ====================

    public function test_transfer_create_by_factory_manager_succeeds()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();
        $cloth = Cloth::factory()->create();
        $branch->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        $this->authenticateAs('factory_manager');

        $data = [
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'cloth_ids' => [$cloth->id],
        ];

        $response = $this->postJson('/api/v1/transfers', $data);

        $response->assertStatus(201);
    }

    public function test_transfer_create_by_reception_employee_fails_403()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();
        $cloth = Cloth::factory()->create();
        $branch->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        $this->authenticateAs('reception_employee');

        $data = [
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'cloth_ids' => [$cloth->id],
        ];

        $response = $this->postJson('/api/v1/transfers', $data);

        $this->assertPermissionDenied($response);
    }
}
