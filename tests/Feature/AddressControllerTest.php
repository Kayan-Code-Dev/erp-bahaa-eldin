<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Address;
use App\Models\City;
use Laravel\Sanctum\Sanctum;

class AddressControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_addresses()
    {
        Address::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/addresses');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_address()
    {
        $address = Address::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $address->id]);
    }

    public function test_store_creates_address()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $city = City::factory()->create();

        $data = [
            'city_id' => $city->id,
            'street' => '123 Main St',
            'building' => '10A',
            'notes' => 'Near park',
        ];
        
        $response = $this->postJson('/api/v1/addresses', $data);

        $response->assertStatus(201)
            ->assertJson([
                'street' => '123 Main St',
            ]);
        
        $this->assertDatabaseHas('addresses', ['street' => '123 Main St']);
    }

    public function test_update_updates_address()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $address = Address::factory()->create();
        $data = ['street' => '456 Another St'];

        $response = $this->putJson("/api/v1/addresses/{$address->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $address->id,
                'street' => '456 Another St',
            ]);

        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'street' => '456 Another St']);
    }

    public function test_destroy_deletes_address()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $address = Address::factory()->create();

        $response = $this->deleteJson("/api/v1/addresses/{$address->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($address);
    }
}

