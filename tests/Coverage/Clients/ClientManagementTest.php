<?php

namespace Tests\Coverage\Clients;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Client;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Phone;
use Laravel\Sanctum\Sanctum;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = [
            'clients.view', 'clients.create', 'clients.update', 'clients.delete', 'clients.export',
            'clients.measurements.view', 'clients.measurements.update',
        ];
        foreach ($permissions as $perm) {
            Permission::findOrCreateByName($perm);
        }
    }

    protected function createUserWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test Role']);
        $role->assignPermission($permission);
        $user->assignRole($role);
        return $user;
    }

    protected function createTestData(): array
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        return ['country' => $country, 'city' => $city];
    }

    public function test_list_clients()
    {
        Client::factory()->count(5)->create();
        $user = $this->createUserWithPermission('clients.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clients');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_clients_with_search_filter()
    {
        Client::factory()->create(['first_name' => 'Ahmed', 'last_name' => 'Ali']);
        Client::factory()->create(['first_name' => 'Mohamed', 'last_name' => 'Hassan']);
        $user = $this->createUserWithPermission('clients.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clients?search=Ahmed');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_create_client()
    {
        $data = $this->createTestData();
        $user = $this->createUserWithPermission('clients.create');
        Sanctum::actingAs($user);

        $clientData = [
            'first_name' => 'John',
            'middle_name' => 'Doe',
            'last_name' => 'Smith',
            'date_of_birth' => '1990-01-01',
            'national_id' => '12345678901234',
            'address' => [
                'street' => 'Test Street',
                'building' => '1',
                'city_id' => $data['city']->id,
            ],
            'phones' => [
                ['phone' => '01234567890', 'type' => 'mobile'],
            ],
        ];
        $response = $this->postJson('/api/v1/clients', $clientData);
        $response->assertStatus(201)->assertJson(['first_name' => 'John', 'national_id' => '12345678901234']);
        $this->assertDatabaseHas('clients', ['national_id' => '12345678901234']);
    }

    public function test_create_client_with_missing_required_fields_fails()
    {
        $user = $this->createUserWithPermission('clients.create');
        Sanctum::actingAs($user);

        // Missing required fields: first_name, middle_name, last_name, date_of_birth, national_id, address, phones
        $response = $this->postJson('/api/v1/clients', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['first_name', 'middle_name', 'last_name', 'date_of_birth', 'national_id', 'address', 'phones']);
    }

    public function test_create_client_with_duplicate_national_id_fails()
    {
        $data = $this->createTestData();
        Client::factory()->create(['national_id' => '12345678901234']);
        $user = $this->createUserWithPermission('clients.create');
        Sanctum::actingAs($user);

        $clientData = [
            'first_name' => 'John',
            'middle_name' => 'Doe',
            'last_name' => 'Smith',
            'date_of_birth' => '1990-01-01',
            'national_id' => '12345678901234',
            'address' => ['street' => 'Test', 'building' => '1', 'city_id' => $data['city']->id],
            'phones' => [['phone' => '01234567891', 'type' => 'mobile']],
        ];
        $response = $this->postJson('/api/v1/clients', $clientData);
        $response->assertStatus(422);
    }

    public function test_show_client()
    {
        $client = Client::factory()->create();
        $user = $this->createUserWithPermission('clients.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/clients/{$client->id}");
        $response->assertStatus(200)->assertJson(['id' => $client->id]);
        $response->assertJsonStructure(['phones', 'address']);
    }

    public function test_update_client()
    {
        $client = Client::factory()->create();
        $user = $this->createUserWithPermission('clients.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/clients/{$client->id}", ['first_name' => 'Updated Name']);
        $response->assertStatus(200)->assertJson(['first_name' => 'Updated Name']);
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'first_name' => 'Updated Name']);
    }

    public function test_delete_client()
    {
        $client = Client::factory()->create();
        $user = $this->createUserWithPermission('clients.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_get_client_measurements()
    {
        $client = Client::factory()->create([
            'breast_size' => '90',
            'waist_size' => '70',
        ]);
        $user = $this->createUserWithPermission('clients.measurements.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/clients/{$client->id}/measurements");
        $response->assertStatus(200)->assertJson(['breast_size' => '90', 'waist_size' => '70']);
    }

    public function test_update_client_measurements()
    {
        $client = Client::factory()->create();
        $user = $this->createUserWithPermission('clients.measurements.update');
        Sanctum::actingAs($user);

        $measurements = [
            'breast_size' => '90',
            'waist_size' => '70',
            'hip_size' => '95',
        ];
        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $measurements);
        $response->assertStatus(200);
        $client->refresh();
        $this->assertEquals('90', $client->breast_size);
        $this->assertNotNull($client->last_measurement_date);
    }

    public function test_update_measurements_with_invalid_values_fails()
    {
        $client = Client::factory()->create();
        $user = $this->createUserWithPermission('clients.measurements.update');
        Sanctum::actingAs($user);

        // Measurement fields have max:20, measurement_notes has max:1000
        $invalidMeasurements = [
            'breast_size' => str_repeat('a', 21), // Exceeds max:20
            'waist_size' => str_repeat('b', 21), // Exceeds max:20
            'measurement_notes' => str_repeat('c', 1001), // Exceeds max:1000
        ];
        $response = $this->putJson("/api/v1/clients/{$client->id}/measurements", $invalidMeasurements);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['breast_size', 'waist_size', 'measurement_notes']);
    }

    public function test_export_clients()
    {
        Client::factory()->count(3)->create();
        $user = $this->createUserWithPermission('clients.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clients/export');
        $response->assertStatus(200);
    }

    public function test_create_client_without_permission_fails()
    {
        $data = $this->createTestData();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/clients', [
            'first_name' => 'Test',
            'middle_name' => 'Test',
            'last_name' => 'Test',
            'date_of_birth' => '1990-01-01',
            'national_id' => '12345678901234',
            'address' => ['street' => 'Test', 'building' => '1', 'city_id' => $data['city']->id],
            'phones' => [['phone' => '01234567890']],
        ]);
        $response->assertStatus(403);
    }
}

