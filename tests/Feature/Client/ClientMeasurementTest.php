<?php

namespace Tests\Feature\Client;

use Tests\Feature\BaseTestCase;
use App\Models\Client;

/**
 * Client Measurement Tests
 *
 * Tests client measurement functionality according to TEST_COVERAGE.md specification
 */
class ClientMeasurementTest extends BaseTestCase
{
    /**
     * Test: Get Client Measurements
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: GET /api/v1/clients/{id}/measurements
     * - Required Permission: clients.measurements.view
     * - Expected Status: 200
     * - Description: Get client measurements
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: accountant, factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */
    public function test_client_measurements_get_by_reception_employee_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        $response = $this->getJson("/api/v1/clients/{$client->id}/measurements");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id', 'first_name', 'last_name',
                'measurements' => [
                    'chest_size', 'waist_size', 'hip_size', 'shoulder_size',
                    'sleeve_size', 'length_size', 'measurement_notes', 'last_measurement_date'
                ]
            ]);
    }

    public function test_client_measurements_get_by_general_manager_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/clients/{$client->id}/measurements");

        $response->assertStatus(200);
    }

    public function test_client_measurements_get_by_accountant_fails_403()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('accountant');

        $response = $this->getJson("/api/v1/clients/{$client->id}/measurements");

        $this->assertPermissionDenied($response);
    }

    public function test_client_measurements_get_by_unauthenticated_fails_401()
    {
        $client = $this->createCompleteClient();

        $response = $this->getJson("/api/v1/clients/{$client->id}/measurements");

        $this->assertUnauthorized($response);
    }

    public function test_client_measurements_get_nonexistent_client_fails_404()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/clients/99999/measurements');

        $this->assertNotFound($response);
    }

    /**
     * Test: Update Client Measurements
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: PUT /api/v1/clients/{id}/measurements
     * - Required Permission: clients.measurements.update
     * - Expected Status: 200
     * - Description: Update client measurements
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: accountant, factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */
    public function test_client_measurements_update_with_valid_data_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        $measurementData = [
            'chest_size' => '40',
            'waist_size' => '32',
            'hip_size' => '38',
            'shoulder_size' => '16',
            'sleeve_size' => '24',
            'length_size' => '30',
            'measurement_notes' => 'Test measurements',
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        $response->assertStatus(200)
            ->assertJson([
                'measurements' => [
                    'chest_size' => '40',
                    'waist_size' => '32',
                    'hip_size' => '38',
                    'shoulder_size' => '16',
                    'sleeve_size' => '24',
                    'length_size' => '30',
                    'measurement_notes' => 'Test measurements',
                ]
            ]);

        // Verify measurements_updated_at is set
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'measurements_updated_at' => now()->toDateTimeString(), // Approximate check
        ]);
    }

    public function test_client_measurements_update_by_general_manager_succeeds()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAsSuperAdmin();

        $measurementData = [
            'chest_size' => '42',
            'waist_size' => '34',
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        $response->assertStatus(200);
    }

    public function test_client_measurements_update_by_accountant_fails_403()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('accountant');

        $measurementData = [
            'chest_size' => '40',
            'waist_size' => '32',
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        $this->assertPermissionDenied($response);
    }

    public function test_client_measurements_update_by_unauthenticated_fails_401()
    {
        $client = $this->createCompleteClient();

        $measurementData = [
            'chest_size' => '40',
            'waist_size' => '32',
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        $this->assertUnauthorized($response);
    }

    public function test_client_measurements_update_nonexistent_client_fails_404()
    {
        $this->authenticateAs('reception_employee');

        $measurementData = [
            'chest_size' => '40',
            'waist_size' => '32',
        ];

        $response = $this->putJson('/api/v1/clients/99999/measurements', $measurementData);

        $this->assertNotFound($response);
    }

    /**
     * Test: Update Measurements with Invalid Values
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: PUT /api/v1/clients/{id}/measurements
     * - Expected Status: 422
     * - Description: Cannot update with invalid measurement values
     */
    public function test_client_measurements_update_with_negative_values_fails_422()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        $measurementData = [
            'chest_size' => '-10', // Negative value
            'waist_size' => '32',
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        $this->assertValidationError($response, ['chest_size']);
    }

    public function test_client_measurements_update_with_too_long_notes_fails_422()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        $measurementData = [
            'chest_size' => '40',
            'waist_size' => '32',
            'measurement_notes' => str_repeat('A', 1001), // Too long (assuming 1000 char limit)
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        $this->assertValidationError($response, ['measurement_notes']);
    }

    public function test_client_measurements_update_with_invalid_date_fails_422()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        $measurementData = [
            'chest_size' => '40',
            'waist_size' => '32',
            'last_measurement_date' => 'invalid-date',
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        $this->assertValidationError($response, ['last_measurement_date']);
    }

    public function test_client_measurements_update_with_future_date_fails_422()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        $measurementData = [
            'chest_size' => '40',
            'waist_size' => '32',
            'last_measurement_date' => now()->addDays(1)->format('Y-m-d'),
        ];

        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        $this->assertValidationError($response, ['last_measurement_date']);
    }

    /**
     * Test: Measurement Permissions
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify measurement permission requirements
     */
    public function test_client_measurements_get_requires_clients_measurements_view_permission()
    {
        $client = $this->createCompleteClient();

        $this->assertEndpointRequiresPermission(
            'GET',
            "/api/v1/clients/{$client->id}/measurements",
            'clients.measurements.view'
        );
    }

    public function test_client_measurements_update_requires_clients_measurements_update_permission()
    {
        $client = $this->createCompleteClient();

        $measurementData = [
            'chest_size' => '40',
            'waist_size' => '32',
        ];

        $this->assertEndpointRequiresPermission(
            'PUT',
            "/api/v1/clients/{$client->id}/measurements",
            'clients.measurements.update',
            $measurementData
        );
    }

    /**
     * Test: Measurement Data Persistence
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify measurement data is properly stored and retrieved
     */
    public function test_client_measurements_data_persistence()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        // Update measurements
        $measurementData = [
            'chest_size' => '42.5',
            'waist_size' => '34.0',
            'hip_size' => '40.0',
            'shoulder_size' => '17.5',
            'sleeve_size' => '25.0',
            'length_size' => '32.0',
            'measurement_notes' => 'Updated measurements for client',
        ];

        $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        // Retrieve and verify
        $response = $this->getJson("/api/v1/clients/{$client->id}/measurements");

        $response->assertStatus(200)
            ->assertJson([
                'measurements' => [
                    'chest_size' => '42.5',
                    'waist_size' => '34.0',
                    'hip_size' => '40.0',
                    'shoulder_size' => '17.5',
                    'sleeve_size' => '25.0',
                    'length_size' => '32.0',
                    'measurement_notes' => 'Updated measurements for client',
                ]
            ]);
    }

    /**
     * Test: Measurement Update Triggers Timestamp
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Verify measurements_updated_at timestamp is updated
     */
    public function test_client_measurements_update_triggers_timestamp()
    {
        $client = $this->createCompleteClient();
        $originalTimestamp = $client->measurements_updated_at;

        $this->authenticateAs('reception_employee');

        // Wait a moment to ensure timestamp difference
        sleep(1);

        $measurementData = [
            'chest_size' => '40',
            'waist_size' => '32',
        ];

        $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurementData);

        // Refresh client from database
        $client->refresh();

        // Verify timestamp was updated
        $this->assertNotEquals($originalTimestamp, $client->measurements_updated_at);
        $this->assertNotNull($client->measurements_updated_at);
    }

    /**
     * Test: Partial Measurement Updates
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Can update only some measurements without affecting others
     */
    public function test_client_measurements_partial_update()
    {
        $client = $this->createCompleteClient();
        $this->authenticateAs('reception_employee');

        // Set initial measurements
        $initialData = [
            'chest_size' => '40',
            'waist_size' => '32',
            'hip_size' => '38',
        ];

        $this->putJson("/api/v1/clients/{$client->id}/measurements", $initialData);

        // Update only chest_size
        $partialUpdate = [
            'chest_size' => '42',
        ];

        $this->putJson("/api/v1/clients/{$client->id}/measurements", $partialUpdate);

        // Verify only chest_size changed, others remain
        $response = $this->getJson("/api/v1/clients/{$client->id}/measurements");

        $response->assertStatus(200)
            ->assertJson([
                'measurements' => [
                    'chest_size' => '42', // Updated
                    'waist_size' => '32', // Unchanged
                    'hip_size' => '38',  // Unchanged
                ]
            ]);
    }
}
