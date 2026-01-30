<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Phone;
use App\Models\Client;
use Laravel\Sanctum\Sanctum;

class PhoneControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_phones()
    {
        Phone::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/phones');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'total_pages', 'per_page']);
    }

    public function test_show_returns_phone()
    {
        $phone = Phone::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/phones/{$phone->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $phone->id,
            ]);
    }

    public function test_store_creates_phone()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $client = Client::factory()->create();

        $data = [
            'client_id' => $client->id,
            'phone' => '01234567890',
            'type' => 'mobile',
        ];
        
        $response = $this->postJson('/api/v1/phones', $data);

        $response->assertStatus(201)
            ->assertJson([
                'phone' => '01234567890',
            ]);
        
        $this->assertDatabaseHas('phones', ['phone' => '01234567890']);
    }

    public function test_update_updates_phone()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $phone = Phone::factory()->create();
        $data = ['phone' => '09876543210'];

        $response = $this->putJson("/api/v1/phones/{$phone->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $phone->id,
                'phone' => '09876543210',
            ]);

        $this->assertDatabaseHas('phones', ['id' => $phone->id, 'phone' => '09876543210']);
    }

    public function test_destroy_deletes_phone()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $phone = Phone::factory()->create();

        $response = $this->deleteJson("/api/v1/phones/{$phone->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($phone);
    }
}

