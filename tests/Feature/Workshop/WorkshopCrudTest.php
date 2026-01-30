<?php

namespace Tests\Feature\Workshop;

use Tests\Feature\BaseTestCase;
use App\Models\Workshop;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;

/**
 * Workshop CRUD Tests
 *
 * Tests all basic CRUD operations for workshops according to TEST_COVERAGE.md specification
 */
class WorkshopCrudTest extends BaseTestCase
{
    // ==================== LIST WORKSHOPS ====================

    /**
     * Test: List Workshops
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: GET /api/v1/workshops
     * - Required Permission: workshops.view
     * - Expected Status: 200
     * - Description: List all workshops with pagination
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     * - Should Fail For: factory_user, reception_employee, sales_employee, accountant, hr_manager, employee
     */

    public function test_workshop_list_by_general_manager_succeeds()
    {
        Workshop::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/workshops');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_workshop_list_by_workshop_manager_succeeds()
    {
        Workshop::factory()->count(3)->create();
        $this->authenticateAs('workshop_manager');

        $response = $this->getJson('/api/v1/workshops');

        $response->assertStatus(200);
    }

    public function test_workshop_list_by_reception_employee_fails_403()
    {
        Workshop::factory()->count(3)->create();
        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/workshops');

        $this->assertPermissionDenied($response);
    }

    // ==================== CREATE WORKSHOP ====================

    /**
     * Test: Create Workshop
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: POST /api/v1/workshops
     * - Required Permission: workshops.create
     * - Expected Status: 201
     * - Description: Create a new workshop (inventory is auto-created)
     * - Should Pass For: general_manager, factory_manager
     * - Should Fail For: Users without permission (403), invalid data (422), duplicate workshop_code (422)
     */

    public function test_workshop_create_with_valid_data_returns_201()
    {
        $address = Address::factory()->create();
        $this->authenticateAs('factory_manager');

        $data = [
            'workshop_code' => 'WS-001',
            'name' => 'Main Workshop',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
                'notes' => $address->notes,
            ],
        ];

        $response = $this->postJson('/api/v1/workshops', $data);

        $response->assertStatus(201)
            ->assertJson([
                'workshop_code' => 'WS-001',
                'name' => 'Main Workshop',
            ]);

        $this->assertDatabaseHas('workshops', [
            'workshop_code' => 'WS-001',
            'name' => 'Main Workshop',
        ]);

        // Verify address was created
        $this->assertDatabaseHas('addresses', [
            'street' => $address->street,
            'building' => $address->building,
            'city_id' => $address->city_id,
        ]);

        // Verify inventory was auto-created
        $workshop = Workshop::where('workshop_code', 'WS-001')->first();
        $this->assertNotNull($workshop->inventory);
        $this->assertEquals('Main Workshop Inventory', $workshop->inventory->name);
    }

    // ==================== CREATE WORKSHOP WITH DUPLICATE CODE ====================

    /**
     * Test: Create Workshop with Duplicate Code (Should Fail)
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: POST /api/v1/workshops
     * - Expected Status: 422
     * - Description: workshop_code must be unique
     */

    public function test_workshop_create_with_duplicate_code_fails_422()
    {
        Workshop::factory()->create(['workshop_code' => 'WS-001']);

        $this->authenticateAs('factory_manager');

        $address = Address::factory()->create();

        $data = [
            'workshop_code' => 'WS-001', // Duplicate
            'name' => 'Different Workshop',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
        ];

        $response = $this->postJson('/api/v1/workshops', $data);

        $this->assertValidationError($response, ['workshop_code']);
    }

    // ==================== SHOW WORKSHOP ====================

    /**
     * Test: Show Workshop
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: GET /api/v1/workshops/{id}
     * - Required Permission: workshops.view
     * - Expected Status: 200
     * - Description: Get single workshop details
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_workshop_show_by_factory_manager_succeeds()
    {
        $workshop = Workshop::factory()->create();
        $this->authenticateAs('factory_manager');

        $response = $this->getJson("/api/v1/workshops/{$workshop->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $workshop->id])
            ->assertJsonStructure([
                'id', 'workshop_code', 'name',
                'inventory' => ['id', 'name'],
                'address' => ['street', 'building', 'city']
            ]);
    }

    // ==================== UPDATE WORKSHOP ====================

    /**
     * Test: Update Workshop
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: PUT /api/v1/workshops/{id}
     * - Required Permission: workshops.update
     * - Expected Status: 200
     * - Description: Update workshop details
     * - Should Pass For: general_manager, factory_manager
     * - Should Fail For: Users without permission (403), invalid data (422)
     */

