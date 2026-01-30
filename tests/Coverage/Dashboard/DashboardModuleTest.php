<?php

namespace Tests\Coverage\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Laravel\Sanctum\Sanctum;

class DashboardModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['dashboard.view'];
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

    public function test_get_dashboard_data()
    {
        $user = $this->createUserWithPermission('dashboard.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/overview');
        $response->assertStatus(200);
    }

    public function test_get_dashboard_summary()
    {
        $user = $this->createUserWithPermission('dashboard.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/summary');
        $response->assertStatus(200);
    }
}

