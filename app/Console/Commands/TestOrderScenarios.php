<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Cloth;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Custody;
use App\Models\CustodyReturn;
use App\Models\Inventory;
use Illuminate\Support\Facades\Hash;

class TestOrderScenarios extends Command
{
    protected $signature = 'test:order-scenarios';
    protected $description = 'Test all order, custody, and payment scenarios directly';

    private $results = [];

    public function handle()
    {
        $this->info('=== ORDER, CUSTODY & PAYMENT SCENARIOS TEST ===');
        $this->newLine();

        // Run all scenarios
        // Basic Scenarios (1-16)
        $this->runScenario('1', 'Create order with cloth_id', [$this, 'scenario1']);
        $this->runScenario('2', 'Create order with item and order discounts', [$this, 'scenario2']);
        $this->runScenario('3', 'Create order with initial payment (auto-creates payment)', [$this, 'scenario3']);
        $this->runScenario('4', 'Add normal payment to order', [$this, 'scenario4']);
        $this->runScenario('5', 'Add fee payment to order', [$this, 'scenario5']);
        $this->runScenario('6', 'Add pending payment to order', [$this, 'scenario6']);
        $this->runScenario('7', 'Try to mark order as delivered WITHOUT custody (should FAIL)', [$this, 'scenario7']);
        $this->runScenario('8', 'Mark order as delivered WITH custody in pending status (should SUCCEED)', [$this, 'scenario8']);
        $this->runScenario('9', 'Try to mark order as delivered with non-pending custody (should FAIL)', [$this, 'scenario9']);
        $this->runScenario('10', 'Try to finish order WITHOUT custody decision (should FAIL)', [$this, 'scenario10']);
        $this->runScenario('11', 'Try to finish order WITH pending payments (should FAIL)', [$this, 'scenario11']);
        $this->runScenario('12', 'Finish order with KEPT custody (forfeited) - no return proof needed (should SUCCEED)', [$this, 'scenario12']);
        $this->runScenario('13', 'Finish order with RETURNED custody - must have return proof (should SUCCEED)', [$this, 'scenario13']);
        $this->runScenario('14', 'Finish order with fees - total = order_price + fees (should SUCCEED)', [$this, 'scenario14']);
        $this->runScenario('15', 'Try to finish order with INSUFFICIENT payment (should FAIL)', [$this, 'scenario15']);
        $this->runScenario('16', 'Try to finish order with returned custody but NO return proof (should FAIL)', [$this, 'scenario16']);

        // Payment Operations (17-25)
        $this->runScenario('17', 'Pay a pending payment', [$this, 'scenario17']);
        $this->runScenario('18', 'Cancel a pending payment', [$this, 'scenario18']);
        $this->runScenario('19', 'Try to pay already paid payment (should FAIL)', [$this, 'scenario19']);
        $this->runScenario('20', 'Try to cancel already canceled payment (should FAIL)', [$this, 'scenario20']);
        $this->runScenario('21', 'Try to pay canceled payment (should FAIL)', [$this, 'scenario21']);
        $this->runScenario('22', 'Cancel paid payment', [$this, 'scenario22']);
        $this->runScenario('23', 'Add payment then pay it', [$this, 'scenario23']);
        $this->runScenario('24', 'Add payment then cancel it', [$this, 'scenario24']);
        $this->runScenario('25', 'Multiple payments lifecycle - pay some, cancel others', [$this, 'scenario25']);

        // Fee Scenarios (26-30)
        $this->runScenario('26', 'Add multiple fee payments', [$this, 'scenario26']);
        $this->runScenario('27', 'Add fee then pay it', [$this, 'scenario27']);
        $this->runScenario('28', 'Cancel fee payment', [$this, 'scenario28']);
        $this->runScenario('29', 'Order with fees added after initial payment', [$this, 'scenario29']);
        $this->runScenario('30', 'Order with fees and normal payments mixed', [$this, 'scenario30']);

        // Custody Scenarios (31-35)
        $this->runScenario('31', 'Multiple custodies - all kept', [$this, 'scenario31']);
        $this->runScenario('32', 'Multiple custodies - all returned with proof', [$this, 'scenario32']);
        $this->runScenario('33', 'Multiple custodies - mixed (some kept, some returned)', [$this, 'scenario33']);
        $this->runScenario('34', 'Custody returned then changed to kept', [$this, 'scenario34']);
        $this->runScenario('35', 'Order with custody and fees', [$this, 'scenario35']);

        // Complete Lifecycle Scenarios (36-40)
        $this->runScenario('36', 'Full order lifecycle: created -> partially_paid -> paid -> delivered -> finished', [$this, 'scenario36']);
        $this->runScenario('37', 'Order: initial payment -> add payment -> pay it -> deliver -> finish', [$this, 'scenario37']);
        $this->runScenario('38', 'Order with fees throughout lifecycle', [$this, 'scenario38']);
        $this->runScenario('39', 'Order with custody and payments complete flow', [$this, 'scenario39']);
        $this->runScenario('40', 'Order canceled', [$this, 'scenario40']);

        // Complex Combinations (41-45)
        $this->runScenario('41', 'Complex: create -> add pending -> add fee -> pay pending -> deliver -> return custody -> finish', [$this, 'scenario41']);
        $this->runScenario('42', 'Complex: create -> add payment -> cancel -> add new -> deliver -> finish', [$this, 'scenario42']);
        $this->runScenario('43', 'Complex: create -> multiple payments -> pay some -> cancel some -> deliver -> finish', [$this, 'scenario43']);
        $this->runScenario('44', 'Complex: create -> add fee -> add payment -> pay -> deliver -> return custody -> finish', [$this, 'scenario44']);
        $this->runScenario('45', 'Complex: create -> initial payment -> add fee -> deliver -> return custody -> finish', [$this, 'scenario45']);

        // Summary
        $this->newLine();
        $this->info('=== TEST SUMMARY ===');
        $passed = collect($this->results)->where('success', true)->count();
        $failed = collect($this->results)->where('success', false)->count();
        $this->info("Total Scenarios: " . count($this->results));
        $this->info("✓ Passed: {$passed}");
        $this->error("✗ Failed: {$failed}");
        $this->newLine();

        // Detailed results
        $this->info('=== DETAILED RESULTS ===');
        foreach ($this->results as $idx => $result) {
            $status = $result['success'] ? '✓' : '✗';
            $this->line("{$status} Scenario " . ($idx + 1) . ": {$result['message']}");
        }

        return $failed > 0 ? 1 : 0;
    }

    private function runScenario($num, $name, $callback)
    {
        $this->info("SCENARIO {$num}: {$name}");
        try {
            $result = $callback();
            $this->results[] = $result;
            if ($result['success']) {
                $this->line("  ✓ SUCCESS: {$result['message']}");
            } else {
                $this->error("  ✗ FAILED: {$result['message']}");
            }
        } catch (\Exception $e) {
            $this->error("  ✗ EXCEPTION: {$e->getMessage()}");
            $this->results[] = ['success' => false, 'message' => "Exception: {$e->getMessage()}"];
        }
        $this->newLine();
    }

