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
use App\Models\ClothType;
use App\Models\Cloth;
use App\Models\Inventory;
use Laravel\Sanctum\Sanctum;

class ClothTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['clothes.view', 'clothes.create', 'clothes.update', 'clothes.delete', 'clothes.export'];
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

    protected function createBranchWithInventory(): array
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        return ['branch' => $branch, 'inventory' => $branch->inventory];
    }

    public function test_list_clothes()
    {
        $clothType = ClothType::factory()->create();
        Cloth::factory()->count(5)->create(['cloth_type_id' => $clothType->id]);
        $user = $this->createUserWithPermission('clothes.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clothes');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_cloth()
    {
        $setup = $this->createBranchWithInventory();
        $clothType = ClothType::factory()->create();
        $user = $this->createUserWithPermission('clothes.create');
        Sanctum::actingAs($user);

        $data = [
            'code' => 'CL-001',
            'name' => 'Test Cloth',
            'cloth_type_id' => $clothType->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
        ];
        $response = $this->postJson('/api/v1/clothes', $data);
        $response->assertStatus(201)->assertJson(['code' => 'CL-001', 'name' => 'Test Cloth']);
        $this->assertDatabaseHas('clothes', ['code' => 'CL-001', 'name' => 'Test Cloth']);
    }

    public function test_show_cloth()
    {
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        $user = $this->createUserWithPermission('clothes.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/clothes/{$cloth->id}");
        $response->assertStatus(200)->assertJson(['id' => $cloth->id]);
    }

    public function test_update_cloth()
    {
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        $user = $this->createUserWithPermission('clothes.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/clothes/{$cloth->id}", ['name' => 'Updated Cloth']);
        $response->assertStatus(200)->assertJson(['name' => 'Updated Cloth']);
        $this->assertDatabaseHas('clothes', ['id' => $cloth->id, 'name' => 'Updated Cloth']);
    }

    public function test_delete_cloth()
    {
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        $user = $this->createUserWithPermission('clothes.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/clothes/{$cloth->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('clothes', ['id' => $cloth->id]);
    }

    public function test_export_clothes()
    {
        $clothType = ClothType::factory()->create();
        Cloth::factory()->count(3)->create(['cloth_type_id' => $clothType->id]);
        $user = $this->createUserWithPermission('clothes.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clothes/export');
        $response->assertStatus(200);
    }

    public function test_get_cloth_unavailable_days()
    {
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        $user = $this->createUserWithPermission('clothes.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/clothes/{$cloth->id}/unavailable-days");
        $response->assertStatus(200);
    }
}


