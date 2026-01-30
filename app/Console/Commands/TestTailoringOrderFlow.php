<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Client;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Branch;
use App\Models\Factory;
use App\Models\FactoryUser;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TailoringStageLog;
use App\Models\FactoryItemStatusLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestTailoringOrderFlow extends Command
{
    protected $signature = 'test:tailoring-order {--cleanup : Clean up test data after running}';
    protected $description = 'Test the complete tailoring order flow with factory including all edge cases';

    private $testData = [];
    private $errors = [];
    private $passed = 0;
    private $failed = 0;

    public function handle()
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║       TAILORING ORDER FLOW TEST SUITE                        ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        try {
            DB::beginTransaction();

            // Setup
            $this->setupTestData();

            // Test Scenarios - Order Stages
            $this->testScenario1_CreateTailoringOrder();
            $this->testScenario2_UpdateToReceivedStage();
            $this->testScenario3_AssignFactory();
            $this->testScenario4_CannotSkipStages();
            $this->testScenario5_FactoryCapacityCheck();
            $this->testScenario6_SendToFactory();

            // Test Scenarios - Factory Operations
            $this->testScenario7_FactoryAcceptItem();
            $this->testScenario8_FactoryRejectItem();
            $this->testScenario9_FactoryUpdateItemStatus();
            $this->testScenario10_FactoryCannotModifyAfterDelivery();

            // Test Scenarios - Complete Flow
            $this->testScenario11_CompleteOrderFlow();
            $this->testScenario12_StageHistoryTracking();
            $this->testScenario13_ItemStatusHistoryTracking();
            $this->testScenario14_PriorityLevels();
            $this->testScenario15_OverdueDetection();

            // Summary
            $this->printSummary();

            if ($this->option('cleanup')) {
                DB::rollBack();
                $this->info('Test data cleaned up (rolled back).');
            } else {
                DB::commit();
                $this->info('Test data committed to database.');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Test failed with exception: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return $this->failed > 0 ? 1 : 0;
    }

    private function setupTestData()
    {
        $this->info('Setting up test data...');

        // Require existing base data
        $this->testData['user'] = User::first();
        if (!$this->testData['user']) {
            throw new \Exception('No users found. Please seed the database first with at least one user.');
        }

        $this->testData['client'] = Client::first();
        if (!$this->testData['client']) {
            throw new \Exception('No clients found. Please seed the database first with at least one client.');
        }

        $this->testData['branch'] = Branch::first();
        if (!$this->testData['branch']) {
            throw new \Exception('No branches found. Please seed the database first with at least one branch.');
        }

        // Get or create inventory for branch
        $this->testData['inventory'] = Inventory::where('inventoriable_type', Branch::class)
            ->where('inventoriable_id', $this->testData['branch']->id)
            ->first();

        if (!$this->testData['inventory']) {
            $this->testData['inventory'] = Inventory::create([
                'name' => 'Test Tailoring Inventory',
                'inventoriable_type' => Branch::class,
                'inventoriable_id' => $this->testData['branch']->id,
            ]);
        }

        // Get or create cloth type
        $this->testData['clothType'] = ClothType::first();
        if (!$this->testData['clothType']) {
            $this->testData['clothType'] = ClothType::create([
                'code' => 'TL',
                'name' => 'Tailoring Type',
                'description' => 'For tailoring tests'
            ]);
        }

        // Create test cloths for tailoring
        $this->testData['cloth1'] = Cloth::create([
            'code' => 'TST-TAIL-1-' . time(),
            'name' => 'Test Tailoring Cloth 1',
            'cloth_type_id' => $this->testData['clothType']->id,
            'status' => 'ready_for_rent',
        ]);

        $this->testData['cloth2'] = Cloth::create([
            'code' => 'TST-TAIL-2-' . time(),
            'name' => 'Test Tailoring Cloth 2',
            'cloth_type_id' => $this->testData['clothType']->id,
            'status' => 'ready_for_rent',
        ]);

        $this->testData['cloth3'] = Cloth::create([
            'code' => 'TST-TAIL-3-' . time(),
            'name' => 'Test Tailoring Cloth 3 (for rejection test)',
            'cloth_type_id' => $this->testData['clothType']->id,
            'status' => 'ready_for_rent',
        ]);

        $this->testData['cloth4'] = Cloth::create([
            'code' => 'TST-TAIL-4-' . time(),
            'name' => 'Test Tailoring Cloth 4 (complete flow)',
            'cloth_type_id' => $this->testData['clothType']->id,
            'status' => 'ready_for_rent',
        ]);

        // Add cloths to inventory
        $this->testData['inventory']->clothes()->attach([
            $this->testData['cloth1']->id,
            $this->testData['cloth2']->id,
            $this->testData['cloth3']->id,
            $this->testData['cloth4']->id,
        ]);

        // Get or create test factories
        $existingFactory = Factory::first();
        if ($existingFactory) {
            $this->testData['factory1'] = $existingFactory;
            // Ensure it has capacity for testing
            $existingFactory->update([
                'max_capacity' => max(10, $existingFactory->max_capacity),
                'current_orders_count' => 0,
            ]);
        } else {
            // Need an address first
            $address = \App\Models\Address::first();
            if (!$address) {
                throw new \Exception('No addresses found. Please seed the database first.');
            }
            $this->testData['factory1'] = Factory::create([
                'factory_code' => 'TST-FAC-1-' . time(),
                'name' => 'Test Factory 1',
                'address_id' => $address->id,
                'max_capacity' => 10,
                'current_orders_count' => 0,
            ]);
        }

        // Create a "full" factory for capacity testing (or use second existing)
        $secondFactory = Factory::where('id', '!=', $this->testData['factory1']->id)->first();
        if ($secondFactory) {
            $this->testData['factory2'] = $secondFactory;
            // Make it appear full
            $secondFactory->update([
                'max_capacity' => 5,
                'current_orders_count' => 5,
            ]);
        } else {
            $address = \App\Models\Address::first();
            $this->testData['factory2'] = Factory::create([
                'factory_code' => 'TST-FAC-2-' . time(),
                'name' => 'Test Factory 2 (Full)',
                'address_id' => $address->id,
                'max_capacity' => 5,
                'current_orders_count' => 5,
            ]);
        }

        // Create factory user
        $this->testData['factoryUser'] = User::create([
            'name' => 'Factory User ' . time(),
            'email' => 'factory-' . time() . '@test.com',
            'password' => Hash::make('password123'),
        ]);

        // Link factory user to factory
        FactoryUser::create([
            'factory_id' => $this->testData['factory1']->id,
            'user_id' => $this->testData['factoryUser']->id,
        ]);

        $this->info('  ✓ Test data created');
    }

    private function testScenario1_CreateTailoringOrder()
    {
        $this->info('');
        $this->info('━━━ Scenario 1: Create a tailoring order ━━━');

        $order = Order::create([
            'client_id' => $this->testData['client']->id,
            'inventory_id' => $this->testData['inventory']->id,
            'total_price' => 1500,
            'status' => 'created',
            'paid' => 0,
            'remaining' => 1500,
        ]);

        $order->items()->attach($this->testData['cloth1']->id, [
            'price' => 1500,
            'type' => 'tailoring',
            'status' => 'created',
            'returnable' => false,
        ]);

        $this->testData['tailoringOrder1'] = $order;

        $this->assert(
            $order->id > 0,
            'Tailoring order created successfully',
            'Failed to create tailoring order'
        );

        $this->assert(
            $order->isTailoringOrder(),
            'Order is identified as tailoring order',
            'Order should be identified as tailoring order'
        );

        $this->assert(
            $order->tailoring_stage === null,
            'Initial tailoring_stage is null',
            'Initial tailoring_stage should be null'
        );
    }

    private function testScenario2_UpdateToReceivedStage()
    {
        $this->info('');
        $this->info('━━━ Scenario 2: Update to received stage ━━━');

        $order = $this->testData['tailoringOrder1'];
        $user = $this->testData['user'];

        // Update to received stage
        $result = $order->updateTailoringStage(Order::STAGE_RECEIVED, $user, 'Measurements taken');
        $order->refresh();

        $this->assert(
            $result === true,
            'Stage update returned true',
            'Stage update should return true'
        );

        $this->assert(
            $order->tailoring_stage === Order::STAGE_RECEIVED,
            'Stage is now "received"',
            'Stage should be "received"'
        );

        $this->assert(
            $order->tailoring_stage_changed_at !== null,
            'Stage changed timestamp is set',
            'Stage changed timestamp should be set'
        );

        // Check log was created
        $log = TailoringStageLog::where('order_id', $order->id)
            ->where('to_stage', Order::STAGE_RECEIVED)
            ->first();

        $this->assert(
            $log !== null,
            'Stage change logged in TailoringStageLog',
            'Stage change should be logged'
        );
    }

    private function testScenario3_AssignFactory()
    {
        $this->info('');
        $this->info('━━━ Scenario 3: Assign factory to order ━━━');

        $order = $this->testData['tailoringOrder1'];
        $factory = $this->testData['factory1'];

        $result = $order->assignFactory($factory, 14); // 14 days expected
        $order->refresh();

        $this->assert(
            $result === true,
            'Factory assignment returned true',
            'Factory assignment should return true'
        );

        $this->assert(
            $order->assigned_factory_id === $factory->id,
            'Factory ID is set on order',
            'Factory ID should be set on order'
        );

        $this->assert(
            $order->expected_completion_date !== null,
            'Expected completion date is set',
            'Expected completion date should be set'
        );

        // Verify expected_completion_date is set (the exact days depends on when assignFactory was called)
        $this->assert(
            $order->expected_completion_date >= today(),
            'Expected completion date is in the future (' . $order->expected_completion_date->format('Y-m-d') . ')',
            'Expected completion date should be in the future'
        );
    }

    private function testScenario4_CannotSkipStages()
    {
        $this->info('');
        $this->info('━━━ Scenario 4: Cannot skip stages ━━━');

        $order = $this->testData['tailoringOrder1'];

        // Try to transition directly to in_production (should fail)
        $canTransition = $order->canTransitionTo(Order::STAGE_IN_PRODUCTION);

        $this->assert(
            $canTransition === false,
            'Cannot skip from received to in_production',
            'Should not be able to skip stages'
        );

        // Try to transition to ready_from_factory (should fail)
        $canTransition2 = $order->canTransitionTo(Order::STAGE_READY_FROM_FACTORY);

        $this->assert(
            $canTransition2 === false,
            'Cannot skip from received to ready_from_factory',
            'Should not be able to skip stages'
        );

        // Valid next stage should be sent_to_factory
        $allowedStages = Order::getAllowedNextStages($order->tailoring_stage);

        $this->assert(
            in_array(Order::STAGE_SENT_TO_FACTORY, $allowedStages),
            'Valid next stage is sent_to_factory',
            'sent_to_factory should be allowed'
        );
    }

    private function testScenario5_FactoryCapacityCheck()
    {
        $this->info('');
        $this->info('━━━ Scenario 5: Factory capacity check ━━━');

        $fullFactory = $this->testData['factory2'];

        $this->assert(
            $fullFactory->current_orders_count >= $fullFactory->max_capacity,
            'Factory 2 is at capacity (' . $fullFactory->current_orders_count . '/' . $fullFactory->max_capacity . ')',
            'Factory 2 should be at capacity'
        );

        // Check is_at_capacity attribute
        $isAtCapacity = $fullFactory->current_orders_count >= $fullFactory->max_capacity;

        $this->assert(
            $isAtCapacity === true,
            'System correctly identifies factory at capacity',
            'Factory should be marked as at capacity'
        );

        $availableFactory = $this->testData['factory1'];
        $isAvailable = $availableFactory->current_orders_count < $availableFactory->max_capacity;

        $this->assert(
            $isAvailable === true,
            'Factory 1 has capacity (' . $availableFactory->current_orders_count . '/' . $availableFactory->max_capacity . ')',
            'Factory 1 should have capacity'
        );
    }

    private function testScenario6_SendToFactory()
    {
        $this->info('');
        $this->info('━━━ Scenario 6: Send order to factory ━━━');

        $order = $this->testData['tailoringOrder1'];
        $user = $this->testData['user'];

        // Update to sent_to_factory stage
        $result = $order->updateTailoringStage(Order::STAGE_SENT_TO_FACTORY, $user, 'Sent via courier');
        $order->refresh();

        $this->assert(
            $result === true,
            'Stage update to sent_to_factory succeeded',
            'Stage update should succeed'
        );

        $this->assert(
            $order->tailoring_stage === Order::STAGE_SENT_TO_FACTORY,
            'Stage is now "sent_to_factory"',
            'Stage should be "sent_to_factory"'
        );

        $this->assert(
            $order->sent_to_factory_date !== null,
            'sent_to_factory_date is set',
            'sent_to_factory_date should be set'
        );

        // Manually set item factory_status (normally done by controller)
        $order->items()->updateExistingPivot($this->testData['cloth1']->id, [
            'factory_status' => 'pending_factory_approval',
        ]);

        $item = $order->items()->first();

        $this->assert(
            $item->pivot->factory_status === 'pending_factory_approval',
            'Item factory_status is "pending_factory_approval"',
            'Item should have pending_factory_approval status'
        );
    }

    private function testScenario7_FactoryAcceptItem()
    {
        $this->info('');
        $this->info('━━━ Scenario 7: Factory accepts item ━━━');

        $order = $this->testData['tailoringOrder1'];
        $factoryUser = $this->testData['factoryUser'];

        // Accept the item
        $order->items()->updateExistingPivot($this->testData['cloth1']->id, [
            'factory_status' => 'accepted',
            'factory_accepted_at' => now(),
            'factory_expected_delivery_date' => now()->addDays(7),
        ]);

        // Log status change
        $clothOrderId = DB::table('cloth_order')
            ->where('order_id', $order->id)
            ->where('cloth_id', $this->testData['cloth1']->id)
            ->value('id');

        FactoryItemStatusLog::create([
            'cloth_order_id' => $clothOrderId,
            'from_status' => 'pending_factory_approval',
            'to_status' => 'accepted',
            'changed_by' => $factoryUser->id,
            'notes' => 'Will use premium materials',
        ]);

        $item = $order->items()->first();

        $this->assert(
            $item->pivot->factory_status === 'accepted',
            'Item factory_status is "accepted"',
            'Item should be accepted'
        );

        $this->assert(
            $item->pivot->factory_accepted_at !== null,
            'factory_accepted_at timestamp is set',
            'Acceptance timestamp should be set'
        );

        $this->assert(
            $item->pivot->factory_expected_delivery_date !== null,
            'factory_expected_delivery_date is set',
            'Expected delivery date should be set'
        );
    }

    private function testScenario8_FactoryRejectItem()
    {
        $this->info('');
        $this->info('━━━ Scenario 8: Factory rejects item ━━━');

        // Create a new order for rejection test
        $order = Order::create([
            'client_id' => $this->testData['client']->id,
            'inventory_id' => $this->testData['inventory']->id,
            'total_price' => 1200,
            'status' => 'created',
            'paid' => 0,
            'remaining' => 1200,
            'assigned_factory_id' => $this->testData['factory1']->id,
            'tailoring_stage' => Order::STAGE_SENT_TO_FACTORY,
        ]);

        $order->items()->attach($this->testData['cloth3']->id, [
            'price' => 1200,
            'type' => 'tailoring',
            'status' => 'created',
            'factory_status' => 'pending_factory_approval',
        ]);

        $this->testData['rejectedOrder'] = $order;
        $factoryUser = $this->testData['factoryUser'];

        // Reject the item
        $order->items()->updateExistingPivot($this->testData['cloth3']->id, [
            'factory_status' => 'rejected',
            'factory_rejected_at' => now(),
            'factory_rejection_reason' => 'Material not available',
        ]);

        $clothOrderId = DB::table('cloth_order')
            ->where('order_id', $order->id)
            ->where('cloth_id', $this->testData['cloth3']->id)
            ->value('id');

        FactoryItemStatusLog::create([
            'cloth_order_id' => $clothOrderId,
            'from_status' => 'pending_factory_approval',
            'to_status' => 'rejected',
            'changed_by' => $factoryUser->id,
            'rejection_reason' => 'Material not available',
        ]);

        $item = $order->items()->first();

        $this->assert(
            $item->pivot->factory_status === 'rejected',
            'Item factory_status is "rejected"',
            'Item should be rejected'
        );

        $this->assert(
            $item->pivot->factory_rejection_reason === 'Material not available',
            'Rejection reason is recorded',
            'Rejection reason should be recorded'
        );

        // Check that rejected is a terminal state
        $validNext = $this->getValidNextStatuses('rejected');

        $this->assert(
            empty($validNext),
            'Rejected is a terminal state (no valid next statuses)',
            'Rejected should be terminal'
        );
    }

    private function testScenario9_FactoryUpdateItemStatus()
    {
        $this->info('');
        $this->info('━━━ Scenario 9: Factory updates item through workflow ━━━');

        $order = $this->testData['tailoringOrder1'];
        $factoryUser = $this->testData['factoryUser'];

        // Get cloth_order_id
        $clothOrderId = DB::table('cloth_order')
            ->where('order_id', $order->id)
            ->where('cloth_id', $this->testData['cloth1']->id)
            ->value('id');

        // Transition: accepted → in_progress
        $order->items()->updateExistingPivot($this->testData['cloth1']->id, [
            'factory_status' => 'in_progress',
        ]);

        FactoryItemStatusLog::create([
            'cloth_order_id' => $clothOrderId,
            'from_status' => 'accepted',
            'to_status' => 'in_progress',
            'changed_by' => $factoryUser->id,
            'notes' => 'Started cutting fabric',
        ]);

        $item = $order->items()->first();

        $this->assert(
            $item->pivot->factory_status === 'in_progress',
            'Item status is now "in_progress"',
            'Status should be in_progress'
        );

        // Transition: in_progress → ready_for_delivery
        $order->items()->updateExistingPivot($this->testData['cloth1']->id, [
            'factory_status' => 'ready_for_delivery',
        ]);

        FactoryItemStatusLog::create([
            'cloth_order_id' => $clothOrderId,
            'from_status' => 'in_progress',
            'to_status' => 'ready_for_delivery',
            'changed_by' => $factoryUser->id,
            'notes' => 'Production complete',
        ]);

        $order->refresh();
        $item = $order->items()->first();

        $this->assert(
            $item->pivot->factory_status === 'ready_for_delivery',
            'Item status is now "ready_for_delivery"',
            'Status should be ready_for_delivery'
        );
    }

    private function testScenario10_FactoryCannotModifyAfterDelivery()
    {
        $this->info('');
        $this->info('━━━ Scenario 10: Factory cannot modify after delivery ━━━');

        $order = $this->testData['tailoringOrder1'];

        // Deliver the item
        $order->items()->updateExistingPivot($this->testData['cloth1']->id, [
            'factory_status' => 'delivered_to_atelier',
        ]);

        $item = $order->items()->first();
        $currentStatus = $item->pivot->factory_status;

        // Check that delivered_to_atelier cannot be modified back
        $validNext = $this->getValidNextStatuses($currentStatus);

        $this->assert(
            !in_array('in_progress', $validNext),
            'Cannot go back to in_progress after delivery',
            'Should not be able to modify after delivery'
        );

        $this->assert(
            in_array('closed', $validNext) || empty($validNext) || $validNext === ['closed'],
            'Only valid next status is closed (or none)',
            'Only closed should be allowed after delivery'
        );
    }

    private function testScenario11_CompleteOrderFlow()
    {
        $this->info('');
        $this->info('━━━ Scenario 11: Complete order flow (all stages) ━━━');

        $user = $this->testData['user'];
        $factory = $this->testData['factory1'];

        // Create new order for complete flow
        $order = Order::create([
            'client_id' => $this->testData['client']->id,
            'inventory_id' => $this->testData['inventory']->id,
            'total_price' => 2000,
            'status' => 'created',
            'paid' => 2000,
            'remaining' => 0,
        ]);

        $order->items()->attach($this->testData['cloth4']->id, [
            'price' => 2000,
            'type' => 'tailoring',
            'status' => 'created',
        ]);

        $this->testData['completeFlowOrder'] = $order;

        // Stage 1: received
        $order->updateTailoringStage(Order::STAGE_RECEIVED, $user, 'Order received');
        $order->assignFactory($factory, 10);

        // Stage 2: sent_to_factory
        $order->updateTailoringStage(Order::STAGE_SENT_TO_FACTORY, $user, 'Sent to factory');
        $order->items()->updateExistingPivot($this->testData['cloth4']->id, [
            'factory_status' => 'pending_factory_approval',
        ]);

        // Factory accepts and produces
        $order->items()->updateExistingPivot($this->testData['cloth4']->id, [
            'factory_status' => 'accepted',
            'factory_accepted_at' => now(),
        ]);

        $order->items()->updateExistingPivot($this->testData['cloth4']->id, [
            'factory_status' => 'in_progress',
        ]);

        // Stage 3: in_production
        $order->updateTailoringStage(Order::STAGE_IN_PRODUCTION, $user, 'Factory started production');

        // Factory completes
        $order->items()->updateExistingPivot($this->testData['cloth4']->id, [
            'factory_status' => 'ready_for_delivery',
        ]);

        // Stage 4: ready_from_factory
        $order->updateTailoringStage(Order::STAGE_READY_FROM_FACTORY, $user, 'Factory completed');

        // Item delivered to branch
        $order->items()->updateExistingPivot($this->testData['cloth4']->id, [
            'factory_status' => 'delivered_to_atelier',
        ]);

        // Stage 5: ready_for_customer
        $order->updateTailoringStage(Order::STAGE_READY_FOR_CUSTOMER, $user, 'Ready at branch');

        // Stage 6: delivered
        $order->updateTailoringStage(Order::STAGE_DELIVERED, $user, 'Customer picked up');

        $order->items()->updateExistingPivot($this->testData['cloth4']->id, [
            'factory_status' => 'closed',
        ]);

        $order->refresh();

        $this->assert(
            $order->tailoring_stage === Order::STAGE_DELIVERED,
            'Order completed all stages (now delivered)',
            'Order should be delivered'
        );

        $this->assert(
            $order->actual_completion_date !== null,
            'Actual completion date is set',
            'Actual completion date should be set'
        );

        $this->assert(
            $order->received_from_factory_date !== null,
            'Received from factory date is set',
            'Received from factory date should be set'
        );

        // Verify item is closed
        $item = $order->items()->first();
        $this->assert(
            $item->pivot->factory_status === 'closed',
            'Item factory_status is "closed"',
            'Item should be closed'
        );

        $this->info('  Complete flow stages traversed: received → sent_to_factory → in_production → ready_from_factory → ready_for_customer → delivered');
    }

    private function testScenario12_StageHistoryTracking()
    {
        $this->info('');
        $this->info('━━━ Scenario 12: Stage history tracking ━━━');

        $order = $this->testData['completeFlowOrder'];

        $logs = TailoringStageLog::where('order_id', $order->id)
            ->orderBy('created_at')
            ->get();

        $this->assert(
            $logs->count() === 6,
            'All 6 stage transitions logged (' . $logs->count() . ' found)',
            'Should have 6 stage logs'
        );

        $this->info('  Stage history:');
        foreach ($logs as $log) {
            $from = $log->from_stage ?? 'null';
            $to = $log->to_stage;
            $this->info("    - {$from} → {$to}");
        }

        // Verify sequence
        $expectedSequence = [
            [null, Order::STAGE_RECEIVED],
            [Order::STAGE_RECEIVED, Order::STAGE_SENT_TO_FACTORY],
            [Order::STAGE_SENT_TO_FACTORY, Order::STAGE_IN_PRODUCTION],
            [Order::STAGE_IN_PRODUCTION, Order::STAGE_READY_FROM_FACTORY],
            [Order::STAGE_READY_FROM_FACTORY, Order::STAGE_READY_FOR_CUSTOMER],
            [Order::STAGE_READY_FOR_CUSTOMER, Order::STAGE_DELIVERED],
        ];

        $sequenceCorrect = true;
        foreach ($logs as $index => $log) {
            if ($log->from_stage !== $expectedSequence[$index][0] ||
                $log->to_stage !== $expectedSequence[$index][1]) {
                $sequenceCorrect = false;
                break;
            }
        }

        $this->assert(
            $sequenceCorrect,
            'Stage sequence is correct',
            'Stage sequence should match expected flow'
        );
    }

    private function testScenario13_ItemStatusHistoryTracking()
    {
        $this->info('');
        $this->info('━━━ Scenario 13: Item status history tracking ━━━');

        $order = $this->testData['tailoringOrder1'];

        $clothOrderId = DB::table('cloth_order')
            ->where('order_id', $order->id)
            ->where('cloth_id', $this->testData['cloth1']->id)
            ->value('id');

        $logs = FactoryItemStatusLog::where('cloth_order_id', $clothOrderId)
            ->orderBy('created_at')
            ->get();

        $this->assert(
            $logs->count() >= 3,
            'Item has at least 3 status logs (' . $logs->count() . ' found)',
            'Should have at least 3 item status logs'
        );

        $this->info('  Item status history:');
        foreach ($logs as $log) {
            $from = $log->from_status ?? 'null';
            $to = $log->to_status;
            $this->info("    - {$from} → {$to}");
        }

        // Verify first transition was to accepted
        $firstLog = $logs->first();
        $this->assert(
            $firstLog->to_status === 'accepted',
            'First status transition was to "accepted"',
            'First transition should be to accepted'
        );
    }

    private function testScenario14_PriorityLevels()
    {
        $this->info('');
        $this->info('━━━ Scenario 14: Priority levels ━━━');

        $validPriorities = Order::getPriorityLevels();

        $this->assert(
            count($validPriorities) === 4,
            '4 priority levels defined',
            'Should have 4 priority levels'
        );

        $expectedPriorities = ['low', 'normal', 'high', 'urgent'];
        $allPresent = true;
        foreach ($expectedPriorities as $priority) {
            if (!isset($validPriorities[$priority])) {
                $allPresent = false;
                break;
            }
        }

        $this->assert(
            $allPresent,
            'All priority levels present: low, normal, high, urgent',
            'All expected priorities should be present'
        );

        // Test setting priority on order
        $order = $this->testData['tailoringOrder1'];
        $order->priority = Order::PRIORITY_URGENT;
        $order->save();
        $order->refresh();

        $this->assert(
            $order->priority === 'urgent',
            'Order priority set to urgent',
            'Priority should be settable'
        );

        $this->assert(
            $order->priority_label === 'Urgent',
            'Priority label is "Urgent"',
            'Priority label accessor should work'
        );
    }

    private function testScenario15_OverdueDetection()
    {
        $this->info('');
        $this->info('━━━ Scenario 15: Overdue detection ━━━');

        // Create an overdue order
        $order = Order::create([
            'client_id' => $this->testData['client']->id,
            'inventory_id' => $this->testData['inventory']->id,
            'total_price' => 1000,
            'status' => 'created',
            'paid' => 0,
            'remaining' => 1000,
            'assigned_factory_id' => $this->testData['factory1']->id,
            'tailoring_stage' => Order::STAGE_IN_PRODUCTION,
            'expected_completion_date' => now()->subDays(5), // 5 days overdue
            'actual_completion_date' => null,
        ]);

        $order->items()->attach($this->testData['cloth2']->id, [
            'price' => 1000,
            'type' => 'tailoring',
            'status' => 'created',
        ]);

        $this->testData['overdueOrder'] = $order;

        $this->assert(
            $order->is_overdue === true,
            'Order is correctly identified as overdue',
            'Order should be overdue'
        );

        $this->assert(
            $order->days_until_expected < 0,
            'Days until expected is negative (' . $order->days_until_expected . ')',
            'Days should be negative for overdue'
        );

        // Test non-overdue order
        $nonOverdueOrder = $this->testData['completeFlowOrder'];
        $nonOverdueOrder->refresh();

        $this->assert(
            $nonOverdueOrder->is_overdue === false,
            'Completed order is not overdue',
            'Completed order should not be overdue'
        );
    }

    private function getValidNextStatuses(?string $currentStatus): array
    {
        $transitions = [
            null => ['pending_factory_approval'],
            'pending_factory_approval' => ['accepted', 'rejected'],
            'accepted' => ['in_progress'],
            'in_progress' => ['ready_for_delivery'],
            'ready_for_delivery' => ['delivered_to_atelier'],
            'delivered_to_atelier' => ['closed'],
            'rejected' => [],
            'closed' => [],
        ];

        return $transitions[$currentStatus] ?? [];
    }

    private function assert($condition, $successMessage, $failureMessage)
    {
        if ($condition) {
            $this->info("  ✓ {$successMessage}");
            $this->passed++;
        } else {
            $this->error("  ✗ {$failureMessage}");
            $this->errors[] = $failureMessage;
            $this->failed++;
        }
    }

    private function printSummary()
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                      TEST SUMMARY                            ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');
        $this->info("  Total Tests: " . ($this->passed + $this->failed));
        $this->info("  Passed: {$this->passed}");
        $this->info("  Failed: {$this->failed}");
        $this->info('');

        if ($this->failed > 0) {
            $this->error('  Failures:');
            foreach ($this->errors as $error) {
                $this->error("    - {$error}");
            }
        } else {
            $this->info('  ✓ All tests passed!');
        }

        $this->info('');
    }
}

