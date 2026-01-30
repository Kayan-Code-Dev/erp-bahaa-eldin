<?php

namespace Tests\Coverage\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Laravel\Sanctum\Sanctum;

class ReportsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['reports.view', 'reports.financial', 'reports.sales', 'reports.inventory'];
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

    public function test_get_sales_report()
    {
        $user = $this->createUserWithPermission('reports.sales');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/sales');
        $response->assertStatus(200);
    }

    public function test_get_financial_report()
    {
        $user = $this->createUserWithPermission('reports.financial');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/financial');
        $response->assertStatus(200);
    }

    public function test_get_available_dresses_report()
    {
        $user = $this->createUserWithPermission('reports.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/available-dresses');
        $response->assertStatus(200);
    }

    public function test_get_expenses_report()
    {
        $user = $this->createUserWithPermission('reports.financial');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/expenses');
        $response->assertStatus(200);
    }

    public function test_get_deposits_report()
    {
        $user = $this->createUserWithPermission('reports.financial');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/deposits');
        $response->assertStatus(200);
    }
}

