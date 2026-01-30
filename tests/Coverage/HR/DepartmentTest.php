<?php

namespace Tests\Coverage\HR;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Department;
use Laravel\Sanctum\Sanctum;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['hr.departments.view', 'hr.departments.manage'];
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

    public function test_list_departments()
    {
        Department::factory()->count(5)->create();
        $user = $this->createUserWithPermission('hr.departments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/departments');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_department()
    {
        $user = $this->createUserWithPermission('hr.departments.manage');
        Sanctum::actingAs($user);

        $data = ['name' => 'Test Department', 'code' => 'TD'];
        $response = $this->postJson('/api/v1/departments', $data);
        $response->assertStatus(201);
        $this->assertDatabaseHas('departments', ['name' => 'Test Department']);
    }

    public function test_get_department_tree()
    {
        $user = $this->createUserWithPermission('hr.departments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/departments/tree');
        $response->assertStatus(200);
    }

    public function test_show_department()
    {
        $department = Department::factory()->create();
        $user = $this->createUserWithPermission('hr.departments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/departments/{$department->id}");
        $response->assertStatus(200)->assertJson(['id' => $department->id]);
    }

    public function test_update_department()
    {
        $department = Department::factory()->create();
        $user = $this->createUserWithPermission('hr.departments.manage');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/departments/{$department->id}", [
            'name' => 'Updated Department',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Updated Department',
        ]);
    }

    public function test_delete_department()
    {
        $department = Department::factory()->create();
        $user = $this->createUserWithPermission('hr.departments.manage');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/departments/{$department->id}");
        $response->assertStatus(200);
    }
}

