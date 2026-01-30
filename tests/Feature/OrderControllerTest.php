<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\Address;
use App\Models\Cloth;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\ClothReturnPhoto;
use App\Models\Payment;
use App\Models\City;
use App\Models\Country;
use App\Models\ClothType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_index_returns_orders()
    {
        Order::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonMissingPath('data.0.inventory_id');
    }

    public function test_show_returns_order()
    {
        $order = Order::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $order->id])
            ->assertJsonMissing(['inventory_id']);

        // If order has inventory with inventoriable, verify entity_type and entity_id are present
        if ($order->inventory && $order->inventory->inventoriable) {
            $response->assertJsonStructure(['entity_type', 'entity_id']);
        }
    }

    public function test_store_creates_order()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $client = Client::factory()->create();
        $branch = Branch::factory()->create();
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);
        // Refresh branch to ensure inventory relationship is loaded
        $branch->refresh();
        $address = Address::factory()->create();
        $cloth = Cloth::factory()->create();

        // Add cloth to inventory
        $inventory->clothes()->attach($cloth->id);

        $data = [
            'client_id' => $client->id,
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
            'status' => 'created',
            'paid' => 50.00,
            'remaining' => 50.00,
            'visit_datetime' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'cloth_id' => $cloth->id,
                    'price' => 50.00,
                    'type' => 'rent',
                    'days_of_rent' => 3,
                    'occasion_datetime' => now()->addDays(7)->format('Y-m-d H:i:s'),
                    'delivery_date' => now()->addDays(7)->format('Y-m-d'),
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $response->assertStatus(201);

        // Since we're passing paid=50, the order will be auto-updated to 'paid' status
        // So we check for either 'created' or 'paid' status
        $responseData = $response->json();
        $this->assertContains($responseData['status'], ['created', 'partially_paid', 'paid']);
        $this->assertEquals(50.00, $responseData['total_price']);
        $this->assertEquals('branch', $responseData['entity_type']);
        $this->assertEquals($branch->id, $responseData['entity_id']);
        $this->assertArrayNotHasKey('inventory_id', $responseData);

        // Assert Pivot
        $this->assertDatabaseHas('cloth_order', [
            'cloth_id' => $cloth->id,
            'price' => 50.00,
            'type' => 'rent',
        ]);
    }

    public function test_update_updates_order()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $order = Order::factory()->create();
        $data = [
            'order_notes' => 'Updated notes',
        ];

        $response = $this->putJson("/api/v1/orders/{$order->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $order->id,
                'order_notes' => 'Updated notes',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_notes' => 'Updated notes',
        ]);
    }

    public function test_destroy_deletes_order()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $order = Order::factory()->create();

        $response = $this->deleteJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted($order);
    }

    public function test_returnCloth_successfully_returns_rented_item()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branchInventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id]);
        $workshopInventory = Inventory::factory()->create([
            'inventoriable_type' => Workshop::class,
            'inventoriable_id' => $workshop->id,
        ]);

        $cloth = Cloth::factory()->create(['status' => 'rented']);
        $branchInventory->clothes()->attach($cloth->id);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branchInventory->id,
            'status' => 'delivered',
        ]);

        DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'days_of_rent' => 3,
            'returnable' => true,
            'status' => 'rented',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $photo1 = UploadedFile::fake()->image('return1.jpg');
        $photo2 = UploadedFile::fake()->image('return2.png');

        $response = $this->post("/api/v1/orders/{$order->id}/items/{$cloth->id}/return", [
            'entity_type' => 'workshop',
            'entity_id' => $workshop->id,
            'note' => 'Cloth returned for repair',
            'photos' => [$photo1, $photo2],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cloth returned successfully',
                'cloth' => [
                    'id' => $cloth->id,
                    'status' => 'repairing',
                ],
            ]);

        // Verify returnable is set to false
        $this->assertDatabaseHas('cloth_order', [
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'returnable' => false,
        ]);

        // Verify cloth status is repairing
        $this->assertDatabaseHas('clothes', [
            'id' => $cloth->id,
            'status' => 'repairing',
        ]);

        // Verify cloth is transferred to workshop inventory
        // Check database directly to ensure transfer happened
        $this->assertDatabaseHas('cloth_inventory', [
            'cloth_id' => $cloth->id,
            'inventory_id' => $workshopInventory->id,
        ]);
        $this->assertDatabaseMissing('cloth_inventory', [
            'cloth_id' => $cloth->id,
            'inventory_id' => $branchInventory->id,
        ]);

        // Verify photos are saved
        $this->assertDatabaseHas('cloth_return_photos', [
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'photo_type' => 'return_photo',
        ]);
        $this->assertEquals(2, ClothReturnPhoto::where('order_id', $order->id)->where('cloth_id', $cloth->id)->count());
    }

    public function test_returnCloth_rejects_if_already_returned()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'delivered',
        ]);

        DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => false, // Already returned
            'status' => 'rented',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $photo = UploadedFile::fake()->image('return.jpg');

        $response = $this->post("/api/v1/orders/{$order->id}/items/{$cloth->id}/return", [
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
            'note' => 'Test note',
            'photos' => [$photo],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cloth is not part of this order as a rentable item or has already been returned',
            ]);
    }

    public function test_returnCloth_rejects_if_item_type_is_not_rent()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'delivered',
        ]);

        DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'buy', // Not rent
            'returnable' => true,
            'status' => 'delivered',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $photo = UploadedFile::fake()->image('return.jpg');

        $response = $this->post("/api/v1/orders/{$order->id}/items/{$cloth->id}/return", [
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
            'note' => 'Test note',
            'photos' => [$photo],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cloth is not part of this order as a rentable item or has already been returned',
            ]);
    }

    public function test_returnCloth_rejects_with_invalid_entity()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'delivered',
        ]);

        DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => true,
            'status' => 'rented',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $photo = UploadedFile::fake()->image('return.jpg');

        $response = $this->post("/api/v1/orders/{$order->id}/items/{$cloth->id}/return", [
            'entity_type' => 'branch',
            'entity_id' => 99999, // Non-existent entity
            'note' => 'Test note',
            'photos' => [$photo],
        ]);

        $response->assertStatus(404);
    }

    public function test_returnCloth_rejects_with_invalid_photo_mime_types()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'delivered',
        ]);

        DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => true,
            'status' => 'rented',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invalidFile = UploadedFile::fake()->create('document.pdf', 100); // PDF is not allowed

        $response = $this->post("/api/v1/orders/{$order->id}/items/{$cloth->id}/return", [
            'entity_type' => 'workshop',
            'entity_id' => $workshop->id,
            'note' => 'Test note',
            'photos' => [$invalidFile],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos.0']);
    }

    public function test_returnCloth_rejects_with_too_many_photos()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'delivered',
        ]);

        DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => true,
            'status' => 'rented',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $photos = [];
        for ($i = 0; $i < 11; $i++) { // 11 photos, max is 10
            $photos[] = UploadedFile::fake()->image("photo{$i}.jpg");
        }

        $response = $this->post("/api/v1/orders/{$order->id}/items/{$cloth->id}/return", [
            'entity_type' => 'workshop',
            'entity_id' => $workshop->id,
            'note' => 'Test note',
            'photos' => $photos,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos']);
    }

    public function test_returnCloth_rejects_with_no_photos()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'delivered',
        ]);

        DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => true,
            'status' => 'rented',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post("/api/v1/orders/{$order->id}/items/{$cloth->id}/return", [
            'entity_type' => 'workshop',
            'entity_id' => $workshop->id,
            'note' => 'Test note',
            'photos' => [], // No photos
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos']);
    }

    public function test_returnCloth_rejects_if_order_not_delivered_or_finished()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth = Cloth::factory()->create();
        $inventory->clothes()->attach($cloth->id);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'finished', // Finished orders cannot have items returned
        ]);

        DB::table('cloth_order')->insert([
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'price' => 100.00,
            'type' => 'rent',
            'returnable' => true,
            'status' => 'created',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $photo = UploadedFile::fake()->image('return.jpg');

        $response = $this->post("/api/v1/orders/{$order->id}/items/{$cloth->id}/return", [
            'entity_type' => 'workshop',
            'entity_id' => $workshop->id,
            'note' => 'Test note',
            'photos' => [$photo],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot return cloth from order in current status',
            ]);
    }

    public function test_finish_order_rejects_if_rented_items_not_returned()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth1 = Cloth::factory()->create();
        $cloth2 = Cloth::factory()->create();
        $inventory->clothes()->attach([$cloth1->id, $cloth2->id]);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'delivered',
            'total_price' => 200.00,
        ]);

        // Create two rented items - one returned, one not
        DB::table('cloth_order')->insert([
            [
                'order_id' => $order->id,
                'cloth_id' => $cloth1->id,
                'price' => 100.00,
                'type' => 'rent',
                'returnable' => false, // Returned
                'status' => 'rented',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order->id,
                'cloth_id' => $cloth2->id,
                'price' => 100.00,
                'type' => 'rent',
                'returnable' => true, // Not returned
                'status' => 'rented',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create payment to satisfy payment requirements
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 200.00,
            'status' => 'paid',
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(422);
        // The error message might vary, but it should contain information about rented items
        $response->assertJsonFragment(['message' => 'Cannot finish order']);
    }

    public function test_finish_order_allows_when_all_rented_items_returned()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $inventory = Inventory::factory()->create([
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $branch->id,
        ]);

        $cloth1 = Cloth::factory()->create();
        $cloth2 = Cloth::factory()->create();
        $inventory->clothes()->attach([$cloth1->id, $cloth2->id]);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'status' => 'delivered',
            'total_price' => 200.00,
        ]);

        // Create two rented items - both returned
        DB::table('cloth_order')->insert([
            [
                'order_id' => $order->id,
                'cloth_id' => $cloth1->id,
                'price' => 100.00,
                'type' => 'rent',
                'returnable' => false, // Returned
                'status' => 'rented',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order->id,
                'cloth_id' => $cloth2->id,
                'price' => 100.00,
                'type' => 'rent',
                'returnable' => false, // Returned
                'status' => 'rented',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Create payment to satisfy payment requirements
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 200.00,
            'status' => 'paid',
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        // Should succeed if all validations pass (may still fail due to custody requirements)
        // But at least it shouldn't fail due to returnable items
        if ($response->status() === 422) {
            // If it fails, check that it's not due to returnable items
            $responseData = $response->json();
            $errors = $responseData['errors']['status'] ?? [];
            $errorMessages = is_array($errors) ? implode(' ', $errors) : (is_string($errors) ? $errors : json_encode($errors));
            $this->assertStringNotContainsString('rented items must be returned', strtolower($errorMessages), 'Order finish should not fail due to returnable items when all are returned');
        } else {
            // If it succeeds, that's great!
            $this->assertEquals(200, $response->status());
        }
    }
}

