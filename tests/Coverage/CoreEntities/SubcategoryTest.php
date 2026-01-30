<?php

namespace Tests\Coverage\CoreEntities;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Category;
use App\Models\Subcategory;
use Laravel\Sanctum\Sanctum;

class SubcategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['subcategories.view', 'subcategories.create', 'subcategories.update', 'subcategories.delete', 'subcategories.export'];
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

    public function test_list_subcategories()
    {
        $category = Category::factory()->create();
        Subcategory::factory()->count(5)->create(['category_id' => $category->id]);
        $user = $this->createUserWithPermission('subcategories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subcategories');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_subcategories_with_category_filter()
    {
        $category = Category::factory()->create();
        Subcategory::factory()->count(3)->create(['category_id' => $category->id]);
        $user = $this->createUserWithPermission('subcategories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/subcategories?category_id={$category->id}");
        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_create_subcategory()
    {
        $category = Category::factory()->create();
        $user = $this->createUserWithPermission('subcategories.create');
        Sanctum::actingAs($user);

        $data = ['name' => 'Test Subcategory', 'category_id' => $category->id];
        $response = $this->postJson('/api/v1/subcategories', $data);
        $response->assertStatus(201)->assertJson(['name' => 'Test Subcategory']);
        $this->assertDatabaseHas('subcategories', ['name' => 'Test Subcategory', 'category_id' => $category->id]);
    }

    public function test_show_subcategory()
    {
        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $user = $this->createUserWithPermission('subcategories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/subcategories/{$subcategory->id}");
        $response->assertStatus(200)->assertJson(['id' => $subcategory->id]);
    }

    public function test_update_subcategory()
    {
        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $user = $this->createUserWithPermission('subcategories.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/subcategories/{$subcategory->id}", ['name' => 'Updated Subcategory']);
        $response->assertStatus(200)->assertJson(['name' => 'Updated Subcategory']);
        $this->assertDatabaseHas('subcategories', ['id' => $subcategory->id, 'name' => 'Updated Subcategory']);
    }

    public function test_delete_subcategory()
    {
        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $user = $this->createUserWithPermission('subcategories.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/subcategories/{$subcategory->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('subcategories', ['id' => $subcategory->id]);
    }

    public function test_export_subcategories()
    {
        $category = Category::factory()->create();
        Subcategory::factory()->count(3)->create(['category_id' => $category->id]);
        $user = $this->createUserWithPermission('subcategories.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subcategories/export');
        $response->assertStatus(200);
    }
}


