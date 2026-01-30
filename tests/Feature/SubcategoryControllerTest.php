<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Subcategory;
use App\Models\Category;
use Laravel\Sanctum\Sanctum;

class SubcategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_subcategories()
    {
        Subcategory::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/subcategories');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_subcategory()
    {
        $subcategory = Subcategory::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/subcategories/{$subcategory->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $subcategory->id]);
    }

    public function test_store_creates_subcategory()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $category = Category::factory()->create();

        $data = [
            'category_id' => $category->id,
            'name' => 'New Subcategory',
            'description' => 'Desc',
        ];
        
        $response = $this->postJson('/api/v1/subcategories', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'New Subcategory',
            ]);
        
        $this->assertDatabaseHas('subcategories', ['name' => 'New Subcategory']);
    }

    public function test_update_updates_subcategory()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $subcategory = Subcategory::factory()->create();
        $data = ['name' => 'Updated Subcategory'];

        $response = $this->putJson("/api/v1/subcategories/{$subcategory->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $subcategory->id,
                'name' => 'Updated Subcategory',
            ]);

        $this->assertDatabaseHas('subcategories', ['id' => $subcategory->id, 'name' => 'Updated Subcategory']);
    }

    public function test_destroy_deletes_subcategory()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $subcategory = Subcategory::factory()->create();

        $response = $this->deleteJson("/api/v1/subcategories/{$subcategory->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($subcategory);
    }
}

