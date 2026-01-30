<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Cloth;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Custody;
use App\Models\CustodyReturn;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

class OrderCustodyPaymentScenariosTest extends BaseTestCase
{
    use RefreshDatabase;

    protected $client;
    protected $branch;
    protected $inventory;
    protected $cloth;
    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations and seed
        Artisan::call('migrate:fresh');
        Artisan::call('db:seed', ['--class' => 'FillAllModelsSeeder']);

        // Get test data
        $this->client = Client::first();
        $this->branch = Branch::first();
        $this->inventory = $this->branch->inventory;
        $this->cloth = Cloth::first();
        $this->user = User::first();

        // Create auth token
        $response = $this->postJson('/api/v1/login', [
            'email' => $this->user->email,
            'password' => 'password123'
        ]);

        if ($response->status() === 200) {
            $this->token = $response->json('token');
        }
    }

    protected function getAuthHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ];
    }

    /**
     * Debug helper: Print comprehensive order state
     */
    private function debugPrintOrderState($order, $label = 'Order State')
    {
        $order->refresh();
        $order->load(['payments', 'custodies.returns']);

        echo "\n--- {$label} ---\n";
        echo "Order ID: {$order->id}\n";
        echo "Total Price: " . number_format($order->total_price, 2) . "\n";
        echo "Paid: " . number_format($order->paid, 2) . "\n";
        echo "Remaining: " . number_format($order->remaining, 2) . "\n";
        echo "Status: {$order->status}\n";
        echo "Payments Count: " . $order->payments->count() . "\n";

        // Fee calculations
        $feePayments = $order->payments->where('payment_type', 'fee');
        $paidFees = $feePayments->where('status', 'paid')->sum('amount');
        $pendingFees = $feePayments->where('status', 'pending')->sum('amount');
        $totalFees = $feePayments->sum('amount');
        echo "Fees - Total: " . number_format($totalFees, 2) . ", Paid: " . number_format($paidFees, 2) . ", Pending: " . number_format($pendingFees, 2) . "\n";

        // Custody count
        echo "Custody Items: " . $order->custodies->count() . "\n";
    }

    /**
     * Debug helper: Print detailed payment breakdown
     */
    private function debugPrintPaymentBreakdown($order)
    {
        $order->refresh();
        $order->load('payments');

        echo "\n--- Payment Breakdown ---\n";
        $payments = $order->payments;

        if ($payments->isEmpty()) {
            echo "No payments found\n";
            return;
        }

        $index = 1;
        foreach ($payments as $payment) {
            echo "Payment #{$index}: ID={$payment->id}, Type={$payment->payment_type}, Status={$payment->status}, Amount=" . number_format($payment->amount, 2);
            if ($payment->notes) {
                echo ", Notes={$payment->notes}";
            }
            echo "\n";
            $index++;
        }

        // Totals by type
        $initialPaid = $payments->where('payment_type', 'initial')->where('status', 'paid')->sum('amount');
        $normalPaid = $payments->where('payment_type', 'normal')->where('status', 'paid')->sum('amount');
        $feePaid = $payments->where('payment_type', 'fee')->where('status', 'paid')->sum('amount');
        $totalPaid = $payments->where('status', 'paid')->sum('amount');

        $initialPending = $payments->where('payment_type', 'initial')->where('status', 'pending')->sum('amount');
        $normalPending = $payments->where('payment_type', 'normal')->where('status', 'pending')->sum('amount');
        $feePending = $payments->where('payment_type', 'fee')->where('status', 'pending')->sum('amount');
        $totalPending = $payments->where('status', 'pending')->sum('amount');

        echo "\nTotals by Type (Paid):\n";
        echo "  Initial: " . number_format($initialPaid, 2) . "\n";
        echo "  Normal: " . number_format($normalPaid, 2) . "\n";
        echo "  Fee: " . number_format($feePaid, 2) . "\n";
        echo "  Total Paid: " . number_format($totalPaid, 2) . "\n";

        echo "\nTotals by Type (Pending):\n";
        echo "  Initial: " . number_format($initialPending, 2) . "\n";
        echo "  Normal: " . number_format($normalPending, 2) . "\n";
        echo "  Fee: " . number_format($feePending, 2) . "\n";
        echo "  Total Pending: " . number_format($totalPending, 2) . "\n";

        // Calculation verification
        $nonFeePaid = $payments->where('status', 'paid')->where('payment_type', '!=', 'fee')->sum('amount');
        echo "\nCalculation Verification:\n";
        echo "  Non-fee Paid: " . number_format($nonFeePaid, 2) . "\n";
        echo "  Order Paid Field: " . number_format($order->paid, 2) . "\n";
        echo "  Match: " . (abs($nonFeePaid - $order->paid) < 0.01 ? "✓" : "✗ (Difference: " . number_format(abs($nonFeePaid - $order->paid), 2) . ")") . "\n";
    }

    /**
     * Debug helper: Print custody status
     */
    private function debugPrintCustodyStatus($order)
    {
        $order->refresh();
        $order->load(['custodies.returns']);

        echo "\n--- Custody Status ---\n";
        $custodies = $order->custodies;

        if ($custodies->isEmpty()) {
            echo "No custody items found\n";
            return;
        }

        foreach ($custodies as $custody) {
            echo "Custody #{$custody->id}: Type={$custody->type}, Description={$custody->description}, Value=" . number_format($custody->value, 2) . ", Status={$custody->status}\n";

            if ($custody->status === 'returned') {
                $returns = $custody->returns;
                if ($returns->isEmpty()) {
                    echo "  ⚠ WARNING: Marked as returned but NO return proof found!\n";
                } else {
                    echo "  Return Proofs: " . $returns->count() . "\n";
                    foreach ($returns as $return) {
                        echo "    - Returned at: {$return->returned_at}, Proof: " . ($return->return_proof_photo ?: 'N/A') . "\n";
                    }
                }
            }
        }

        // Validation summary
        $pendingCustody = $custodies->where('status', 'pending');
        $returnedCustody = $custodies->where('status', 'returned');
        $forfeitedCustody = $custodies->where('status', 'forfeited');

        echo "\nCustody Summary:\n";
        echo "  Pending: " . $pendingCustody->count() . "\n";
        echo "  Returned: " . $returnedCustody->count() . "\n";
        echo "  Forfeited (Kept): " . $forfeitedCustody->count() . "\n";

        // Check for returned without proof
        $returnedWithoutProof = $returnedCustody->filter(function($c) {
            return $c->returns->isEmpty();
        });

        if ($returnedWithoutProof->isNotEmpty()) {
            echo "  ⚠ WARNING: " . $returnedWithoutProof->count() . " returned custody items without proof!\n";
        }
    }

    /**
     * Debug helper: Print calculation details
     */
    private function debugPrintCalculationDetails($order, $operation, $beforeValues = [], $afterValues = [])
    {
        $order->refresh();
        $order->load('payments');

        echo "\n--- Calculation Details: {$operation} ---\n";

        if (!empty($beforeValues)) {
            echo "Before:\n";
            foreach ($beforeValues as $key => $value) {
                echo "  {$key}: " . (is_numeric($value) ? number_format($value, 2) : $value) . "\n";
            }
        }

        // Current payment calculations
        $totalPaid = $order->payments->where('status', 'paid')->sum('amount');
        $nonFeePaid = $order->payments->where('status', 'paid')->where('payment_type', '!=', 'fee')->sum('amount');
        $feePaid = $order->payments->where('payment_type', 'fee')->where('status', 'paid')->sum('amount');
        $feePending = $order->payments->where('payment_type', 'fee')->where('status', 'pending')->sum('amount');
        $requiredAmount = $order->total_price + $feePaid;

        echo "\nCurrent Calculations:\n";
        echo "  Total Price: " . number_format($order->total_price, 2) . "\n";
        echo "  Fees Paid: " . number_format($feePaid, 2) . "\n";
        echo "  Fees Pending: " . number_format($feePending, 2) . "\n";
        echo "  Required Amount (total_price + fees_paid): " . number_format($requiredAmount, 2) . "\n";
        echo "  Total Paid (all payments): " . number_format($totalPaid, 2) . "\n";
        echo "  Non-fee Paid: " . number_format($nonFeePaid, 2) . "\n";
        echo "  Order Paid Field: " . number_format($order->paid, 2) . "\n";
        echo "  Order Remaining: " . number_format($order->remaining, 2) . "\n";
        echo "  Formula: remaining = max(0, total_price - non_fee_paid)\n";
        echo "  Calculated: remaining = max(0, " . number_format($order->total_price, 2) . " - " . number_format($nonFeePaid, 2) . ") = " . number_format(max(0, $order->total_price - $nonFeePaid), 2) . "\n";
        echo "  Match: " . (abs($order->remaining - max(0, $order->total_price - $nonFeePaid)) < 0.01 ? "✓" : "✗") . "\n";

        if (!empty($afterValues)) {
            echo "\nAfter:\n";
            foreach ($afterValues as $key => $value) {
                echo "  {$key}: " . (is_numeric($value) ? number_format($value, 2) : $value) . "\n";
            }
        }
    }

    public function testAllScenarios()
    {
        $results = [];

        echo "\n=== ORDER, CUSTODY & PAYMENT SCENARIOS TEST ===\n\n";

        // SCENARIO 1: Create order with cloth_id (should succeed)
        echo "SCENARIO 1: Create order with cloth_id\n";
        $result1 = $this->testCreateOrderWithClothId();
        $results[] = $result1;
        echo "Result: " . ($result1['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result1['message'] . "\n\n";

        // SCENARIO 2: Create order with discounts (item-level and order-level)
        echo "SCENARIO 2: Create order with item and order discounts\n";
        $result2 = $this->testCreateOrderWithDiscounts();
        $results[] = $result2;
        echo "Result: " . ($result2['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result2['message'] . "\n\n";

        // SCENARIO 3: Create order with initial payment (auto-creates payment record)
        echo "SCENARIO 3: Create order with initial payment\n";
        $result3 = $this->testCreateOrderWithInitialPayment();
        $results[] = $result3;
        echo "Result: " . ($result3['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result3['message'] . "\n\n";

        // SCENARIO 4: Add normal payment to order
        echo "SCENARIO 4: Add normal payment to order\n";
        $result4 = $this->testAddNormalPayment();
        $results[] = $result4;
        echo "Result: " . ($result4['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result4['message'] . "\n\n";

        // SCENARIO 5: Add fee payment to order
        echo "SCENARIO 5: Add fee payment to order\n";
        $result5 = $this->testAddFeePayment();
        $results[] = $result5;
        echo "Result: " . ($result5['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result5['message'] . "\n\n";

        // SCENARIO 6: Add pending payment
        echo "SCENARIO 6: Add pending payment\n";
        $result6 = $this->testAddPendingPayment();
        $results[] = $result6;
        echo "Result: " . ($result6['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result6['message'] . "\n\n";

        // SCENARIO 7: Try to mark order as delivered without custody (should fail)
        echo "SCENARIO 7: Try to mark order as delivered without custody\n";
        $result7 = $this->testDeliveredWithoutCustody();
        $results[] = $result7;
        echo "Result: " . ($result7['success'] ? "✓ SUCCESS (correctly failed)" : "✗ FAILED") . "\n";
        echo "Details: " . $result7['message'] . "\n\n";

        // SCENARIO 8: Create custody and mark as delivered (should succeed)
        echo "SCENARIO 8: Create custody and mark as delivered\n";
        $result8 = $this->testDeliveredWithCustody();
        $results[] = $result8;
        echo "Result: " . ($result8['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result8['message'] . "\n\n";

        // SCENARIO 9: Try to finish order without custody decision (should fail)
        echo "SCENARIO 9: Try to finish order without custody decision\n";
        $result9 = $this->testFinishedWithoutCustodyDecision();
        $results[] = $result9;
        echo "Result: " . ($result9['success'] ? "✓ SUCCESS (correctly failed)" : "✗ FAILED") . "\n";
        echo "Details: " . $result9['message'] . "\n\n";

        // SCENARIO 10: Try to finish order with pending payments (should fail)
        echo "SCENARIO 10: Try to finish order with pending payments\n";
        $result10 = $this->testFinishedWithPendingPayments();
        $results[] = $result10;
        echo "Result: " . ($result10['success'] ? "✓ SUCCESS (correctly failed)" : "✗ FAILED") . "\n";
        echo "Details: " . $result10['message'] . "\n\n";

        // SCENARIO 11: Finish order with kept custody (should succeed)
        echo "SCENARIO 11: Finish order with kept custody\n";
        $result11 = $this->testFinishedWithKeptCustody();
        $results[] = $result11;
        echo "Result: " . ($result11['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result11['message'] . "\n\n";

        // SCENARIO 12: Finish order with returned custody (should succeed)
        echo "SCENARIO 12: Finish order with returned custody\n";
        $result12 = $this->testFinishedWithReturnedCustody();
        $results[] = $result12;
        echo "Result: " . ($result12['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result12['message'] . "\n\n";

        // SCENARIO 13: Finish order with fees (should succeed)
        echo "SCENARIO 13: Finish order with fees\n";
        $result13 = $this->testFinishedWithFees();
        $results[] = $result13;
        echo "Result: " . ($result13['success'] ? "✓ SUCCESS" : "✗ FAILED") . "\n";
        echo "Details: " . $result13['message'] . "\n\n";

        // SCENARIO 14: Try to finish order with insufficient payment (should fail)
        echo "SCENARIO 14: Try to finish order with insufficient payment\n";
        $result14 = $this->testFinishedWithInsufficientPayment();
        $results[] = $result14;
        echo "Result: " . ($result14['success'] ? "✓ SUCCESS (correctly failed)" : "✗ FAILED") . "\n";
        echo "Details: " . $result14['message'] . "\n\n";

        // Summary
        echo "\n=== TEST SUMMARY ===\n";
        $passed = collect($results)->where('success', true)->count();
        $failed = collect($results)->where('success', false)->count();
        echo "Total Scenarios: " . count($results) . "\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";

        return $results;
    }

    private function testCreateOrderWithClothId()
    {
        try {
            echo "\n=== SCENARIO 1: Create order with cloth_id ===\n";
            echo "Description: Create a basic order with a cloth item\n\n";

            echo "--- Action: Creating Order ---\n";
            echo "Client ID: {$this->client->id}\n";
            echo "Entity Type: branch, Entity ID: {$this->branch->id}\n";
            echo "Item: Cloth ID {$this->cloth->id}, Price: 100.00, Type: buy\n";

            $response = $this->postJson('/api/v1/orders', [
                'client_id' => $this->client->id,
                'entity_type' => 'branch',
                'entity_id' => $this->branch->id,
                'status' => 'created',
                'items' => [
                    [
                        'cloth_id' => $this->cloth->id,
                        'price' => 100.00,
                        'type' => 'buy',
                        'status' => 'created'
                    ]
                ]
            ], $this->getAuthHeaders());

            if ($response->status() === 201) {
                $order = Order::find($response->json('id'));
                $this->debugPrintOrderState($order, 'After Order Creation');
                $this->debugPrintPaymentBreakdown($order);

                echo "\n--- Validation ---\n";
                echo "Expected: Order created with total_price = 100.00\n";
                echo "Actual Total Price: " . number_format($order->total_price, 2) . "\n";
                echo "Match: " . (abs($order->total_price - 100.00) < 0.01 ? "✓" : "✗") . "\n";

                return [
                    'success' => true,
                    'message' => "Order created successfully. ID: {$order->id}, Total: {$order->total_price}"
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testCreateOrderWithDiscounts()
    {
        try {
            echo "\n=== SCENARIO 2: Create order with item and order discounts ===\n";
            echo "Description: Create order with item-level fixed discount and order-level percentage discount\n\n";

            echo "--- Action: Creating Order with Discounts ---\n";
            echo "Item Discount: fixed, 5.00\n";
            echo "Order Discount: percentage, 10%\n";
            echo "Calculation: (100 - 5) * 0.9 = 85.5\n";

            $response = $this->postJson('/api/v1/orders', [
                'client_id' => $this->client->id,
                'entity_type' => 'branch',
                'entity_id' => $this->branch->id,
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'items' => [
                    [
                        'cloth_id' => $this->cloth->id,
                        'price' => 100.00,
                        'type' => 'buy',
                        'discount_type' => 'fixed',
                        'discount_value' => 5.00
                    ]
                ]
            ], $this->getAuthHeaders());

            if ($response->status() === 201) {
                $order = Order::find($response->json('id'));
                $this->debugPrintOrderState($order, 'After Order Creation');
                $this->debugPrintPaymentBreakdown($order);

                // Item: 100 - 5 = 95, Order: 95 * 0.9 = 85.5
                $expected = 85.5;
                $actual = $order->total_price;

                echo "\n--- Validation ---\n";
                echo "Expected Total Price: " . number_format($expected, 2) . "\n";
                echo "Actual Total Price: " . number_format($actual, 2) . "\n";
                echo "Difference: " . number_format(abs($actual - $expected), 2) . "\n";
                echo "Match: " . (abs($actual - $expected) < 0.01 ? "✓" : "✗") . "\n";

                return [
                    'success' => abs($actual - $expected) < 0.01,
                    'message' => "Order created. Expected: {$expected}, Got: {$actual}"
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testCreateOrderWithInitialPayment()
    {
        try {
            echo "\n=== SCENARIO 3: Create order with initial payment ===\n";
            echo "Description: Create order with initial payment (auto-creates payment record)\n\n";

            echo "--- Action: Creating Order with Initial Payment ---\n";
            echo "Initial Payment Amount: 50.00\n";
            echo "Order Total: 100.00\n";

            $response = $this->postJson('/api/v1/orders', [
                'client_id' => $this->client->id,
                'entity_type' => 'branch',
                'entity_id' => $this->branch->id,
                'paid' => 50.00,
                'items' => [
                    [
                        'cloth_id' => $this->cloth->id,
                        'price' => 100.00,
                        'type' => 'buy'
                    ]
                ]
            ], $this->getAuthHeaders());

            if ($response->status() === 201) {
                $order = Order::find($response->json('id'));
                $payment = Payment::where('order_id', $order->id)->first();

                $this->debugPrintOrderState($order, 'After Order Creation');
                $this->debugPrintPaymentBreakdown($order);

                echo "\n--- Validation ---\n";
                $hasPayment = $payment && $payment->payment_type === 'initial';
                echo "Expected: Payment record created with type='initial'\n";
                echo "Payment Found: " . ($payment ? "Yes (ID: {$payment->id})" : "No") . "\n";
                if ($payment) {
                    echo "Payment Type: {$payment->payment_type}\n";
                    echo "Payment Amount: " . number_format($payment->amount, 2) . "\n";
                    echo "Payment Status: {$payment->status}\n";
                }
                echo "Match: " . ($hasPayment ? "✓" : "✗") . "\n";

                echo "\nExpected Order State:\n";
                echo "  Paid: 50.00\n";
                echo "  Remaining: 50.00\n";
                echo "  Status: partially_paid\n";
                echo "Actual Order State:\n";
                echo "  Paid: " . number_format($order->paid, 2) . "\n";
                echo "  Remaining: " . number_format($order->remaining, 2) . "\n";
                echo "  Status: {$order->status}\n";

                return [
                    'success' => $hasPayment,
                    'message' => $hasPayment
                        ? "Order created with initial payment. Payment ID: {$payment->id}, Type: {$payment->payment_type}"
                        : "Order created but payment not found or wrong type"
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testAddNormalPayment()
    {
        try {
            echo "\n=== SCENARIO 4: Add normal payment to order ===\n";
            echo "Description: Add a normal payment to an existing order\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found to add payment to\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintPaymentBreakdown($order);

            $beforePaid = $order->paid;
            $beforeRemaining = $order->remaining;
            $beforeStatus = $order->status;

            echo "\n--- Action: Adding Normal Payment ---\n";
            echo "Amount: 30.00\n";
            echo "Type: normal\n";
            echo "Status: paid\n";

            $response = $this->postJson("/api/v1/orders/{$order->id}/add-payment", [
                'amount' => 30.00,
                'payment_type' => 'normal',
                'status' => 'paid'
            ], $this->getAuthHeaders());

            if ($response->status() === 200) {
                $order->refresh();
                $payment = Payment::where('order_id', $order->id)
                    ->where('payment_type', 'normal')
                    ->latest()
                    ->first();

                $this->debugPrintOrderState($order, 'After Adding Payment');
                $this->debugPrintPaymentBreakdown($order);
                $this->debugPrintCalculationDetails($order, 'Add Normal Payment', [
                    'Paid Before' => $beforePaid,
                    'Remaining Before' => $beforeRemaining,
                    'Status Before' => $beforeStatus
                ], [
                    'Paid After' => $order->paid,
                    'Remaining After' => $order->remaining,
                    'Status After' => $order->status
                ]);

                echo "\n--- Validation ---\n";
                echo "Payment Created: " . ($payment !== null ? "✓ (ID: {$payment->id})" : "✗") . "\n";
                if ($payment) {
                    echo "Payment Amount: " . number_format($payment->amount, 2) . "\n";
                    echo "Payment Status: {$payment->status}\n";
                    echo "Payment Type: {$payment->payment_type}\n";
                }

                $expectedPaid = $beforePaid + 30.00;
                $expectedRemaining = max(0, $order->total_price - $expectedPaid);
                echo "\nExpected Paid: " . number_format($expectedPaid, 2) . "\n";
                echo "Actual Paid: " . number_format($order->paid, 2) . "\n";
                echo "Match: " . (abs($order->paid - $expectedPaid) < 0.01 ? "✓" : "✗") . "\n";

                echo "\nExpected Remaining: " . number_format($expectedRemaining, 2) . "\n";
                echo "Actual Remaining: " . number_format($order->remaining, 2) . "\n";
                echo "Match: " . (abs($order->remaining - $expectedRemaining) < 0.01 ? "✓" : "✗") . "\n";

                return [
                    'success' => $payment !== null,
                    'message' => $payment
                        ? "Normal payment added. Amount: {$payment->amount}, Status: {$payment->status}"
                        : "Payment not found"
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testAddFeePayment()
    {
        try {
            echo "\n=== SCENARIO 5: Add fee payment to order ===\n";
            echo "Description: Add a fee payment to an existing order (fees are tracked separately)\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found to add payment to\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintPaymentBreakdown($order);

            $beforePaid = $order->paid;
            $beforeRemaining = $order->remaining;
            $beforeFees = Payment::where('order_id', $order->id)
                ->where('payment_type', 'fee')
                ->where('status', 'paid')
                ->sum('amount');

            echo "\n--- Action: Adding Fee Payment ---\n";
            echo "Amount: 25.00\n";
            echo "Type: fee\n";
            echo "Status: paid\n";
            echo "Notes: Repair fee\n";
            echo "\nNote: Fees are tracked separately and should NOT affect order's paid/remaining fields\n";

            $response = $this->postJson("/api/v1/orders/{$order->id}/add-payment", [
                'amount' => 25.00,
                'payment_type' => 'fee',
                'status' => 'paid',
                'notes' => 'Repair fee'
            ], $this->getAuthHeaders());

            if ($response->status() === 200) {
                $order->refresh();
                $payment = Payment::where('order_id', $order->id)
                    ->where('payment_type', 'fee')
                    ->latest()
                    ->first();

                $this->debugPrintOrderState($order, 'After Adding Fee Payment');
                $this->debugPrintPaymentBreakdown($order);
                $this->debugPrintCalculationDetails($order, 'Add Fee Payment', [
                    'Paid Before' => $beforePaid,
                    'Remaining Before' => $beforeRemaining,
                    'Fees Paid Before' => $beforeFees
                ], [
                    'Paid After' => $order->paid,
                    'Remaining After' => $order->remaining,
                    'Fees Paid After' => Payment::where('order_id', $order->id)
                        ->where('payment_type', 'fee')
                        ->where('status', 'paid')
                        ->sum('amount')
                ]);

                echo "\n--- Validation ---\n";
                echo "Fee Payment Created: " . ($payment !== null ? "✓ (ID: {$payment->id})" : "✗") . "\n";
                if ($payment) {
                    echo "Fee Amount: " . number_format($payment->amount, 2) . "\n";
                    echo "Fee Status: {$payment->status}\n";
                    echo "Fee Notes: {$payment->notes}\n";
                }

                // Fees should NOT affect paid/remaining
                echo "\nFee Impact Check:\n";
                echo "  Paid should remain: " . number_format($beforePaid, 2) . "\n";
                echo "  Paid actually is: " . number_format($order->paid, 2) . "\n";
                echo "  Match: " . (abs($order->paid - $beforePaid) < 0.01 ? "✓ (fees don't affect paid)" : "✗ (unexpected change)") . "\n";

                echo "  Remaining should remain: " . number_format($beforeRemaining, 2) . "\n";
                echo "  Remaining actually is: " . number_format($order->remaining, 2) . "\n";
                echo "  Match: " . (abs($order->remaining - $beforeRemaining) < 0.01 ? "✓ (fees don't affect remaining)" : "✗ (unexpected change)") . "\n";

                return [
                    'success' => $payment !== null,
                    'message' => $payment
                        ? "Fee payment added. Amount: {$payment->amount}, Type: {$payment->payment_type}"
                        : "Fee payment not found"
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testAddPendingPayment()
    {
        try {
            echo "\n=== SCENARIO 6: Add pending payment ===\n";
            echo "Description: Add a pending payment (should not affect order paid/remaining until paid)\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found to add payment to\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintPaymentBreakdown($order);

            $beforePaid = $order->paid;
            $beforeRemaining = $order->remaining;
            $beforePendingCount = Payment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->count();

            echo "\n--- Action: Adding Pending Payment ---\n";
            echo "Amount: 20.00\n";
            echo "Type: normal\n";
            echo "Status: pending\n";
            echo "\nNote: Pending payments should NOT affect order's paid/remaining fields\n";

            $response = $this->postJson("/api/v1/orders/{$order->id}/add-payment", [
                'amount' => 20.00,
                'status' => 'pending',
                'payment_type' => 'normal'
            ], $this->getAuthHeaders());

            if ($response->status() === 200) {
                $order->refresh();
                $payment = Payment::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->latest()
                    ->first();

                $this->debugPrintOrderState($order, 'After Adding Pending Payment');
                $this->debugPrintPaymentBreakdown($order);

                echo "\n--- Validation ---\n";
                echo "Pending Payment Created: " . ($payment !== null ? "✓ (ID: {$payment->id})" : "✗") . "\n";
                if ($payment) {
                    echo "Payment Amount: " . number_format($payment->amount, 2) . "\n";
                    echo "Payment Status: {$payment->status}\n";
                }

                // Pending payments should NOT affect paid/remaining
                echo "\nPending Payment Impact Check:\n";
                echo "  Paid should remain: " . number_format($beforePaid, 2) . "\n";
                echo "  Paid actually is: " . number_format($order->paid, 2) . "\n";
                echo "  Match: " . (abs($order->paid - $beforePaid) < 0.01 ? "✓ (pending doesn't affect paid)" : "✗ (unexpected change)") . "\n";

                echo "  Remaining should remain: " . number_format($beforeRemaining, 2) . "\n";
                echo "  Remaining actually is: " . number_format($order->remaining, 2) . "\n";
                echo "  Match: " . (abs($order->remaining - $beforeRemaining) < 0.01 ? "✓ (pending doesn't affect remaining)" : "✗ (unexpected change)") . "\n";

                $afterPendingCount = Payment::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->count();
                echo "  Pending Count Before: {$beforePendingCount}\n";
                echo "  Pending Count After: {$afterPendingCount}\n";
                echo "  Match: " . ($afterPendingCount === $beforePendingCount + 1 ? "✓" : "✗") . "\n";

                return [
                    'success' => $payment !== null,
                    'message' => $payment
                        ? "Pending payment added. Amount: {$payment->amount}, Status: {$payment->status}"
                        : "Pending payment not found"
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testDeliveredWithoutCustody()
    {
        try {
            echo "\n=== SCENARIO 7: Try to mark order as delivered without custody ===\n";
            echo "Description: Attempt to deliver order without custody (should fail)\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintCustodyStatus($order);

            echo "\n--- Action: Attempting to Mark as Delivered ---\n";
            echo "Expected: Should fail with 422 (no custody records)\n";

            $response = $this->putJson("/api/v1/orders/{$order->id}", [
                'status' => 'delivered'
            ], $this->getAuthHeaders());

            // Should fail with 422
            if ($response->status() === 422) {
                $errors = $response->json('errors.status');
                echo "\n--- Validation ---\n";
                echo "Status Code: 422 (Expected)\n";
                echo "Error: " . implode(', ', $errors ?? []) . "\n";
                echo "Result: ✓ Correctly rejected\n";

                return [
                    'success' => true,
                    'message' => "Correctly rejected. Error: " . implode(', ', $errors ?? [])
                ];
            }

            echo "\n--- Validation ---\n";
            echo "Status Code: {$response->status()} (Expected: 422)\n";
            echo "Result: ✗ Should have failed but didn't\n";

            return ['success' => false, 'message' => "Should have failed but got status: {$response->status()}"];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testDeliveredWithCustody()
    {
        try {
            echo "\n=== SCENARIO 8: Create custody and mark as delivered ===\n";
            echo "Description: Create custody record and successfully mark order as delivered\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintCustodyStatus($order);

            echo "\n--- Action: Creating Custody ---\n";
            echo "Type: money\n";
            echo "Description: Security deposit\n";
            echo "Value: 100.00\n";
            echo "Status: pending\n";

            // Create custody
            $custody = Custody::create([
                'order_id' => $order->id,
                'type' => 'money',
                'description' => 'Security deposit',
                'value' => 100.00,
                'status' => 'pending'
            ]);

            echo "Custody Created: ✓ (ID: {$custody->id})\n";

            $this->debugPrintCustodyStatus($order);

            echo "\n--- Action: Marking Order as Delivered ---\n";

            $response = $this->putJson("/api/v1/orders/{$order->id}", [
                'status' => 'delivered'
            ], $this->getAuthHeaders());

            if ($response->status() === 200) {
                $order->refresh();
                $this->debugPrintOrderState($order, 'After Delivery');
                $this->debugPrintCustodyStatus($order);

                echo "\n--- Validation ---\n";
                echo "Expected Status: delivered\n";
                echo "Actual Status: {$order->status}\n";
                echo "Match: " . ($order->status === 'delivered' ? "✓" : "✗") . "\n";

                return [
                    'success' => $order->status === 'delivered',
                    'message' => "Order marked as delivered with custody. Custody ID: {$custody->id}"
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testFinishedWithoutCustodyDecision()
    {
        try {
            echo "\n=== SCENARIO 9: Try to finish order without custody decision ===\n";
            echo "Description: Attempt to finish order with pending custody (should fail)\n\n";

            $order = Order::where('status', 'delivered')->first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No delivered order found\n";
                return ['success' => false, 'message' => 'No delivered order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);

            echo "\n--- Action: Attempting to Mark as Finished ---\n";
            echo "Expected: Should fail with 422 (custody still pending)\n";

            $response = $this->putJson("/api/v1/orders/{$order->id}", [
                'status' => 'finished'
            ], $this->getAuthHeaders());

            // Should fail
            if ($response->status() === 422) {
                $errors = $response->json('errors');
                echo "\n--- Validation ---\n";
                echo "Status Code: 422 (Expected)\n";
                echo "Errors: " . json_encode($errors, JSON_PRETTY_PRINT) . "\n";
                echo "Result: ✓ Correctly rejected\n";

                return [
                    'success' => true,
                    'message' => "Correctly rejected. Error: " . json_encode($errors)
                ];
            }

            echo "\n--- Validation ---\n";
            echo "Status Code: {$response->status()} (Expected: 422)\n";
            echo "Result: ✗ Should have failed but didn't\n";

            return ['success' => false, 'message' => "Should have failed but got status: {$response->status()}"];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testFinishedWithPendingPayments()
    {
        try {
            echo "\n=== SCENARIO 10: Try to finish order with pending payments ===\n";
            echo "Description: Attempt to finish order with pending payments (should fail)\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);

            echo "\n--- Action: Creating Custody (Kept) ---\n";
            // Create custody and mark as kept
            $custody = Custody::create([
                'order_id' => $order->id,
                'type' => 'money',
                'description' => 'Deposit',
                'value' => 50.00,
                'status' => 'forfeited' // Kept
            ]);
            echo "Custody Created: ✓ (ID: {$custody->id}, Status: forfeited)\n";

            echo "\n--- Action: Creating Pending Payment ---\n";
            // Create pending payment
            $pendingPayment = Payment::create([
                'order_id' => $order->id,
                'amount' => 10.00,
                'status' => 'pending',
                'payment_type' => 'normal'
            ]);
            echo "Pending Payment Created: ✓ (ID: {$pendingPayment->id}, Amount: 10.00)\n";

            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);

            echo "\n--- Action: Attempting to Mark as Finished ---\n";
            echo "Expected: Should fail with 422 (pending payments exist)\n";

            $response = $this->putJson("/api/v1/orders/{$order->id}", [
                'status' => 'finished'
            ], $this->getAuthHeaders());

            // Should fail
            if ($response->status() === 422) {
                $errors = $response->json('errors');
                echo "\n--- Validation ---\n";
                echo "Status Code: 422 (Expected)\n";
                echo "Errors: " . json_encode($errors, JSON_PRETTY_PRINT) . "\n";
                echo "Result: ✓ Correctly rejected due to pending payments\n";

                return [
                    'success' => true,
                    'message' => "Correctly rejected due to pending payments"
                ];
            }

            echo "\n--- Validation ---\n";
            echo "Status Code: {$response->status()} (Expected: 422)\n";
            echo "Result: ✗ Should have failed but didn't\n";

            return ['success' => false, 'message' => "Should have failed but got status: {$response->status()}"];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testFinishedWithKeptCustody()
    {
        try {
            echo "\n=== SCENARIO 11: Finish order with kept custody ===\n";
            echo "Description: Finish order with all custody forfeited (kept) and all payments paid\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);

            echo "\n--- Action: Creating Custody (Kept) ---\n";
            // Create custody and mark as kept
            $custody = Custody::create([
                'order_id' => $order->id,
                'type' => 'money',
                'description' => 'Security deposit',
                'value' => 100.00,
                'status' => 'forfeited' // Customer keeps it
            ]);
            echo "Custody Created: ✓ (ID: {$custody->id}, Status: forfeited)\n";

            echo "\n--- Action: Ensuring All Payments Are Paid ---\n";
            // Ensure all payments are paid
            Payment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->update(['status' => 'paid']);

            // Ensure order is fully paid
            $order->refresh();
            $totalPaid = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->sum('amount');
            $feePayments = Payment::where('order_id', $order->id)
                ->where('payment_type', 'fee')
                ->where('status', 'paid')
                ->sum('amount');
            $nonFeePaid = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->where('payment_type', '!=', 'fee')
                ->sum('amount');
            $required = $order->total_price + $feePayments;

            echo "Total Paid (all): " . number_format($totalPaid, 2) . "\n";
            echo "Non-fee Paid: " . number_format($nonFeePaid, 2) . "\n";
            echo "Fee Paid: " . number_format($feePayments, 2) . "\n";
            echo "Order Total: " . number_format($order->total_price, 2) . "\n";
            echo "Required (total_price + fees): " . number_format($required, 2) . "\n";

            if ($nonFeePaid < $order->total_price) {
                $additionalNeeded = $order->total_price - $nonFeePaid;
                echo "Adding additional payment: " . number_format($additionalNeeded, 2) . "\n";
                Payment::create([
                    'order_id' => $order->id,
                    'amount' => $additionalNeeded,
                    'status' => 'paid',
                    'payment_type' => 'normal'
                ]);
            }

            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);
            $this->debugPrintCalculationDetails($order, 'Before Finishing');

            echo "\n--- Action: Marking Order as Finished ---\n";

            $response = $this->putJson("/api/v1/orders/{$order->id}", [
                'status' => 'finished'
            ], $this->getAuthHeaders());

            if ($response->status() === 200) {
                $order->refresh();
                $this->debugPrintOrderState($order, 'After Finishing');

                echo "\n--- Validation ---\n";
                echo "Expected Status: finished\n";
                echo "Actual Status: {$order->status}\n";
                echo "Match: " . ($order->status === 'finished' ? "✓" : "✗") . "\n";

                return [
                    'success' => $order->status === 'finished',
                    'message' => "Order finished with kept custody. Custody status: {$custody->status}"
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";
            $errors = $response->json('errors');
            if ($errors) {
                echo "Errors: " . json_encode($errors, JSON_PRETTY_PRINT) . "\n";
            }

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testFinishedWithReturnedCustody()
    {
        try {
            echo "\n=== SCENARIO 12: Finish order with returned custody ===\n";
            echo "Description: Finish order with custody returned and proof uploaded\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);

            echo "\n--- Action: Creating Custody ---\n";
            // Create custody
            $custody = Custody::create([
                'order_id' => $order->id,
                'type' => 'money',
                'description' => 'Security deposit',
                'value' => 100.00,
                'status' => 'pending'
            ]);
            echo "Custody Created: ✓ (ID: {$custody->id}, Status: pending)\n";

            echo "\n--- Action: Marking Custody as Returned with Proof ---\n";
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
            echo "Custody Return Created: ✓ (ID: {$custodyReturn->id}, Proof: {$custodyReturn->return_proof_photo})\n";

            echo "\n--- Action: Ensuring All Payments Are Paid ---\n";
            // Ensure all payments are paid
            Payment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->update(['status' => 'paid']);

            // Ensure order is fully paid
            $order->refresh();
            $totalPaid = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->sum('amount');
            $feePayments = Payment::where('order_id', $order->id)
                ->where('payment_type', 'fee')
                ->where('status', 'paid')
                ->sum('amount');
            $nonFeePaid = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->where('payment_type', '!=', 'fee')
                ->sum('amount');
            $required = $order->total_price + $feePayments;

            echo "Total Paid (all): " . number_format($totalPaid, 2) . "\n";
            echo "Non-fee Paid: " . number_format($nonFeePaid, 2) . "\n";
            echo "Fee Paid: " . number_format($feePayments, 2) . "\n";
            echo "Order Total: " . number_format($order->total_price, 2) . "\n";
            echo "Required (total_price + fees): " . number_format($required, 2) . "\n";

            if ($nonFeePaid < $order->total_price) {
                $additionalNeeded = $order->total_price - $nonFeePaid;
                echo "Adding additional payment: " . number_format($additionalNeeded, 2) . "\n";
                Payment::create([
                    'order_id' => $order->id,
                    'amount' => $additionalNeeded,
                    'status' => 'paid',
                    'payment_type' => 'normal'
                ]);
            }

            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);
            $this->debugPrintCalculationDetails($order, 'Before Finishing');

            echo "\n--- Action: Marking Order as Finished ---\n";

            $response = $this->putJson("/api/v1/orders/{$order->id}", [
                'status' => 'finished'
            ], $this->getAuthHeaders());

            if ($response->status() === 200) {
                $order->refresh();
                $this->debugPrintOrderState($order, 'After Finishing');

                echo "\n--- Validation ---\n";
                echo "Expected Status: finished\n";
                echo "Actual Status: {$order->status}\n";
                echo "Match: " . ($order->status === 'finished' ? "✓" : "✗") . "\n";

                return [
                    'success' => $order->status === 'finished',
                    'message' => "Order finished with returned custody. Return proof uploaded."
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";
            $errors = $response->json('errors');
            if ($errors) {
                echo "Errors: " . json_encode($errors, JSON_PRETTY_PRINT) . "\n";
            }

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testFinishedWithFees()
    {
        try {
            echo "\n=== SCENARIO 13: Finish order with fees ===\n";
            echo "Description: Finish order with fees added (fees must be paid separately)\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);

            echo "\n--- Action: Creating Custody (Kept) ---\n";
            // Create custody and mark as kept
            $custody = Custody::create([
                'order_id' => $order->id,
                'type' => 'money',
                'description' => 'Deposit',
                'value' => 50.00,
                'status' => 'forfeited'
            ]);
            echo "Custody Created: ✓ (ID: {$custody->id}, Status: forfeited)\n";

            echo "\n--- Action: Adding Fee Payment ---\n";
            // Add fee payment
            $feePayment = Payment::create([
                'order_id' => $order->id,
                'amount' => 25.00,
                'status' => 'paid',
                'payment_type' => 'fee',
                'notes' => 'Repair fee'
            ]);
            echo "Fee Payment Created: ✓ (ID: {$feePayment->id}, Amount: 25.00)\n";

            // Ensure order is fully paid (total + fees)
            $order->refresh();
            $totalPaid = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->sum('amount');
            $feePayments = Payment::where('order_id', $order->id)
                ->where('payment_type', 'fee')
                ->where('status', 'paid')
                ->sum('amount');
            $nonFeePaid = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->where('payment_type', '!=', 'fee')
                ->sum('amount');
            $required = $order->total_price + $feePayments;

            echo "\nPayment Summary:\n";
            echo "  Total Paid (all): " . number_format($totalPaid, 2) . "\n";
            echo "  Non-fee Paid: " . number_format($nonFeePaid, 2) . "\n";
            echo "  Fee Paid: " . number_format($feePayments, 2) . "\n";
            echo "  Order Total: " . number_format($order->total_price, 2) . "\n";
            echo "  Required (total_price + fees): " . number_format($required, 2) . "\n";

            if ($nonFeePaid < $order->total_price) {
                $additionalNeeded = $order->total_price - $nonFeePaid;
                echo "Adding additional payment: " . number_format($additionalNeeded, 2) . "\n";
                Payment::create([
                    'order_id' => $order->id,
                    'amount' => $additionalNeeded,
                    'status' => 'paid',
                    'payment_type' => 'normal'
                ]);
            }

            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);
            $this->debugPrintCalculationDetails($order, 'Before Finishing with Fees');

            echo "\n--- Action: Marking Order as Finished ---\n";
            echo "Note: For finishing, non-fee payments must >= total_price AND all fees must be paid\n";

            $response = $this->putJson("/api/v1/orders/{$order->id}", [
                'status' => 'finished'
            ], $this->getAuthHeaders());

            if ($response->status() === 200) {
                $order->refresh();
                $this->debugPrintOrderState($order, 'After Finishing');

                echo "\n--- Validation ---\n";
                echo "Expected Status: finished\n";
                echo "Actual Status: {$order->status}\n";
                echo "Match: " . ($order->status === 'finished' ? "✓" : "✗") . "\n";

                $finalFeePayments = Payment::where('order_id', $order->id)
                    ->where('payment_type', 'fee')
                    ->where('status', 'paid')
                    ->sum('amount');
                $finalTotalPaid = Payment::where('order_id', $order->id)
                    ->where('status', 'paid')
                    ->sum('amount');

                return [
                    'success' => $order->status === 'finished',
                    'message' => "Order finished with fees. Total: {$order->total_price}, Fees: {$finalFeePayments}, Paid: {$finalTotalPaid}"
                ];
            }

            echo "\n--- Error ---\n";
            echo "Status Code: {$response->status()}\n";
            echo "Message: " . $response->json('message') . "\n";
            $errors = $response->json('errors');
            if ($errors) {
                echo "Errors: " . json_encode($errors, JSON_PRETTY_PRINT) . "\n";
            }

            return ['success' => false, 'message' => $response->json('message')];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function testFinishedWithInsufficientPayment()
    {
        try {
            echo "\n=== SCENARIO 14: Try to finish order with insufficient payment ===\n";
            echo "Description: Attempt to finish order with payments less than total_price (should fail)\n\n";

            $order = Order::first();
            if (!$order) {
                echo "--- Error ---\n";
                echo "No order found\n";
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');
            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);

            echo "\n--- Action: Creating Custody (Kept) ---\n";
            // Create custody and mark as kept
            $custody = Custody::create([
                'order_id' => $order->id,
                'type' => 'money',
                'description' => 'Deposit',
                'value' => 50.00,
                'status' => 'forfeited'
            ]);
            echo "Custody Created: ✓ (ID: {$custody->id}, Status: forfeited)\n";

            echo "\n--- Action: Setting Up Insufficient Payment ---\n";
            // Ensure insufficient payment
            Payment::where('order_id', $order->id)->delete();
            $insufficientAmount = $order->total_price - 10;
            $insufficientPayment = Payment::create([
                'order_id' => $order->id,
                'amount' => $insufficientAmount, // Less than required
                'status' => 'paid',
                'payment_type' => 'normal'
            ]);
            echo "Payment Created: ✓ (ID: {$insufficientPayment->id}, Amount: " . number_format($insufficientAmount, 2) . ")\n";
            echo "Order Total: " . number_format($order->total_price, 2) . "\n";
            echo "Shortfall: 10.00\n";

            $order->refresh();
            $this->debugPrintCustodyStatus($order);
            $this->debugPrintPaymentBreakdown($order);
            $this->debugPrintCalculationDetails($order, 'Before Attempting to Finish');

            echo "\n--- Action: Attempting to Mark as Finished ---\n";
            echo "Expected: Should fail with 422 (insufficient payment)\n";

            $response = $this->putJson("/api/v1/orders/{$order->id}", [
                'status' => 'finished'
            ], $this->getAuthHeaders());

            // Should fail
            if ($response->status() === 422) {
                $errors = $response->json('errors');
                echo "\n--- Validation ---\n";
                echo "Status Code: 422 (Expected)\n";
                echo "Errors: " . json_encode($errors, JSON_PRETTY_PRINT) . "\n";
                echo "Result: ✓ Correctly rejected due to insufficient payment\n";

                return [
                    'success' => true,
                    'message' => "Correctly rejected due to insufficient payment"
                ];
            }

            echo "\n--- Validation ---\n";
            echo "Status Code: {$response->status()} (Expected: 422)\n";
            echo "Result: ✗ Should have failed but didn't\n";

            return ['success' => false, 'message' => "Should have failed but got status: {$response->status()}"];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ========== NEW EDGE CASE SCENARIOS ==========

    /**
     * Test fee added to unpaid order
     */
    private function testFeeAddedToUnpaidOrder()
    {
        try {
            echo "\n=== EDGE CASE: Fee added to unpaid order ===\n";
            echo "Description: Add fee when order has no payments (fees should not affect paid/remaining)\n\n";

            // Create fresh order
            $response = $this->postJson('/api/v1/orders', [
                'client_id' => $this->client->id,
                'entity_type' => 'branch',
                'entity_id' => $this->branch->id,
                'items' => [
                    [
                        'cloth_id' => $this->cloth->id,
                        'price' => 100.00,
                        'type' => 'buy'
                    ]
                ]
            ], $this->getAuthHeaders());

            if ($response->status() !== 201) {
                return ['success' => false, 'message' => 'Failed to create order'];
            }

            $order = Order::find($response->json('id'));
            $this->debugPrintOrderState($order, 'Initial State (Unpaid)');

            $beforePaid = $order->paid;
            $beforeRemaining = $order->remaining;

            echo "\n--- Action: Adding Fee Payment ---\n";
            $response = $this->postJson("/api/v1/orders/{$order->id}/add-payment", [
                'amount' => 15.00,
                'payment_type' => 'fee',
                'status' => 'paid',
                'notes' => 'Late fee'
            ], $this->getAuthHeaders());

            if ($response->status() === 200) {
                $order->refresh();
                $this->debugPrintOrderState($order, 'After Adding Fee');
                $this->debugPrintPaymentBreakdown($order);
                $this->debugPrintCalculationDetails($order, 'Add Fee to Unpaid Order', [
                    'Paid Before' => $beforePaid,
                    'Remaining Before' => $beforeRemaining
                ], [
                    'Paid After' => $order->paid,
                    'Remaining After' => $order->remaining
                ]);

                echo "\n--- Validation ---\n";
                $paidUnchanged = abs($order->paid - $beforePaid) < 0.01;
                $remainingUnchanged = abs($order->remaining - $beforeRemaining) < 0.01;
                echo "Paid unchanged: " . ($paidUnchanged ? "✓" : "✗") . "\n";
                echo "Remaining unchanged: " . ($remainingUnchanged ? "✓" : "✗") . "\n";

                return [
                    'success' => $paidUnchanged && $remainingUnchanged,
                    'message' => "Fee added to unpaid order. Paid: {$order->paid}, Remaining: {$order->remaining}"
                ];
            }

            return ['success' => false, 'message' => 'Failed to add fee payment'];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Test fee added to partially paid order
     */
    private function testFeeAddedToPartiallyPaidOrder()
    {
        try {
            echo "\n=== EDGE CASE: Fee added to partially paid order ===\n";
            echo "Description: Add fee when order is partially paid (fees should not affect paid/remaining)\n\n";

            // Create order with initial payment
            $response = $this->postJson('/api/v1/orders', [
                'client_id' => $this->client->id,
                'entity_type' => 'branch',
                'entity_id' => $this->branch->id,
                'paid' => 50.00,
                'items' => [
                    [
                        'cloth_id' => $this->cloth->id,
                        'price' => 100.00,
                        'type' => 'buy'
                    ]
                ]
            ], $this->getAuthHeaders());

            if ($response->status() !== 201) {
                return ['success' => false, 'message' => 'Failed to create order'];
            }

            $order = Order::find($response->json('id'));
            $this->debugPrintOrderState($order, 'Initial State (Partially Paid)');

            $beforePaid = $order->paid;
            $beforeRemaining = $order->remaining;

            echo "\n--- Action: Adding Fee Payment ---\n";
            $response = $this->postJson("/api/v1/orders/{$order->id}/add-payment", [
                'amount' => 20.00,
                'payment_type' => 'fee',
                'status' => 'paid',
                'notes' => 'Service fee'
            ], $this->getAuthHeaders());

            if ($response->status() === 200) {
                $order->refresh();
                $this->debugPrintOrderState($order, 'After Adding Fee');
                $this->debugPrintPaymentBreakdown($order);
                $this->debugPrintCalculationDetails($order, 'Add Fee to Partially Paid Order', [
                    'Paid Before' => $beforePaid,
                    'Remaining Before' => $beforeRemaining
                ], [
                    'Paid After' => $order->paid,
                    'Remaining After' => $order->remaining
                ]);

                echo "\n--- Validation ---\n";
                $paidUnchanged = abs($order->paid - $beforePaid) < 0.01;
                $remainingUnchanged = abs($order->remaining - $beforeRemaining) < 0.01;
                echo "Paid unchanged: " . ($paidUnchanged ? "✓" : "✗") . "\n";
                echo "Remaining unchanged: " . ($remainingUnchanged ? "✓" : "✗") . "\n";

                return [
                    'success' => $paidUnchanged && $remainingUnchanged,
                    'message' => "Fee added to partially paid order. Paid: {$order->paid}, Remaining: {$order->remaining}"
                ];
            }

            return ['success' => false, 'message' => 'Failed to add fee payment'];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Test multiple fees added sequentially
     */
    private function testMultipleFeesSequential()
    {
        try {
            echo "\n=== EDGE CASE: Multiple fees added sequentially ===\n";
            echo "Description: Add multiple fees one after another (each should not affect paid/remaining)\n\n";

            // Create order
            $response = $this->postJson('/api/v1/orders', [
                'client_id' => $this->client->id,
                'entity_type' => 'branch',
                'entity_id' => $this->branch->id,
                'paid' => 30.00,
                'items' => [
                    [
                        'cloth_id' => $this->cloth->id,
                        'price' => 100.00,
                        'type' => 'buy'
                    ]
                ]
            ], $this->getAuthHeaders());

            if ($response->status() !== 201) {
                return ['success' => false, 'message' => 'Failed to create order'];
            }

            $order = Order::find($response->json('id'));
            $this->debugPrintOrderState($order, 'Initial State');

            $initialPaid = $order->paid;
            $initialRemaining = $order->remaining;

            // Add first fee
            echo "\n--- Action: Adding First Fee (10.00) ---\n";
            $response1 = $this->postJson("/api/v1/orders/{$order->id}/add-payment", [
                'amount' => 10.00,
                'payment_type' => 'fee',
                'status' => 'paid',
                'notes' => 'Fee 1'
            ], $this->getAuthHeaders());

            $order->refresh();
            $this->debugPrintOrderState($order, 'After First Fee');
            $this->debugPrintPaymentBreakdown($order);

            $afterFirstPaid = $order->paid;
            $afterFirstRemaining = $order->remaining;

            // Add second fee
            echo "\n--- Action: Adding Second Fee (15.00) ---\n";
            $response2 = $this->postJson("/api/v1/orders/{$order->id}/add-payment", [
                'amount' => 15.00,
                'payment_type' => 'fee',
                'status' => 'paid',
                'notes' => 'Fee 2'
            ], $this->getAuthHeaders());

            $order->refresh();
            $this->debugPrintOrderState($order, 'After Second Fee');
            $this->debugPrintPaymentBreakdown($order);
            $this->debugPrintCalculationDetails($order, 'Multiple Fees Sequential');

            echo "\n--- Validation ---\n";
            $paidUnchanged = abs($order->paid - $initialPaid) < 0.01;
            $remainingUnchanged = abs($order->remaining - $initialRemaining) < 0.01;
            echo "Paid unchanged after both fees: " . ($paidUnchanged ? "✓" : "✗") . "\n";
            echo "Remaining unchanged after both fees: " . ($remainingUnchanged ? "✓" : "✗") . "\n";

            $feePayments = Payment::where('order_id', $order->id)
                ->where('payment_type', 'fee')
                ->where('status', 'paid')
                ->sum('amount');
            echo "Total Fees Paid: " . number_format($feePayments, 2) . " (Expected: 25.00)\n";
            echo "Fees Match: " . (abs($feePayments - 25.00) < 0.01 ? "✓" : "✗") . "\n";

            return [
                'success' => $paidUnchanged && $remainingUnchanged && abs($feePayments - 25.00) < 0.01,
                'message' => "Multiple fees added. Total fees: {$feePayments}, Paid: {$order->paid}, Remaining: {$order->remaining}"
            ];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Test payment amount zero (should fail)
     */
    private function testPaymentAmountZero()
    {
        try {
            echo "\n=== EDGE CASE: Attempt to add zero amount payment ===\n";
            echo "Description: Try to add payment with amount 0 (should fail validation)\n\n";

            $order = Order::first();
            if (!$order) {
                return ['success' => false, 'message' => 'No order found'];
            }

            $this->debugPrintOrderState($order, 'Initial State');

            echo "\n--- Action: Attempting to Add Zero Payment ---\n";
            $response = $this->postJson("/api/v1/orders/{$order->id}/add-payment", [
                'amount' => 0.00,
                'payment_type' => 'normal',
                'status' => 'paid'
            ], $this->getAuthHeaders());

            if ($response->status() === 422) {
                $errors = $response->json('errors');
                echo "\n--- Validation ---\n";
                echo "Status Code: 422 (Expected)\n";
                echo "Errors: " . json_encode($errors, JSON_PRETTY_PRINT) . "\n";
                echo "Result: ✓ Correctly rejected\n";

                return [
                    'success' => true,
                    'message' => "Correctly rejected zero amount payment"
                ];
            }

            echo "\n--- Validation ---\n";
            echo "Status Code: {$response->status()} (Expected: 422)\n";
            echo "Result: ✗ Should have failed but didn't\n";

            return ['success' => false, 'message' => "Should have failed but got status: {$response->status()}"];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Test payment canceled then paid (should fail)
     */
    private function testPaymentCanceledThenPaid()
    {
        try {
            echo "\n=== EDGE CASE: Try to pay a canceled payment ===\n";
            echo "Description: Attempt to mark a canceled payment as paid (should fail)\n\n";

            $order = Order::first();
            if (!$order) {
                return ['success' => false, 'message' => 'No order found'];
            }

            // Create and cancel a payment
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => 20.00,
                'status' => 'paid',
                'payment_type' => 'normal'
            ]);

            echo "Payment Created: ✓ (ID: {$payment->id})\n";

            // Cancel the payment
            $response = $this->postJson("/api/v1/orders/{$order->id}/payments/{$payment->id}/cancel", [
                'notes' => 'Test cancellation'
            ], $this->getAuthHeaders());

            $payment->refresh();
            echo "Payment Canceled: ✓ (Status: {$payment->status})\n";

            $this->debugPrintPaymentBreakdown($order);

            echo "\n--- Action: Attempting to Pay Canceled Payment ---\n";
            $response = $this->postJson("/api/v1/orders/{$order->id}/payments/{$payment->id}/pay", [], $this->getAuthHeaders());

            if ($response->status() === 422) {
                $errors = $response->json('errors');
                echo "\n--- Validation ---\n";
                echo "Status Code: 422 (Expected)\n";
                echo "Errors: " . json_encode($errors, JSON_PRETTY_PRINT) . "\n";
                echo "Result: ✓ Correctly rejected\n";

                return [
                    'success' => true,
                    'message' => "Correctly rejected paying canceled payment"
                ];
            }

            echo "\n--- Validation ---\n";
            echo "Status Code: {$response->status()} (Expected: 422)\n";
            echo "Result: ✗ Should have failed but didn't\n";

            return ['success' => false, 'message' => "Should have failed but got status: {$response->status()}"];
        } catch (\Exception $e) {
            echo "\n--- Exception ---\n";
            echo "Error: {$e->getMessage()}\n";
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}



