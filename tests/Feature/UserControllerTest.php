<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_users()
    {
        User::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_user()
    {
        $user_to_find = User::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/users/{$user_to_find->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $user_to_find->id]);
    }

    public function test_store_creates_user()
    {
        $admin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($admin);

        $data = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ];
        
        $response = $this->postJson('/api/v1/users', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'New User',
                'email' => 'newuser@example.com',
            ]);
        
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_update_updates_user()
    {
        $admin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($admin);

        $user = User::factory()->create(['email' => 'userToUpdate@example.com']);
        $data = ['name' => 'Updated User Name'];

        $response = $this->putJson("/api/v1/users/{$user->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'name' => 'Updated User Name',
            ]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated User Name']);
    }

    public function test_destroy_deletes_user()
    {
        $admin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($admin);

        $user = User::factory()->create(['email' => 'userToDelete@example.com']);

        $response = $this->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($user);
    }
}

