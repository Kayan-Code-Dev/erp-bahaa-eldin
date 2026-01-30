<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Cloth;
use App\Models\Address;
use App\Models\Branch;
use Laravel\Sanctum\Sanctum;

class ClothControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_clothes()
    {
        Cloth::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clothes');

        $response->assertStatus(200);
    }

    public function test_show_returns_cloth()
    {
        $cloth = Cloth::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/clothes/{$cloth->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $cloth->id]);
    }

    public function test_store_creates_cloth()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = \App\Models\Country::factory()->create();
        $city = \App\Models\City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        // Ensure branch has inventory (Branch::created event should create it, but let's verify)
        $branch->refresh();
        if (!$branch->inventory) {
            $branch->inventory()->create(['name' => $branch->name . ' Inventory']);
        }
        $clothType = \App\Models\ClothType::factory()->create();

        $data = [
            'code' => 'TEST-001',
            'name' => 'Test Cloth',
            'breast_size' => '40',
            'cloth_type_id' => $clothType->id,
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
        ];

        $response = $this->postJson('/api/v1/clothes', $data);

        $response->assertStatus(201)
            ->assertJson([
                'code' => 'TEST-001',
                'name' => 'Test Cloth',
            ]);

        $this->assertDatabaseHas('clothes', ['code' => 'TEST-001']);
    }

    public function test_update_updates_cloth()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $cloth = Cloth::factory()->create();
        $data = ['name' => 'Updated Cloth Name'];

        $response = $this->putJson("/api/v1/clothes/{$cloth->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $cloth->id,
                'name' => 'Updated Cloth Name',
            ]);

        $this->assertDatabaseHas('clothes', ['id' => $cloth->id, 'name' => 'Updated Cloth Name']);
    }

    public function test_destroy_deletes_cloth()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $cloth = Cloth::factory()->create();

        $response = $this->deleteJson("/api/v1/clothes/{$cloth->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($cloth);
    }

    public function test_update_rejects_if_cloth_in_unfinished_order()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = \App\Models\Country::factory()->create();
        $city = \App\Models\City::factory()->create(['country_id' => $country->id]);
        $address = \App\Models\Address::factory()->create(['city_id' => $city->id]);
        $client = \App\Models\Client::factory()->create(['address_id' => $address->id]);
        $branch = \App\Models\Branch::factory()->create(['address_id' => $address->id]);
        $inventory = \App\Models\Inventory::factory()->create([
            'inventoriable_type' => \App\Models\Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = \App\Models\Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'created', // Unfinished order
        ]);

        \Illuminate\Support\Facades\DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => true,
            'status' => 'created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = ['name' => 'Updated Cloth Name'];

        $response = $this->putJson("/api/v1/clothes/{$cloth->id}", $data);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot update cloth. Cloth is currently in an unfinished order.',
            ]);
    }

    public function test_update_allows_if_cloth_only_in_finished_orders()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = \App\Models\Country::factory()->create();
        $city = \App\Models\City::factory()->create(['country_id' => $country->id]);
        $address = \App\Models\Address::factory()->create(['city_id' => $city->id]);
        $client = \App\Models\Client::factory()->create(['address_id' => $address->id]);
        $branch = \App\Models\Branch::factory()->create(['address_id' => $address->id]);
        $inventory = \App\Models\Inventory::factory()->create([
            'inventoriable_type' => \App\Models\Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = \App\Models\Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'finished', // Finished order
        ]);

        \Illuminate\Support\Facades\DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => false,
            'status' => 'finished',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = ['name' => 'Updated Cloth Name'];

        $response = $this->putJson("/api/v1/clothes/{$cloth->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $cloth->id,
                'name' => 'Updated Cloth Name',
            ]);
    }

    public function test_destroy_rejects_if_cloth_in_unfinished_order()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = \App\Models\Country::factory()->create();
        $city = \App\Models\City::factory()->create(['country_id' => $country->id]);
        $address = \App\Models\Address::factory()->create(['city_id' => $city->id]);
        $client = \App\Models\Client::factory()->create(['address_id' => $address->id]);
        $branch = \App\Models\Branch::factory()->create(['address_id' => $address->id]);
        $inventory = \App\Models\Inventory::factory()->create([
            'inventoriable_type' => \App\Models\Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = \App\Models\Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'delivered', // Unfinished order
        ]);

        \Illuminate\Support\Facades\DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => true,
            'status' => 'rented',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/clothes/{$cloth->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete cloth. Cloth is currently in an unfinished order.',
            ]);
    }

    public function test_destroy_allows_if_cloth_not_in_any_order()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $cloth = Cloth::factory()->create();

        $response = $this->deleteJson("/api/v1/clothes/{$cloth->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($cloth);
    }

    public function test_destroy_allows_if_cloth_only_in_finished_orders()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = \App\Models\Country::factory()->create();
        $city = \App\Models\City::factory()->create(['country_id' => $country->id]);
        $address = \App\Models\Address::factory()->create(['city_id' => $city->id]);
        $client = \App\Models\Client::factory()->create(['address_id' => $address->id]);
        $branch = \App\Models\Branch::factory()->create(['address_id' => $address->id]);
        $inventory = \App\Models\Inventory::factory()->create([
            'inventoriable_type' => \App\Models\Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = \App\Models\Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'finished', // Finished order
        ]);

        \Illuminate\Support\Facades\DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => false,
            'status' => 'finished',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/clothes/{$cloth->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($cloth);
    }
}

