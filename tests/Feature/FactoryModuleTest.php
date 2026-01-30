<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Factory;
use App\Models\FactoryUser;
use App\Models\Order;
use App\Models\Cloth;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\Branch;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\FactoryItemStatusLog;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class FactoryModuleTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $factory;
    protected $factoryUser;
    protected $user;
    protected $otherFactory;
    protected $otherFactoryUser;
    protected $otherUser;
    protected $branch;
    protected $inventory;
    protected $client;
    protected $cloth;
    protected $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions and roles for factory users
        $factoryUserRole = \App\Models\Role::create([
            'name' => 'factory_user',
            'description' => 'Factory User',
        ]);

        $permissions = [
            'factories.orders.view',
            'factories.orders.accept',
            'factories.orders.reject',
            'factories.orders.update-status',
            'factories.orders.add-notes',
            'factories.orders.set-delivery-date',
            'factories.orders.deliver',
            'factories.reports.view',
            'factories.dashboard.view',
            'factories.manage',
        ];

        foreach ($permissions as $perm) {
            \App\Models\Permission::findOrCreateByName($perm);
            $factoryUserRole->assignPermission($perm);
        }

        // Create admin role with all permissions
        $adminRole = \App\Models\Role::create([
            'name' => 'admin',
            'description' => 'Administrator',
        ]);

        $adminPermissions = [
            'factories.view',
            'factories.create',
            'factories.update',
            'factories.delete',
            'factories.manage',
            'tailoring.manage',
        ];

        foreach ($adminPermissions as $perm) {
            \App\Models\Permission::findOrCreateByName($perm);
            $adminRole->assignPermission($perm);
        }

        // Create country and city
        $country = Country::create(['name' => 'Egypt']);
        $city = City::create(['name' => 'Cairo', 'country_id' => $country->id]);

        // Create address
        $address = Address::create([
            'street' => 'Test Street',
            'building' => 'Building 1',
            'city_id' => $city->id,
        ]);

        // Create branch and inventory
        $this->branch = Branch::create([
            'branch_code' => 'BR-001',
            'name' => 'Main Branch',
            'address_id' => $address->id,
        ]);
        $this->branch->refresh(); // Ensure cashbox is loaded
        
        // Create inventory for branch
        if (!$this->branch->inventory) {
            $this->branch->inventory()->create(['name' => 'Branch Inventory']);
            $this->branch->refresh();
        }
        $this->inventory = $this->branch->inventory;

        // Create factories
        $factoryAddress = Address::create([
            'street' => 'Factory Street',
            'building' => 'Factory Building',
            'city_id' => $city->id,
        ]);

        $this->factory = Factory::create([
            'factory_code' => 'FA-001',
            'name' => 'Test Factory',
            'address_id' => $factoryAddress->id,
            'factory_status' => Factory::STATUS_ACTIVE,
        ]);
        $this->factory->inventory()->create(['name' => 'Factory Inventory']);

        $otherFactoryAddress = Address::create([
            'street' => 'Other Factory Street',
            'building' => 'Other Factory Building',
            'city_id' => $city->id,
        ]);

        $this->otherFactory = Factory::create([
            'factory_code' => 'FA-002',
            'name' => 'Other Factory',
            'address_id' => $otherFactoryAddress->id,
            'factory_status' => Factory::STATUS_ACTIVE,
        ]);
        $this->otherFactory->inventory()->create(['name' => 'Other Factory Inventory']);

        // Create users
        $this->user = User::create([
            'name' => 'Factory User',
            'email' => 'factory@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->user->assignRole($factoryUserRole);

        $this->otherUser = User::create([
            'name' => 'Other Factory User',
            'email' => 'otherfactory@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->otherUser->assignRole($factoryUserRole);

        // Create factory users
        $this->factoryUser = FactoryUser::create([
            'user_id' => $this->user->id,
            'factory_id' => $this->factory->id,
            'is_active' => true,
        ]);

        $this->otherFactoryUser = FactoryUser::create([
            'user_id' => $this->otherUser->id,
            'factory_id' => $this->otherFactory->id,
            'is_active' => true,
        ]);

        // Create client
        $clientAddress = Address::create([
            'street' => 'Client Street',
            'building' => 'Client Building',
            'city_id' => $city->id,
        ]);

        $this->client = Client::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'middle_name' => 'Middle',
            'date_of_birth' => '1990-01-01',
            'national_id' => '12345678901234',
            'address_id' => $clientAddress->id,
        ]);

        // Create cloth type and cloth
        $clothType = \App\Models\ClothType::create([
            'code' => 'DT-001',
            'name' => 'Dress Type',
            'description' => 'Test dress type',
        ]);

        $this->cloth = Cloth::create([
            'code' => 'CL-001',
            'name' => 'Test Cloth',
            'status' => 'ready_for_rent',
            'cloth_type_id' => $clothType->id,
        ]);
        $this->inventory->clothes()->attach($this->cloth->id);

        // Create order with tailoring item
        $this->order = Order::create([
            'client_id' => $this->client->id,
            'inventory_id' => $this->inventory->id,
            'total_price' => 1000,
            'status' => 'pending',
            'paid' => 0,
            'remaining' => 1000,
        ]);

        $this->order->items()->attach($this->cloth->id, [
            'price' => 1000,
            'type' => 'tailoring',
            'status' => 'created',
        ]);
    }

    // ==================== Factory User Management Tests ====================

    public function test_factory_can_list_users()
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->getJson("/api/v1/factories/{$this->factory->id}/users");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'user_id', 'factory_id', 'is_active', 'user']
                ]
            ]);
    }

    public function test_factory_can_assign_user()
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $admin->assignRole($adminRole);

        $newUser = User::create([
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/factories/{$this->factory->id}/users/{$newUser->id}"
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('factory_users', [
            'user_id' => $newUser->id,
            'factory_id' => $this->factory->id,
            'is_active' => true,
        ]);
    }

    public function test_factory_cannot_assign_user_already_assigned()
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/factories/{$this->factory->id}/users/{$this->user->id}"
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_factory_can_remove_user()
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->deleteJson(
            "/api/v1/factories/{$this->factory->id}/users/{$this->user->id}"
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('factory_users', [
            'user_id' => $this->user->id,
            'factory_id' => $this->factory->id,
        ]);
    }

    // ==================== Factory Order Listing Tests ====================

    public function test_factory_user_can_list_their_orders()
    {
        // Assign order to factory
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/factory/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
                'total_pages',
            ]);
    }

    public function test_factory_user_cannot_see_other_factory_orders()
    {
        // Assign order to other factory
        $this->order->update([
            'assigned_factory_id' => $this->otherFactory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/factory/orders');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_factory_user_can_filter_orders_by_status()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/factory/orders?factory_status=pending_factory_approval');

        $response->assertStatus(200);
    }

    public function test_factory_user_can_view_order_details()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/factory/orders/{$this->order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'client',
                'items',
            ]);

        // Verify prices are hidden
        $data = $response->json();
        $this->assertArrayNotHasKey('total_price', $data);
        $this->assertArrayNotHasKey('paid', $data);
        $this->assertArrayNotHasKey('remaining', $data);
    }

    public function test_factory_user_cannot_view_order_from_other_factory()
    {
        $this->order->update([
            'assigned_factory_id' => $this->otherFactory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/factory/orders/{$this->order->id}");

        $response->assertStatus(403);
    }

    // ==================== Item Acceptance/Rejection Tests ====================

    public function test_factory_user_can_accept_item()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        // Set item to pending_factory_approval
        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'pending_factory_approval',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/accept",
            [
                'expected_delivery_date' => now()->addDays(7)->format('Y-m-d'),
                'notes' => 'Will complete in 7 days',
            ]
        );

        $response->assertStatus(200);

        $this->order->refresh();
        $item = $this->order->items()->where('clothes.id', $this->cloth->id)->first();
        $this->assertEquals('accepted', $item->pivot->factory_status);
        $this->assertNotNull($item->pivot->factory_accepted_at);
    }

    public function test_factory_user_can_reject_item_with_reason()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'pending_factory_approval',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/reject",
            [
                'rejection_reason' => 'Cannot complete due to material shortage',
                'notes' => 'Need special fabric',
            ]
        );

        $response->assertStatus(200);

        $this->order->refresh();
        $item = $this->order->items()->where('clothes.id', $this->cloth->id)->first();
        $this->assertEquals('rejected', $item->pivot->factory_status);
        $this->assertEquals('Cannot complete due to material shortage', $item->pivot->factory_rejection_reason);
        $this->assertNotNull($item->pivot->factory_rejected_at);
    }

    public function test_factory_user_cannot_reject_item_without_reason()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'pending_factory_approval',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/reject",
            []
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_factory_user_cannot_accept_item_with_invalid_status()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        // Set item to rejected (cannot accept from rejected)
        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'rejected',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/accept",
            []
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ==================== Status Update Tests ====================

    public function test_factory_user_can_update_item_status_to_in_progress()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'accepted',
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/status",
            [
                'status' => 'in_progress',
                'notes' => 'Started production',
            ]
        );

        $response->assertStatus(200);

        $this->order->refresh();
        $item = $this->order->items()->where('clothes.id', $this->cloth->id)->first();
        $this->assertEquals('in_progress', $item->pivot->factory_status);
    }

    public function test_factory_user_can_update_item_status_to_ready_for_delivery()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/status",
            [
                'status' => 'ready_for_delivery',
                'notes' => 'Production completed',
            ]
        );

        $response->assertStatus(200);

        $this->order->refresh();
        $item = $this->order->items()->where('clothes.id', $this->cloth->id)->first();
        $this->assertEquals('ready_for_delivery', $item->pivot->factory_status);
    }

    public function test_factory_user_cannot_update_status_with_invalid_transition()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'accepted',
        ]);

        // Cannot jump from accepted to ready_for_delivery
        $response = $this->actingAs($this->user)->putJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/status",
            [
                'status' => 'ready_for_delivery',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_factory_user_cannot_modify_item_after_delivery()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'delivered_to_atelier',
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/status",
            [
                'status' => 'in_progress',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ==================== Delivery Tests ====================

    public function test_factory_user_can_deliver_item()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'ready_for_delivery',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/deliver",
            [
                'notes' => 'Delivered to atelier',
            ]
        );

        $response->assertStatus(200);

        $this->order->refresh();
        $item = $this->order->items()->where('clothes.id', $this->cloth->id)->first();
        $this->assertEquals('delivered_to_atelier', $item->pivot->factory_status);
        $this->assertNotNull($item->pivot->factory_delivered_at);
    }

    public function test_factory_user_cannot_deliver_item_not_ready()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/deliver",
            []
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ==================== Notes and Delivery Date Tests ====================

    public function test_factory_user_can_update_item_notes()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'accepted',
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/notes",
            [
                'notes' => 'Updated notes',
            ]
        );

        $response->assertStatus(200);

        $this->order->refresh();
        $item = $this->order->items()->where('clothes.id', $this->cloth->id)->first();
        $this->assertEquals('Updated notes', $item->pivot->factory_notes);
    }

    public function test_factory_user_can_set_delivery_date()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'accepted',
        ]);

        $deliveryDate = now()->addDays(10)->format('Y-m-d');

        $response = $this->actingAs($this->user)->putJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/delivery-date",
            [
                'expected_delivery_date' => $deliveryDate,
            ]
        );

        $response->assertStatus(200);

        $this->order->refresh();
        $item = $this->order->items()->where('clothes.id', $this->cloth->id)->first();
        $this->assertEquals($deliveryDate, $item->pivot->factory_expected_delivery_date);
    }

    // ==================== Status History Tests ====================

    public function test_factory_user_can_view_item_status_history()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $clothOrderId = DB::table('cloth_order')
            ->where('order_id', $this->order->id)
            ->where('cloth_id', $this->cloth->id)
            ->value('id');

        FactoryItemStatusLog::create([
            'cloth_order_id' => $clothOrderId,
            'from_status' => null,
            'to_status' => 'pending_factory_approval',
            'changed_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/history"
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'from_status', 'to_status', 'changed_by', 'created_at']
                ]
            ]);
    }

    // ==================== Dashboard Tests ====================

    public function test_factory_user_can_view_dashboard()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'pending_factory_approval',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/factory/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'new_orders_count',
                'in_progress_count',
                'overdue_count',
                'average_completion_days',
            ]);
    }

    public function test_factory_user_can_view_statistics()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'accepted',
            'factory_accepted_at' => now()->subDays(5),
            'factory_delivered_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/factory/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'total_items',
                'accepted_items',
                'rejected_items',
                'delivered_items',
                'average_completion_days',
                'on_time_rate',
                'rejection_rate',
            ]);
    }

    // ==================== Notification Tests ====================

    public function test_notification_sent_when_order_sent_to_factory()
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $admin->assignRole($adminRole);

        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
        ]);

        $response = $this->actingAs($admin)->postJson(
            "/api/v1/orders/{$this->order->id}/tailoring-stage",
            [
                'stage' => Order::STAGE_SENT_TO_FACTORY,
            ]
        );

        $response->assertStatus(200);

        // Check notification was created for factory user
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_FACTORY_ORDER_NEW,
            'reference_type' => Order::class,
            'reference_id' => $this->order->id,
        ]);
    }

    // ==================== Access Control Tests ====================

    public function test_user_without_factory_cannot_access_factory_routes()
    {
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'regular@test.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($regularUser)->getJson('/api/v1/factory/orders');

        $response->assertStatus(403);
    }

    public function test_factory_user_cannot_access_other_factory_order()
    {
        $this->order->update([
            'assigned_factory_id' => $this->otherFactory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/accept",
            []
        );

        $response->assertStatus(403);
    }

    // ==================== Status Log Tests ====================

    public function test_status_log_created_on_acceptance()
    {
        // Ensure user has factory relationship loaded
        $this->user->refresh();
        
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'pending_factory_approval',
        ]);

        $clothOrderId = DB::table('cloth_order')
            ->where('order_id', $this->order->id)
            ->where('cloth_id', $this->cloth->id)
            ->value('id');

        $response = $this->actingAs($this->user)->postJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/accept",
            []
        );

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('factory_item_status_logs', [
            'cloth_order_id' => $clothOrderId,
            'from_status' => 'pending_factory_approval',
            'to_status' => 'accepted',
            'changed_by' => $this->user->id,
        ]);
    }

    public function test_status_log_created_on_rejection()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $this->order->items()->updateExistingPivot($this->cloth->id, [
            'factory_status' => 'pending_factory_approval',
        ]);

        $clothOrderId = DB::table('cloth_order')
            ->where('order_id', $this->order->id)
            ->where('cloth_id', $this->cloth->id)
            ->value('id');

        $this->actingAs($this->user)->postJson(
            "/api/v1/factory/orders/{$this->order->id}/items/{$this->cloth->id}/reject",
            [
                'rejection_reason' => 'Test rejection',
            ]
        );

        $this->assertDatabaseHas('factory_item_status_logs', [
            'cloth_order_id' => $clothOrderId,
            'from_status' => 'pending_factory_approval',
            'to_status' => 'rejected',
            'changed_by' => $this->user->id,
            'rejection_reason' => 'Test rejection',
        ]);
    }
}


