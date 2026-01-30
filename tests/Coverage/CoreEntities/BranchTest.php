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
use Laravel\Sanctum\Sanctum;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['branches.view', 'branches.create', 'branches.update', 'branches.delete', 'branches.export'];
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

    public function test_list_branches()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        Branch::factory()->count(5)->create(['address_id' => $address->id]);
        $user = $this->createUserWithPermission('branches.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/branches');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_branch()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('branches.create');
        Sanctum::actingAs($user);

        $data = [
            'branch_code' => 'BR-001',
            'name' => 'Test Branch',
            'address' => [
                'street' => 'Test Street',
                'building' => '1',
                'city_id' => $city->id,
            ],
        ];
        $response = $this->postJson('/api/v1/branches', $data);
        $response->assertStatus(201)->assertJson(['name' => 'Test Branch', 'branch_code' => 'BR-001']);
        $this->assertDatabaseHas('branches', ['branch_code' => 'BR-001', 'name' => 'Test Branch']);
        $this->assertNotNull($response->json('inventory'));
    }

    public function test_create_branch_with_inventory_name()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('branches.create');
        Sanctum::actingAs($user);

        $data = [
            'branch_code' => 'BR-002',
            'name' => 'Test Branch 2',
            'address' => ['street' => 'Test', 'building' => '1', 'city_id' => $city->id],
            'inventory_name' => 'Custom Inventory Name',
        ];
        $response = $this->postJson('/api/v1/branches', $data);
        $response->assertStatus(201);
        $branch = Branch::find($response->json('id'));
        $this->assertEquals('Custom Inventory Name', $branch->inventory->name);
    }

    public function test_show_branch()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $user = $this->createUserWithPermission('branches.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/branches/{$branch->id}");
        $response->assertStatus(200)->assertJson(['id' => $branch->id]);
        $response->assertJsonStructure(['inventory', 'address']);
    }

    public function test_update_branch()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $user = $this->createUserWithPermission('branches.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/branches/{$branch->id}", ['name' => 'Updated Branch']);
        $response->assertStatus(200)->assertJson(['name' => 'Updated Branch']);
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Updated Branch']);
    }

    public function test_delete_branch()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $user = $this->createUserWithPermission('branches.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/branches/{$branch->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
    }

    public function test_export_branches()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        Branch::factory()->count(3)->create(['address_id' => $address->id]);
        $user = $this->createUserWithPermission('branches.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/branches/export');
        $response->assertStatus(200);
    }

    public function test_create_branch_creates_inventory_automatically()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('branches.create');
        Sanctum::actingAs($user);

        $data = [
            'branch_code' => 'BR-003',
            'name' => 'Auto Inventory Branch',
            'address' => ['street' => 'Test', 'building' => '1', 'city_id' => $city->id],
        ];
        $response = $this->postJson('/api/v1/branches', $data);
        $branch = Branch::find($response->json('id'));
        $this->assertNotNull($branch->inventory);
        $this->assertEquals('Auto Inventory Branch Inventory', $branch->inventory->name);
    }
}


