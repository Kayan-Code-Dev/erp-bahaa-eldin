<?php

namespace Tests\Coverage\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\Cashbox;
use Laravel\Sanctum\Sanctum;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['transactions.view', 'cashbox.manage', 'transactions.create'];
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

    public function test_list_transactions()
    {
        Transaction::factory()->count(5)->create();
        $user = $this->createUserWithPermission('transactions.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transactions');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_show_transaction()
    {
        $transaction = Transaction::factory()->create();
        $user = $this->createUserWithPermission('transactions.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/transactions/{$transaction->id}");
        $response->assertStatus(200)->assertJson(['id' => $transaction->id]);
    }

    public function test_get_transaction_categories()
    {
        $user = $this->createUserWithPermission('transactions.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transactions/categories');
        $response->assertStatus(200);
    }

    public function test_get_transactions_for_cashbox()
    {
        $cashbox = Cashbox::factory()->create();
        Transaction::factory()->count(3)->create(['cashbox_id' => $cashbox->id]);
        $user = $this->createUserWithPermission('transactions.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/cashboxes/{$cashbox->id}/transactions");
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_reverse_transaction()
    {
        $transaction = Transaction::factory()->create();
        $user = $this->createUserWithPermission('cashbox.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/reverse");
        $response->assertStatus(200);
    }
}

