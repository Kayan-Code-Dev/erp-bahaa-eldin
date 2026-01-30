<?php

namespace Tests\Coverage\CoreEntities;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use Laravel\Sanctum\Sanctum;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['addresses.view', 'addresses.create', 'addresses.update', 'addresses.delete', 'addresses.export'];
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

    public function test_list_addresses()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        Address::factory()->count(5)->create(['city_id' => $city->id]);
        $user = $this->createUserWithPermission('addresses.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/addresses');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_addresses_unauthenticated()
    {
        $response = $this->getJson('/api/v1/addresses');
        $response->assertStatus(401);
    }

    public function test_create_address()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('addresses.create');
        Sanctum::actingAs($user);

        $data = ['street' => 'Tahrir St', 'building' => '2A', 'city_id' => $city->id];
        $response = $this->postJson('/api/v1/addresses', $data);
        $response->assertStatus(201)->assertJson(['street' => 'Tahrir St']);
        $this->assertDatabaseHas('addresses', ['street' => 'Tahrir St', 'building' => '2A']);
    }

    public function test_create_address_without_permission()
    {
        $city = City::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/addresses', ['street' => 'Test', 'building' => '1', 'city_id' => $city->id]);
        $response->assertStatus(403);
    }

    public function test_create_address_missing_required_fields()
    {
        $user = $this->createUserWithPermission('addresses.create');
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/addresses', []);
        $response->assertStatus(422);
    }

    public function test_show_address()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $user = $this->createUserWithPermission('addresses.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/addresses/{$address->id}");
        $response->assertStatus(200)->assertJson(['id' => $address->id]);
    }

    public function test_update_address()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $user = $this->createUserWithPermission('addresses.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/addresses/{$address->id}", ['street' => 'Updated Street']);
        $response->assertStatus(200)->assertJson(['street' => 'Updated Street']);
        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'street' => 'Updated Street']);
    }

    public function test_delete_address()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $user = $this->createUserWithPermission('addresses.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/addresses/{$address->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('addresses', ['id' => $address->id]);
    }

    public function test_export_addresses()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        Address::factory()->count(3)->create(['city_id' => $city->id]);
        $user = $this->createUserWithPermission('addresses.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/addresses/export');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_all_address_endpoints()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/v1/addresses');
        $response->assertStatus(200);

        $response = $this->postJson('/api/v1/addresses', ['street' => 'Test', 'building' => '1', 'city_id' => $city->id]);
        $response->assertStatus(201);
        $addressId = $response->json('id');

        $response = $this->getJson("/api/v1/addresses/{$addressId}");
        $response->assertStatus(200);

        $response = $this->putJson("/api/v1/addresses/{$addressId}", ['street' => 'Updated']);
        $response->assertStatus(200);

        $response = $this->deleteJson("/api/v1/addresses/{$addressId}");
        $response->assertStatus(204);
    }
}


