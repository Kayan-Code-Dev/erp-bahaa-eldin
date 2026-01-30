<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Client;
use App\Models\Order;
use App\Models\Cloth;
use App\Models\Factory;
use App\Models\FactoryEvaluation;
use App\Models\TailoringStageLog;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Services\FactoryStatisticsService;

class TailoringAndFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Factory $factory;
    protected Client $client;
    protected Order $order;
    protected Cloth $cloth;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Create country, city, and address
        $country = Country::create(['name' => 'Egypt']);
        $city = City::create(['name' => 'Cairo', 'country_id' => $country->id]);
        $address = Address::create([
            'street' => 'Test Street',
            'building' => 'Building 1',
            'city_id' => $city->id,
        ]);

        // Create branch with inventory
        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'branch_code' => 'TB001',
            'address_id' => $address->id,
        ]);

        // Create inventory for the branch using the polymorphic relationship
        $this->branch->inventory()->create([
            'name' => 'Branch Inventory',
        ]);

        $branchInventory = $this->branch->inventory;

        // Create super admin user (using the email-based super admin pattern)
        $this->user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);

        // Create factory
        $factoryAddress = Address::create([
            'street' => 'Factory Street',
            'building' => 'Factory Building',
            'city_id' => $city->id,
        ]);

        $this->factory = Factory::create([
            'factory_code' => 'FA001',
            'name' => 'Test Factory',
            'address_id' => $factoryAddress->id,
            'factory_status' => 'active',
            'max_capacity' => 10,
        ]);

        // Create factory inventory
        Inventory::create([
            'name' => 'Factory Inventory',
            'inventoriable_type' => Factory::class,
            'inventoriable_id' => $this->factory->id,
        ]);

        // Create client using factory
        $this->client = Client::factory()->create();

        // Create cloth using factory
        $this->cloth = Cloth::factory()->create([
            'status' => 'ready_for_rent',
        ]);

        // Attach cloth to branch inventory
        $this->cloth->inventories()->attach($branchInventory->id);

        // Create order
        $this->order = Order::create([
            'client_id' => $this->client->id,
            'inventory_id' => $branchInventory->id,
            'total_price' => 1000,
            'paid' => 500,
            'remaining' => 500,
            'status' => 'pending',
        ]);

        // Attach cloth as tailoring type to make it a tailoring order
        $this->order->items()->attach($this->cloth->id, [
            'price' => 1000,
            'type' => 'tailoring',
            'status' => 'created',
        ]);
    }

    // ==================== TAILORING STAGE TESTS ====================

    /** @test */
    public function can_get_tailoring_stages()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/orders/tailoring/stages');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stages' => [
                    'received',
                    'sent_to_factory',
                    'in_production',
                    'ready_from_factory',
                    'ready_for_customer',
                    'delivered',
                ],
                'priorities',
            ]);
    }

    /** @test */
    public function can_initialize_tailoring_stage()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$this->order->id}/tailoring-stage", [
                'stage' => 'received',
                'notes' => 'Order received for tailoring',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Tailoring stage updated successfully',
            ]);

        $this->order->refresh();
        $this->assertEquals('received', $this->order->tailoring_stage);
        $this->assertNotNull($this->order->tailoring_stage_changed_at);

        // Check stage log was created
        $this->assertDatabaseHas('tailoring_stage_logs', [
            'order_id' => $this->order->id,
            'from_stage' => null,
            'to_stage' => 'received',
        ]);
    }

    /** @test */
    public function can_transition_through_tailoring_stages()
    {
        // Initialize to received
        $this->order->update(['tailoring_stage' => 'received']);

        // Assign factory first
        $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$this->order->id}/assign-factory", [
                'factory_id' => $this->factory->id,
                'expected_days' => 7,
            ]);

        // Move to sent_to_factory
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$this->order->id}/tailoring-stage", [
                'stage' => 'sent_to_factory',
                'notes' => 'Sent to factory',
            ]);

        $response->assertStatus(200);

        $this->order->refresh();
        $this->assertEquals('sent_to_factory', $this->order->tailoring_stage);
        $this->assertNotNull($this->order->sent_to_factory_date);
    }

    /** @test */
    public function cannot_skip_tailoring_stages()
    {
        // Initialize to received
        $this->order->update(['tailoring_stage' => 'received']);

        // Try to skip directly to in_production (should fail)
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$this->order->id}/tailoring-stage", [
                'stage' => 'in_production',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid stage transition',
            ]);
    }

    /** @test */
    public function can_assign_factory_to_order()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$this->order->id}/assign-factory", [
                'factory_id' => $this->factory->id,
                'expected_days' => 7,
                'priority' => 'high',
                'factory_notes' => 'Rush order',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Factory assigned successfully',
            ]);

        $this->order->refresh();
        $this->assertEquals($this->factory->id, $this->order->assigned_factory_id);
        $this->assertEquals('high', $this->order->priority);
        $this->assertEquals('Rush order', $this->order->factory_notes);
        $this->assertNotNull($this->order->expected_completion_date);
    }

    /** @test */
    public function cannot_assign_factory_at_capacity()
    {
        // Set factory to full capacity
        $this->factory->update([
            'current_orders_count' => 10,
            'max_capacity' => 10,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$this->order->id}/assign-factory", [
                'factory_id' => $this->factory->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Factory is at maximum capacity',
            ]);
    }

    /** @test */
    public function can_get_order_stage_history()
    {
        // Create some stage transitions
        $this->order->updateTailoringStage('received', $this->user, 'Initial stage');
        
        $this->order->assignFactory($this->factory, 7);
        $this->order->updateTailoringStage('sent_to_factory', $this->user, 'Sent to factory');

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/orders/{$this->order->id}/stage-history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'order_id',
                'current_stage',
                'current_stage_label',
                'history' => [
                    '*' => [
                        'id',
                        'order_id',
                        'from_stage',
                        'to_stage',
                        'notes',
                        'created_at',
                    ],
                ],
            ]);

        $history = $response->json('history');
        $this->assertCount(2, $history);
    }

    /** @test */
    public function can_list_tailoring_orders()
    {
        // Set up order with tailoring stage
        $this->order->update([
            'tailoring_stage' => 'received',
            'assigned_factory_id' => $this->factory->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/orders/tailoring');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'tailoring_stage',
                        'tailoring_stage_label',
                        'priority_label',
                    ],
                ],
            ]);
    }

    /** @test */
    public function can_filter_tailoring_orders_by_stage()
    {
        $this->order->update(['tailoring_stage' => 'in_production']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/orders/tailoring?stage=in_production');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $order) {
            $this->assertEquals('in_production', $order['tailoring_stage']);
        }
    }

    /** @test */
    public function can_get_overdue_orders()
    {
        // Set order as overdue
        $this->order->update([
            'tailoring_stage' => 'in_production',
            'assigned_factory_id' => $this->factory->id,
            'sent_to_factory_date' => now()->subDays(14),
            'expected_completion_date' => now()->subDays(7),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/orders/tailoring/overdue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total',
                'orders',
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    /** @test */
    public function can_get_pending_pickup_orders()
    {
        $this->order->update([
            'tailoring_stage' => 'ready_from_factory',
            'assigned_factory_id' => $this->factory->id,
            'actual_completion_date' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/orders/tailoring/pending-pickup');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    /** @test */
    public function can_get_ready_for_customer_orders()
    {
        $this->order->update([
            'tailoring_stage' => 'ready_for_customer',
            'assigned_factory_id' => $this->factory->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/orders/tailoring/ready-for-customer');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    /** @test */
    public function cannot_update_stage_for_non_tailoring_order()
    {
        // Create a non-tailoring order
        $branchInventory = $this->branch->inventory;
        $regularOrder = Order::create([
            'client_id' => $this->client->id,
            'inventory_id' => $branchInventory->id,
            'total_price' => 500,
            'paid' => 500,
            'remaining' => 0,
            'status' => 'pending',
        ]);

        // Attach cloth as rental type
        $regularOrder->items()->attach($this->cloth->id, [
            'price' => 500,
            'type' => 'rent',
            'status' => 'created',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$regularOrder->id}/tailoring-stage", [
                'stage' => 'received',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This is not a tailoring order',
            ]);
    }

    // ==================== FACTORY STATISTICS TESTS ====================

    /** @test */
    public function can_get_overall_factory_statistics()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/factories/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_factories',
                'active_factories',
                'total_current_orders',
                'total_orders_completed',
                'average_quality_rating',
                'average_on_time_rate',
            ]);
    }

    /** @test */
    public function can_get_factory_ranking()
    {
        // Create another factory with evaluations
        $address = Address::first();
        $factory2 = Factory::create([
            'factory_code' => 'FA002',
            'name' => 'Second Factory',
            'address_id' => $address->id,
            'factory_status' => 'active',
            'quality_rating' => 4.5,
            'on_time_rate' => 90,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/factories/ranking?limit=5');

        $response->assertStatus(200);
        
        $ranking = $response->json();
        $this->assertIsArray($ranking);
        
        if (count($ranking) > 0) {
            $this->assertArrayHasKey('rank', $ranking[0]);
            $this->assertArrayHasKey('name', $ranking[0]);
            $this->assertArrayHasKey('performance_score', $ranking[0]);
        }
    }

    /** @test */
    public function can_get_factory_workload_distribution()
    {
        $this->factory->update(['current_orders_count' => 5]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/factories/workload');

        $response->assertStatus(200);
        
        $workload = $response->json();
        $this->assertIsArray($workload);
        
        if (count($workload) > 0) {
            $this->assertArrayHasKey('factory_id', $workload[0]);
            $this->assertArrayHasKey('current_orders', $workload[0]);
            $this->assertArrayHasKey('utilization', $workload[0]);
        }
    }

    /** @test */
    public function can_get_factory_recommendation()
    {
        // Set up factory with good stats
        $this->factory->update([
            'quality_rating' => 4.5,
            'on_time_rate' => 95,
            'current_orders_count' => 2,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/factories/recommend?expected_days=7&priority=high');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'recommended_factory',
                'reason',
            ]);
    }

    /** @test */
    public function can_get_factory_summary()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/factories/{$this->factory->id}/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'factory',
                'statistics' => [
                    'current_orders_count',
                    'total_orders_completed',
                    'average_completion_days',
                    'quality_rating',
                    'on_time_rate',
                ],
                'current_orders',
                'overdue_orders',
                'recent_evaluations',
            ]);
    }

    /** @test */
    public function can_get_factory_trends()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/factories/{$this->factory->id}/trends?months=3");

        $response->assertStatus(200);
        
        $trends = $response->json();
        $this->assertIsArray($trends);
        $this->assertGreaterThanOrEqual(3, count($trends));
        
        if (count($trends) > 0) {
            $this->assertArrayHasKey('month', $trends[0]);
            $this->assertArrayHasKey('month_label', $trends[0]);
        }
    }

    /** @test */
    public function can_recalculate_factory_statistics()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/factories/{$this->factory->id}/recalculate");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Statistics recalculated successfully',
            ]);

        $this->factory->refresh();
        $this->assertNotNull($this->factory->stats_calculated_at);
    }

    /** @test */
    public function can_get_factory_orders()
    {
        // Assign order to factory
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => 'in_production',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/factories/{$this->factory->id}/orders");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
            ]);
    }

    /** @test */
    public function can_filter_factory_orders_by_stage()
    {
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => 'in_production',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/factories/{$this->factory->id}/orders?stage=in_production");

        $response->assertStatus(200);
    }

    // ==================== FACTORY EVALUATION TESTS ====================

    /** @test */
    public function can_create_factory_evaluation()
    {
        // Set up order as completed from factory
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => 'ready_from_factory',
            'sent_to_factory_date' => now()->subDays(7),
            'expected_completion_date' => now(),
            'actual_completion_date' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/factories/{$this->factory->id}/evaluations", [
                'order_id' => $this->order->id,
                'quality_rating' => 4,
                'craftsmanship_rating' => 5,
                'communication_rating' => 4,
                'packaging_rating' => 4,
                'notes' => 'Good work overall',
                'positive_feedback' => 'Excellent craftsmanship',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'evaluation' => [
                    'id',
                    'factory_id',
                    'order_id',
                    'quality_rating',
                    'on_time',
                ],
            ]);

        $this->assertDatabaseHas('factory_evaluations', [
            'factory_id' => $this->factory->id,
            'order_id' => $this->order->id,
            'quality_rating' => 4,
        ]);
    }

    /** @test */
    public function can_create_general_factory_evaluation_without_order()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/factories/{$this->factory->id}/evaluations", [
                'quality_rating' => 3,
                'notes' => 'General feedback',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('factory_evaluations', [
            'factory_id' => $this->factory->id,
            'order_id' => null,
            'quality_rating' => 3,
        ]);
    }

    /** @test */
    public function cannot_create_duplicate_evaluation_for_same_order()
    {
        // Create first evaluation
        FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'order_id' => $this->order->id,
            'quality_rating' => 4,
            'on_time' => true,
            'evaluated_by' => $this->user->id,
        ]);

        // Try to create duplicate
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/factories/{$this->factory->id}/evaluations", [
                'order_id' => $this->order->id,
                'quality_rating' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'An evaluation for this order already exists',
            ]);
    }

    /** @test */
    public function can_list_factory_evaluations()
    {
        // Create some evaluations
        FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'quality_rating' => 5,
            'on_time' => true,
            'evaluated_by' => $this->user->id,
        ]);

        FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'quality_rating' => 3,
            'on_time' => false,
            'evaluated_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/factories/{$this->factory->id}/evaluations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
            ]);

        $this->assertEquals(2, $response->json('total'));
    }

    /** @test */
    public function can_filter_evaluations_by_quality()
    {
        FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'quality_rating' => 5,
            'on_time' => true,
            'evaluated_by' => $this->user->id,
        ]);

        FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'quality_rating' => 2,
            'on_time' => true,
            'evaluated_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/factories/{$this->factory->id}/evaluations?min_quality=4");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        foreach ($data as $evaluation) {
            $this->assertGreaterThanOrEqual(4, $evaluation['quality_rating']);
        }
    }

    /** @test */
    public function can_filter_evaluations_by_on_time()
    {
        FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'quality_rating' => 4,
            'on_time' => true,
            'evaluated_by' => $this->user->id,
        ]);

        FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'quality_rating' => 3,
            'on_time' => false,
            'evaluated_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/factories/{$this->factory->id}/evaluations?on_time=false");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        foreach ($data as $evaluation) {
            $this->assertFalse($evaluation['on_time']);
        }
    }

    /** @test */
    public function can_view_evaluation_details()
    {
        $evaluation = FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'order_id' => $this->order->id,
            'quality_rating' => 4,
            'completion_days' => 7,
            'expected_days' => 8,
            'on_time' => true,
            'craftsmanship_rating' => 5,
            'notes' => 'Good quality',
            'evaluated_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/evaluations/{$evaluation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'factory_id',
                'order_id',
                'quality_rating',
                'completion_days',
                'expected_days',
                'on_time',
                'factory',
                'evaluator',
            ]);
    }

    // ==================== MODEL TESTS ====================

    /** @test */
    public function order_is_overdue_accessor_works()
    {
        $this->order->update([
            'expected_completion_date' => now()->subDays(3),
            'actual_completion_date' => null,
        ]);

        $this->assertTrue($this->order->fresh()->is_overdue);

        // After completion, should not be overdue
        $this->order->update(['actual_completion_date' => now()]);
        $this->assertFalse($this->order->fresh()->is_overdue);
    }

    /** @test */
    public function order_days_until_expected_accessor_works()
    {
        $this->order->update([
            'expected_completion_date' => now()->addDays(5),
        ]);

        $this->assertEquals(5, $this->order->fresh()->days_until_expected);
    }

    /** @test */
    public function factory_is_at_capacity_accessor_works()
    {
        $this->factory->update([
            'current_orders_count' => 10,
            'max_capacity' => 10,
        ]);

        $this->assertTrue($this->factory->fresh()->is_at_capacity);

        $this->factory->update(['current_orders_count' => 5]);
        $this->assertFalse($this->factory->fresh()->is_at_capacity);
    }

    /** @test */
    public function factory_performance_score_calculation()
    {
        $this->factory->update([
            'quality_rating' => 4.0,
            'on_time_rate' => 80,
        ]);

        $factory = $this->factory->fresh();
        
        // 60% quality (4.0 * 0.6 = 2.4) + 40% on-time ((80/100)*5*0.4 = 1.6)
        // Expected: 4.0
        $this->assertEqualsWithDelta(4.0, $factory->performance_score, 0.1);
    }

    /** @test */
    public function evaluation_average_rating_accessor_works()
    {
        $evaluation = FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'quality_rating' => 4,
            'craftsmanship_rating' => 5,
            'communication_rating' => 3,
            'packaging_rating' => 4,
            'on_time' => true,
            'evaluated_by' => $this->user->id,
        ]);

        // Average of 4, 5, 3, 4 = 4.0
        $this->assertEquals(4.0, $evaluation->average_rating);
    }

    /** @test */
    public function evaluation_delay_days_accessor_works()
    {
        $evaluation = FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'quality_rating' => 3,
            'completion_days' => 10,
            'expected_days' => 7,
            'on_time' => false,
            'evaluated_by' => $this->user->id,
        ]);

        // 10 - 7 = 3 days late
        $this->assertEquals(3, $evaluation->delay_days);
    }

    /** @test */
    public function tailoring_stage_log_transition_description_works()
    {
        $log = TailoringStageLog::create([
            'order_id' => $this->order->id,
            'from_stage' => 'received',
            'to_stage' => 'sent_to_factory',
            'changed_by' => $this->user->id,
        ]);

        $this->assertStringContainsString('Order Received', $log->transition_description);
        $this->assertStringContainsString('Sent to Factory', $log->transition_description);
    }

    // ==================== FACTORY STATISTICS SERVICE TESTS ====================

    /** @test */
    public function factory_statistics_service_recalculates_correctly()
    {
        // Create completed orders
        $this->order->update([
            'assigned_factory_id' => $this->factory->id,
            'tailoring_stage' => 'delivered',
            'sent_to_factory_date' => now()->subDays(7),
            'actual_completion_date' => now()->subDays(1),
        ]);

        // Create evaluation
        FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'order_id' => $this->order->id,
            'quality_rating' => 5,
            'on_time' => true,
            'evaluated_by' => $this->user->id,
        ]);

        $service = new FactoryStatisticsService();
        $service->recalculateForFactory($this->factory);

        $this->factory->refresh();
        $this->assertEquals(1, $this->factory->total_orders_completed);
        $this->assertEquals(1, $this->factory->total_evaluations);
        $this->assertEquals(5.0, $this->factory->quality_rating);
        $this->assertEquals(100, $this->factory->on_time_rate);
    }

    // ==================== VALIDATION TESTS ====================

    /** @test */
    public function quality_rating_must_be_between_1_and_5()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/factories/{$this->factory->id}/evaluations", [
                'quality_rating' => 6,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quality_rating']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/factories/{$this->factory->id}/evaluations", [
                'quality_rating' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quality_rating']);
    }

    /** @test */
    public function tailoring_stage_must_be_valid()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$this->order->id}/tailoring-stage", [
                'stage' => 'invalid_stage',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stage']);
    }

    /** @test */
    public function priority_must_be_valid()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/orders/{$this->order->id}/assign-factory", [
                'factory_id' => $this->factory->id,
                'priority' => 'invalid_priority',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }
}


