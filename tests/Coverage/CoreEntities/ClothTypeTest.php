<?php

namespace Tests\Coverage\CoreEntities;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\ClothType;
use Laravel\Sanctum\Sanctum;

class ClothTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['cloth-types.view', 'cloth-types.create', 'cloth-types.update', 'cloth-types.delete', 'cloth-types.export'];
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

    public function test_list_cloth_types()
    {
        ClothType::factory()->count(5)->create();
        $user = $this->createUserWithPermission('cloth-types.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cloth-types');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_cloth_type()
    {
        $user = $this->createUserWithPermission('cloth-types.create');
        Sanctum::actingAs($user);

        $data = ['code' => 'CT-001', 'name' => 'Test Cloth Type', 'description' => 'Test Description'];
        $response = $this->postJson('/api/v1/cloth-types', $data);
        $response->assertStatus(201)->assertJson(['code' => 'CT-001', 'name' => 'Test Cloth Type']);
        $this->assertDatabaseHas('cloth_types', ['code' => 'CT-001', 'name' => 'Test Cloth Type']);
    }

    public function test_create_cloth_type_with_subcategories()
    {
        $category = Category::factory()->create();
        $subcategory1 = Subcategory::factory()->create(['category_id' => $category->id]);
        $subcategory2 = Subcategory::factory()->create(['category_id' => $category->id]);
        $user = $this->createUserWithPermission('cloth-types.create');
        Sanctum::actingAs($user);

        $data = [
            'code' => 'CT-002',
            'name' => 'Test Cloth Type 2',
            'subcat_id' => [$subcategory1->id, $subcategory2->id],
        ];
        $response = $this->postJson('/api/v1/cloth-types', $data);
        $response->assertStatus(201);
        $clothType = ClothType::find($response->json('id'));
        $this->assertCount(2, $clothType->subcategories);
    }

    public function test_show_cloth_type()
    {
        $clothType = ClothType::factory()->create();
        $user = $this->createUserWithPermission('cloth-types.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/cloth-types/{$clothType->id}");
        $response->assertStatus(200)->assertJson(['id' => $clothType->id]);
    }

    public function test_update_cloth_type()
    {
        $clothType = ClothType::factory()->create();
        $user = $this->createUserWithPermission('cloth-types.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/cloth-types/{$clothType->id}", ['name' => 'Updated Cloth Type']);
        $response->assertStatus(200)->assertJson(['name' => 'Updated Cloth Type']);
        $this->assertDatabaseHas('cloth_types', ['id' => $clothType->id, 'name' => 'Updated Cloth Type']);
    }

    public function test_delete_cloth_type()
    {
        $clothType = ClothType::factory()->create();
        $user = $this->createUserWithPermission('cloth-types.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/cloth-types/{$clothType->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('cloth_types', ['id' => $clothType->id]);
    }

    public function test_export_cloth_types()
    {
        ClothType::factory()->count(3)->create();
        $user = $this->createUserWithPermission('cloth-types.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cloth-types/export');
        $response->assertStatus(200);
    }
}