    public function test_workshop_update_with_valid_data_succeeds()
    {
        $workshop = Workshop::factory()->create();
        $newAddress = Address::factory()->create();
        $this->authenticateAs('factory_manager');

        $updateData = [
            'name' => 'Updated Workshop',
            'address' => [
                'street' => $newAddress->street,
                'building' => $newAddress->building,
                'city_id' => $newAddress->city_id,
            ],
        ];

        $response = $this->putJson("/api/v1/workshops/{$workshop->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('workshops', [
            'id' => $workshop->id,
            'name' => 'Updated Workshop',
        ]);
    }

    // ==================== DELETE WORKSHOP ====================

    /**
     * Test: Delete Workshop
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: DELETE /api/v1/workshops/{id}
     * - Required Permission: workshops.delete
     * - Expected Status: 200/204
     * - Description: Delete a workshop
     * - Should Pass For: general_manager, factory_manager
     * - Should Fail For: Users without permission (403), workshop with clothes (should prevent or allow based on business rules)
     */

    public function test_workshop_delete_without_clothes_succeeds()
    {
        $workshop = Workshop::factory()->create();
        $this->authenticateAs('factory_manager');

        $response = $this->deleteJson("/api/v1/workshops/{$workshop->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($workshop);
    }

    // ==================== DELETE WORKSHOP WITH CLOTHES ====================

    /**
     * Test: Delete Workshop with Clothes (Should Fail)
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: DELETE /api/v1/workshops/{id}
     * - Expected Status: 422/409
     * - Description: Cannot delete workshop that has clothes
     */

    public function test_workshop_delete_with_clothes_fails()
    {
        $workshop = Workshop::factory()->create();
        $cloth = \App\Models\Cloth::factory()->create();
        // Add cloth to workshop inventory
        $workshop->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        $this->authenticateAs('factory_manager');

        $response = $this->deleteJson("/api/v1/workshops/{$workshop->id}");

        $response->assertStatus(422); // Or 409 depending on implementation
    }

    // ==================== EXPORT WORKSHOPS ====================

    /**
     * Test: Export Workshops
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: GET /api/v1/workshops/export
     * - Required Permission: workshops.export
     * - Expected Status: 200
     * - Description: Export workshops to file
     * - Should Pass For: general_manager, factory_manager
     * - Should Fail For: Users without permission (403)
     */

    public function test_workshop_export_by_factory_manager_succeeds()
    {
        Workshop::factory()->count(3)->create();
        $this->authenticateAs('factory_manager');

        $response = $this->get('/api/v1/workshops/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
    }

    // ==================== WORKSHOP CLOTH MANAGEMENT ====================

    /**
     * Test: List Workshop Clothes
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: GET /api/v1/workshops/{id}/clothes
     * - Required Permission: workshops.manage-clothes
     * - Expected Status: 200
     * - Description: List all clothes currently in workshop with status
     * - Should Pass For: general_manager, workshop_manager
     */

    public function test_workshop_clothes_list_succeeds()
    {
        $workshop = Workshop::factory()->create();
        $cloth1 = \App\Models\Cloth::factory()->create();
        $cloth2 = \App\Models\Cloth::factory()->create();

        // Add cloths to workshop inventory
        $workshop->inventory->clothes()->attach($cloth1->id, ['quantity' => 1]);
        $workshop->inventory->clothes()->attach($cloth2->id, ['quantity' => 1]);

        $this->authenticateAs('workshop_manager');

        $response = $this->getJson("/api/v1/workshops/{$workshop->id}/clothes");

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }

    // ==================== GET PENDING TRANSFERS ====================

    /**
     * Test: Get Pending Transfers
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: GET /api/v1/workshops/{id}/pending-transfers
     * - Required Permission: workshops.approve-transfers
     * - Expected Status: 200
     * - Description: Get pending incoming transfers for workshop
     * - Should Pass For: general_manager, workshop_manager
     */

    public function test_workshop_pending_transfers_list_succeeds()
    {
        $workshop = Workshop::factory()->create();

        // Create pending transfer to workshop
        \App\Models\Transfer::factory()->create([
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('workshop_manager');

        $response = $this->getJson("/api/v1/workshops/{$workshop->id}/pending-transfers");

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    // ==================== UPDATE CLOTH STATUS IN WORKSHOP ====================

    /**
     * Test: Update Cloth Status in Workshop
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: POST /api/v1/workshops/{id}/update-cloth-status
     * - Required Permission: workshops.update-status
     * - Expected Status: 200
     * - Description: Update cloth status in workshop (received → processing → ready_for_delivery)
     * - Should Pass For: general_manager, workshop_manager
     * - Should Fail For: Users without permission (403), cloth not in workshop (422)
     */

    public function test_workshop_update_cloth_status_succeeds()
    {
        $workshop = Workshop::factory()->create();
        $cloth = \App\Models\Cloth::factory()->create();

        // Add cloth to workshop inventory
        $workshop->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        $this->authenticateAs('workshop_manager');

        $response = $this->postJson("/api/v1/workshops/{$workshop->id}/update-cloth-status", [
            'cloth_id' => $cloth->id,
            'status' => 'processing',
        ]);

        $response->assertStatus(200);

        // Verify workshop log was created
        $this->assertDatabaseHas('workshop_logs', [
            'workshop_id' => $workshop->id,
            'cloth_id' => $cloth->id,
            'action' => 'status_changed',
        ]);
    }

    // ==================== UPDATE CLOTH STATUS - CLOTH NOT IN WORKSHOP ====================

    /**
     * Test: Update Cloth Status - Cloth Not in Workshop (Should Fail)
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: POST /api/v1/workshops/{id}/update-cloth-status
     * - Expected Status: 422
     * - Description: Cannot update status for cloth not in workshop inventory
     */

    public function test_workshop_update_cloth_status_not_in_workshop_fails_422()
    {
        $workshop = Workshop::factory()->create();
        $cloth = \App\Models\Cloth::factory()->create();
        // Cloth is NOT in workshop inventory

        $this->authenticateAs('workshop_manager');

        $response = $this->postJson("/api/v1/workshops/{$workshop->id}/update-cloth-status", [
            'cloth_id' => $cloth->id,
            'status' => 'processing',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ==================== RETURN CLOTH FROM WORKSHOP ====================

    /**
     * Test: Return Cloth from Workshop
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: POST /api/v1/workshops/{id}/return-cloth
     * - Required Permission: workshops.return-cloth
     * - Expected Status: 200
     * - Description: Return cloth from workshop to branch
     * - Should Pass For: general_manager, workshop_manager
     */

    public function test_workshop_return_cloth_succeeds()
    {
        $workshop = Workshop::factory()->create();
        $branch = \App\Models\Branch::factory()->create();
        $cloth = \App\Models\Cloth::factory()->create();

        // Add cloth to workshop inventory
        $workshop->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        $this->authenticateAs('workshop_manager');

        $response = $this->postJson("/api/v1/workshops/{$workshop->id}/return-cloth", [
            'cloth_id' => $cloth->id,
        ]);

        $response->assertStatus(200);

        // Verify cloth moved back to branch inventory (assuming it came from branch)
        $this->assertFalse($workshop->inventory->clothes()->where('cloth_id', $cloth->id)->exists());

        // Verify workshop log was created
        $this->assertDatabaseHas('workshop_logs', [
            'workshop_id' => $workshop->id,
            'cloth_id' => $cloth->id,
            'action' => 'returned',
        ]);
    }

    // ==================== GET WORKSHOP LOGS ====================

    /**
     * Test: Get Workshop Logs
     * - Type: Feature Test
     * - Module: Workshops
     * - Endpoint: GET /api/v1/workshops/{id}/logs
     * - Required Permission: workshops.view-logs
     * - Expected Status: 200
     * - Description: Get workshop operation logs with filters
     * - Should Pass For: general_manager, workshop_manager
     */

    public function test_workshop_logs_list_succeeds()
    {
        $workshop = Workshop::factory()->create();

        // Create workshop logs
        \App\Models\WorkshopLog::factory()->count(3)->create([
            'workshop_id' => $workshop->id,
        ]);

        $this->authenticateAs('workshop_manager');

        $response = $this->getJson("/api/v1/workshops/{$workshop->id}/logs");

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_workshop_create_without_required_fields_fails_422()
    {
        $this->authenticateAs('factory_manager');

        $response = $this->postJson('/api/v1/workshops', []);

        $this->assertValidationError($response, ['workshop_code', 'name', 'address']);
    }

    public function test_workshop_create_with_invalid_workshop_code_fails_422()
    {
        $this->authenticateAs('factory_manager');

        $address = Address::factory()->create();

        $data = [
            'workshop_code' => 'INVALID-CODE', // Should be format like WS-001
            'name' => 'Test Workshop',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
        ];

        $response = $this->postJson('/api/v1/workshops', $data);

        $this->assertValidationError($response, ['workshop_code']);
    }

    // ==================== PERMISSION TESTS ====================

    public function test_workshop_create_by_factory_manager_succeeds()
    {
        $address = Address::factory()->create();
        $this->authenticateAs('factory_manager');

        $data = [
            'workshop_code' => 'WS-001',
            'name' => 'Test Workshop',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
        ];

        $response = $this->postJson('/api/v1/workshops', $data);

        $response->assertStatus(201);
    }

    public function test_workshop_create_by_workshop_manager_fails_403()
    {
        $address = Address::factory()->create();
        $this->authenticateAs('workshop_manager');

        $data = [
            'workshop_code' => 'WS-001',
            'name' => 'Test Workshop',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
        ];

        $response = $this->postJson('/api/v1/workshops', $data);

        $this->assertPermissionDenied($response);
    }
}
