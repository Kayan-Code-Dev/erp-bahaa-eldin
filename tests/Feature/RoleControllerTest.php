<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Laravel\Sanctum\Sanctum;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_roles()
    {
        Role::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_role()
    {
        $role = Role::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $role->id]);
    }

    public function test_store_creates_role()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $data = [
            'name' => 'New Role',
            'description' => 'Test Desc',
        ];
        
        $response = $this->postJson('/api/v1/roles', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'New Role',
            ]);
        
        $this->assertDatabaseHas('roles', ['name' => 'New Role']);
    }

    public function test_update_updates_role()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $role = Role::factory()->create();
        $data = ['name' => 'Updated Role'];

        $response = $this->putJson("/api/v1/roles/{$role->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $role->id,
                'name' => 'Updated Role',
            ]);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Updated Role']);
    }

    public function test_destroy_deletes_role()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $role = Role::factory()->create();

        $response = $this->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(204);

        // Roles table doesn't have soft deletes in migration? 
        // Migration check: $table->timestamps(); no softDeletes mentioned in 2025_12_19_185803_create_roles_table.php
        // Wait, let me double check migration 2025_12_19_185803.
        // It does NOT have softDeletes.
        
        $this->assertModelMissing($role);
    }
}

