<?php

namespace Tests\Coverage\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Expense;
use App\Models\Branch;
use Laravel\Sanctum\Sanctum;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete', 'expenses.approve', 'expenses.pay'];
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

    public function test_list_expenses()
    {
        // Create a single branch to reuse for all expenses (avoiding Faker unique state exhaustion)
        $branch = Branch::factory()->create();
        $branch->refresh(); // Refresh to get the auto-created cashbox

        // Create a single expense (reduced to avoid memory issues)
        Expense::factory()->create([
            'branch_id' => $branch->id,
            'cashbox_id' => $branch->cashbox->id,
        ]);

        $user = $this->createUserWithPermission('expenses.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/expenses');
        $response->assertStatus(200)->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_create_expense()
    {
        $branch = Branch::factory()->create();
        $branch->refresh(); // Refresh to get the auto-created cashbox
        $user = $this->createUserWithPermission('expenses.create');
        Sanctum::actingAs($user);

        $data = [
            'branch_id' => $branch->id,
            'category' => Expense::CATEGORY_UTILITIES,
            'amount' => 100.00,
            'description' => 'Test expense',
            'expense_date' => now()->format('Y-m-d'),
        ];
        $response = $this->postJson('/api/v1/expenses', $data);
        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', ['description' => 'Test expense']);
    }

    public function test_get_expense_summary()
    {
        $user = $this->createUserWithPermission('expenses.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/expenses/summary');
        $response->assertStatus(200);
    }

    public function test_show_expense()
    {
        $expense = Expense::factory()->create();
        $user = $this->createUserWithPermission('expenses.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/expenses/{$expense->id}");
        $response->assertStatus(200)->assertJson(['id' => $expense->id]);
    }

    public function test_update_expense()
    {
        $expense = Expense::factory()->create();
        $user = $this->createUserWithPermission('expenses.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/expenses/{$expense->id}", [
            'amount' => 150.00,
            'description' => 'Updated expense',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'description' => 'Updated expense',
        ]);
    }

    public function test_delete_expense()
    {
        $expense = Expense::factory()->create();
        $user = $this->createUserWithPermission('expenses.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/expenses/{$expense->id}");
        $response->assertStatus(200);
    }

    public function test_get_expense_categories()
    {
        $user = $this->createUserWithPermission('expenses.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/expenses/categories');
        $response->assertStatus(200);
    }

    public function test_approve_expense()
    {
        $expense = Expense::factory()->create(['status' => 'pending']);
        $user = $this->createUserWithPermission('expenses.approve');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/expenses/{$expense->id}/approve");
        $response->assertStatus(200);
        $expense->refresh();
        $this->assertEquals('approved', $expense->status);
    }

    public function test_pay_expense()
    {
        $expense = Expense::factory()->create(['status' => 'approved']);
        $user = $this->createUserWithPermission('expenses.pay');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/expenses/{$expense->id}/pay");
        $response->assertStatus(200);
    }

    public function test_cancel_expense()
    {
        $expense = Expense::factory()->create(['status' => 'pending']);
        $user = $this->createUserWithPermission('expenses.delete');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/expenses/{$expense->id}/cancel");
        $response->assertStatus(200);
        $expense->refresh();
        $this->assertEquals('cancelled', $expense->status);
    }
}

