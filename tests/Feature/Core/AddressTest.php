<?php

namespace Tests\Feature\Core;

use Tests\Feature\BaseTestCase;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;

/**
 * Address CRUD Tests
 *
 * Tests all basic CRUD operations for addresses according to TEST_COVERAGE.md specification
 */
class AddressTest extends BaseTestCase
{
    // ==================== LIST ADDRESSES ====================

    /**
     * Test: List Addresses
     * - Type: Feature Test
     * - Module: Addresses
     * - Endpoint: GET /api/v1/addresses
     * - Required Permission: addresses.view
     * - Expected Status: 200
     * - Description: List all addresses with pagination and filtering
     * - Should Pass For: general_manager, roles with addresses.view
     * - Should Fail For: Users without addresses.view permission (403), unauthenticated (401)
     */

    public function test_address_list_by_general_manager_succeeds()
    {
        Address::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/addresses');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    // ==================== CREATE ADDRESS ====================

    /**
     * Test: Create Address
     * - Type: Feature Test
     * - Module: Addresses
     * - Endpoint: POST /api/v1/addresses
     * - Required Permission: addresses.create
     * - Expected Status: 201
     * - Description: Create a new address
     * - Should Pass For: general_manager, roles with addresses.create
     * - Should Fail For: Users without addresses.create (403), invalid data (422)
     */

    public function test_address_create_with_valid_data_returns_201()
    {
        $city = City::factory()->create();
        $this->authenticateAsSuperAdmin();

        $data = [
            'street' => '123 Main Street',
            'building' => 'A',
            'city_id' => $city->id,
            'notes' => 'Near the mall',
        ];

        $response = $this->postJson('/api/v1/addresses', $data);

        $response->assertStatus(201)
            ->assertJson([
                'street' => '123 Main Street',
                'building' => 'A',
                'city_id' => $city->id,
                'notes' => 'Near the mall',
            ]);

        $this->assertDatabaseHas('addresses', [
            'street' => '123 Main Street',
            'building' => 'A',
            'city_id' => $city->id,
            'notes' => 'Near the mall',
        ]);
    }

    // ==================== SHOW ADDRESS ====================

    /**
     * Test: Show Address
     * - Type: Feature Test
     * - Module: Addresses
     * - Endpoint: GET /api/v1/addresses/{id}
     * - Required Permission: addresses.view
     * - Expected Status: 200
     * - Description: Get single address details
     * - Should Pass For: general_manager, roles with addresses.view
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_address_show_by_general_manager_succeeds()
    {
        $address = Address::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $address->id])
            ->assertJsonStructure([
                'id', 'street', 'building', 'city_id', 'notes',
                'city' => ['id', 'name', 'country']
            ]);
    }

    // ==================== UPDATE ADDRESS ====================

    /**
     * Test: Update Address
     * - Type: Feature Test
     * - Module: Addresses
     * - Endpoint: PUT /api/v1/addresses/{id}
     * - Required Permission: addresses.update
     * - Expected Status: 200
     * - Description: Update address details
     * - Should Pass For: general_manager, roles with addresses.update
     * - Should Fail For: Users without permission (403), invalid data (422)
     */

    public function test_address_update_with_valid_data_succeeds()
    {
        $address = Address::factory()->create();
        $newCity = City::factory()->create();
        $this->authenticateAsSuperAdmin();

        $updateData = [
            'street' => '456 Updated Street',
            'building' => 'B',
            'city_id' => $newCity->id,
            'notes' => 'Updated notes',
        ];

        $response = $this->putJson("/api/v1/addresses/{$address->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'street' => '456 Updated Street',
            'building' => 'B',
            'city_id' => $newCity->id,
            'notes' => 'Updated notes',
        ]);
    }

    // ==================== DELETE ADDRESS ====================

    /**
     * Test: Delete Address
     * - Type: Feature Test
     * - Module: Addresses
     * - Endpoint: DELETE /api/v1/addresses/{id}
     * - Required Permission: addresses.delete
     * - Expected Status: 200/204
     * - Description: Delete an address
     * - Should Pass For: general_manager, roles with addresses.delete
     * - Should Fail For: Users without permission (403), address with clients (409/422)
     */

    public function test_address_delete_without_clients_succeeds()
    {
        $address = Address::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($address);
    }

    // ==================== DELETE ADDRESS WITH CLIENTS (SHOULD FAIL) ====================

    /**
     * Test: Delete Address with Clients (Should Fail)
     * - Type: Feature Test
     * - Module: Addresses
     * - Endpoint: DELETE /api/v1/addresses/{id}
     * - Expected Status: 422/409
     * - Description: Cannot delete address that has clients
     */

    public function test_address_delete_with_clients_fails()
    {
        $address = Address::factory()->create();
        // Create a client for this address
        \App\Models\Client::factory()->create(['address_id' => $address->id]);
        $this->authenticateAsSuperAdmin();

        $response = $this->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(422); // Or 409 depending on implementation
    }

    // ==================== EXPORT ADDRESSES ====================

    /**
     * Test: Export Addresses
     * - Type: Feature Test
     * - Module: Addresses
     * - Endpoint: GET /api/v1/addresses/export
     * - Required Permission: addresses.export
     * - Expected Status: 200
     * - Description: Export addresses to file
     * - Should Pass For: general_manager, roles with addresses.export
     * - Should Fail For: Users without permission (403)
     */

    public function test_address_export_by_general_manager_succeeds()
    {
        Address::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->get('/api/v1/addresses/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
    }

    // ==================== VALIDATION TESTS ====================

    public function test_address_create_without_required_fields_fails_422()
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/api/v1/addresses', []);

        $this->assertValidationError($response, ['street', 'building', 'city_id']);
    }

    public function test_address_create_with_invalid_city_id_fails_422()
    {
        $this->authenticateAsSuperAdmin();

        $data = [
            'street' => '123 Main Street',
            'building' => 'A',
            'city_id' => 99999, // Non-existent city
        ];

        $response = $this->postJson('/api/v1/addresses', $data);

        $this->assertValidationError($response, ['city_id']);
    }

    public function test_address_create_with_notes_too_long_fails_422()
    {
        $city = City::factory()->create();
        $this->authenticateAsSuperAdmin();

        $data = [
            'street' => '123 Main Street',
            'building' => 'A',
            'city_id' => $city->id,
            'notes' => str_repeat('A', 1001), // Too long (assuming 1000 char limit)
        ];

        $response = $this->postJson('/api/v1/addresses', $data);

        $this->assertValidationError($response, ['notes']);
    }
}
