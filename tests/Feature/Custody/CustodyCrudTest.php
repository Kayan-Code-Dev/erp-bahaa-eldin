<?php

namespace Tests\Feature\Custody;

use Tests\Feature\BaseTestCase;
use App\Models\Custody;
use App\Models\Order;
use Illuminate\Http\UploadedFile;

/**
 * Custody CRUD Tests
 *
 * Tests all basic CRUD operations for custody according to TEST_COVERAGE.md specification
 */
class CustodyCrudTest extends BaseTestCase
{
    // ==================== LIST CUSTODY ITEMS ====================

    /**
     * Test: List Custody Items
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: GET /api/v1/custody
     * - Required Permission: custody.view
     * - Expected Status: 200
     * - Description: List all custody items with pagination and filtering
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_custody_list_by_general_manager_succeeds()
    {
        Custody::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/custody');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_custody_list_by_accountant_succeeds()
    {
        Custody::factory()->count(3)->create();
        $this->authenticateAs('accountant');

        $response = $this->getJson('/api/v1/custody');

        $response->assertStatus(200);
    }

    public function test_custody_list_by_factory_user_fails_403()
    {
        Custody::factory()->count(3)->create();
        $this->authenticateAs('factory_user');

        $response = $this->getJson('/api/v1/custody');

        $this->assertPermissionDenied($response);
    }

    // ==================== LIST CUSTODY FOR ORDER ====================

    /**
     * Test: List Custody for Order
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: GET /api/v1/orders/{id}/custody
     * - Required Permission: custody.view
     * - Expected Status: 200
     * - Description: List all custody items for a specific order
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     */

    public function test_custody_list_for_order_succeeds()
    {
        $order = $this->createCompleteOrder();
        Custody::factory()->count(2)->create(['order_id' => $order->id]);
        // Create custody for different order
        Custody::factory()->create();

        $this->authenticateAs('reception_employee');

        $response = $this->getJson("/api/v1/orders/{$order->id}/custody");

        $response->assertStatus(200);
        $response->assertJsonCount(2); // Only custody for this order
    }

    // ==================== SHOW CUSTODY ITEM ====================

    /**
     * Test: Show Custody Item
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: GET /api/v1/custody/{id}
     * - Required Permission: custody.view
     * - Expected Status: 200
     * - Description: Get single custody item details with photos
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_custody_show_by_general_manager_succeeds()
    {
        $custody = Custody::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/custody/{$custody->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $custody->id])
            ->assertJsonStructure([
                'id', 'order_id', 'type', 'description', 'value', 'status',
                'photos' => [
                    '*' => ['id', 'photo_path', 'photo_url']
                ]
            ]);
    }

    public function test_custody_show_by_accountant_succeeds()
    {
        $custody = Custody::factory()->create();
        $this->authenticateAs('accountant');

        $response = $this->getJson("/api/v1/custody/{$custody->id}");

        $response->assertStatus(200);
    }

    // ==================== CREATE CUSTODY (MONEY TYPE) ====================

    /**
     * Test: Create Custody (Money Type)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/orders/{id}/custody
     * - Required Permission: custody.create
     * - Expected Status: 201
     * - Description: Create money type custody (requires value)
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: Users without permission (403), invalid data (422), order in wrong status (422)
     */

    public function test_custody_create_money_type_succeeds()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $data = [
            'type' => 'money',
            'description' => 'Cash deposit of 500 EGP',
            'value' => 500.00,
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $response->assertStatus(201)
            ->assertJson([
                'type' => 'money',
                'description' => 'Cash deposit of 500 EGP',
                'value' => 500.00,
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('custody', [
            'order_id' => $order->id,
            'type' => 'money',
            'value' => 500.00,
            'status' => 'pending',
        ]);
    }

    // ==================== CREATE CUSTODY (PHYSICAL ITEM TYPE) ====================

    /**
     * Test: Create Custody (Physical Item Type)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/orders/{id}/custody
     * - Required Permission: custody.create
     * - Expected Status: 201
     * - Description: Create physical item custody (requires photos)
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     */

    public function test_custody_create_physical_item_type_succeeds()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $photo1 = UploadedFile::fake()->image('photo1.jpg');
        $photo2 = UploadedFile::fake()->image('photo2.jpg');

        $data = [
            'type' => 'physical_item',
            'description' => 'Gold necklace custody',
            'photos' => [$photo1, $photo2],
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $response->assertStatus(201)
            ->assertJson([
                'type' => 'physical_item',
                'description' => 'Gold necklace custody',
                'status' => 'pending',
            ]);

        // Verify photos were uploaded
        $custody = Custody::where('order_id', $order->id)->first();
        $this->assertEquals(2, $custody->photos()->count());
    }

    // ==================== CREATE CUSTODY (DOCUMENT TYPE) ====================

    /**
     * Test: Create Custody (Document Type)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/orders/{id}/custody
     * - Required Permission: custody.create
     * - Expected Status: 201
     * - Description: Create document type custody
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     */

    public function test_custody_create_document_type_succeeds()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $data = [
            'type' => 'document',
            'description' => 'Passport custody',
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $response->assertStatus(201)
            ->assertJson([
                'type' => 'document',
                'description' => 'Passport custody',
                'status' => 'pending',
            ]);
    }

    // ==================== CREATE CUSTODY WITH INVALID ORDER STATUS ====================

    /**
     * Test: Create Custody with Invalid Order Status (Should Fail)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/orders/{id}/custody
     * - Expected Status: 422
     * - Description: Cannot add custody to orders in delivered/finished/cancelled status
     */

    public function test_custody_create_with_invalid_order_status_fails_422()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'delivered']); // Invalid status for adding custody
        $this->authenticateAs('reception_employee');

