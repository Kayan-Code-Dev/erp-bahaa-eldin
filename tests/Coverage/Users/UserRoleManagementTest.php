<?php

namespace Tests\Coverage\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Laravel\Sanctum\Sanctum;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['users.view', 'users.create', 'users.update', 'users.delete', 'roles.view', 'roles.manage'];
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

    public function test_list_users()
    {
        User::factory()->count(5)->create();
        $user = $this->createUserWithPermission('users.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_user()
    {
        $user = $this->createUserWithPermission('users.create');
        Sanctum::actingAs($user);

        $data = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password',
        ];
        $response = $this->postJson('/api/v1/users', $data);
        $response->assertStatus(201);
    }

    public function test_list_roles()
    {
        $user = $this->createUserWithPermission('roles.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/roles');
        $response->assertStatus(200);
    }

    public function test_show_user()
    {
        $testUser = User::factory()->create();
        $user = $this->createUserWithPermission('users.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/users/{$testUser->id}");
        $response->assertStatus(200)->assertJson(['id' => $testUser->id]);
    }

    public function test_update_user()
    {
        $testUser = User::factory()->create();
        $user = $this->createUserWithPermission('users.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/users/{$testUser->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $testUser->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_delete_user()
    {
        $testUser = User::factory()->create();
        $user = $this->createUserWithPermission('users.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/users/{$testUser->id}");
        $response->assertStatus(204);
    }
}

