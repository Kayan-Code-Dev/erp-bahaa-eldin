<?php

namespace Tests\Coverage\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Receivable;
use Laravel\Sanctum\Sanctum;

class ReceivableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['receivables.view', 'receivables.create', 'receivables.update', 'receivables.collect'];
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

    public function test_list_receivables()
    {
        Receivable::factory()->count(5)->create();
        $user = $this->createUserWithPermission('receivables.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/receivables');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_receivable()
    {
        $user = $this->createUserWithPermission('receivables.create');
        Sanctum::actingAs($user);

        $data = [
            'amount' => 500.00,
            'description' => 'Test receivable',
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ];
        $response = $this->postJson('/api/v1/receivables', $data);
        $response->assertStatus(201);
    }

    public function test_collect_receivable()
    {
        $receivable = Receivable::factory()->create(['status' => 'pending']);
        $user = $this->createUserWithPermission('receivables.collect');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/receivables/{$receivable->id}/collect", [
            'amount' => $receivable->amount,
            'collection_date' => now()->format('Y-m-d'),
        ]);
        $response->assertStatus(200);
    }

    public function test_show_receivable()
    {
        $receivable = Receivable::factory()->create();
        $user = $this->createUserWithPermission('receivables.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/receivables/{$receivable->id}");
        $response->assertStatus(200)->assertJson(['id' => $receivable->id]);
    }

    public function test_update_receivable()
    {
        $receivable = Receivable::factory()->create();
        $user = $this->createUserWithPermission('receivables.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/receivables/{$receivable->id}", [
            'amount' => 600.00,
            'description' => 'Updated receivable',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'description' => 'Updated receivable',
        ]);
    }
}

