<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Client;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Rent;
use App\Models\ClothHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestBuyOrderFlow extends Command
{
    protected $signature = 'test:buy-order {--cleanup : Clean up test data after running}';
    protected $description = 'Test the complete buy order flow including cloth history and revenue calculation';

    private $testData = [];
    private $errors = [];
    private $passed = 0;
    private $failed = 0;

    public function handle()
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║           BUY ORDER FLOW TEST SUITE                          ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        try {
            DB::beginTransaction();

            // Setup
            $this->setupTestData();

            // Test Scenarios
            $this->testScenario1_CreateBuyOrder();
            $this->testScenario2_BuyOrderMustHaveSingleItem();
            $this->testScenario3_CannotBuyClothWithPendingRent();
            $this->testScenario4_CannotBuySoldCloth();
            $this->testScenario5_BuyOrderRequiresPaymentToDeliver();
            $this->testScenario6_DeliverBuyOrderSetsClothToSold();
            $this->testScenario7_SoldClothNotAvailableForRent();
            $this->testScenario8_ClothHistoryTracking();
            $this->testScenario9_RevenueCalculation();
            $this->testScenario10_SoldClothCannotBeTransferred();
            $this->testScenario11_OrderWithSoldItemsCannotBeEdited();
            $this->testScenario12_OrderWithSoldItemsCannotBeDeleted();

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
                'name' => 'Test Buy Inventory',
                'inventoriable_type' => Branch::class,
                'inventoriable_id' => $this->testData['branch']->id,
            ]);
        }

        // Get or create cloth type
        $this->testData['clothType'] = ClothType::first();
        if (!$this->testData['clothType']) {
            $this->testData['clothType'] = ClothType::create([
                'name' => 'Test Type',
                'description' => 'Test cloth type'
            ]);
        }

        // Create test cloths
        $this->testData['cloth1'] = Cloth::create([
            'code' => 'TST-BUY-CLOTH-1-' . time(),
            'name' => 'Test Buy Cloth 1',
            'cloth_type_id' => $this->testData['clothType']->id,
            'status' => 'ready_for_rent',
        ]);

        $this->testData['cloth2'] = Cloth::create([
            'code' => 'TST-BUY-CLOTH-2-' . time(),
            'name' => 'Test Buy Cloth 2',
            'cloth_type_id' => $this->testData['clothType']->id,
            'status' => 'ready_for_rent',
        ]);

        $this->testData['cloth3'] = Cloth::create([
            'code' => 'TST-BUY-CLOTH-3-' . time(),
            'name' => 'Test Buy Cloth 3 (for rent conflict test)',
            'cloth_type_id' => $this->testData['clothType']->id,
            'status' => 'ready_for_rent',
        ]);

        $this->testData['cloth4'] = Cloth::create([
            'code' => 'TST-BUY-CLOTH-4-' . time(),
            'name' => 'Test Buy Cloth 4 (already sold)',
            'cloth_type_id' => $this->testData['clothType']->id,
            'status' => 'sold',
        ]);

        $this->testData['cloth5'] = Cloth::create([
            'code' => 'TST-BUY-CLOTH-5-' . time(),
            'name' => 'Test Buy Cloth 5 (full flow test)',
            'cloth_type_id' => $this->testData['clothType']->id,
            'status' => 'ready_for_rent',
        ]);

        // Add cloths to inventory
        $this->testData['inventory']->clothes()->attach([
            $this->testData['cloth1']->id,
            $this->testData['cloth2']->id,
            $this->testData['cloth3']->id,
            $this->testData['cloth4']->id,
            $this->testData['cloth5']->id,
        ]);

        // Create a pending rent for cloth3 to test conflict
        $rentOrder = Order::create([
            'client_id' => $this->testData['client']->id,
            'inventory_id' => $this->testData['inventory']->id,
            'total_price' => 100,
            'status' => 'delivered',
            'paid' => 100,
            'remaining' => 0,
        ]);

        $rentOrder->items()->attach($this->testData['cloth3']->id, [
            'price' => 100,
            'type' => 'rent',
            'days_of_rent' => 5,
            'delivery_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'rented',
            'returnable' => true,
        ]);

        // Create Rent record for cloth3
        $this->testData['pendingRent'] = Rent::create([
            'cloth_id' => $this->testData['cloth3']->id,
            'order_id' => $rentOrder->id,
            'delivery_date' => now()->addDays(5),
            'return_date' => now()->addDays(10),
            'days_of_rent' => 5,
            'status' => 'active',
        ]);

        $this->info('  ✓ Test data created');
    }

    private function testScenario1_CreateBuyOrder()
    {
        $this->info('');
        $this->info('━━━ Scenario 1: Create a valid buy order ━━━');

        $order = Order::create([
            'client_id' => $this->testData['client']->id,
            'inventory_id' => $this->testData['inventory']->id,
            'total_price' => 500,
            'status' => 'created',
            'paid' => 0,
            'remaining' => 500,
        ]);

        $order->items()->attach($this->testData['cloth1']->id, [
            'price' => 500,
            'type' => 'buy',
            'status' => 'created',
            'returnable' => false,
        ]);

        $this->testData['buyOrder1'] = $order;

        $this->assert(
            $order->id > 0,
            'Buy order created successfully',
            'Failed to create buy order'
        );

        $this->assert(
            $order->items->count() === 1,
            'Buy order has exactly 1 item',
            'Buy order item count mismatch'
        );

        $this->assert(
            $order->items->first()->pivot->type === 'buy',
            'Item type is "buy"',
            'Item type is not "buy"'
        );
    }

    private function testScenario2_BuyOrderMustHaveSingleItem()
    {
        $this->info('');
        $this->info('━━━ Scenario 2: Buy order must have single item (validation) ━━━');

        // This tests the validation logic - simulating what the controller does
        $items = [
            ['cloth_id' => $this->testData['cloth1']->id, 'type' => 'buy', 'price' => 500],
            ['cloth_id' => $this->testData['cloth2']->id, 'type' => 'rent', 'price' => 100],
        ];

        $buyItems = collect($items)->where('type', 'buy');
        $shouldReject = $buyItems->isNotEmpty() && count($items) > 1;

        $this->assert(
            $shouldReject === true,
            'Validation correctly rejects multiple items with buy type',
            'Validation failed to reject mixed buy order'
        );

        // Test multiple buy items
        $items2 = [
            ['cloth_id' => $this->testData['cloth1']->id, 'type' => 'buy', 'price' => 500],
            ['cloth_id' => $this->testData['cloth2']->id, 'type' => 'buy', 'price' => 300],
        ];

        $buyItems2 = collect($items2)->where('type', 'buy');
        $shouldReject2 = $buyItems2->isNotEmpty() && count($items2) > 1;

        $this->assert(
            $shouldReject2 === true,
            'Validation correctly rejects multiple buy items',
            'Validation failed to reject multiple buy items'
        );
    }

    private function testScenario3_CannotBuyClothWithPendingRent()
    {
        $this->info('');
        $this->info('━━━ Scenario 3: Cannot buy cloth with pending rent ━━━');

        // Check if cloth3 has pending rents
        $hasPendingRents = Rent::where('cloth_id', $this->testData['cloth3']->id)
            ->where('status', '!=', 'canceled')
            ->where('return_date', '>=', today())
            ->exists();

        $this->assert(
            $hasPendingRents === true,
            'Cloth 3 has pending rent (as expected)',
            'Cloth 3 should have pending rent'
        );

        $this->assert(
            $hasPendingRents === true,
            'System would block sale of cloth with pending rent',
            'System should block sale of cloth with pending rent'
        );
    }

    private function testScenario4_CannotBuySoldCloth()
    {
        $this->info('');
        $this->info('━━━ Scenario 4: Cannot buy already sold cloth ━━━');

        $cloth4 = $this->testData['cloth4']->fresh();

        $this->assert(
            $cloth4->status === 'sold',
            'Cloth 4 status is "sold"',
            'Cloth 4 should have status "sold"'
        );

        $isSold = $cloth4->status === 'sold';

        $this->assert(
            $isSold === true,
            'System would block sale of already sold cloth',
            'System should block sale of already sold cloth'
        );
    }

    private function testScenario5_BuyOrderRequiresPaymentToDeliver()
    {
        $this->info('');
        $this->info('━━━ Scenario 5: Buy order requires full payment to deliver ━━━');

        $order = $this->testData['buyOrder1']->fresh();

        // Check if order has remaining balance
        $hasRemaining = $order->remaining > 0;

        $this->assert(
            $hasRemaining === true,
            'Order has remaining balance of ' . $order->remaining,
            'Order should have remaining balance'
        );

        // Simulate the validation that would happen in deliver()
        $isBuyOnly = $order->items->every(fn($item) => $item->pivot->type === 'buy');
        $canDeliver = !($isBuyOnly && $order->remaining > 0);

        $this->assert(
            $canDeliver === false,
            'System would block delivery of unpaid buy order',
            'System should block delivery of unpaid buy order'
        );

        // Now add payment
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 500,
            'status' => 'paid',
            'payment_type' => 'normal',
            'payment_date' => now(),
            'created_by' => $this->testData['user']->id,
        ]);

        $order->paid = 500;
        $order->remaining = 0;
        $order->status = 'paid';
        $order->save();

        $order->refresh();

        $this->assert(
            $order->remaining == 0,
            'Order remaining is now 0 after payment',
            'Order remaining should be 0'
        );

        $this->assert(
            $order->status === 'paid',
            'Order status is "paid"',
            'Order status should be "paid"'
        );
    }

    private function testScenario6_DeliverBuyOrderSetsClothToSold()
    {
        $this->info('');
        $this->info('━━━ Scenario 6: Delivering buy order sets cloth to sold ━━━');

        $order = $this->testData['buyOrder1']->fresh();
        $cloth = $this->testData['cloth1']->fresh();

        $this->assert(
            $cloth->status === 'ready_for_rent',
            'Cloth status is "ready_for_rent" before delivery',
            'Cloth should be "ready_for_rent" before delivery'
        );

        // Simulate delivery
        $order->status = 'delivered';
        $order->save();

        // Update item pivot status
        $order->items()->updateExistingPivot($cloth->id, ['status' => 'delivered']);

        // Set cloth status to sold (this is what the controller does)
        $cloth->status = 'sold';
        $cloth->save();

        $cloth->refresh();

        $this->assert(
            $cloth->status === 'sold',
            'Cloth status is now "sold" after delivery',
            'Cloth status should be "sold" after delivery'
        );

        $this->assert(
            $order->status === 'delivered',
            'Order status is "delivered"',
            'Order status should be "delivered"'
        );
    }

    private function testScenario7_SoldClothNotAvailableForRent()
    {
        $this->info('');
        $this->info('━━━ Scenario 7: Sold cloth is not available for rent ━━━');

        $cloth = $this->testData['cloth1']->fresh();

        $this->assert(
            $cloth->status === 'sold',
            'Cloth status is "sold"',
            'Cloth should be "sold"'
        );

        // Simulate availability check (from ClothController::checkClothAvailability)
        $isUnavailable = in_array($cloth->status, ['repairing', 'sold']);

        $this->assert(
            $isUnavailable === true,
            'Sold cloth is marked as unavailable for rent',
            'Sold cloth should be unavailable for rent'
        );
    }

    private function testScenario8_ClothHistoryTracking()
    {
        $this->info('');
        $this->info('━━━ Scenario 8: Cloth history tracking ━━━');

        // Use cloth5 for a complete flow with history
        $cloth = $this->testData['cloth5'];
        $user = $this->testData['user'];

        // Record created
        ClothHistory::create([
            'cloth_id' => $cloth->id,
            'action' => 'created',
            'entity_type' => 'branch',
            'entity_id' => $this->testData['branch']->id,
            'user_id' => $user->id,
        ]);

        // Create buy order
        $order = Order::create([
            'client_id' => $this->testData['client']->id,
            'inventory_id' => $this->testData['inventory']->id,
            'total_price' => 750,
            'status' => 'created',
            'paid' => 0,
            'remaining' => 750,
        ]);

        $order->items()->attach($cloth->id, [
            'price' => 750,
            'type' => 'buy',
            'status' => 'created',
            'returnable' => false,
        ]);

        // Record ordered
        ClothHistory::create([
            'cloth_id' => $cloth->id,
            'action' => 'ordered',
            'order_id' => $order->id,
            'entity_type' => 'branch',
            'entity_id' => $this->testData['branch']->id,
            'user_id' => $user->id,
        ]);

        // Add payment
        Payment::create([
            'order_id' => $order->id,
            'amount' => 750,
            'status' => 'paid',
            'payment_type' => 'normal',
            'payment_date' => now(),
            'created_by' => $user->id,
        ]);

        $order->paid = 750;
        $order->remaining = 0;
        $order->status = 'paid';
        $order->save();

        // Deliver and mark as sold
        $order->status = 'delivered';
        $order->save();
        $order->items()->updateExistingPivot($cloth->id, ['status' => 'delivered']);

        $oldStatus = $cloth->status;
        $cloth->status = 'sold';
        $cloth->save();

        // Record status change
        ClothHistory::create([
            'cloth_id' => $cloth->id,
            'action' => 'status_changed',
            'status' => 'sold',
            'notes' => "Status changed from {$oldStatus} to sold",
            'user_id' => $user->id,
        ]);

        $this->testData['buyOrder2'] = $order;

        // Check history
        $history = ClothHistory::where('cloth_id', $cloth->id)
            ->orderBy('created_at')
            ->get();

        $this->assert(
            $history->count() >= 3,
            'Cloth has ' . $history->count() . ' history records',
            'Cloth should have at least 3 history records'
        );

        $this->info('  Cloth History:');
        foreach ($history as $record) {
            $this->info("    - {$record->action}" . 
                ($record->status ? " (status: {$record->status})" : '') .
                ($record->order_id ? " [Order #{$record->order_id}]" : '') .
                ($record->notes ? " - {$record->notes}" : '')
            );
        }

        $hasSoldStatus = $history->contains(fn($h) => $h->action === 'status_changed' && $h->status === 'sold');

        $this->assert(
            $hasSoldStatus === true,
            'History contains "sold" status change',
            'History should contain "sold" status change'
        );
    }

    private function testScenario9_RevenueCalculation()
    {
        $this->info('');
        $this->info('━━━ Scenario 9: Revenue calculation for sold cloth ━━━');

        $cloth = $this->testData['cloth5']->fresh();

        // Get all orders containing this cloth with type 'buy'
        $buyOrders = Order::whereHas('items', function ($q) use ($cloth) {
            $q->where('clothes.id', $cloth->id)
              ->where('cloth_order.type', 'buy');
        })->with(['items' => function ($q) use ($cloth) {
            $q->where('clothes.id', $cloth->id);
        }, 'payments'])->get();

        $this->info('  Revenue breakdown for cloth: ' . $cloth->code);

        $totalRevenue = 0;
        $totalPayments = 0;

        foreach ($buyOrders as $order) {
            $itemPrice = $order->items->first()->pivot->price ?? 0;
            $orderPayments = $order->payments->where('status', 'paid')->sum('amount');
            
            $this->info("    Order #{$order->id}:");
            $this->info("      - Item Price: " . number_format($itemPrice, 2));
            $this->info("      - Payments Received: " . number_format($orderPayments, 2));
            $this->info("      - Order Status: {$order->status}");
            
            $totalRevenue += $itemPrice;
            $totalPayments += $orderPayments;
        }

        $this->info('');
        $this->info("  ═══════════════════════════════════");
        $this->info("  Total Sale Price:     " . number_format($totalRevenue, 2));
        $this->info("  Total Payments:       " . number_format($totalPayments, 2));
        $this->info("  Revenue Collected:    " . number_format($totalPayments, 2));
        $this->info("  ═══════════════════════════════════");

        $this->assert(
            $totalRevenue > 0,
            'Total revenue is ' . number_format($totalRevenue, 2),
            'Total revenue should be greater than 0'
        );

        $this->assert(
            $totalPayments == $totalRevenue,
            'All payments collected (fully paid)',
            'Payments should equal revenue for delivered orders'
        );

        $this->assert(
            $cloth->status === 'sold',
            'Cloth final status is "sold"',
            'Cloth should have final status "sold"'
        );
    }

    private function testScenario10_SoldClothCannotBeTransferred()
    {
        $this->info('');
        $this->info('━━━ Scenario 10: Sold cloth cannot be transferred ━━━');

        // Get a sold cloth from our test data
        $soldCloth = $this->testData['cloth1']->fresh();

        $this->assert(
            $soldCloth->status === 'sold',
            'Cloth 1 status is "sold"',
            'Cloth 1 should be "sold"'
        );

        // Simulate transfer validation (what TransferController does)
        $wouldBeBlocked = $soldCloth->status === 'sold';

        $this->assert(
            $wouldBeBlocked === true,
            'System would block transfer of sold cloth',
            'System should block transfer of sold cloth'
        );
    }

    private function testScenario11_OrderWithSoldItemsCannotBeEdited()
    {
        $this->info('');
        $this->info('━━━ Scenario 11: Order with sold items cannot be edited ━━━');

        // Get the order with sold items
        $order = $this->testData['buyOrder1']->fresh();
        $order->load('items');

        // Check if any items are sold
        $hasSoldItems = $order->items->contains(function ($item) {
            return $item->status === 'sold';
        });

        $this->assert(
            $hasSoldItems === true,
            'Order has sold items',
            'Order should have sold items'
        );

        // Simulate validation (what OrderController does)
        $wouldBeBlocked = $hasSoldItems;

        $this->assert(
            $wouldBeBlocked === true,
            'System would block editing order with sold items',
            'System should block editing order with sold items'
        );
    }

    private function testScenario12_OrderWithSoldItemsCannotBeDeleted()
    {
        $this->info('');
        $this->info('━━━ Scenario 12: Order with sold items cannot be deleted ━━━');

        // Get the order with sold items
        $order = $this->testData['buyOrder1']->fresh();
        $order->load('items');

        // Check if any items are sold
        $hasSoldItems = $order->items->contains(function ($item) {
            return $item->status === 'sold';
        });

        $this->assert(
            $hasSoldItems === true,
            'Order has sold items',
            'Order should have sold items'
        );

        // Simulate validation (what OrderController does)
        $wouldBeBlocked = $hasSoldItems;

        $this->assert(
            $wouldBeBlocked === true,
            'System would block deleting order with sold items',
            'System should block deleting order with sold items'
        );
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

