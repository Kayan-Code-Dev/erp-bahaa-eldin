<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\City;
use App\Models\Country;
use Laravel\Sanctum\Sanctum;

class CityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_cities()
    {
        City::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cities');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_city()
    {
        $city = City::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/cities/{$city->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $city->id]);
    }

    public function test_store_creates_city()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();

        $data = [
            'country_id' => $country->id,
            'name' => 'New City',
        ];
        
        $response = $this->postJson('/api/v1/cities', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'New City',
            ]);
        
        $this->assertDatabaseHas('cities', ['name' => 'New City']);
    }

    public function test_update_updates_city()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $city = City::factory()->create();
        $data = ['name' => 'Updated City'];

        $response = $this->putJson("/api/v1/cities/{$city->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $city->id,
                'name' => 'Updated City',
            ]);

        $this->assertDatabaseHas('cities', ['id' => $city->id, 'name' => 'Updated City']);
    }

    public function test_destroy_deletes_city()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $city = City::factory()->create();

        $response = $this->deleteJson("/api/v1/cities/{$city->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($city);
    }
}