        $data = [
            'type' => 'money',
            'description' => 'Test custody',
            'value' => 100.00,
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ==================== CREATE MONEY CUSTODY WITHOUT VALUE ====================

    /**
     * Test: Create Money Custody without Value (Should Fail)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/orders/{id}/custody
     * - Expected Status: 422
     * - Description: Money type custody requires value field
     */

    public function test_custody_create_money_without_value_fails_422()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $data = [
            'type' => 'money',
            'description' => 'Cash deposit',
            // Missing value
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $this->assertValidationError($response, ['value']);
    }

    // ==================== CREATE PHYSICAL ITEM CUSTODY WITHOUT PHOTOS ====================

    /**
     * Test: Create Physical Item Custody without Photos (Should Fail)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/orders/{id}/custody
     * - Expected Status: 422
     * - Description: Physical item custody requires at least one photo
     */

    public function test_custody_create_physical_item_without_photos_fails_422()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $data = [
            'type' => 'physical_item',
            'description' => 'Gold necklace',
            // Missing photos
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $this->assertValidationError($response, ['photos']);
    }

    // ==================== UPDATE CUSTODY ====================

    /**
     * Test: Update Custody
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: PUT /api/v1/custody/{id}
     * - Required Permission: custody.update
     * - Expected Status: 200
     * - Description: Update custody item details (notes, description)
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: Users without permission (403), custody already returned (422)
     */

    public function test_custody_update_with_valid_data_succeeds()
    {
        $custody = Custody::factory()->create(['status' => 'pending']);
        $this->authenticateAs('reception_employee');

        $updateData = [
            'description' => 'Updated description',
            'notes' => 'Additional notes',
        ];

        $response = $this->putJson("/api/v1/custody/{$custody->id}", $updateData);

        $response->assertStatus(200);

        $custody->refresh();
        $this->assertEquals('Updated description', $custody->description);
        $this->assertEquals('Additional notes', $custody->notes);
    }

    // ==================== EXPORT CUSTODY ====================

    /**
     * Test: Export Custody
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: GET /api/v1/custody/export
     * - Required Permission: custody.export
     * - Expected Status: 200
     * - Description: Export custody items to file
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: Users without permission (403)
     */

    public function test_custody_export_by_accountant_succeeds()
    {
        Custody::factory()->count(3)->create();
        $this->authenticateAs('accountant');

        $response = $this->get('/api/v1/custody/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
    }

    // ==================== VALIDATION TESTS ====================

    public function test_custody_create_without_required_fields_fails_422()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", []);

        $this->assertValidationError($response, ['type', 'description']);
    }

    public function test_custody_create_with_invalid_type_fails_422()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $data = [
            'type' => 'invalid_type',
            'description' => 'Test custody',
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $this->assertValidationError($response, ['type']);
    }

    public function test_custody_create_with_invalid_photo_format_fails_422()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);
        $data = [
            'type' => 'physical_item',
            'description' => 'Test item',
            'photos' => [$invalidFile],
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $this->assertValidationError($response, ['photos.0']);
    }

    public function test_custody_create_with_too_many_photos_fails_422()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $photos = [];
        for ($i = 0; $i < 3; $i++) { // Assuming max is 2
            $photos[] = UploadedFile::fake()->image("photo{$i}.jpg");
        }

        $data = [
            'type' => 'physical_item',
            'description' => 'Test item',
            'photos' => $photos,
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $this->assertValidationError($response, ['photos']);
    }
}
