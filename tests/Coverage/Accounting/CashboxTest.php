<?php

namespace Tests\Coverage\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Cashbox;
use App\Models\Branch;
use Laravel\Sanctum\Sanctum;

class CashboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['cashbox.view', 'cashbox.manage'];
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

    public function test_list_cashboxes()
    {
        Cashbox::factory()->count(3)->create();
        $user = $this->createUserWithPermission('cashbox.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cashboxes');
        $response->assertStatus(200);
    }

    public function test_show_cashbox()
    {
        $cashbox = Cashbox::factory()->create();
        $user = $this->createUserWithPermission('cashbox.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/cashboxes/{$cashbox->id}");
        $response->assertStatus(200)->assertJson(['id' => $cashbox->id]);
    }

    public function test_get_cashbox_daily_summary()
    {
        $cashbox = Cashbox::factory()->create();
        $user = $this->createUserWithPermission('cashbox.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/cashboxes/{$cashbox->id}/daily-summary");
        $response->assertStatus(200);
    }

    public function test_update_cashbox()
    {
        $cashbox = Cashbox::factory()->create();
        $user = $this->createUserWithPermission('cashbox.manage');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/cashboxes/{$cashbox->id}", [
            'name' => 'Updated Cashbox Name',
            'description' => 'Updated description',
            'is_active' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cashboxes', [
            'id' => $cashbox->id,
            'name' => 'Updated Cashbox Name',
        ]);
    }

    public function test_recalculate_cashbox_balance()
    {
        $cashbox = Cashbox::factory()->create();
        $user = $this->createUserWithPermission('cashbox.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/cashboxes/{$cashbox->id}/recalculate");
        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'previous_balance', 'calculated_balance', 'difference']);
    }

    public function test_get_branch_cashbox()
    {
        $branch = Branch::factory()->create();
        $branch->refresh(); // Refresh to get the auto-created cashbox
        $cashbox = $branch->cashbox; // Use the auto-created cashbox
        $user = $this->createUserWithPermission('cashbox.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/branches/{$branch->id}/cashbox");
        $response->assertStatus(200)
            ->assertJsonStructure(['cashbox', 'branch', 'today_summary']);
    }
}

