<?php

namespace Tests\Feature\Core;

use Tests\Feature\BaseTestCase;
use App\Models\City;
use App\Models\Country;

/**
 * City CRUD Tests
 *
 * Tests all basic CRUD operations for cities according to TEST_COVERAGE.md specification
 */
class CityTest extends BaseTestCase
{
    // ==================== LIST CITIES ====================

    /**
     * Test: List Cities
     * - Type: Feature Test
     * - Module: Cities
     * - Endpoint: GET /api/v1/cities
     * - Required Permission: cities.view
     * - Expected Status: 200
     * - Description: List all cities with pagination and filtering
     * - Should Pass For: general_manager, roles with cities.view
     * - Should Fail For: Users without cities.view permission (403), unauthenticated (401)
     */

    public function test_city_list_by_general_manager_succeeds()
    {
        City::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/cities');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_city_list_with_country_filter_works()
    {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();
        $city1 = City::factory()->create(['country_id' => $country1->id]);
        $city2 = City::factory()->create(['country_id' => $country2->id]);

        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/cities?country_id={$country1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $city1->id]);
        $response->assertJsonMissing(['id' => $city2->id]);
    }

    // ==================== CREATE CITY ====================

    /**
     * Test: Create City
     * - Type: Feature Test
     * - Module: Cities
     * - Endpoint: POST /api/v1/cities
     * - Required Permission: cities.create
     * - Expected Status: 201
     * - Description: Create a new city
     * - Should Pass For: general_manager, roles with cities.create
     * - Should Fail For: Users without cities.create (403), invalid data (422)
     */

    public function test_city_create_with_valid_data_returns_201()
    {
        $country = Country::factory()->create();
        $this->authenticateAsSuperAdmin();

        $data = [
            'name' => 'Cairo',
            'country_id' => $country->id,
        ];

        $response = $this->postJson('/api/v1/cities', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Cairo',
                'country_id' => $country->id,
            ]);

        $this->assertDatabaseHas('cities', [
            'name' => 'Cairo',
            'country_id' => $country->id,
        ]);
    }

    // ==================== SHOW CITY ====================

    /**
     * Test: Show City
     * - Type: Feature Test
     * - Module: Cities
     * - Endpoint: GET /api/v1/cities/{id}
     * - Required Permission: cities.view
     * - Expected Status: 200
     * - Description: Get single city details
     * - Should Pass For: general_manager, roles with cities.view
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_city_show_by_general_manager_succeeds()
    {
        $city = City::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/cities/{$city->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $city->id])
            ->assertJsonStructure([
                'id', 'name', 'country_id',
                'country' => ['id', 'name']
            ]);
    }

    // ==================== UPDATE CITY ====================

    /**
     * Test: Update City
     * - Type: Feature Test
     * - Module: Cities
     * - Endpoint: PUT /api/v1/cities/{id}
     * - Required Permission: cities.update
     * - Expected Status: 200
     * - Description: Update city details
     * - Should Pass For: general_manager, roles with cities.update
     * - Should Fail For: Users without permission (403), invalid data (422)
     */

    public function test_city_update_with_valid_data_succeeds()
    {
        $city = City::factory()->create();
        $newCountry = Country::factory()->create();
        $this->authenticateAsSuperAdmin();

        $updateData = [
            'name' => 'Updated City',
            'country_id' => $newCountry->id,
        ];

        $response = $this->putJson("/api/v1/cities/{$city->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cities', [
            'id' => $city->id,
            'name' => 'Updated City',
            'country_id' => $newCountry->id,
        ]);
    }

    // ==================== DELETE CITY ====================

    /**
     * Test: Delete City
     * - Type: Feature Test
     * - Module: Cities
     * - Endpoint: DELETE /api/v1/cities/{id}
     * - Required Permission: cities.delete
     * - Expected Status: 200/204
     * - Description: Delete a city
     * - Should Pass For: general_manager, roles with cities.delete
     * - Should Fail For: Users without permission (403), city with addresses (409/422)
     */

    public function test_city_delete_without_addresses_succeeds()
    {
        $city = City::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->deleteJson("/api/v1/cities/{$city->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($city);
    }

    // ==================== DELETE CITY WITH ADDRESSES (SHOULD FAIL) ====================

    /**
     * Test: Delete City with Addresses (Should Fail)
     * - Type: Feature Test
     * - Module: Cities
     * - Endpoint: DELETE /api/v1/cities/{id}
     * - Expected Status: 422/409
     * - Description: Cannot delete city that has addresses
     */

    public function test_city_delete_with_addresses_fails()
    {
        $city = City::factory()->create();
        // Create an address for this city
        \App\Models\Address::factory()->create(['city_id' => $city->id]);
        $this->authenticateAsSuperAdmin();

        $response = $this->deleteJson("/api/v1/cities/{$city->id}");

        $response->assertStatus(422); // Or 409 depending on implementation
    }

    // ==================== EXPORT CITIES ====================

    /**
     * Test: Export Cities
     * - Type: Feature Test
     * - Module: Cities
     * - Endpoint: GET /api/v1/cities/export
     * - Required Permission: cities.export
     * - Expected Status: 200
     * - Description: Export cities to file
     * - Should Pass For: general_manager, roles with cities.export
     * - Should Fail For: Users without permission (403)
     */

    public function test_city_export_by_general_manager_succeeds()
    {
        City::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->get('/api/v1/cities/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
    }

    // ==================== VALIDATION TESTS ====================

    public function test_city_create_without_required_fields_fails_422()
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/api/v1/cities', []);

        $this->assertValidationError($response, ['name', 'country_id']);
    }

    public function test_city_create_with_invalid_country_id_fails_422()
    {
        $this->authenticateAsSuperAdmin();

        $data = [
            'name' => 'Cairo',
            'country_id' => 99999, // Non-existent country
        ];

        $response = $this->postJson('/api/v1/cities', $data);

        $this->assertValidationError($response, ['country_id']);
    }

    public function test_city_create_with_duplicate_name_in_same_country_fails_422()
    {
        $country = Country::factory()->create();
        City::factory()->create([
            'name' => 'Cairo',
            'country_id' => $country->id
        ]);

        $this->authenticateAsSuperAdmin();

        $data = [
            'name' => 'Cairo', // Duplicate in same country
            'country_id' => $country->id,
        ];

        $response = $this->postJson('/api/v1/cities', $data);

        $this->assertValidationError($response, ['name']);
    }

    public function test_city_create_with_same_name_in_different_country_succeeds()
    {
        $country1 = Country::factory()->create();
        $country2 = Country::factory()->create();
        City::factory()->create([
            'name' => 'Cairo',
            'country_id' => $country1->id
        ]);

        $this->authenticateAsSuperAdmin();

        $data = [
            'name' => 'Cairo', // Same name, different country
            'country_id' => $country2->id,
        ];

        $response = $this->postJson('/api/v1/cities', $data);

        $response->assertStatus(201);
    }
}
