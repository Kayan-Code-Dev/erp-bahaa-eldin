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
use App\Models\Branch;
use App\Models\Inventory;
use Laravel\Sanctum\Sanctum;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['inventories.view', 'inventories.create', 'inventories.update', 'inventories.delete', 'inventories.export'];
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

    public function test_list_inventories()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
            'name' => 'Test Inventory',
        ]);
        $user = $this->createUserWithPermission('inventories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/inventories');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_inventory()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $user = $this->createUserWithPermission('inventories.create');
        Sanctum::actingAs($user);

        $data = [
            'name' => 'Test Inventory',
            'inventoriable_type' => 'branch',
            'inventoriable_id' => $branch->id,
        ];
        $response = $this->postJson('/api/v1/inventories', $data);
        $response->assertStatus(201)->assertJson(['name' => 'Test Inventory']);
        $this->assertDatabaseHas('inventories', ['name' => 'Test Inventory', 'inventoriable_id' => $branch->id]);
    }

    public function test_get_inventory_clothes()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);
        $user = $this->createUserWithPermission('inventories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/inventories/{$inventory->id}/clothes");
        $response->assertStatus(200);
    }

    public function test_show_inventory()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);
        $user = $this->createUserWithPermission('inventories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/inventories/{$inventory->id}");
        $response->assertStatus(200)->assertJson(['id' => $inventory->id]);
    }

    public function test_update_inventory()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);
        $user = $this->createUserWithPermission('inventories.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/inventories/{$inventory->id}", ['name' => 'Updated Inventory']);
        $response->assertStatus(200)->assertJson(['name' => 'Updated Inventory']);
        $this->assertDatabaseHas('inventories', ['id' => $inventory->id, 'name' => 'Updated Inventory']);
    }

    public function test_delete_inventory()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);
        $user = $this->createUserWithPermission('inventories.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/inventories/{$inventory->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('inventories', ['id' => $inventory->id]);
    }

    public function test_export_inventories()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        Inventory::factory()->count(3)->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);
        $user = $this->createUserWithPermission('inventories.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/inventories/export');
        $response->assertStatus(200);
    }
}