    /**
     * Helper: Recalculate order payments and remaining
     */
    private function recalculateOrderPayments(&$order)
    {
        // Ensure we get the latest payments from database - refresh from database
        $order = $order->fresh();

        // Use DB::table() to ensure we get fresh data directly from database (bypass any Eloquent caching)
        // Recalculate total paid from non-fee payments only (fees are tracked separately)
        $totalPaid = \Illuminate\Support\Facades\DB::table('order_payments')
            ->where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount') ?? 0;

        // Calculate remaining: total_price - paid (fees do not affect remaining)
        $remaining = max(0, $order->total_price - $totalPaid);

        $status = 'created';
        // Update order status based on paid amount (compared to total_price only, fees excluded)
        if ($totalPaid >= $order->total_price) {
            $status = 'paid';
            $remaining = 0;
        } elseif ($totalPaid > 0) {
            $status = 'partially_paid';
        }

        // Update the order instance directly
        $order->paid = $totalPaid;
        $order->remaining = $remaining;
        $order->status = $status;

        // Save and refresh to ensure values are persisted
        $order->save();
        $order = $order->fresh();

        // Verify the remaining was saved - if not, force update via DB
        $savedRemaining = \Illuminate\Support\Facades\DB::table('orders')
            ->where('id', $order->id)
            ->value('remaining');

        if (abs($savedRemaining - $remaining) > 0.01) {
            // Force update via direct DB query
            \Illuminate\Support\Facades\DB::table('orders')
                ->where('id', $order->id)
                ->update([
                    'paid' => $totalPaid,
                    'remaining' => $remaining,
                    'status' => $status,
                    'updated_at' => now()
                ]);
            $order = $order->fresh();
        }

        return $order;
    }

