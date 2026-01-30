<?php

namespace Tests\Feature\Core;

use Tests\Feature\BaseTestCase;
use App\Models\Country;

/**
 * Country CRUD Tests
 *
 * Tests all basic CRUD operations for countries according to TEST_COVERAGE.md specification
 */
class CountryTest extends BaseTestCase
{
    // ==================== LIST COUNTRIES ====================

    /**
     * Test: List Countries
     * - Type: Feature Test
     * - Module: Countries
     * - Endpoint: GET /api/v1/countries
     * - Required Permission: countries.view
     * - Expected Status: 200
     * - Description: List all countries with pagination and filtering
     * - Should Pass For: general_manager, roles with countries.view
     * - Should Fail For: Users without countries.view permission (403), unauthenticated (401)
     */

    public function test_country_list_by_general_manager_succeeds()
    {
        Country::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/countries');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(3, 'data');
    }

    public function test_country_list_with_search_filter_works()
    {
        $country1 = Country::factory()->create(['name' => 'Egypt']);
        $country2 = Country::factory()->create(['name' => 'Saudi Arabia']);
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/countries?search=Egypt');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $country1->id]);
        $response->assertJsonMissing(['id' => $country2->id]);
    }

    // ==================== CREATE COUNTRY ====================

    /**
     * Test: Create Country
     * - Type: Feature Test
     * - Module: Countries
     * - Endpoint: POST /api/v1/countries
     * - Required Permission: countries.create
     * - Expected Status: 201
     * - Description: Create a new country
     * - Should Pass For: general_manager, roles with countries.create
     * - Should Fail For: Users without countries.create (403), invalid data (422)
     */

    public function test_country_create_with_valid_data_returns_201()
    {
        $this->authenticateAsSuperAdmin();

        $data = [
            'name' => 'Egypt',
            'code' => 'EG',
        ];

        $response = $this->postJson('/api/v1/countries', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'Egypt',
                'code' => 'EG',
            ]);

        $this->assertDatabaseHas('countries', [
            'name' => 'Egypt',
            'code' => 'EG',
        ]);
    }

    // ==================== CREATE COUNTRY WITH DUPLICATE NAME (SHOULD FAIL) ====================

    /**
     * Test: Create Country with Duplicate Name (Should Fail)
     * - Type: Feature Test
     * - Module: Countries
     * - Endpoint: POST /api/v1/countries
     * - Expected Status: 422
     * - Description: Cannot create country with duplicate name
     */

    public function test_country_create_with_duplicate_name_fails_422()
    {
        Country::factory()->create(['name' => 'Egypt']);

        $this->authenticateAsSuperAdmin();

        $data = [
            'name' => 'Egypt',
            'code' => 'EG2',
        ];

        $response = $this->postJson('/api/v1/countries', $data);

        $this->assertValidationError($response, ['name']);
    }

    // ==================== SHOW COUNTRY ====================

    /**
     * Test: Show Country
     * - Type: Feature Test
     * - Module: Countries
     * - Endpoint: GET /api/v1/countries/{id}
     * - Required Permission: countries.view
     * - Expected Status: 200
     * - Description: Get single country details
     * - Should Pass For: general_manager, roles with countries.view
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_country_show_by_general_manager_succeeds()
    {
        $country = Country::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/countries/{$country->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $country->id]);
    }

    public function test_country_show_nonexistent_country_fails_404()
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/countries/99999');

        $this->assertNotFound($response);
    }

    // ==================== UPDATE COUNTRY ====================

    /**
     * Test: Update Country
     * - Type: Feature Test
     * - Module: Countries
     * - Endpoint: PUT /api/v1/countries/{id}
     * - Required Permission: countries.update
     * - Expected Status: 200
     * - Description: Update country details
     * - Should Pass For: general_manager, roles with countries.update
     * - Should Fail For: Users without permission (403), invalid data (422)
     */

    public function test_country_update_with_valid_data_succeeds()
    {
        $country = Country::factory()->create();
        $this->authenticateAsSuperAdmin();

        $updateData = [
            'name' => 'Updated Country',
            'code' => 'UC',
        ];

        $response = $this->putJson("/api/v1/countries/{$country->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('countries', [
            'id' => $country->id,
            'name' => 'Updated Country',
            'code' => 'UC',
        ]);
    }

    // ==================== DELETE COUNTRY ====================

    /**
     * Test: Delete Country
     * - Type: Feature Test
     * - Module: Countries
     * - Endpoint: DELETE /api/v1/countries/{id}
     * - Required Permission: countries.delete
     * - Expected Status: 200/204
     * - Description: Delete a country
     * - Should Pass For: general_manager, roles with countries.delete
     * - Should Fail For: Users without permission (403), country with cities (409/422)
     */

    public function test_country_delete_without_cities_succeeds()
    {
        $country = Country::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->deleteJson("/api/v1/countries/{$country->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($country);
    }

    // ==================== DELETE COUNTRY WITH CITIES (SHOULD FAIL) ====================

    /**
     * Test: Delete Country with Cities (Should Fail)
     * - Type: Feature Test
     * - Module: Countries
     * - Endpoint: DELETE /api/v1/countries/{id}
     * - Expected Status: 422/409
     * - Description: Cannot delete country that has cities
     */

    public function test_country_delete_with_cities_fails()
    {
        $country = Country::factory()->create();
        // Create a city for this country
        \App\Models\City::factory()->create(['country_id' => $country->id]);
        $this->authenticateAsSuperAdmin();

        $response = $this->deleteJson("/api/v1/countries/{$country->id}");

        $response->assertStatus(422); // Or 409 depending on implementation
    }

    // ==================== EXPORT COUNTRIES ====================

    /**
     * Test: Export Countries
     * - Type: Feature Test
     * - Module: Countries
     * - Endpoint: GET /api/v1/countries/export
     * - Required Permission: countries.export
     * - Expected Status: 200
     * - Description: Export countries to file
     * - Should Pass For: general_manager, roles with countries.export
     * - Should Fail For: Users without permission (403)
     */

    public function test_country_export_by_general_manager_succeeds()
    {
        Country::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->get('/api/v1/countries/export');

        $response->assertStatus(200);
        // Note: This would typically return a file download, so we check headers
        $response->assertHeader('Content-Type', 'text/csv');
    }

    // ==================== VALIDATION TESTS ====================

    public function test_country_create_without_required_fields_fails_422()
    {
        $this->authenticateAsSuperAdmin();

        $response = $this->postJson('/api/v1/countries', []);

        $this->assertValidationError($response, ['name']);
    }

    public function test_country_create_with_duplicate_code_fails_422()
    {
        Country::factory()->create(['code' => 'EG']);

        $this->authenticateAsSuperAdmin();

        $data = [
            'name' => 'Different Country',
            'code' => 'EG', // Duplicate
        ];

        $response = $this->postJson('/api/v1/countries', $data);

        $this->assertValidationError($response, ['code']);
    }
}
