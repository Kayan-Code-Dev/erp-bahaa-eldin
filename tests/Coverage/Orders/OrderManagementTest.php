<?php

namespace Tests\Coverage\Orders;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Client;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\ClothType;
use App\Models\Cloth;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Custody;
use App\Models\CustodyPhoto;
use App\Models\CustodyReturn;
use App\Models\Rent;
use App\Models\Workshop;
use App\Models\ClothReturnPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = [
            'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'orders.export',
            'orders.deliver', 'orders.cancel', 'orders.finish', 'orders.return', 'orders.add-payment',
            'custody.create', 'custody.return', 'payments.create', 'payments.pay',
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

    protected function createOrderSetup(): array
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        $branch->inventory->clothes()->attach($cloth->id);

        return [
            'client' => $client,
            'branch' => $branch,
            'inventory' => $branch->inventory,
            'cloth' => $cloth,
        ];
    }

    public function test_list_orders()
    {
        Order::factory()->count(5)->create();
        $user = $this->createUserWithPermission('orders.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_rental_order()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $data = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'items' => [
                [
                    'cloth_id' => $setup['cloth']->id,
                    'price' => 100.00,
                    'type' => 'rent',
                    'days_of_rent' => 3,
                    'occasion_datetime' => now()->addDays(7)->format('Y-m-d H:i:s'),
                    'delivery_date' => now()->addDays(7)->format('Y-m-d'),
                ],
            ],
        ];
        $response = $this->postJson('/api/v1/orders', $data);
        $response->assertStatus(201)->assertJson(['status' => 'created']);
        $this->assertDatabaseHas('orders', ['client_id' => $setup['client']->id]);
    }

    public function test_create_sale_order()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $data = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'items' => [
                [
                    'cloth_id' => $setup['cloth']->id,
                    'price' => 200.00,
                    'type' => 'buy',
                ],
            ],
        ];
        $response = $this->postJson('/api/v1/orders', $data);
        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', ['client_id' => $setup['client']->id]);
    }

    public function test_create_order_with_initial_payment()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $data = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 50.00,
            'items' => [
                [
                    'cloth_id' => $setup['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ],
            ],
        ];
        $response = $this->postJson('/api/v1/orders', $data);
        $response->assertStatus(201);
        $order = Order::find($response->json('id'));
        $this->assertEquals(50.00, $order->paid);
    }

    public function test_show_order()
    {
        $order = Order::factory()->create();
        $user = $this->createUserWithPermission('orders.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/orders/{$order->id}");
        $response->assertStatus(200)->assertJson(['id' => $order->id]);
    }

    public function test_update_order()
    {
        $order = Order::factory()->create();
        $user = $this->createUserWithPermission('orders.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/orders/{$order->id}", ['order_notes' => 'Updated notes']);
        $response->assertStatus(200)->assertJson(['order_notes' => 'Updated notes']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_notes' => 'Updated notes']);
    }

    public function test_delete_order()
    {
        $order = Order::factory()->create();
        $user = $this->createUserWithPermission('orders.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/orders/{$order->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_export_orders()
    {
        Order::factory()->count(3)->create();
        $user = $this->createUserWithPermission('orders.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders/export');
        $response->assertStatus(200);
    }

    public function test_create_order_with_item_level_discount()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $data = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'items' => [
                [
                    'cloth_id' => $setup['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                ],
            ],
        ];
        $response = $this->postJson('/api/v1/orders', $data);
        $response->assertStatus(201);
        $order = Order::find($response->json('id'));
        $this->assertEquals(90.00, $order->total_price);
    }

    public function test_create_order_with_order_level_discount()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $data = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'discount_type' => 'fixed',
            'discount_value' => 20.00,
            'items' => [
                [
                    'cloth_id' => $setup['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ],
            ],
        ];
        $response = $this->postJson('/api/v1/orders', $data);
        $response->assertStatus(201);
        $order = Order::find($response->json('id'));
        $this->assertEquals(80.00, $order->total_price);
    }

    public function test_order_status_auto_calculation_paid_equals_zero()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $data = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $response = $this->postJson('/api/v1/orders', $data);
        $response->assertStatus(201);
        $order = Order::find($response->json('id'));
        $this->assertEquals('created', $order->status);
        $this->assertEquals(100.00, $order->remaining);
    }

    public function test_order_status_auto_calculation_paid_less_than_total()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $data = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 50.00,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $response = $this->postJson('/api/v1/orders', $data);
        $response->assertStatus(201);
        $order = Order::find($response->json('id'));
        $this->assertEquals('partially_paid', $order->status);
        $this->assertEquals(50.00, $order->remaining);
    }

    public function test_order_status_auto_calculation_paid_equals_total()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $data = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 100.00,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $response = $this->postJson('/api/v1/orders', $data);
        $response->assertStatus(201);
        $order = Order::find($response->json('id'));
        $this->assertEquals('paid', $order->status);
        $this->assertEquals(0.00, $order->remaining);
    }

    public function test_deliver_order_without_custody_fails()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $orderData = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 100.00,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $orderResponse = $this->postJson('/api/v1/orders', $orderData);
        $order = Order::find($orderResponse->json('id'));

        $user = $this->createUserWithPermission('orders.deliver');
        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v1/orders/{$order->id}/deliver");
        $response->assertStatus(422);
    }

    public function test_cancel_order()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $orderData = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $orderResponse = $this->postJson('/api/v1/orders', $orderData);
        $order = Order::find($orderResponse->json('id'));

        $user = $this->createUserWithPermission('orders.cancel');
        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel");
        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
    }

    public function test_create_order_with_both_item_and_order_discounts()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $data = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'items' => [
                [
                    'cloth_id' => $setup['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                    'discount_type' => 'percentage',
                    'discount_value' => 5,
                ],
            ],
        ];
        $response = $this->postJson('/api/v1/orders', $data);
        $response->assertStatus(201);
        $order = Order::find($response->json('id'));
        // Item: 100 - 5% = 95, then order: 95 - 10% = 85.50
        $this->assertEquals(85.50, $order->total_price);
    }

    public function test_create_order_without_permission_fails()
    {
        $setup = $this->createOrderSetup();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders', [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100, 'type' => 'buy'],
            ],
        ]);
        $response->assertStatus(403);
    }

    public function test_deliver_order_with_pending_custody()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $orderData = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 100.00,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $orderResponse = $this->postJson('/api/v1/orders', $orderData);
        $order = Order::find($orderResponse->json('id'));

        // Create custody
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
        ]);
        $order->refresh();

        // Deliver order
        $user = $this->createUserWithPermission('orders.deliver');
        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v1/orders/{$order->id}/deliver");
        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('delivered', $order->status);
    }

    public function test_finish_order_should_fail_with_pending_payments()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $orderData = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 50.00,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $orderResponse = $this->postJson('/api/v1/orders', $orderData);
        $order = Order::find($orderResponse->json('id'));

        // Create custody and deliver
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
        ]);
        $user = $this->createUserWithPermission('orders.deliver');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/deliver");
        $order->refresh();

        // Try to finish with pending payments
        $user = $this->createUserWithPermission('orders.finish');
        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");
        $response->assertStatus(422);
    }

    public function test_finish_order_should_fail_with_pending_custody()
    {
        $setup = $this->createOrderSetup();
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $orderData = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 100.00,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $orderResponse = $this->postJson('/api/v1/orders', $orderData);
        $order = Order::find($orderResponse->json('id'));

        // Create custody and deliver
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
        ]);
        $user = $this->createUserWithPermission('orders.deliver');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/deliver");
        $order->refresh();

        // Try to finish with pending custody
        $user = $this->createUserWithPermission('orders.finish');
        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");
        $response->assertStatus(422);
    }

    public function test_finish_order_with_kept_custody()
    {
        $setup = $this->createOrderSetup();
        Storage::fake('private');
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $orderData = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 100.00,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $orderResponse = $this->postJson('/api/v1/orders', $orderData);
        $order = Order::find($orderResponse->json('id'));

        // Create custody and deliver
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);
        $custodyResponse = $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
        ]);
        $custodyId = $custodyResponse->json('id');
        $user = $this->createUserWithPermission('orders.deliver');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/deliver");
        $order->refresh();

        // Mark custody as forfeited
        $user = $this->createUserWithPermission('custody.return');
        Sanctum::actingAs($user);
        $photo = UploadedFile::fake()->image('receipt.jpg');
        $this->post("/api/v1/custody/{$custodyId}/return", [
            'custody_action' => 'forfeit',
            'acknowledgement_receipt_photos' => [$photo],
            'reason_of_kept' => 'Client did not collect',
        ]);
        $order->refresh();

        // Finish order
        $user = $this->createUserWithPermission('orders.finish');
        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");
        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('finished', $order->status);
    }

    public function test_finish_order_with_returned_custody_and_proof()
    {
        $setup = $this->createOrderSetup();
        Storage::fake('private');
        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $orderData = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 100.00,
            'items' => [
                ['cloth_id' => $setup['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ],
        ];
        $orderResponse = $this->postJson('/api/v1/orders', $orderData);
        $order = Order::find($orderResponse->json('id'));

        // Create custody and deliver
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);
        $custodyResponse = $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
        ]);
        $custodyId = $custodyResponse->json('id');
        $user = $this->createUserWithPermission('orders.deliver');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/deliver");
        $order->refresh();

        // Return custody with proof
        $user = $this->createUserWithPermission('custody.return');
        Sanctum::actingAs($user);
        $photo = UploadedFile::fake()->image('return.jpg');
        $this->post("/api/v1/custody/{$custodyId}/return", [
            'custody_action' => 'returned_to_user',
            'acknowledgement_receipt_photos' => [$photo],
        ]);
        $order->refresh();

        // Finish order
        $user = $this->createUserWithPermission('orders.finish');
        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");
        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('finished', $order->status);
    }

    public function test_return_single_rent_item()
    {
        $setup = $this->createOrderSetup();
        Storage::fake('private');
        $workshop = Workshop::factory()->create(['address_id' => $setup['client']->address_id]);
        $workshopInventory = Inventory::factory()->create([
            'inventoriable_type' => Workshop::class,
            'inventoriable_id' => $workshop->id,
        ]);

        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $orderData = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 100.00,
            'items' => [
                [
                    'cloth_id' => $setup['cloth']->id,
                    'price' => 100.00,
                    'type' => 'rent',
                    'days_of_rent' => 3,
                    'delivery_date' => now()->addDays(1)->format('Y-m-d'),
                ],
            ],
        ];
        $orderResponse = $this->postJson('/api/v1/orders', $orderData);
        $order = Order::find($orderResponse->json('id'));

        // Create custody and deliver
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
        ]);
        $user = $this->createUserWithPermission('orders.deliver');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/deliver");
        $order->refresh();

        // Return single rent item
        $user = $this->createUserWithPermission('orders.return');
        Sanctum::actingAs($user);
        $photo = UploadedFile::fake()->image('return.jpg');
        $response = $this->post("/api/v1/orders/{$order->id}/items/{$setup['cloth']->id}/return", [
            'entity_type' => 'workshop',
            'entity_id' => $workshop->id,
            'note' => 'Returned for repair',
            'photos' => [$photo],
        ]);
        $response->assertStatus(200);
        $setup['cloth']->refresh();
        $this->assertEquals('repairing', $setup['cloth']->status);
    }

    public function test_return_multiple_rent_items()
    {
        $setup = $this->createOrderSetup();
        Storage::fake('private');
        $clothType2 = ClothType::factory()->create();
        $cloth2 = Cloth::factory()->create(['cloth_type_id' => $clothType2->id]);
        $setup['branch']->inventory->clothes()->attach($cloth2->id);

        $user = $this->createUserWithPermission('orders.create');
        Sanctum::actingAs($user);

        $orderData = [
            'client_id' => $setup['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $setup['branch']->id,
            'paid' => 200.00,
            'items' => [
                [
                    'cloth_id' => $setup['cloth']->id,
                    'price' => 100.00,
                    'type' => 'rent',
                    'days_of_rent' => 3,
                    'delivery_date' => now()->addDays(1)->format('Y-m-d'),
                ],
                [
                    'cloth_id' => $cloth2->id,
                    'price' => 100.00,
                    'type' => 'rent',
                    'days_of_rent' => 3,
                    'delivery_date' => now()->addDays(1)->format('Y-m-d'),
                ],
            ],
        ];
        $orderResponse = $this->postJson('/api/v1/orders', $orderData);
        $order = Order::find($orderResponse->json('id'));

        // Create custody and deliver
        $user = $this->createUserWithPermission('custody.create');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/custody", [
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
        ]);
        $user = $this->createUserWithPermission('orders.deliver');
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/orders/{$order->id}/deliver");
        $order->refresh();

        // Return multiple items
        $user = $this->createUserWithPermission('orders.return');
        Sanctum::actingAs($user);
        $response = $this->postJson("/api/v1/orders/{$order->id}/return", [
            'items' => [
                [
                    'cloth_id' => $setup['cloth']->id,
                    'status' => 'ready_for_rent',
                    'notes' => 'Returned',
                ],
                [
                    'cloth_id' => $cloth2->id,
                    'status' => 'ready_for_rent',
                    'notes' => 'Returned',
                ],
            ],
        ]);
        $response->assertStatus(200);
    }
}

