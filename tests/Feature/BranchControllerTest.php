<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Address;
use Laravel\Sanctum\Sanctum;

class BranchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_branches()
    {
        Branch::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/branches');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_branch()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/branches/{$branch->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $branch->id]);
    }

    public function test_store_creates_branch()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $inventory = Inventory::factory()->create();
        $address = Address::factory()->create();

        $data = [
            'inventory_id' => $inventory->id,
            'address_id' => $address->id,
            'address' => [
                'city_id' => $address->city_id,
                'street' => $address->street,
                'building' => $address->building,
                'notes' => $address->notes,
            ],
            'branch_code' => 'BR-999',
            'name' => 'New Branch',
        ];
        
        $response = $this->postJson('/api/v1/branches', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'New Branch',
                'branch_code' => 'BR-999',
            ]);
        
        $this->assertDatabaseHas('branches', ['branch_code' => 'BR-999']);
    }

    public function test_update_updates_branch()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $branch = Branch::factory()->create();
        $data = ['name' => 'Updated Branch'];

        $response = $this->putJson("/api/v1/branches/{$branch->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $branch->id,
                'name' => 'Updated Branch',
            ]);

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Updated Branch']);
    }

    public function test_destroy_deletes_branch()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $branch = Branch::factory()->create();

        $response = $this->deleteJson("/api/v1/branches/{$branch->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($branch);
    }
}

