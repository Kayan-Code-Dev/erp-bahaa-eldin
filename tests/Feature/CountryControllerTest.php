<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Country;
use Laravel\Sanctum\Sanctum;

class CountryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_countries()
    {
        Country::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/countries');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_country()
    {
        $country = Country::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/countries/{$country->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $country->id]);
    }

    public function test_store_creates_country()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $data = ['name' => 'New Country Test'];
        
        $response = $this->postJson('/api/v1/countries', $data);

        $response->assertStatus(201)
            ->assertJson([
                'name' => 'New Country Test',
            ]);
        
        $this->assertDatabaseHas('countries', ['name' => 'New Country Test']);
    }

    public function test_update_updates_country()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $data = ['name' => 'Updated Country Name'];

        $response = $this->putJson("/api/v1/countries/{$country->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $country->id,
                'name' => 'Updated Country Name',
            ]);

        $this->assertDatabaseHas('countries', ['id' => $country->id, 'name' => 'Updated Country Name']);
    }

    public function test_destroy_deletes_country()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();

        $response = $this->deleteJson("/api/v1/countries/{$country->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($country);
    }
}