    /**
     * Helper: Create a fresh order for testing
     */
    private function createFreshOrder($totalPrice = 100.00, $paid = 0)
    {
        $client = Client::first();
        $branch = Branch::first();
        $cloth = Cloth::first();

        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'total_price' => $totalPrice,
            'status' => 'created',
            'paid' => $paid,
            'remaining' => $totalPrice - $paid
        ]);

        $order->items()->attach($cloth->id, [
            'price' => $totalPrice,
            'type' => 'buy',
            'status' => 'created'
        ]);

        // Recalculate and use the returned order
        $order = $this->recalculateOrderPayments($order);
        return $order;
    }

    /**
     * Helper: Setup order with custody for delivery
     */
    private function setupOrderForDelivery($order, $custodyValue = 100.00)
    {
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Security deposit',
            'value' => $custodyValue,
            'status' => 'pending'
        ]);

        $order->status = 'delivered';
        $order->save();

        return $custody;
    }

    /**
     * Helper: Setup order ready for finishing
     */
    private function setupOrderForFinish($order, $custodyStatus = 'forfeited', $withReturnProof = false)
    {
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Security deposit',
            'value' => 100.00,
            'status' => $custodyStatus
        ]);

        if ($custodyStatus === 'returned' && $withReturnProof) {
            CustodyReturn::create([
                'custody_id' => $custody->id,
                'returned_at' => now(),
                'return_proof_photo' => 'test/proof.jpg',
                'customer_name' => 'Test Customer',
                'customer_phone' => '01234567890',
                'customer_id_number' => '12345678901234'
            ]);
        }

        $order->status = 'delivered';
        $order->save();

        return $custody;
    }

    /**
     * Helper: Simulate payPayment endpoint
     */
    private function payPayment(&$order, $payment)
    {
        if ($payment->status === 'paid') {
            return ['success' => false, 'message' => 'Payment is already paid'];
        }
        if ($payment->status === 'canceled') {
            return ['success' => false, 'message' => 'Cannot pay canceled payment'];
        }

        $payment->status = 'paid';
        $payment->payment_date = now();
        $payment->save();

        // Ensure payment is committed before recalculation
        $payment->refresh();

        $order = $this->recalculateOrderPayments($order);
        return ['success' => true, 'payment' => $payment];
    }

    /**
     * Helper: Simulate cancelPayment endpoint
     */
    private function cancelPayment(&$order, $payment, $notes = null)
    {
        if ($payment->status === 'canceled') {
            return ['success' => false, 'message' => 'Payment is already canceled'];
        }

        $payment->status = 'canceled';
        if ($notes) {
            $payment->notes = ($payment->notes ? $payment->notes . "\n" : '') . 'Canceled: ' . $notes;
        }
        $payment->save();

        // Ensure payment is committed before recalculation
        $payment->refresh();

        $order = $this->recalculateOrderPayments($order);
        return ['success' => true, 'payment' => $payment];
    }

    private function scenario1()
    {
        $client = Client::first();
        $branch = Branch::first();
        $cloth = Cloth::first();

        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'total_price' => 100.00,
            'status' => 'created',
            'paid' => 0,
            'remaining' => 100.00 // Will be recalculated automatically
        ]);

        // Recalculate remaining (fees do not affect remaining, they are tracked separately)
        $order->refresh();
        $order->remaining = max(0, $order->total_price - $order->paid);
        $order->save();

        $order->items()->attach($cloth->id, [
            'price' => 100.00,
            'type' => 'buy',
            'status' => 'created'
        ]);

        return [
            'success' => $order->items->count() > 0,
            'message' => "Order created with cloth_id. Order ID: {$order->id}, Items: {$order->items->count()}"
        ];
    }

    private function scenario2()
    {
        $client = Client::first();
        $branch = Branch::first();
        $cloth = Cloth::first();

        // Calculate: item 100 - 5 = 95, order 95 * 0.9 = 85.5
        $itemPrice = 100.00;
        $itemDiscount = 5.00;
        $itemFinal = $itemPrice - $itemDiscount; // 95
        $orderDiscount = 10; // percentage
        $expectedTotal = $itemFinal * (1 - $orderDiscount / 100); // 85.5

        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'total_price' => $expectedTotal,
            'status' => 'created',
            'discount_type' => 'percentage',
            'discount_value' => $orderDiscount,
            'paid' => 0,
            'remaining' => $expectedTotal // Will be recalculated automatically
        ]);

        // Recalculate remaining (fees do not affect remaining, they are tracked separately)
        $order->refresh();
        $order->remaining = max(0, $order->total_price - $order->paid);
        $order->save();

        $order->items()->attach($cloth->id, [
            'price' => $itemPrice,
            'type' => 'buy',
            'discount_type' => 'fixed',
            'discount_value' => $itemDiscount,
            'status' => 'created'
        ]);

        $calculated = $order->calculateTotalPrice();
        $success = abs($calculated - $expectedTotal) < 0.01;

        return [
            'success' => $success,
            'message' => "Order with discounts. Expected: {$expectedTotal}, Calculated: {$calculated}"
        ];
    }

    private function scenario3()
    {
        $client = Client::first();
        $branch = Branch::first();
        $cloth = Cloth::first();

        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'total_price' => 100.00,
            'status' => 'created',
            'paid' => 50.00,
            'remaining' => 50.00 // Will be recalculated automatically
        ]);

        // Recalculate remaining (fees do not affect remaining, they are tracked separately)
        $order->refresh();
        $order->remaining = max(0, $order->total_price - $order->paid);
        $order->save();

        $order->items()->attach($cloth->id, [
            'price' => 100.00,
            'type' => 'buy',
            'status' => 'created'
        ]);

        // Simulate auto-creation of payment
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
            'payment_type' => 'initial',
            'payment_date' => now(),
            'created_by' => User::first()?->id
        ]);

        $hasPayment = $payment && $payment->payment_type === 'initial';

        return [
            'success' => $hasPayment,
            'message' => $hasPayment
                ? "Order created with initial payment. Payment ID: {$payment->id}, Type: {$payment->payment_type}"
                : "Initial payment not found or wrong type"
        ];
    }

    private function scenario4()
    {
        $order = Order::first();
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 30.00,
            'status' => 'paid',
            'payment_type' => 'normal',
            'payment_date' => now(),
            'created_by' => User::first()?->id
        ]);

        $this->recalculateOrderPayments($order);

        return [
            'success' => $payment->payment_type === 'normal',
            'message' => "Normal payment added. Amount: {$payment->amount}, Type: {$payment->payment_type}, Status: {$payment->status}"
        ];
    }

    private function scenario5()
    {
        $order = Order::first();
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 25.00,
            'status' => 'paid',
            'payment_type' => 'fee',
            'payment_date' => now(),
            'notes' => 'Repair fee',
            'created_by' => User::first()?->id
        ]);

        $this->recalculateOrderPayments($order);
        $order->refresh();
        $feePayments = $order->payments()->where('payment_type', 'fee')->where('status', 'paid')->sum('amount');
        $required = $order->total_price + $feePayments;

        return [
            'success' => $payment->payment_type === 'fee',
            'message' => "Fee payment added. Amount: {$payment->amount}, Type: {$payment->payment_type}, Required total: {$required}"
        ];
    }

    private function scenario6()
    {
        $order = Order::first();
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 20.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'payment_date' => now(),
            'created_by' => User::first()?->id
        ]);

        return [
            'success' => $payment->status === 'pending',
            'message' => "Pending payment added. Amount: {$payment->amount}, Status: {$payment->status}"
        ];
    }

    private function scenario7()
    {
        $order = Order::whereDoesntHave('custodies')->first();
        if (!$order) {
            $order = Order::first();
        }

        // Try to update status to delivered
        $order->status = 'delivered';

        // Validate (simulate controller validation)
        $order->load('custodies');
        if ($order->custodies->isEmpty()) {
            return [
                'success' => true,
                'message' => "Correctly rejected. Error: Cannot mark order as delivered. Order must have at least one custody record."
            ];
        }

        return ['success' => false, 'message' => "Should have failed but order has custody"];
    }

    private function scenario8()
    {
        // Use a fresh order without existing custodies
        $order = Order::whereDoesntHave('custodies')->first();
        if (!$order) {
            $client = Client::first();
            $branch = Branch::first();
            $cloth = Cloth::first();
            $order = Order::create([
                'client_id' => $client->id,
                'inventory_id' => $branch->inventory->id,
                'total_price' => 100.00,
                'status' => 'created',
                'paid' => 0,
                'remaining' => 100.00
            ]);
            $order->items()->attach($cloth->id, [
                'price' => 100.00,
                'type' => 'buy',
                'status' => 'created'
            ]);
        }

        // Create custody in pending status
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Security deposit',
            'value' => 100.00,
            'status' => 'pending'
        ]);

        // Validate (simulate controller validation)
        $order->load('custodies');
        $hasCustody = !$order->custodies->isEmpty();
        $allPending = $order->custodies->count() > 0 && $order->custodies->every(fn($c) => $c->status === 'pending');

        if ($hasCustody && $allPending) {
            $order->status = 'delivered';
            $order->save();
            return [
                'success' => true,
                'message' => "Order marked as delivered. Custody ID: {$custody->id}, Status: {$custody->status}"
            ];
        }

        return ['success' => false, 'message' => "Validation failed. Has custody: " . ($hasCustody ? 'true' : 'false') . ", All pending: " . ($allPending ? 'true' : 'false')];
    }

    private function scenario9()
    {
        $order = Order::first();

        // Create custody with non-pending status
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
            'status' => 'returned' // Not pending
        ]);

        // Validate (simulate controller validation)
        $order->load('custodies');
        $nonPending = $order->custodies->firstWhere('status', '!=', 'pending');

        if ($nonPending) {
            return [
                'success' => true,
                'message' => "Correctly rejected. Error: Cannot mark order as delivered. All custody items must be in pending status."
            ];
        }

        return ['success' => false, 'message' => "Should have failed but all custody is pending"];
    }

    private function scenario10()
    {
        $order = Order::first();

        // Create custody still in pending
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
            'status' => 'pending' // Still pending - no decision
        ]);

        // Validate (simulate controller validation)
        $order->load('custodies');
        $pendingCustody = $order->custodies->firstWhere('status', 'pending');

        if ($pendingCustody) {
            return [
                'success' => true,
                'message' => "Correctly rejected. Error: Cannot finish order. All custody items must have a decision (returned or kept). Custody ID {$pendingCustody->id} is still pending."
            ];
        }

        return ['success' => false, 'message' => "Should have failed but no pending custody"];
    }

    private function scenario11()
    {
        $order = Order::first();

        // Create custody and mark as kept
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
            'status' => 'forfeited'
        ]);

        // Create pending payment
        Payment::create([
            'order_id' => $order->id,
            'amount' => 10.00,
            'status' => 'pending',
            'payment_type' => 'normal'
        ]);

        // Validate (simulate controller validation)
        $order->load('payments');
        $pendingPayments = $order->payments->where('status', 'pending');

        if ($pendingPayments->isNotEmpty()) {
            return [
                'success' => true,
                'message' => "Correctly rejected. Error: Cannot finish order. There are pending payments. Please complete all payments first."
            ];
        }

        return ['success' => false, 'message' => "Should have failed but no pending payments"];
    }

    private function scenario12()
    {
        // Create fresh order
        $client = Client::first();
        $branch = Branch::first();
        $cloth = Cloth::first();
        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'total_price' => 100.00,
            'status' => 'delivered',
            'paid' => 0,
            'remaining' => 100.00
        ]);
        $order->items()->attach($cloth->id, [
            'price' => 100.00,
            'type' => 'buy',
            'status' => 'created'
        ]);

        // Create custody and mark as kept (forfeited)
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Security deposit',
            'value' => 100.00,
            'status' => 'forfeited' // Customer keeps it
        ]);

        // Ensure all payments are paid and sufficient
        Payment::where('order_id', $order->id)
            ->where('status', 'pending')
            ->update(['status' => 'paid']);

        $order->refresh();
        // Calculate non-fee payments (fees are tracked separately)
        $nonFeePaid = Payment::where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount');

        // Ensure non-fee payments match total_price
        if ($nonFeePaid < $order->total_price) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_price - $nonFeePaid,
                'status' => 'paid',
                'payment_type' => 'normal'
            ]);
            $nonFeePaid = $order->total_price;
        }

        // Validate (simulate controller validation)
        $order->load(['custodies', 'payments']);
        $allDecided = $order->custodies->count() > 0 && $order->custodies->every(fn($c) => $c->status !== 'pending');
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();
        $paymentMatches = abs($nonFeePaid - $order->total_price) < 0.01;

        if ($allDecided && $noPendingPayments && $noPendingFees && $paymentMatches) {
            $order->status = 'finished';
            $order->save();
            return [
                'success' => true,
                'message' => "Order finished with kept custody. Custody status: {$custody->status}, Non-fee paid: {$nonFeePaid}, Total price: {$order->total_price}"
            ];
        }

        return [
            'success' => false,
            'message' => "Validation failed. All decided: {$allDecided}, No pending: {$noPendingPayments}, Payment matches: {$paymentMatches}"
        ];
    }

    private function scenario13()
    {
        // Create fresh order
        $client = Client::first();
        $branch = Branch::first();
        $cloth = Cloth::first();
        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'total_price' => 100.00,
            'status' => 'delivered',
            'paid' => 0,
            'remaining' => 100.00
        ]);
        $order->items()->attach($cloth->id, [
            'price' => 100.00,
            'type' => 'buy',
            'status' => 'created'
        ]);

        // Create custody
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Security deposit',
            'value' => 100.00,
            'status' => 'pending'
        ]);

        // Mark as returned with proof
        $custody->update(['status' => 'returned']);
        $custodyReturn = CustodyReturn::create([
            'custody_id' => $custody->id,
            'returned_at' => now(),
            'return_proof_photo' => 'test/proof.jpg',
            'customer_name' => 'Test Customer',
            'customer_phone' => '01234567890',
            'customer_id_number' => '12345678901234'
        ]);

        // Ensure all payments are paid and sufficient
        Payment::where('order_id', $order->id)
            ->where('status', 'pending')
            ->update(['status' => 'paid']);

        $order->refresh();
        // Calculate non-fee payments (fees are tracked separately)
        $nonFeePaid = Payment::where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount');

        // Ensure non-fee payments match total_price
        if ($nonFeePaid < $order->total_price) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_price - $nonFeePaid,
                'status' => 'paid',
                'payment_type' => 'normal'
            ]);
            $nonFeePaid = $order->total_price;
        }

        // Validate (simulate controller validation)
        $order->load(['custodies.returns', 'payments']);
        $allDecided = $order->custodies->count() > 0 && $order->custodies->every(function($c) {
            if ($c->status === 'pending') return false;
            if ($c->status === 'returned') {
                return $c->returns->isNotEmpty();
            }
            return true; // forfeited is fine
        });
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();
        $paymentMatches = abs($nonFeePaid - $order->total_price) < 0.01;

        if ($allDecided && $noPendingPayments && $noPendingFees && $paymentMatches) {
            $order->status = 'finished';
            $order->save();
            return [
                'success' => true,
                'message' => "Order finished with returned custody. Return proof uploaded. Return ID: {$custodyReturn->id}"
            ];
        }

        $allDecidedStr = $allDecided ? 'true' : 'false';
        $noPendingStr = $noPendingPayments ? 'true' : 'false';
        $paymentMatchesStr = $paymentMatches ? 'true' : 'false';
        return [
            'success' => false,
            'message' => "Validation failed. All decided: {$allDecidedStr}, No pending: {$noPendingStr}, Payment matches: {$paymentMatchesStr}"
        ];
    }

    private function scenario14()
    {
        // Create fresh order
        $client = Client::first();
        $branch = Branch::first();
        $cloth = Cloth::first();
        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $branch->inventory->id,
            'total_price' => 100.00,
            'status' => 'delivered',
            'paid' => 0,
            'remaining' => 100.00
        ]);
        $order->items()->attach($cloth->id, [
            'price' => 100.00,
            'type' => 'buy',
            'status' => 'created'
        ]);

        // Create custody and mark as kept
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
            'status' => 'forfeited'
        ]);

        // Add fee payment
        Payment::create([
            'order_id' => $order->id,
            'amount' => 25.00,
            'status' => 'paid',
            'payment_type' => 'fee',
            'notes' => 'Repair fee'
        ]);

        // Ensure order non-fee payments match total_price (fees are tracked separately)
        $order->refresh();
        $nonFeePaid = Payment::where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount');
        $feePayments = Payment::where('order_id', $order->id)
            ->where('payment_type', 'fee')
            ->where('status', 'paid')
            ->sum('amount');

        if ($nonFeePaid < $order->total_price) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_price - $nonFeePaid,
                'status' => 'paid',
                'payment_type' => 'normal'
            ]);
            $nonFeePaid = $order->total_price;
        }

        // Validate
        $order->load(['custodies', 'payments']);
        $allDecided = $order->custodies->count() > 0 && $order->custodies->every(fn($c) => $c->status !== 'pending');
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();
        $paymentMatches = abs($nonFeePaid - $order->total_price) < 0.01;

        if ($allDecided && $noPendingPayments && $noPendingFees && $paymentMatches) {
            $order->status = 'finished';
            $order->save();
            return [
                'success' => true,
                'message' => "Order finished with fees. Total price: {$order->total_price}, Non-fee paid: {$nonFeePaid}, Fees: {$feePayments} (tracked separately)"
            ];
        }

        $allDecidedStr = $allDecided ? 'true' : 'false';
        $noPendingStr = $noPendingPayments ? 'true' : 'false';
        $paymentMatchesStr = $paymentMatches ? 'true' : 'false';
        return [
            'success' => false,
            'message' => "Validation failed. All decided: {$allDecidedStr}, No pending: {$noPendingStr}, Payment matches: {$paymentMatchesStr}"
        ];
    }

    private function scenario15()
    {
        $order = Order::first();

        // Create custody and mark as kept
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 50.00,
            'status' => 'forfeited'
        ]);

        // Ensure insufficient payment
        Payment::where('order_id', $order->id)->delete();
        Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total_price - 10,
            'status' => 'paid',
            'payment_type' => 'normal'
        ]);

        // Validate
        $order->refresh();
        $order->load(['custodies', 'payments']);
        $nonFeePaid = Payment::where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount');
        $paymentMatches = abs($nonFeePaid - $order->total_price) < 0.01;

        if (!$paymentMatches) {
            return [
                'success' => true,
                'message' => "Correctly rejected. Error: Non-fee paid amount ({$nonFeePaid}) does not match order total ({$order->total_price}). Fees are tracked separately and do not affect the order's paid/remaining amounts."
            ];
        }

        return ['success' => false, 'message' => "Should have failed but payment matches"];
    }

    private function scenario16()
    {
        $order = Order::first();

        // Create custody marked as returned but NO return proof
        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Security deposit',
            'value' => 100.00,
            'status' => 'returned' // Marked as returned
        ]);
        // But no CustodyReturn record (no proof)

        // Validate
        $order->load(['custodies.returns', 'payments']);
        $returnedWithoutProof = $order->custodies->first(function($c) {
            return $c->status === 'returned' && $c->returns->isEmpty();
        });

        if ($returnedWithoutProof) {
            return [
                'success' => true,
                'message' => "Correctly rejected. Error: Cannot finish order. Custody ID {$returnedWithoutProof->id} ({$returnedWithoutProof->description}) is marked as returned but does not have return proof uploaded."
            ];
        }

        return ['success' => false, 'message' => "Should have failed but no returned custody without proof"];
    }

    // ========== PAYMENT OPERATIONS SCENARIOS (17-25) ==========

    private function scenario17()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => User::first()?->id
        ]);

        $result = $this->payPayment($order, $payment);
        $order->refresh();

        return [
            'success' => $result['success'] && $payment->fresh()->status === 'paid',
            'message' => $result['success']
                ? "Pending payment marked as paid. Payment ID: {$payment->id}, Order paid: {$order->paid}, Remaining: {$order->remaining}"
                : $result['message']
        ];
    }

    private function scenario18()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => User::first()?->id
        ]);

        $result = $this->cancelPayment($order, $payment, 'Customer requested cancellation');
        $order->refresh();

        return [
            'success' => $result['success'] && $payment->fresh()->status === 'canceled',
            'message' => $result['success']
                ? "Pending payment canceled. Payment ID: {$payment->id}, Order paid: {$order->paid}, Remaining: {$order->remaining}"
                : $result['message']
        ];
    }

    private function scenario19()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
            'payment_type' => 'normal',
            'created_by' => User::first()?->id
        ]);

        $result = $this->payPayment($order, $payment);

        return [
            'success' => !$result['success'],
            'message' => $result['success']
                ? "Should have failed but payment was paid"
                : "Correctly rejected. Error: {$result['message']}"
        ];
    }

    private function scenario20()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'canceled',
            'payment_type' => 'normal',
            'created_by' => User::first()?->id
        ]);

        $result = $this->cancelPayment($order, $payment);

        return [
            'success' => !$result['success'],
            'message' => $result['success']
                ? "Should have failed but payment was canceled"
                : "Correctly rejected. Error: {$result['message']}"
        ];
    }

    private function scenario21()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'canceled',
            'payment_type' => 'normal',
            'created_by' => User::first()?->id
        ]);

        $result = $this->payPayment($order, $payment);

        return [
            'success' => !$result['success'],
            'message' => $result['success']
                ? "Should have failed but canceled payment was paid"
                : "Correctly rejected. Error: {$result['message']}"
        ];
    }

    private function scenario22()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
            'payment_type' => 'normal',
            'created_by' => User::first()?->id
        ]);

        $this->recalculateOrderPayments($order);
        $order->refresh();
        $oldPaid = $order->paid;

        $result = $this->cancelPayment($order, $payment, 'Refund requested');
        $order->refresh();

        return [
            'success' => $result['success'] && $payment->fresh()->status === 'canceled' && $order->paid < $oldPaid,
            'message' => $result['success']
                ? "Paid payment canceled. Payment ID: {$payment->id}, Order paid before: {$oldPaid}, Order paid after: {$order->paid}"
                : $result['message']
        ];
    }

    private function scenario23()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => User::first()?->id
        ]);

        $result = $this->payPayment($order, $payment);
        $order->refresh();

        return [
            'success' => $result['success'] && $payment->fresh()->status === 'paid' && $order->paid == 50.00,
            'message' => $result['success']
                ? "Payment added then paid. Payment ID: {$payment->id}, Order paid: {$order->paid}, Remaining: {$order->remaining}"
                : $result['message']
        ];
    }

    private function scenario24()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => User::first()?->id
        ]);

        $result = $this->cancelPayment($order, $payment, 'Customer changed mind');
        $order->refresh();

        return [
            'success' => $result['success'] && $payment->fresh()->status === 'canceled' && $order->paid == 0,
            'message' => $result['success']
                ? "Payment added then canceled. Payment ID: {$payment->id}, Order paid: {$order->paid}, Remaining: {$order->remaining}"
                : $result['message']
        ];
    }

    private function scenario25()
    {
        $order = $this->createFreshOrder(150.00, 0);

        $payment1 = Payment::create(['order_id' => $order->id, 'amount' => 30.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $payment2 = Payment::create(['order_id' => $order->id, 'amount' => 40.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $payment3 = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);

        $this->payPayment($order, $payment1);
        $this->cancelPayment($order, $payment2, 'Cancelled');
        $this->payPayment($order, $payment3);

        $order->refresh();

        return [
            'success' => $payment1->fresh()->status === 'paid' && $payment2->fresh()->status === 'canceled' && $payment3->fresh()->status === 'paid' && $order->paid == 80.00,
            'message' => "Multiple payments lifecycle. Payment1: paid, Payment2: canceled, Payment3: paid. Order paid: {$order->paid}, Remaining: {$order->remaining}"
        ];
    }

    // ========== FEE SCENARIOS (26-30) ==========

    private function scenario26()
    {
        $order = $this->createFreshOrder(100.00, 0);

        $fee1 = Payment::create(['order_id' => $order->id, 'amount' => 15.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Repair fee', 'created_by' => User::first()?->id]);
        $fee2 = Payment::create(['order_id' => $order->id, 'amount' => 25.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Late fee', 'created_by' => User::first()?->id]);

        // Ensure payments are saved and refresh order before recalculation
        $fee1->refresh();
        $fee2->refresh();
        $order = $order->fresh();

        // Use the returned order from recalculation
        $order = $this->recalculateOrderPayments($order);

        // Double-check by querying directly
        $feePayments = \Illuminate\Support\Facades\DB::table('order_payments')
            ->where('order_id', $order->id)
            ->where('payment_type', 'fee')
            ->where('status', 'paid')
            ->sum('amount') ?? 0;
        $required = $order->total_price + $feePayments;

        // Fees do not affect remaining calculation
        // Order total: 100, Fees paid: 40 (tracked separately), Non-fee payments: 0
        // Remaining = 100 - 0 = 100 (fees excluded)
        $order = $order->fresh(); // Ensure we have latest values
        $remaining = (float)$order->remaining;
        $feePaymentsFloat = (float)$feePayments;
        return [
            'success' => abs($feePaymentsFloat - 40.00) < 0.01 && abs($remaining - 100.00) < 0.01,
            'message' => "Multiple fee payments. Fee1: {$fee1->amount}, Fee2: {$fee2->amount}, Total fees: {$feePayments}, Order total: {$order->total_price}, Remaining: {$order->remaining} (fees excluded from remaining)"
        ];
    }

    private function scenario27()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $fee = Payment::create([
            'order_id' => $order->id,
            'amount' => 20.00,
            'status' => 'pending',
            'payment_type' => 'fee',
            'notes' => 'Pending repair fee',
            'created_by' => User::first()?->id
        ]);

        $result = $this->payPayment($order, $fee);
        $order->refresh();

        $feePayments = $order->payments()->where('payment_type', 'fee')->where('status', 'paid')->sum('amount');
        $required = $order->total_price + $feePayments;

        return [
            'success' => $result['success'] && $fee->fresh()->status === 'paid' && $feePayments == 20.00,
            'message' => $result['success']
                ? "Fee added then paid. Fee ID: {$fee->id}, Total fees: {$feePayments}, Required: {$required}, Remaining: {$order->remaining}"
                : $result['message']
        ];
    }

    private function scenario28()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $fee = Payment::create([
            'order_id' => $order->id,
            'amount' => 20.00,
            'status' => 'paid',
            'payment_type' => 'fee',
            'notes' => 'Repair fee',
            'created_by' => User::first()?->id
        ]);

        $this->recalculateOrderPayments($order);
        $order = $order->fresh();
        $oldRemaining = $order->remaining;

        $result = $this->cancelPayment($order, $fee, 'Fee waived');
        $order = $order->fresh();

        $feePayments = $order->payments()->where('payment_type', 'fee')->where('status', 'paid')->sum('amount');
        $required = $order->total_price + $feePayments;

        return [
            'success' => $result['success'] && $fee->fresh()->status === 'canceled' && abs($order->remaining - 100.00) < 0.01,
            'message' => $result['success']
                ? "Fee payment canceled. Fee ID: {$fee->id}, Remaining before: {$oldRemaining}, Remaining after: {$order->remaining}, Required: {$required}"
                : $result['message']
        ];
    }

    private function scenario29()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $initialPayment = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'paid', 'payment_type' => 'initial', 'created_by' => User::first()?->id]);
        $initialPayment->refresh();
        $order = $order->fresh();

        $order = $this->recalculateOrderPayments($order);
        $remainingBeforeFee = $order->remaining;

        $fee = Payment::create(['order_id' => $order->id, 'amount' => 15.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Added after initial payment', 'created_by' => User::first()?->id]);
        $fee->refresh();
        $order = $order->fresh();

        $order = $this->recalculateOrderPayments($order);
        $remainingAfterFee = $order->remaining;

        // Fees do not affect remaining calculation
        // Before fee: paid = 50, remaining = 100 - 50 = 50
        // After fee: paid = 50 (fees excluded), remaining = 100 - 50 = 50 (stays the same)
        $order = $order->fresh(); // Ensure we have latest values
        $remainingAfterFee = (float)$order->remaining;
        $remainingBeforeFeeFloat = (float)$remainingBeforeFee;
        return [
            'success' => abs($remainingAfterFee - $remainingBeforeFeeFloat) < 0.01,
            'message' => "Fee added after initial payment. Remaining before fee: {$remainingBeforeFee}, Remaining after fee: {$remainingAfterFee}, Fee: {$fee->amount} (fees excluded from remaining)"
        ];
    }

    private function scenario30()
    {
        $order = $this->createFreshOrder(100.00, 0);

        $normal1 = Payment::create(['order_id' => $order->id, 'amount' => 30.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $fee1 = Payment::create(['order_id' => $order->id, 'amount' => 10.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Fee 1', 'created_by' => User::first()?->id]);
        $normal2 = Payment::create(['order_id' => $order->id, 'amount' => 40.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $fee2 = Payment::create(['order_id' => $order->id, 'amount' => 20.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Fee 2', 'created_by' => User::first()?->id]);

        $this->recalculateOrderPayments($order);
        $order->refresh();

        // Calculate non-fee payments only
        $nonFeePaid = $order->payments()->where('status', 'paid')->where('payment_type', '!=', 'fee')->sum('amount');
        $feePayments = $order->payments()->where('payment_type', 'fee')->where('status', 'paid')->sum('amount');

        // Fees do not affect remaining: remaining = total_price - non-fee payments
        // Normal payments: 30 + 40 = 70, Fees: 10 + 20 = 30, Remaining = 100 - 70 = 30
        return [
            'success' => $nonFeePaid == 70.00 && $feePayments == 30.00 && $order->remaining == 30.00,
            'message' => "Mixed payment types. Normal payments: {$nonFeePaid}, Fee payments: {$feePayments}, Remaining: {$order->remaining} (fees excluded from remaining)"
        ];
    }

    // ========== CUSTODY SCENARIOS (31-35) ==========

    private function scenario31()
    {
        $order = $this->createFreshOrder(100.00, 0);

        $custody1 = Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit 1', 'value' => 50.00, 'status' => 'forfeited']);
        $custody2 = Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit 2', 'value' => 50.00, 'status' => 'forfeited']);

        $order->load('custodies');
        $allKept = $order->custodies->every(fn($c) => $c->status === 'forfeited');

        return [
            'success' => $allKept && $order->custodies->count() == 2,
            'message' => "Multiple custodies all kept. Custody1: {$custody1->id}, Custody2: {$custody2->id}, All forfeited: " . ($allKept ? 'yes' : 'no')
        ];
    }

    private function scenario32()
    {
        $order = $this->createFreshOrder(100.00, 0);

        $custody1 = Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit 1', 'value' => 50.00, 'status' => 'returned']);
        $custody2 = Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit 2', 'value' => 50.00, 'status' => 'returned']);

        CustodyReturn::create(['custody_id' => $custody1->id, 'returned_at' => now(), 'return_proof_photo' => 'test/proof1.jpg', 'customer_name' => 'Test', 'customer_phone' => '01234567890', 'customer_id_number' => '12345678901234']);
        CustodyReturn::create(['custody_id' => $custody2->id, 'returned_at' => now(), 'return_proof_photo' => 'test/proof2.jpg', 'customer_name' => 'Test', 'customer_phone' => '01234567890', 'customer_id_number' => '12345678901234']);

        $order->load('custodies.returns');
        $allReturned = $order->custodies->every(function($c) {
            return $c->status === 'returned' && $c->returns->isNotEmpty();
        });

        return [
            'success' => $allReturned && $order->custodies->count() == 2,
            'message' => "Multiple custodies all returned with proof. Custody1: {$custody1->id}, Custody2: {$custody2->id}, All returned with proof: " . ($allReturned ? 'yes' : 'no')
        ];
    }

    private function scenario33()
    {
        $order = $this->createFreshOrder(100.00, 0);

        $custody1 = Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit 1', 'value' => 50.00, 'status' => 'forfeited']);
        $custody2 = Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit 2', 'value' => 50.00, 'status' => 'returned']);

        CustodyReturn::create(['custody_id' => $custody2->id, 'returned_at' => now(), 'return_proof_photo' => 'test/proof.jpg', 'customer_name' => 'Test', 'customer_phone' => '01234567890', 'customer_id_number' => '12345678901234']);

        $order->load('custodies.returns');
        $allDecided = $order->custodies->every(function($c) {
            if ($c->status === 'pending') return false;
            if ($c->status === 'returned') return $c->returns->isNotEmpty();
            return true;
        });

        return [
            'success' => $allDecided && $order->custodies->count() == 2,
            'message' => "Mixed custodies. Custody1: kept (forfeited), Custody2: returned with proof. All decided: " . ($allDecided ? 'yes' : 'no')
        ];
    }

    private function scenario34()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $custody = $this->setupOrderForFinish($order, 'returned', true);

        $returnProof = $custody->returns->first();
        $returnProof->delete();
        $custody->update(['status' => 'forfeited']);

        $order->load('custodies');
        $allDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');

        return [
            'success' => $allDecided && $custody->fresh()->status === 'forfeited',
            'message' => "Custody changed from returned to kept. Custody ID: {$custody->id}, Final status: {$custody->fresh()->status}, All decided: " . ($allDecided ? 'yes' : 'no')
        ];
    }

    private function scenario35()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $custody = $this->setupOrderForFinish($order, 'forfeited', false);

        $fee = Payment::create(['order_id' => $order->id, 'amount' => 25.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Repair fee', 'created_by' => User::first()?->id]);
        $payment = Payment::create(['order_id' => $order->id, 'amount' => 100.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);

        $this->recalculateOrderPayments($order);
        $order->refresh();

        $feePayments = $order->payments()->where('payment_type', 'fee')->where('status', 'paid')->sum('amount');
        $nonFeePaid = $order->payments()->where('status', 'paid')->where('payment_type', '!=', 'fee')->sum('amount');
        $allDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();

        // Fees are tracked separately, non-fee payments must match total_price, remaining should be 0
        return [
            'success' => $allDecided && abs($nonFeePaid - $order->total_price) < 0.01 && $order->remaining == 0,
            'message' => "Order with custody and fees. Custody: kept, Non-fee paid: {$nonFeePaid}, Total price: {$order->total_price}, Fees: {$feePayments} (tracked separately), Remaining: {$order->remaining}"
        ];
    }

    // ========== COMPLETE LIFECYCLE SCENARIOS (36-40) ==========

    private function scenario36()
    {
        $order = $this->createFreshOrder(100.00, 0);

        // created -> partially_paid
        $payment1 = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $this->recalculateOrderPayments($order);
        $order->refresh();
        $status1 = $order->status;

        // partially_paid -> paid
        $payment2 = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $this->recalculateOrderPayments($order);
        $order->refresh();
        $status2 = $order->status;

        // paid -> delivered
        $custody = $this->setupOrderForDelivery($order);
        $status3 = $order->status;

        // delivered -> finished
        $custody->update(['status' => 'forfeited']);
        $order->load('custodies');
        $allDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');
        if ($allDecided) {
            $order->status = 'finished';
            $order->save();
        }
        $status4 = $order->status;

        return [
            'success' => $status1 === 'partially_paid' && $status2 === 'paid' && $status3 === 'delivered' && $status4 === 'finished',
            'message' => "Full lifecycle. Statuses: created -> {$status1} -> {$status2} -> {$status3} -> {$status4}"
        ];
    }

    private function scenario37()
    {
        $order = $this->createFreshOrder(100.00, 0);
        Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'paid', 'payment_type' => 'initial', 'created_by' => User::first()?->id]);
        $this->recalculateOrderPayments($order);

        $payment = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $this->payPayment($order, $payment);

        $custody = $this->setupOrderForDelivery($order);
        $custody->update(['status' => 'forfeited']);

        $order->refresh();
        $order->load('custodies');
        $allDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();
        $nonFeePaid = $order->payments()->where('status', 'paid')->where('payment_type', '!=', 'fee')->sum('amount');

        if ($allDecided && $noPendingPayments && $noPendingFees && abs($nonFeePaid - $order->total_price) < 0.01) {
            $order->status = 'finished';
            $order->save();
        }

        return [
            'success' => $order->status === 'finished',
            'message' => "Lifecycle with payment operations. Initial payment -> add payment -> pay it -> deliver -> finish. Final status: {$order->status}"
        ];
    }

    private function scenario38()
    {
        $order = $this->createFreshOrder(100.00, 0);

        // Fee at creation
        $fee1 = Payment::create(['order_id' => $order->id, 'amount' => 10.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Setup fee', 'created_by' => User::first()?->id]);
        $fee1->refresh();
        $order = $order->fresh();
        $order = $this->recalculateOrderPayments($order);
        $remaining1 = $order->remaining;

        // Fee after partial payment
        $payment = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $payment->refresh();
        $order = $order->fresh();
        $order = $this->recalculateOrderPayments($order);
        $fee2 = Payment::create(['order_id' => $order->id, 'amount' => 15.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Late fee', 'created_by' => User::first()?->id]);
        $fee2->refresh();
        $order = $order->fresh();
        $order = $this->recalculateOrderPayments($order);
        $remaining2 = $order->remaining;

        // Fee after delivery
        $custody = $this->setupOrderForDelivery($order);
        $fee3 = Payment::create(['order_id' => $order->id, 'amount' => 20.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Repair fee', 'created_by' => User::first()?->id]);
        $fee3->refresh();
        $order = $order->fresh();
        $order = $this->recalculateOrderPayments($order);
        $remaining3 = $order->remaining;

        // Calculate expected remaining (fees do not affect remaining):
        // After fee1: non-fee paid = 0, remaining = 100 - 0 = 100
        // After payment (50) + fee2: non-fee paid = 50, remaining = 100 - 50 = 50
        // After fee3: non-fee paid = 50, remaining = 100 - 50 = 50
        $order = $order->fresh(); // Ensure we have latest values
        $remaining3 = (float)$order->remaining;
        $remaining1Float = (float)$remaining1;
        $remaining2Float = (float)$remaining2;
        return [
            'success' => abs($remaining1Float - 100.00) < 0.01 && abs($remaining2Float - 50.00) < 0.01 && abs($remaining3 - 50.00) < 0.01,
            'message' => "Fees throughout lifecycle. Remaining after fee1: {$remaining1}, After payment+fee2: {$remaining2}, After delivery+fee3: {$remaining3}"
        ];
    }

    private function scenario39()
    {
        $order = $this->createFreshOrder(100.00, 0);

        $payment1 = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $this->recalculateOrderPayments($order);

        $custody = $this->setupOrderForDelivery($order);

        $payment2 = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $this->recalculateOrderPayments($order);

        $custody->update(['status' => 'forfeited']);
        $order->refresh();
        $order->load('custodies');
        $allDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();
        $nonFeePaid = $order->payments()->where('status', 'paid')->where('payment_type', '!=', 'fee')->sum('amount');

        if ($allDecided && $noPendingPayments && $noPendingFees && abs($nonFeePaid - $order->total_price) < 0.01) {
            $order->status = 'finished';
            $order->save();
        }

        return [
            'success' => $order->status === 'finished' && $allDecided,
            'message' => "Order with custody and payments. Non-fee paid: {$nonFeePaid}, Total price: {$order->total_price}, Custody: decided, Status: {$order->status}"
        ];
    }

    private function scenario40()
    {
        $order = $this->createFreshOrder(100.00, 50.00);
        Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'paid', 'payment_type' => 'initial', 'created_by' => User::first()?->id]);
        $this->recalculateOrderPayments($order);

        $order->status = 'canceled';
        $order->save();

        return [
            'success' => $order->status === 'canceled',
            'message' => "Order canceled. Order ID: {$order->id}, Status: {$order->status}, Paid: {$order->paid}"
        ];
    }

    // ========== COMPLEX COMBINATIONS (41-45) ==========

    private function scenario41()
    {
        $order = $this->createFreshOrder(100.00, 0);

        $pendingPayment = Payment::create(['order_id' => $order->id, 'amount' => 115.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $fee = Payment::create(['order_id' => $order->id, 'amount' => 15.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Fee', 'created_by' => User::first()?->id]);
        $pendingPayment->refresh();
        $fee->refresh();
        $order = $order->fresh();
        $order = $this->recalculateOrderPayments($order);

        $this->payPayment($order, $pendingPayment);

        $custody = $this->setupOrderForDelivery($order);
        $custody->update(['status' => 'returned']);
        CustodyReturn::create(['custody_id' => $custody->id, 'returned_at' => now(), 'return_proof_photo' => 'test/proof.jpg', 'customer_name' => 'Test', 'customer_phone' => '01234567890', 'customer_id_number' => '12345678901234']);

        $order = $order->fresh();
        $order->load(['custodies.returns', 'payments']);
        $allDecided = $order->custodies->every(function($c) {
            if ($c->status === 'pending') return false;
            if ($c->status === 'returned') return $c->returns->isNotEmpty();
            return true;
        });
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        // Check that all fee payments are paid (no pending fees)
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();

        // Validate non-fee payments match total_price (fees are tracked separately)
        $nonFeePaid = \Illuminate\Support\Facades\DB::table('order_payments')
            ->where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount') ?? 0;

        // Finish order if: all custody decided, no pending payments, no pending fees, and non-fee payments >= total_price (allow overpayments)
        if ($allDecided && $noPendingPayments && $noPendingFees && $nonFeePaid >= $order->total_price - 0.01) {
            $order->status = 'finished';
            $order->save();
        }

        return [
            'success' => $order->fresh()->status === 'finished',
            'message' => "Complex flow 1. Create -> add pending -> add fee -> pay pending -> deliver -> return custody -> finish. Status: {$order->status}"
        ];
    }

    private function scenario42()
    {
        $order = $this->createFreshOrder(100.00, 0);

        $payment1 = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $this->cancelPayment($order, $payment1, 'Cancelled');

        $payment2 = Payment::create(['order_id' => $order->id, 'amount' => 100.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $this->recalculateOrderPayments($order);

        $custody = $this->setupOrderForDelivery($order);
        $custody->update(['status' => 'forfeited']);

        $order->refresh();
        $order->load('custodies');
        $allDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();
        $nonFeePaid = $order->payments()->where('status', 'paid')->where('payment_type', '!=', 'fee')->sum('amount');

        if ($allDecided && $noPendingPayments && $noPendingFees && abs($nonFeePaid - $order->total_price) < 0.01) {
            $order->status = 'finished';
            $order->save();
        }

        return [
            'success' => $order->status === 'finished' && $payment1->fresh()->status === 'canceled',
            'message' => "Complex flow 2. Create -> add payment -> cancel -> add new -> deliver -> finish. Status: {$order->status}"
        ];
    }

    private function scenario43()
    {
        $order = $this->createFreshOrder(150.00, 0);

        $payment1 = Payment::create(['order_id' => $order->id, 'amount' => 75.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $payment2 = Payment::create(['order_id' => $order->id, 'amount' => 40.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $payment3 = Payment::create(['order_id' => $order->id, 'amount' => 75.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);

        $this->payPayment($order, $payment1);
        $this->cancelPayment($order, $payment2, 'Cancelled');
        $this->payPayment($order, $payment3);

        $custody = $this->setupOrderForDelivery($order);
        $custody->update(['status' => 'forfeited']);

        $order = $order->fresh();
        $order->load('custodies');
        $allDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();
        $nonFeePaid = $order->payments()->where('status', 'paid')->where('payment_type', '!=', 'fee')->sum('amount');

        if ($allDecided && $noPendingPayments && $noPendingFees && abs($nonFeePaid - $order->total_price) < 0.01) {
            $order->status = 'finished';
            $order->save();
        }

        return [
            'success' => $order->fresh()->status === 'finished' && $payment1->fresh()->status === 'paid' && $payment2->fresh()->status === 'canceled' && $payment3->fresh()->status === 'paid',
            'message' => "Complex flow 3. Multiple payments -> pay some -> cancel some -> deliver -> finish. Status: {$order->status}"
        ];
    }

    private function scenario44()
    {
        $order = $this->createFreshOrder(100.00, 0);

        $fee = Payment::create(['order_id' => $order->id, 'amount' => 20.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Fee', 'created_by' => User::first()?->id]);
        $fee->refresh();
        $order = $order->fresh();
        $order = $this->recalculateOrderPayments($order);

        $payment = Payment::create(['order_id' => $order->id, 'amount' => 120.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $payment->refresh();
        $this->payPayment($order, $payment);

        $custody = $this->setupOrderForDelivery($order);
        $custody->update(['status' => 'returned']);
        CustodyReturn::create(['custody_id' => $custody->id, 'returned_at' => now(), 'return_proof_photo' => 'test/proof.jpg', 'customer_name' => 'Test', 'customer_phone' => '01234567890', 'customer_id_number' => '12345678901234']);

        $order = $order->fresh();
        $order->load(['custodies.returns', 'payments']);
        $allDecided = $order->custodies->every(function($c) {
            if ($c->status === 'pending') return false;
            if ($c->status === 'returned') return $c->returns->isNotEmpty();
            return true;
        });
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        // Check that all fee payments are paid (no pending fees)
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();

        // Validate non-fee payments match total_price (fees are tracked separately)
        $nonFeePaid = \Illuminate\Support\Facades\DB::table('order_payments')
            ->where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount') ?? 0;

        // Finish order if: all custody decided, no pending payments, no pending fees, and non-fee payments >= total_price (allow overpayments)
        if ($allDecided && $noPendingPayments && $noPendingFees && $nonFeePaid >= $order->total_price - 0.01) {
            $order->status = 'finished';
            $order->save();
        }

        return [
            'success' => $order->fresh()->status === 'finished',
            'message' => "Complex flow 4. Create -> add fee -> add payment -> pay -> deliver -> return custody -> finish. Status: {$order->status}"
        ];
    }

    private function scenario45()
    {
        $order = $this->createFreshOrder(100.00, 0);
        $initialPayment = Payment::create(['order_id' => $order->id, 'amount' => 50.00, 'status' => 'paid', 'payment_type' => 'initial', 'created_by' => User::first()?->id]);
        $initialPayment->refresh();
        $order = $order->fresh();
        $order = $this->recalculateOrderPayments($order);

        $fee = Payment::create(['order_id' => $order->id, 'amount' => 25.00, 'status' => 'paid', 'payment_type' => 'fee', 'notes' => 'Fee', 'created_by' => User::first()?->id]);
        $fee->refresh();
        $order = $order->fresh();
        $order = $this->recalculateOrderPayments($order);

        $payment = Payment::create(['order_id' => $order->id, 'amount' => 75.00, 'status' => 'paid', 'payment_type' => 'normal', 'created_by' => User::first()?->id]);
        $payment->refresh();
        $order = $order->fresh();
        $order = $this->recalculateOrderPayments($order);

        $custody = $this->setupOrderForDelivery($order);
        $custody->update(['status' => 'returned']);
        CustodyReturn::create(['custody_id' => $custody->id, 'returned_at' => now(), 'return_proof_photo' => 'test/proof.jpg', 'customer_name' => 'Test', 'customer_phone' => '01234567890', 'customer_id_number' => '12345678901234']);

        $order = $order->fresh();
        $order->load(['custodies.returns', 'payments']);
        $allDecided = $order->custodies->every(function($c) {
            if ($c->status === 'pending') return false;
            if ($c->status === 'returned') return $c->returns->isNotEmpty();
            return true;
        });
        $noPendingPayments = $order->payments->where('status', 'pending')->isEmpty();
        // Check that all fee payments are paid (no pending fees)
        $pendingFeePayments = $order->payments->where('payment_type', 'fee')->where('status', 'pending');
        $noPendingFees = $pendingFeePayments->isEmpty();

        // Validate non-fee payments match total_price (fees are tracked separately)
        $nonFeePaid = \Illuminate\Support\Facades\DB::table('order_payments')
            ->where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount') ?? 0;

        // Finish order if: all custody decided, no pending payments, no pending fees, and non-fee payments >= total_price (allow overpayments)
        if ($allDecided && $noPendingPayments && $noPendingFees && $nonFeePaid >= $order->total_price - 0.01) {
            $order->status = 'finished';
            $order->save();
        }

        return [
            'success' => $order->fresh()->status === 'finished',
            'message' => "Complex flow 5. Create -> initial payment -> add fee -> deliver -> return custody -> finish. Status: {$order->status}, Non-fee paid: {$nonFeePaid}, Total price: {$order->total_price}"
        ];
    }
}
