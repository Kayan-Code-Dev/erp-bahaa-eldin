<?php

namespace Tests\Coverage\Tailoring;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Order;
use App\Models\Factory;
use App\Models\Client;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\ClothType;
use App\Models\Cloth;
use Laravel\Sanctum\Sanctum;

class TailoringModuleTest extends TestCase
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
            'tailoring.view', 'tailoring.manage', 'orders.view', 'orders.create',
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

    protected function createTailoringOrder(array $attributes = []): Order
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        
        $order = Order::factory()->create(array_merge([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
        ], $attributes));
        
        $order->items()->attach($cloth->id, ['type' => 'tailoring', 'price' => 100]);
        
        return $order->fresh();
    }

    public function test_list_tailoring_orders()
    {
        Order::factory()->count(5)->create(['tailoring_stage' => 'received']);
        $user = $this->createUserWithPermission('tailoring.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders/tailoring');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_get_tailoring_stages()
    {
        $user = $this->createUserWithPermission('tailoring.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders/tailoring/stages');
        $response->assertStatus(200);
    }

    public function test_update_tailoring_stage_received_to_sent_to_factory()
    {
        $order = $this->createTailoringOrder(['tailoring_stage' => Order::STAGE_RECEIVED]);
        $factory = Factory::factory()->create();
        $user = $this->createUserWithPermission('tailoring.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/tailoring-stage", [
            'stage' => Order::STAGE_SENT_TO_FACTORY,
            'factory_id' => $factory->id,
        ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(Order::STAGE_SENT_TO_FACTORY, $order->tailoring_stage);
        $this->assertEquals($factory->id, $order->assigned_factory_id);
    }

    public function test_update_tailoring_stage_invalid_transition_fails()
    {
        $order = $this->createTailoringOrder(['tailoring_stage' => Order::STAGE_RECEIVED]);
        $user = $this->createUserWithPermission('tailoring.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/tailoring-stage", [
            'stage' => Order::STAGE_IN_PRODUCTION, // Skipping sent_to_factory
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'current_stage', 'requested_stage', 'allowed_stages']);
    }

    public function test_update_tailoring_stage_missing_factory_fails()
    {
        $order = $this->createTailoringOrder(['tailoring_stage' => Order::STAGE_RECEIVED]);
        $user = $this->createUserWithPermission('tailoring.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/tailoring-stage", [
            'stage' => Order::STAGE_SENT_TO_FACTORY,
            // Missing factory_id
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Factory must be assigned when moving to sent_to_factory stage']);
    }

    public function test_update_tailoring_stage_in_production_to_ready_from_factory()
    {
        $factory = Factory::factory()->create();
        $order = $this->createTailoringOrder([
            'tailoring_stage' => Order::STAGE_IN_PRODUCTION,
            'assigned_factory_id' => $factory->id,
        ]);
        $user = $this->createUserWithPermission('tailoring.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/tailoring-stage", [
            'stage' => Order::STAGE_READY_FROM_FACTORY,
        ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(Order::STAGE_READY_FROM_FACTORY, $order->tailoring_stage);
    }

    public function test_assign_factory_to_order()
    {
        $order = $this->createTailoringOrder();
        $factory = Factory::factory()->create();
        $user = $this->createUserWithPermission('tailoring.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/assign-factory", [
            'factory_id' => $factory->id,
            'expected_days' => 7,
        ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals($factory->id, $order->assigned_factory_id);
        $this->assertNotNull($order->expected_completion_date);
    }

    public function test_get_tailoring_stage_history()
    {
        $order = $this->createTailoringOrder(['tailoring_stage' => Order::STAGE_RECEIVED]);
        $factory = Factory::factory()->create();
        $user = $this->createUserWithPermission('tailoring.manage');
        Sanctum::actingAs($user);

        // Update stage to create history
        $this->postJson("/api/v1/orders/{$order->id}/tailoring-stage", [
            'stage' => Order::STAGE_SENT_TO_FACTORY,
            'factory_id' => $factory->id,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->id}/stage-history");
        $response->assertStatus(200)
            ->assertJsonStructure(['order_id', 'current_stage', 'history']);
    }

    public function test_update_tailoring_stage_not_tailoring_order_fails()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        
        // Create rental order (not tailoring)
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
        ]);
        $order->items()->attach($cloth->id, ['type' => 'rent', 'price' => 100]);

        $user = $this->createUserWithPermission('tailoring.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/orders/{$order->id}/tailoring-stage", [
            'stage' => Order::STAGE_RECEIVED,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'This is not a tailoring order']);
    }
}

