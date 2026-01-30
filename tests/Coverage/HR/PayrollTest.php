<?php

namespace Tests\Coverage\HR;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Payroll;
use Laravel\Sanctum\Sanctum;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['hr.payroll.view', 'hr.payroll.generate', 'hr.payroll.manage'];
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

    public function test_list_payrolls()
    {
        $user = $this->createUserWithPermission('hr.payroll.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/payrolls');
        $response->assertStatus(200);
    }

    public function test_show_payroll()
    {
        $payroll = Payroll::factory()->create();
        $user = $this->createUserWithPermission('hr.payroll.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}");
        $response->assertStatus(200)->assertJson(['id' => $payroll->id]);
    }

    public function test_get_payroll_statuses()
    {
        $user = $this->createUserWithPermission('hr.payroll.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/payrolls/statuses');
        $response->assertStatus(200);
    }
}

