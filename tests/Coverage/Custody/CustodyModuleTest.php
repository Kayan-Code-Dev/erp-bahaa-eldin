<?php

namespace Tests\Coverage\Custody;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Order;
use App\Models\Custody;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Branch;
use App\Models\Client;
use App\Models\CustodyPhoto;
use App\Models\CustodyReturn;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class CustodyModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = [
            'custody.view', 'custody.create', 'custody.return', 'custody.update', 'custody.export',
            'orders.deliver', 'orders.finish',
        ];
        foreach ($permissions as $perm) {
            Permission::findOrCreateByName($perm);
        }
    }

    protected function createUserWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test Role']);
        $role->assignPermission($permission);
        $user->assignRole($role);
        return $user;
    }

    protected function createOrder(): Order
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        return Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'status' => 'created',
        ]);
    }

    public function test_create_money_custody()
    {
        $order = $this->createOrder();
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);

        $data = [
            'type' => 'money',
            'description' => 'Cash deposit',
            'value' => 500.00,
        ];
        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);
        $response->assertStatus(201)->assertJson(['type' => 'money', 'value' => 500.00]);
        $this->assertDatabaseHas('custodies', ['order_id' => $order->id, 'type' => 'money']);
    }

    public function test_create_physical_item_custody_with_photos()
    {
        $order = $this->createOrder();
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);

        $photo = UploadedFile::fake()->image('photo.jpg');
        $response = $this->post("/api/v1/orders/{$order->id}/custody", [
            'type' => 'physical_item',
            'description' => 'Physical item',
            'photos' => [$photo],
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('custodies', ['order_id' => $order->id, 'type' => 'physical_item']);
    }

    public function test_list_custodies_for_order()
    {
        $order = $this->createOrder();
        Custody::factory()->count(3)->create(['order_id' => $order->id]);
        $user = $this->createUserWithPermission('custody.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}/custody");
        $response->assertStatus(200);
    }

    public function test_return_custody()
    {
        $order = $this->createOrder();
        $custody = Custody::factory()->create(['order_id' => $order->id, 'status' => 'pending']);
        $user = $this->createUserWithPermission('custody.return');
        Sanctum::actingAs($user);

        $photo = UploadedFile::fake()->image('return.jpg');
        $response = $this->post("/api/v1/custody/{$custody->id}/return", [
            'custody_action' => 'returned_to_user',
            'acknowledgement_receipt_photos' => [$photo],
            'notes' => 'Returned successfully',
        ]);
        $response->assertStatus(200);
        $custody->refresh();
        $this->assertEquals('returned', $custody->status);
    }

    public function test_create_custody_without_permission_fails()
    {
        $order = $this->createOrder();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Test',
            'value' => 100,
        ]);
        $response->assertStatus(403);
    }

    public function test_list_custody_items()
    {
        Custody::factory()->count(5)->create();
        $user = $this->createUserWithPermission('custody.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/custody');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_show_custody_item()
    {
        $order = $this->createOrder();
        $custody = Custody::factory()->create(['order_id' => $order->id]);
        CustodyPhoto::factory()->count(2)->create(['custody_id' => $custody->id]);
        $user = $this->createUserWithPermission('custody.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/custody/{$custody->id}");
        $response->assertStatus(200)->assertJson(['id' => $custody->id]);
    }

    public function test_create_document_custody()
    {
        $order = $this->createOrder();
        $order->status = 'paid';
        $order->save();
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);

        $data = [
            'type' => 'document',
            'description' => 'ID document',
        ];
        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", $data);
        $response->assertStatus(201)->assertJson(['type' => 'document']);
        $this->assertDatabaseHas('custodies', ['order_id' => $order->id, 'type' => 'document']);
    }

    public function test_create_custody_with_invalid_order_status_fails()
    {
        $order = $this->createOrder();
        $order->status = 'delivered';
        $order->save();
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Test',
            'value' => 100,
        ]);
        $response->assertStatus(422);
    }

    public function test_create_money_custody_without_value_fails()
    {
        $order = $this->createOrder();
        $order->status = 'paid';
        $order->save();
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Test',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['value']);
    }

    public function test_create_physical_item_custody_without_photos_fails()
    {
        $order = $this->createOrder();
        $order->status = 'paid';
        $order->save();
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'physical_item',
            'description' => 'Test',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['photos']);
    }

    public function test_update_custody()
    {
        $order = $this->createOrder();
        $custody = Custody::factory()->create(['order_id' => $order->id, 'status' => 'pending']);
        $user = $this->createUserWithPermission('custody.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/custody/{$custody->id}", [
            'notes' => 'Updated notes',
        ]);
        $response->assertStatus(200);
        $custody->refresh();
        $this->assertEquals('Updated notes', $custody->notes);
    }

    public function test_export_custody()
    {
        Custody::factory()->count(3)->create();
        $user = $this->createUserWithPermission('custody.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/custody/export');
        $response->assertStatus(200);
    }

    public function test_return_custody_with_photos()
    {
        $order = $this->createOrder();
        $custody = Custody::factory()->create(['order_id' => $order->id, 'status' => 'pending', 'type' => 'physical_item']);
        $user = $this->createUserWithPermission('custody.return');
        Sanctum::actingAs($user);

        $photo = UploadedFile::fake()->image('return.jpg');
        $response = $this->post("/api/v1/custody/{$custody->id}/return", [
            'custody_action' => 'returned_to_user',
            'acknowledgement_receipt_photos' => [$photo],
            'notes' => 'Returned successfully',
        ]);
        $response->assertStatus(200);
        $custody->refresh();
        $this->assertEquals('returned', $custody->status);
        $this->assertNotNull($custody->returned_at);
    }

    public function test_mark_custody_as_kept_forfeited()
    {
        $order = $this->createOrder();
        $custody = Custody::factory()->create(['order_id' => $order->id, 'status' => 'pending']);
        $user = $this->createUserWithPermission('custody.return');
        Sanctum::actingAs($user);

        $photo = UploadedFile::fake()->image('receipt.jpg');
        $response = $this->post("/api/v1/custody/{$custody->id}/return", [
            'custody_action' => 'forfeit',
            'acknowledgement_receipt_photos' => [$photo],
            'reason_of_kept' => 'Client did not collect',
            'notes' => 'Custody forfeited',
        ]);
        $response->assertStatus(200);
        $custody->refresh();
        $this->assertEquals('forfeited', $custody->status);
    }

    public function test_create_custody_with_invalid_type_fails()
    {
        $order = $this->createOrder();
        $order->status = 'paid';
        $order->save();
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'invalid_type',
            'description' => 'Test',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_create_custody_with_invalid_photo_format_fails()
    {
        $order = $this->createOrder();
        $order->status = 'paid';
        $order->save();
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);

        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);
        $response = $this->post("/api/v1/orders/{$order->id}/custody", [
            'type' => 'physical_item',
            'description' => 'Test',
            'photos' => [$invalidFile],
        ]);
        $response->assertStatus(422);
    }

    public function test_create_custody_with_too_many_photos_fails()
    {
        $order = $this->createOrder();
        $order->status = 'paid';
        $order->save();
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);

        $photo1 = UploadedFile::fake()->image('photo1.jpg');
        $photo2 = UploadedFile::fake()->image('photo2.jpg');
        $photo3 = UploadedFile::fake()->image('photo3.jpg');
        $response = $this->post("/api/v1/orders/{$order->id}/custody", [
            'type' => 'physical_item',
            'description' => 'Test',
            'photos' => [$photo1, $photo2, $photo3],
        ]);
        $response->assertStatus(422);
    }
}

