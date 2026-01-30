<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Inventory;
use App\Models\Address;
use App\Models\City;
use Laravel\Sanctum\Sanctum;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_inventories()
    {
        Inventory::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/inventories');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_inventory()
    {
        $inventory = Inventory::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/inventories/{$inventory->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $inventory->id]);
    }

    public function test_store_creates_inventory()
    {
        $this->markTestSkipped('Store method not implemented in InventoryController');
    }

    public function test_update_updates_inventory()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $inventory = Inventory::factory()->create();
        $data = ['name' => 'Updated Warehouse'];

        $response = $this->putJson("/api/v1/inventories/{$inventory->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $inventory->id,
                'name' => 'Updated Warehouse',
            ]);

        $this->assertDatabaseHas('inventories', ['id' => $inventory->id, 'name' => 'Updated Warehouse']);
    }

    public function test_destroy_deletes_inventory()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $inventory = Inventory::factory()->create();

        $response = $this->deleteJson("/api/v1/inventories/{$inventory->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($inventory);
    }
}

