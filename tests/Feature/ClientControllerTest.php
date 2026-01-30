<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use Laravel\Sanctum\Sanctum;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_clients()
    {
        Client::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_client()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $client->id]);
    }

    public function test_store_creates_client()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $address = Address::factory()->create();

        $data = [
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
                'notes' => $address->notes,
            ],
            'phones' => [
                [
                    'phone_number' => '+201234567890',
                    'phone_type' => 'mobile',
                ]
            ],
            'first_name' => 'John',
            'middle_name' => 'Doe',
            'last_name' => 'Smith',
            'date_of_birth' => '1990-01-01',
            'national_id' => '12345678901234',
        ];
        
        $response = $this->postJson('/api/v1/clients', $data);

        $response->assertStatus(201)
            ->assertJson([
                'first_name' => 'John',
                'national_id' => '12345678901234',
            ]);
        
        $this->assertDatabaseHas('clients', ['national_id' => '12345678901234']);
    }

    public function test_update_updates_client()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $client = Client::factory()->create();
        $data = ['first_name' => 'Jane'];

        $response = $this->putJson("/api/v1/clients/{$client->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $client->id,
                'first_name' => 'Jane',
            ]);

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'first_name' => 'Jane']);
    }

    public function test_destroy_deletes_client()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $client = Client::factory()->create();

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($client);
    }
}

