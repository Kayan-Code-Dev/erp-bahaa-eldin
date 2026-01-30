<?php

namespace Tests\Coverage\CoreEntities;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Category;
use Laravel\Sanctum\Sanctum;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['categories.view', 'categories.create', 'categories.update', 'categories.delete', 'categories.export'];
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

    public function test_list_categories()
    {
        Category::factory()->count(5)->create();
        $user = $this->createUserWithPermission('categories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/categories');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_categories_unauthenticated()
    {
        $response = $this->getJson('/api/v1/categories');
        $response->assertStatus(401);
    }

    public function test_create_category()
    {
        $user = $this->createUserWithPermission('categories.create');
        Sanctum::actingAs($user);

        $data = ['name' => 'Test Category', 'description' => 'Test Description'];
        $response = $this->postJson('/api/v1/categories', $data);
        $response->assertStatus(201)->assertJson(['name' => 'Test Category']);
        $this->assertDatabaseHas('categories', ['name' => 'Test Category']);
    }

    public function test_create_category_without_permission()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/categories', ['name' => 'Test']);
        $response->assertStatus(403);
    }

    public function test_show_category()
    {
        $category = Category::factory()->create();
        $user = $this->createUserWithPermission('categories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/categories/{$category->id}");
        $response->assertStatus(200)->assertJson(['id' => $category->id]);
    }

    public function test_update_category()
    {
        $category = Category::factory()->create();
        $user = $this->createUserWithPermission('categories.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/categories/{$category->id}", ['name' => 'Updated Category']);
        $response->assertStatus(200)->assertJson(['name' => 'Updated Category']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Category']);
    }

    public function test_delete_category()
    {
        $category = Category::factory()->create();
        $user = $this->createUserWithPermission('categories.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_export_categories()
    {
        Category::factory()->count(3)->create();
        $user = $this->createUserWithPermission('categories.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/categories/export');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_all_category_endpoints()
    {
        $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/v1/categories');
        $response->assertStatus(200);

        $response = $this->postJson('/api/v1/categories', ['name' => 'Test Category']);
        $response->assertStatus(201);
        $categoryId = $response->json('id');

        $response = $this->getJson("/api/v1/categories/{$categoryId}");
        $response->assertStatus(200);

        $response = $this->putJson("/api/v1/categories/{$categoryId}", ['name' => 'Updated']);
        $response->assertStatus(200);

        $response = $this->deleteJson("/api/v1/categories/{$categoryId}");
        $response->assertStatus(204);
    }
}


