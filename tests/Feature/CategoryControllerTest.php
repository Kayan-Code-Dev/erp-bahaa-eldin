<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use Laravel\Sanctum\Sanctum;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_categories()
    {
        Category::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_category()
    {
        $category = Category::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $category->id]);
    }

    public function test_store_creates_category()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $data = [
            'name' => 'New Category',
            'description' => 'Test Description',
        ];
        
        $response = $this->postJson('/api/v1/categories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'New Category',
            ]);
        
        $this->assertDatabaseHas('categories', ['name' => 'New Category']);
    }

    public function test_update_updates_category()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $category = Category::factory()->create();
        $data = ['name' => 'Updated Category'];

        $response = $this->putJson("/api/v1/categories/{$category->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $category->id,
                'name' => 'Updated Category',
            ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Category']);
    }

    public function test_destroy_deletes_category()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($category);
    }
}

