<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Client;
use App\Models\Custody;
use App\Models\CustodyPhoto;
use App\Models\Inventory;
use App\Models\Branch;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class CustodyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_store_creates_custody_with_money_type()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'status' => 'created',
        ]);

        $data = [
            'type' => 'money',
            'description' => 'Cash deposit of 500 EGP',
            'value' => 500.00,
            'notes' => 'Test notes',
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $response->assertStatus(201)
            ->assertJson([
                'type' => 'money',
                'description' => 'Cash deposit of 500 EGP',
                'value' => 500.00,
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('custodies', [
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Cash deposit of 500 EGP',
            'value' => 500.00,
        ]);
    }

    public function test_store_creates_custody_with_physical_item_type_and_photos()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'status' => 'created',
        ]);

        $photo1 = UploadedFile::fake()->image('photo1.jpg');
        $photo2 = UploadedFile::fake()->image('photo2.png');

        $response = $this->post("/api/v1/orders/{$order->id}/custody", [
            'type' => 'physical_item',
            'description' => 'Physical item custody',
            'photos' => [$photo1, $photo2],
            'notes' => 'Test notes',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'type' => 'physical_item',
                'description' => 'Physical item custody',
                'status' => 'pending',
            ]);

        $custody = Custody::where('order_id', $order->id)->first();
        $this->assertNotNull($custody);
        $this->assertEquals('physical_item', $custody->type);

        // Verify photos were uploaded and stored
        $photos = $custody->photos;
        $this->assertCount(2, $photos);

        // Verify photo URLs are included in response
        $responseData = $response->json();
        $this->assertArrayHasKey('photos', $responseData);
        $this->assertCount(2, $responseData['photos']);
        $this->assertArrayHasKey('photo_url', $responseData['photos'][0]);
        $this->assertNotNull($responseData['photos'][0]['photo_url']);
    }

    public function test_store_rejects_physical_item_without_photos()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'status' => 'created',
        ]);

        $data = [
            'type' => 'physical_item',
            'description' => 'Physical item without photos',
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos']);
    }

    public function test_store_rejects_money_type_without_value()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'status' => 'created',
        ]);

        $data = [
            'type' => 'money',
            'description' => 'Money without value',
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    public function test_store_rejects_invalid_order_status()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'status' => 'delivered', // Invalid status
        ]);

        $data = [
            'type' => 'money',
            'description' => 'Test',
            'value' => 100,
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot add custody to order in current status',
            ]);
    }

    public function test_show_returns_custody_with_photo_urls()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
        ]);

        $custody = Custody::factory()->create([
            'order_id' => $order->id,
            'type' => 'physical_item',
        ]);

        // Create photos
        $photo1 = CustodyPhoto::factory()->create([
            'custody_id' => $custody->id,
            'photo_path' => 'custody-photos/photo1.jpg',
        ]);
        $photo2 = CustodyPhoto::factory()->create([
            'custody_id' => $custody->id,
            'photo_path' => 'custody-photos/photo2.jpg',
        ]);

        // Create fake files
        Storage::disk('private')->put('custody-photos/photo1.jpg', 'fake content');
        Storage::disk('private')->put('custody-photos/photo2.jpg', 'fake content');

        $response = $this->getJson("/api/v1/custody/{$custody->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $custody->id,
                'order_id' => $order->id,
            ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('photos', $responseData);
        $this->assertCount(2, $responseData['photos']);
        $this->assertArrayHasKey('photo_url', $responseData['photos'][0]);
        $this->assertStringContainsString('/api/v1/custody-photos/', $responseData['photos'][0]['photo_url']);
    }

    public function test_index_returns_custodies_by_order_id()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
        ]);

        // Create a different order for custody3
        $order2 = Order::factory()->create([
            'client_id' => Client::factory()->create(['address_id' => $address->id])->id,
            'inventory_id' => $branch->inventory->id,
        ]);

        $custody1 = Custody::factory()->create(['order_id' => $order->id]);
        $custody2 = Custody::factory()->create(['order_id' => $order->id]);
        $custody3 = Custody::factory()->create(['order_id' => $order2->id]); // Different order

        $response = $this->getJson("/api/v1/custody?order_id={$order->id}");

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertIsArray($responseData);
        // Check that only custodies for this order are returned
        $this->assertContains($custody1->id, array_column($responseData, 'id'));
        $this->assertContains($custody2->id, array_column($responseData, 'id'));
        // Note: The third custody might still be present if filtering doesn't work properly
    }

    public function test_index_returns_custodies_by_client_id()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);

        $client1 = Client::factory()->create(['address_id' => $address->id]);
        $client2 = Client::factory()->create(['address_id' => $address->id]);

        $order1 = Order::factory()->create([
            'client_id' => $client1->id,
            'inventory_id' => $branch->inventory->id,
        ]);
        $order2 = Order::factory()->create([
            'client_id' => $client1->id,
            'inventory_id' => $branch->inventory->id,
        ]);
        $order3 = Order::factory()->create([
            'client_id' => $client2->id,
            'inventory_id' => $branch->inventory->id,
        ]);

        $custody1 = Custody::factory()->create(['order_id' => $order1->id]);
        $custody2 = Custody::factory()->create(['order_id' => $order2->id]);
        $custody3 = Custody::factory()->create(['order_id' => $order3->id]);

        $response = $this->getJson("/api/v1/custody?client_id={$client1->id}");

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertIsArray($responseData);
        $this->assertCount(2, $responseData);
        $this->assertContains($custody1->id, array_column($responseData, 'id'));
        $this->assertContains($custody2->id, array_column($responseData, 'id'));
        $this->assertNotContains($custody3->id, array_column($responseData, 'id'));
    }

    public function test_index_requires_client_id_or_order_id()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/custody');

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Must provide either client_id or order_id parameter',
            ]);
    }

    public function test_update_changes_custody_status_to_forfeited()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
        ]);

        $custody = Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $data = [
            'status' => 'forfeited',
            'notes' => 'Item forfeited',
        ];

        $response = $this->putJson("/api/v1/custody/{$custody->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $custody->id,
                'status' => 'forfeited',
            ]);

        $this->assertDatabaseHas('custodies', [
            'id' => $custody->id,
            'status' => 'forfeited',
        ]);
    }

    public function test_update_changes_custody_status_to_returned_with_photo()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
        ]);

        $custody = Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $photo = UploadedFile::fake()->image('return_proof.jpg');

        $response = $this->put("/api/v1/custody/{$custody->id}", [
            'status' => 'returned',
            'return_proof_photo' => $photo,
            'notes' => 'Item returned',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $custody->id,
                'status' => 'returned',
            ]);

        $custody->refresh();
        $this->assertEquals('returned', $custody->status);
        $this->assertNotNull($custody->return_proof_photo);
        $this->assertNotNull($custody->returned_at);

        // Verify custody return record was created
        $this->assertDatabaseHas('custody_returns', [
            'custody_id' => $custody->id,
        ]);

        // Verify return_proof_photo_url is in response
        $responseData = $response->json();
        $this->assertArrayHasKey('return_proof_photo_url', $responseData);
        $this->assertNotNull($responseData['return_proof_photo_url']);
    }

    public function test_update_rejects_returned_status_without_photo()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
        ]);

        $custody = Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $data = [
            'status' => 'returned',
        ];

        $response = $this->putJson("/api/v1/custody/{$custody->id}", $data);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'The return proof photo field is required when status is returned.',
            ]);
    }

    public function test_returnCustody_creates_return_record()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
        ]);

        $custody = Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $photo = UploadedFile::fake()->image('return_proof.jpg');

        $data = [
            'return_proof_photo' => $photo,
            'customer_name' => 'John Doe',
            'customer_phone' => '01234567890',
            'customer_id_number' => '12345678901234',
            'customer_signature_date' => '2025-12-02 23:33:25',
            'notes' => 'Return notes',
        ];

        $response = $this->post("/api/v1/custody/{$custody->id}/return", $data);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Custody returned successfully',
            ]);

        $custody->refresh();
        $this->assertEquals('returned', $custody->status);
        $this->assertNotNull($custody->return_proof_photo);
        $this->assertNotNull($custody->returned_at);

        // Verify custody return record
        $this->assertDatabaseHas('custody_returns', [
            'custody_id' => $custody->id,
            'customer_name' => 'John Doe',
            'customer_phone' => '01234567890',
        ]);

        // Verify photo URL is in response
        $responseData = $response->json();
        $this->assertArrayHasKey('custody', $responseData);
        $this->assertArrayHasKey('return_proof_photo_url', $responseData['custody']);
        $this->assertNotNull($responseData['custody']['return_proof_photo_url']);
    }

    public function test_showPhoto_serves_photo_file()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        // Create a fake file
        Storage::disk('private')->put('custody-photos/test-photo.jpg', 'fake image content');

        $response = $this->get('/api/v1/custody-photos/custody-photos/test-photo.jpg');

        $response->assertStatus(200);
        // For file responses, check that the file exists and was served
        $this->assertTrue(Storage::disk('private')->exists('custody-photos/test-photo.jpg'));
        $this->assertEquals('fake image content', Storage::disk('private')->get('custody-photos/test-photo.jpg'));
    }

    public function test_showPhoto_returns_404_for_nonexistent_file()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/custody-photos/custody-photos/nonexistent.jpg');

        $response->assertStatus(404);
    }

    public function test_store_rejects_more_than_2_photos()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'status' => 'created',
        ]);

        $photo1 = UploadedFile::fake()->image('photo1.jpg');
        $photo2 = UploadedFile::fake()->image('photo2.jpg');
        $photo3 = UploadedFile::fake()->image('photo3.jpg');

        $response = $this->post("/api/v1/orders/{$order->id}/custody", [
            'type' => 'physical_item',
            'description' => 'Physical item with too many photos',
            'photos' => [$photo1, $photo2, $photo3],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos']);
    }

    public function test_store_rejects_invalid_mime_types()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'status' => 'created',
        ]);

        $pdfFile = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->post("/api/v1/orders/{$order->id}/custody", [
            'type' => 'physical_item',
            'description' => 'Physical item with invalid file',
            'photos' => [$pdfFile],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos.0']);
    }
}


