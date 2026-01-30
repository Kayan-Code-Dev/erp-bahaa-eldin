<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\City;
use App\Models\Country;
use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientMeasurementsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $city;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user for authentication (super admin for permissions)
        $this->user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);

        // Create country and city for address
        $country = Country::create(['name' => 'Egypt']);
        $this->city = City::create(['name' => 'Cairo', 'country_id' => $country->id]);
    }

    /**
     * Helper to create a client with address
     */
    private function createClientWithAddress(array $overrides = []): Client
    {
        $address = Address::create([
            'street' => 'Test Street',
            'building' => '123',
            'city_id' => $this->city->id,
        ]);

        return Client::create(array_merge([
            'first_name' => 'Test',
            'middle_name' => 'Middle',
            'last_name' => 'Client',
            'date_of_birth' => '1990-01-01',
            'national_id' => '12345678901234',
            'address_id' => $address->id,
        ], $overrides));
    }

    /** @test */
    public function client_can_be_created_with_measurements()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/clients', [
            'first_name' => 'Ahmed',
            'middle_name' => 'Mohamed',
            'last_name' => 'Ali',
            'date_of_birth' => '1990-05-15',
            'national_id' => '12345678901234',
            'breast_size' => '90',
            'waist_size' => '70',
            'sleeve_size' => '60',
            'hip_size' => '95',
            'shoulder_size' => '40',
            'length_size' => '160',
            'measurement_notes' => 'Prefers loose fit',
            'address' => [
                'street' => 'Tahrir Square',
                'building' => '2A',
                'city_id' => $this->city->id,
            ],
            'phones' => [
                ['phone' => '01234567890', 'type' => 'mobile'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('breast_size', '90')
            ->assertJsonPath('waist_size', '70')
            ->assertJsonPath('sleeve_size', '60')
            ->assertJsonPath('hip_size', '95')
            ->assertJsonPath('shoulder_size', '40')
            ->assertJsonPath('length_size', '160')
            ->assertJsonPath('measurement_notes', 'Prefers loose fit');

        // Verify last_measurement_date is set
        $this->assertNotNull($response->json('last_measurement_date'));
    }

    /** @test */
    public function client_can_be_created_without_measurements()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/clients', [
            'first_name' => 'Ahmed',
            'middle_name' => 'Mohamed',
            'last_name' => 'Ali',
            'date_of_birth' => '1990-05-15',
            'national_id' => '12345678901234',
            'address' => [
                'street' => 'Tahrir Square',
                'building' => '2A',
                'city_id' => $this->city->id,
            ],
            'phones' => [
                ['phone' => '01234567890', 'type' => 'mobile'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('breast_size', null)
            ->assertJsonPath('waist_size', null)
            ->assertJsonPath('last_measurement_date', null);
    }

    /** @test */
    public function client_measurements_can_be_updated_via_put()
    {
        $client = $this->createClientWithAddress(['national_id' => '99999999999999']);
        $client->phones()->create(['phone' => '09876543210', 'type' => 'mobile']);

        $response = $this->actingAs($this->user)->putJson("/api/v1/clients/{$client->id}", [
            'breast_size' => '92',
            'waist_size' => '72',
            'hip_size' => '97',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('breast_size', '92')
            ->assertJsonPath('waist_size', '72')
            ->assertJsonPath('hip_size', '97');

        // Verify last_measurement_date is updated
        $this->assertNotNull($response->json('last_measurement_date'));
    }

    /** @test */
    public function client_measurements_can_be_updated_via_dedicated_endpoint()
    {
        $client = $this->createClientWithAddress();

        $response = $this->actingAs($this->user)->putJson("/api/v1/clients/{$client->id}/measurements", [
            'breast_size' => '88',
            'waist_size' => '68',
            'sleeve_size' => '58',
            'hip_size' => '93',
            'shoulder_size' => '38',
            'length_size' => '155',
            'measurement_notes' => 'Updated measurements',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Measurements updated successfully')
            ->assertJsonPath('client.breast_size', '88')
            ->assertJsonPath('client.waist_size', '68')
            ->assertJsonPath('client.sleeve_size', '58')
            ->assertJsonPath('client.hip_size', '93')
            ->assertJsonPath('client.shoulder_size', '38')
            ->assertJsonPath('client.length_size', '155')
            ->assertJsonPath('client.measurement_notes', 'Updated measurements');

        // Verify last_measurement_date is set
        $this->assertNotNull($response->json('client.last_measurement_date'));
    }

    /** @test */
    public function client_measurements_can_be_retrieved()
    {
        $client = $this->createClientWithAddress([
            'breast_size' => '90',
            'waist_size' => '70',
            'sleeve_size' => '60',
            'hip_size' => '95',
            'shoulder_size' => '40',
            'length_size' => '160',
            'measurement_notes' => 'Test notes',
            'last_measurement_date' => '2025-01-09',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/clients/{$client->id}/measurements");

        $response->assertStatus(200)
            ->assertJsonPath('client_id', $client->id)
            ->assertJsonPath('breast_size', '90')
            ->assertJsonPath('waist_size', '70')
            ->assertJsonPath('sleeve_size', '60')
            ->assertJsonPath('hip_size', '95')
            ->assertJsonPath('shoulder_size', '40')
            ->assertJsonPath('length_size', '160')
            ->assertJsonPath('measurement_notes', 'Test notes')
            ->assertJsonPath('has_measurements', true);
    }

    /** @test */
    public function has_measurements_returns_false_when_no_measurements()
    {
        $client = $this->createClientWithAddress();

        $response = $this->actingAs($this->user)->getJson("/api/v1/clients/{$client->id}/measurements");

        $response->assertStatus(200)
            ->assertJsonPath('has_measurements', false)
            ->assertJsonPath('breast_size', null)
            ->assertJsonPath('waist_size', null);
    }

    /** @test */
    public function measurement_fields_are_included_in_client_show()
    {
        $client = $this->createClientWithAddress([
            'breast_size' => '90',
            'waist_size' => '70',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJsonPath('breast_size', '90')
            ->assertJsonPath('waist_size', '70');
    }

    /** @test */
    public function measurement_fields_are_included_in_client_index()
    {
        $client = $this->createClientWithAddress([
            'breast_size' => '90',
            'waist_size' => '70',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/clients');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        
        $foundClient = collect($data)->firstWhere('id', $client->id);
        $this->assertEquals('90', $foundClient['breast_size']);
        $this->assertEquals('70', $foundClient['waist_size']);
    }

    /** @test */
    public function measurement_validation_enforces_max_length()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/clients', [
            'first_name' => 'Ahmed',
            'middle_name' => 'Mohamed',
            'last_name' => 'Ali',
            'date_of_birth' => '1990-05-15',
            'national_id' => '12345678901234',
            'breast_size' => str_repeat('x', 25), // Exceeds max:20
            'address' => [
                'street' => 'Tahrir Square',
                'building' => '2A',
                'city_id' => $this->city->id,
            ],
            'phones' => [
                ['phone' => '01234567890', 'type' => 'mobile'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['breast_size']);
    }

    /** @test */
    public function measurement_notes_validation_enforces_max_length()
    {
        $client = $this->createClientWithAddress();

        $response = $this->actingAs($this->user)->putJson("/api/v1/clients/{$client->id}/measurements", [
            'measurement_notes' => str_repeat('x', 1005), // Exceeds max:1000
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['measurement_notes']);
    }

    /** @test */
    public function update_measurements_returns_404_for_nonexistent_client()
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/clients/99999/measurements', [
            'breast_size' => '90',
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function get_measurements_returns_404_for_nonexistent_client()
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/clients/99999/measurements');

        $response->assertStatus(404);
    }

    /** @test */
    public function model_has_measurements_method_works_correctly()
    {
        $clientWithMeasurements = $this->createClientWithAddress([
            'national_id' => '11111111111111',
            'breast_size' => '90',
        ]);

        $clientWithoutMeasurements = $this->createClientWithAddress([
            'national_id' => '22222222222222',
        ]);

        $this->assertTrue($clientWithMeasurements->hasMeasurements());
        $this->assertFalse($clientWithoutMeasurements->hasMeasurements());
    }

    /** @test */
    public function model_get_measurements_method_returns_array()
    {
        $client = $this->createClientWithAddress([
            'breast_size' => '90',
            'waist_size' => '70',
            'last_measurement_date' => '2025-01-09',
        ]);

        $measurements = $client->getMeasurements();

        $this->assertIsArray($measurements);
        $this->assertEquals('90', $measurements['breast_size']);
        $this->assertEquals('70', $measurements['waist_size']);
        $this->assertEquals('2025-01-09', $measurements['last_measurement_date']);
    }

    /** @test */
    public function model_update_measurements_method_auto_sets_date()
    {
        $client = $this->createClientWithAddress();

        $this->assertNull($client->last_measurement_date);

        $client->updateMeasurements([
            'breast_size' => '90',
            'waist_size' => '70',
        ]);

        $client->refresh();

        $this->assertEquals('90', $client->breast_size);
        $this->assertEquals('70', $client->waist_size);
        $this->assertNotNull($client->last_measurement_date);
    }

    /** @test */
    public function measurement_endpoints_require_authentication()
    {
        $client = $this->createClientWithAddress();

        // Test GET measurements without auth
        $response = $this->getJson("/api/v1/clients/{$client->id}/measurements");
        $response->assertStatus(401);

        // Test PUT measurements without auth
        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", [
            'breast_size' => '90',
        ]);
        $response->assertStatus(401);
    }
}



