<?php

namespace Tests\Coverage\HR;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['hr.employees.view', 'hr.employees.create', 'hr.employees.update', 'hr.employees.delete'];
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

    public function test_list_employees()
    {
        Employee::factory()->count(5)->create();
        $user = $this->createUserWithPermission('hr.employees.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/employees');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_employee()
    {
        $user = $this->createUserWithPermission('hr.employees.create');
        Sanctum::actingAs($user);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ];
        $response = $this->postJson('/api/v1/employees', $data);
        $response->assertStatus(201);
    }

    public function test_show_employee()
    {
        $employee = Employee::factory()->create();
        $user = $this->createUserWithPermission('hr.employees.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/employees/{$employee->id}");
        $response->assertStatus(200)->assertJson(['id' => $employee->id]);
    }

    public function test_update_employee()
    {
        $employee = Employee::factory()->create();
        $user = $this->createUserWithPermission('hr.employees.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/employees/{$employee->id}", [
            'first_name' => 'Jane',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'Jane',
        ]);
    }

    public function test_delete_employee()
    {
        $employee = Employee::factory()->create();
        $user = $this->createUserWithPermission('hr.employees.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}");
        $response->assertStatus(204);
    }
}

