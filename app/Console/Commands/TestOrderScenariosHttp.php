<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Cloth;
use App\Models\Inventory;

class TestOrderScenariosHttp extends Command
{
    protected $signature = 'test:order-scenarios-http {--base-url=http://127.0.0.1:8000}';
    protected $description = 'Test order scenarios via HTTP API endpoints';

    private $results = [];
    private $baseUrl;
    private $token = '';
    /** @var \App\Models\User|null */
    private $user = null;

    public function handle()
    {
        $this->baseUrl = $this->option('base-url');
        $this->info("=== ORDER SCENARIOS TEST (HTTP API) ===");
        $this->info("Base URL: {$this->baseUrl}");
        $this->newLine();

        // Authenticate
        if (!$this->authenticate()) {
            $this->error('Failed to authenticate. Please ensure the server is running and a user exists.');
            return 1;
        }

        // Setup test data (ensure inventory is ready)
        $this->info("Setting up test data...");
        $this->line("DEBUG: About to call setupTestData");
        try {
            $this->setupTestData();
            $this->line("DEBUG: setupTestData completed successfully");
        } catch (\Exception $e) {
            $this->error("Failed to setup test data: {$e->getMessage()}");
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
        $this->newLine();

        // Run all scenarios that can be tested via API
        // Note: Some scenarios require direct database access (custody creation, etc.)
        // and will be skipped or adapted to use available endpoints

        $this->runScenario('1', 'Create order with cloth_id', [$this, 'scenario1']);
        $this->runScenario('2', 'Create order with item and order discounts', [$this, 'scenario2']);
        $this->runScenario('3', 'Create order with initial payment (auto-creates payment)', [$this, 'scenario3']);
        $this->runScenario('4', 'Add normal payment to order', [$this, 'scenario4']);
        $this->runScenario('5', 'Add fee payment to order', [$this, 'scenario5']);
        $this->runScenario('6', 'Add pending payment to order', [$this, 'scenario6']);
        $this->runScenario('17', 'Pay a pending payment', [$this, 'scenario17']);
        $this->runScenario('18', 'Cancel a pending payment', [$this, 'scenario18']);
        $this->runScenario('23', 'Add payment then pay it', [$this, 'scenario23']);
        $this->runScenario('24', 'Add payment then cancel it', [$this, 'scenario24']);
        $this->runScenario('26', 'Add multiple fee payments', [$this, 'scenario26']);
        $this->runScenario('29', 'Order with fees added after initial payment', [$this, 'scenario29']);
        $this->runScenario('30', 'Order with fees and normal payments mixed', [$this, 'scenario30']);

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

    private function authenticate()
    {
        // Login with admin credentials
        $this->info("Attempting to login with admin@admin.com...");
        $response = Http::post("{$this->baseUrl}/api/v1/login", [
            'email' => 'admin@admin.com',
            'password' => '123123123',
        ]);

        if (!$response) {
            $this->error("Failed to get response from server");
            return false;
        }

        $token = $response->json('token');
        if ($response->successful() && $token) {
            $this->token = $token;
            $userData = $response->json('user');
            $this->info("✓ Authenticated as: admin@admin.com");
            $this->info("✓ Token saved and will be sent with all requests");
            return true;
        }

        $statusCode = $response->status();
        $errorMessage = $response->json('message') ?? 'Unknown error';
        $responseBody = $response->body() ?? 'No response body';
        $this->error("Failed to authenticate (HTTP {$statusCode}): {$errorMessage}");
        $this->error("Response body: " . $responseBody);
        return false;
    }

    private function http($method, $endpoint, $data = [])
    {
        $url = "{$this->baseUrl}/api/v1/{$endpoint}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->{strtolower($method)}($url, $data);

        return [
            'status' => $response->status(),
            'body' => $response->json(),
            'success' => $response->successful(),
        ];
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
    }

    private function setupTestData()
    {
        $this->line("  Fetching test data from database...");
        $client = Client::first();
        $branch = Branch::first();
        $cloth = Cloth::first();

        if (!$client || !$branch || !$cloth) {
            throw new \Exception('Missing test data: client, branch, or cloth');
        }

        $this->line("  Found: Client ID {$client->id}, Branch ID {$branch->id}, Cloth ID {$cloth->id}");

        // Ensure branch has an inventory
        $this->line("  Checking branch inventory...");
        $inventory = $branch->fresh()->inventory;
        if (!$inventory) {
            // Create inventory for branch if it doesn't exist
            $this->line("  Creating inventory for branch...");
            $inventory = Inventory::create([
                'name' => $branch->name . ' Inventory',
                'inventoriable_type' => Branch::class,
                'inventoriable_id' => $branch->id,
            ]);
            $this->info("  ✓ Created inventory for branch: {$branch->name} (ID: {$inventory->id})");
        } else {
            $this->line("  ✓ Branch inventory exists (ID: {$inventory->id})");
        }

        // Ensure cloth is in the branch's inventory using direct DB query to avoid relationship caching
        $this->line("  Checking if cloth is in inventory...");
        $exists = DB::table('cloth_inventory')
            ->where('inventory_id', $inventory->id)
            ->where('cloth_id', $cloth->id)
            ->exists();

        if (!$exists) {
            $this->line("  Adding cloth to inventory...");
            DB::table('cloth_inventory')->insert([
                'inventory_id' => $inventory->id,
                'cloth_id' => $cloth->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("  ✓ Added cloth {$cloth->code} (ID: {$cloth->id}) to branch inventory (ID: {$inventory->id})");
        } else {
            $this->line("  ✓ Cloth {$cloth->code} already in inventory");
        }

        // Verify the setup
        $this->line("  Verifying setup...");
        $verify = DB::table('cloth_inventory')
            ->where('inventory_id', $inventory->id)
            ->where('cloth_id', $cloth->id)
            ->exists();
        
        if (!$verify) {
            throw new \Exception("Failed to verify cloth in inventory after setup");
        }
        
        $this->info("  ✓ Test data setup complete: Client ID {$client->id}, Branch ID {$branch->id}, Cloth ID {$cloth->id}, Inventory ID {$inventory->id}");
    }

    private function getTestData()
    {
        $client = Client::first();
        $branch = Branch::first();
        $cloth = Cloth::first();

        if (!$client || !$branch || !$cloth) {
            throw new \Exception('Missing test data: client, branch, or cloth');
        }

        return [
            'client' => $client,
            'branch' => $branch,
            'cloth' => $cloth,
        ];
    }

    // ========== SCENARIOS ==========

    private function scenario1()
    {
        $data = $this->getTestData();

        $response = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if ($response['success'] && isset($response['body']['id'])) {
            $orderId = $response['body']['id'];
            $itemsCount = count($response['body']['items'] ?? []);
            return [
                'success' => $itemsCount > 0,
                'message' => "Order created with cloth_id. Order ID: {$orderId}, Items: {$itemsCount}"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to create order: " . ($response['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario2()
    {
        $data = $this->getTestData();

        $response = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                    'discount_type' => 'fixed',
                    'discount_value' => 5,
                ]
            ]
        ]);

        if ($response['success'] && isset($response['body']['total_price'])) {
            $expected = 85.5; // 100 - 5 (item discount) = 95, then 95 - 10% = 85.5
            $calculated = (float)$response['body']['total_price'];
            return [
                'success' => abs($calculated - $expected) < 0.01,
                'message' => "Order with discounts. Expected: {$expected}, Calculated: {$calculated}"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to create order with discounts: " . ($response['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario3()
    {
        $data = $this->getTestData();

        $response = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'paid' => 50.00,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if ($response['success'] && isset($response['body']['id'])) {
            $orderId = $response['body']['id'];

            // Check if payment was created
            $orderResponse = $this->http('GET', "orders/{$orderId}");
            $payments = $orderResponse['body']['payments'] ?? [];
            $initialPayment = collect($payments)->firstWhere('payment_type', 'initial');

            return [
                'success' => $initialPayment !== null,
                'message' => $initialPayment
                    ? "Order created with initial payment. Payment ID: {$initialPayment['id']}, Type: {$initialPayment['payment_type']}"
                    : "Order created but no initial payment found"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to create order: " . ($response['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario4()
    {
        $data = $this->getTestData();

        // Create order first
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];

        // Add payment
        $paymentResponse = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 30.00,
            'payment_type' => 'normal',
            'status' => 'paid',
        ]);

        if ($paymentResponse['success']) {
            return [
                'success' => true,
                'message' => "Normal payment added. Amount: 30, Type: normal, Status: paid"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to add payment: " . ($paymentResponse['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario5()
    {
        $data = $this->getTestData();

        // Create order first
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];

        // Add fee payment
        $paymentResponse = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 25.00,
            'payment_type' => 'fee',
            'status' => 'paid',
        ]);

        if ($paymentResponse['success']) {
            return [
                'success' => true,
                'message' => "Fee payment added. Amount: 25, Type: fee"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to add fee payment: " . ($paymentResponse['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario6()
    {
        $data = $this->getTestData();

        // Create order first
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];

        // Add pending payment
        $paymentResponse = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 20.00,
            'payment_type' => 'normal',
            'status' => 'pending',
        ]);

        if ($paymentResponse['success']) {
            return [
                'success' => true,
                'message' => "Pending payment added. Amount: 20, Status: pending"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to add pending payment: " . ($paymentResponse['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario17()
    {
        $data = $this->getTestData();

        // Create order with pending payment
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];

        // Add pending payment
        $addPaymentResponse = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 50.00,
            'payment_type' => 'normal',
            'status' => 'pending',
        ]);

        if (!$addPaymentResponse['success']) {
            return ['success' => false, 'message' => 'Failed to add pending payment'];
        }

        $paymentId = $addPaymentResponse['body']['id'] ?? $addPaymentResponse['body']['payment']['id'] ?? null;
        if (!$paymentId) {
            // Try to get from order
            $orderResponse = $this->http('GET', "orders/{$orderId}");
            $payments = $orderResponse['body']['payments'] ?? [];
            $pendingPayment = collect($payments)->firstWhere('status', 'pending');
            $paymentId = $pendingPayment['id'] ?? null;
        }

        if (!$paymentId) {
            return ['success' => false, 'message' => 'Could not find payment ID'];
        }

        // Pay the payment
        $payResponse = $this->http('POST', "orders/{$orderId}/payments/{$paymentId}/pay");

        if ($payResponse['success']) {
            // Check order updated
            $orderResponse = $this->http('GET', "orders/{$orderId}");
            $order = $orderResponse['body'];
            return [
                'success' => true,
                'message' => "Pending payment marked as paid. Payment ID: {$paymentId}, Order paid: {$order['paid']}, Remaining: {$order['remaining']}"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to pay payment: " . ($payResponse['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario18()
    {
        $data = $this->getTestData();

        // Create order with pending payment
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];

        // Add pending payment
        $addPaymentResponse = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 50.00,
            'payment_type' => 'normal',
            'status' => 'pending',
        ]);

        if (!$addPaymentResponse['success']) {
            return ['success' => false, 'message' => 'Failed to add pending payment'];
        }

        $paymentId = $addPaymentResponse['body']['id'] ?? $addPaymentResponse['body']['payment']['id'] ?? null;
        if (!$paymentId) {
            $orderResponse = $this->http('GET', "orders/{$orderId}");
            $payments = $orderResponse['body']['payments'] ?? [];
            $pendingPayment = collect($payments)->firstWhere('status', 'pending');
            $paymentId = $pendingPayment['id'] ?? null;
        }

        if (!$paymentId) {
            return ['success' => false, 'message' => 'Could not find payment ID'];
        }

        // Cancel the payment
        $cancelResponse = $this->http('POST', "orders/{$orderId}/payments/{$paymentId}/cancel", [
            'notes' => 'Test cancellation'
        ]);

        if ($cancelResponse['success']) {
            $orderResponse = $this->http('GET', "orders/{$orderId}");
            $order = $orderResponse['body'];
            return [
                'success' => true,
                'message' => "Pending payment canceled. Payment ID: {$paymentId}, Order paid: {$order['paid']}, Remaining: {$order['remaining']}"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to cancel payment: " . ($cancelResponse['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario23()
    {
        $data = $this->getTestData();

        // Create order
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];

        // Add payment
        $addPaymentResponse = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 50.00,
            'payment_type' => 'normal',
            'status' => 'pending',
        ]);

        if (!$addPaymentResponse['success']) {
            return ['success' => false, 'message' => 'Failed to add payment'];
        }

        $paymentId = $addPaymentResponse['body']['id'] ?? $addPaymentResponse['body']['payment']['id'] ?? null;
        if (!$paymentId) {
            $orderResponse = $this->http('GET', "orders/{$orderId}");
            $payments = $orderResponse['body']['payments'] ?? [];
            $pendingPayment = collect($payments)->firstWhere('status', 'pending');
            $paymentId = $pendingPayment['id'] ?? null;
        }

        if (!$paymentId) {
            return ['success' => false, 'message' => 'Could not find payment ID'];
        }

        // Pay it
        $payResponse = $this->http('POST', "orders/{$orderId}/payments/{$paymentId}/pay");

        if ($payResponse['success']) {
            $orderResponse = $this->http('GET', "orders/{$orderId}");
            $order = $orderResponse['body'];
            return [
                'success' => true,
                'message' => "Payment added then paid. Payment ID: {$paymentId}, Order paid: {$order['paid']}, Remaining: {$order['remaining']}"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to pay payment: " . ($payResponse['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario24()
    {
        $data = $this->getTestData();

        // Create order
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];

        // Add payment
        $addPaymentResponse = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 50.00,
            'payment_type' => 'normal',
            'status' => 'pending',
        ]);

        if (!$addPaymentResponse['success']) {
            return ['success' => false, 'message' => 'Failed to add payment'];
        }

        $paymentId = $addPaymentResponse['body']['id'] ?? $addPaymentResponse['body']['payment']['id'] ?? null;
        if (!$paymentId) {
            $orderResponse = $this->http('GET', "orders/{$orderId}");
            $payments = $orderResponse['body']['payments'] ?? [];
            $pendingPayment = collect($payments)->firstWhere('status', 'pending');
            $paymentId = $pendingPayment['id'] ?? null;
        }

        if (!$paymentId) {
            return ['success' => false, 'message' => 'Could not find payment ID'];
        }

        // Cancel it
        $cancelResponse = $this->http('POST', "orders/{$orderId}/payments/{$paymentId}/cancel");

        if ($cancelResponse['success']) {
            $orderResponse = $this->http('GET', "orders/{$orderId}");
            $order = $orderResponse['body'];
            return [
                'success' => true,
                'message' => "Payment added then canceled. Payment ID: {$paymentId}, Order paid: {$order['paid']}, Remaining: {$order['remaining']}"
            ];
        }

        return [
            'success' => false,
            'message' => "Failed to cancel payment: " . ($cancelResponse['body']['message'] ?? 'Unknown error')
        ];
    }

    private function scenario26()
    {
        $data = $this->getTestData();

        // Create order
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];

        // Add first fee
        $fee1Response = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 15.00,
            'payment_type' => 'fee',
            'status' => 'paid',
        ]);

        // Add second fee
        $fee2Response = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 25.00,
            'payment_type' => 'fee',
            'status' => 'paid',
        ]);

        // Check order
        $orderResponse = $this->http('GET', "orders/{$orderId}");
        $order = $orderResponse['body'];
        $payments = $order['payments'] ?? [];
        $feePayments = collect($payments)->where('payment_type', 'fee')->where('status', 'paid');
        $totalFees = $feePayments->sum('amount');

        return [
            'success' => $fee1Response['success'] && $fee2Response['success'] && abs($totalFees - 40.00) < 0.01 && abs($order['remaining'] - 100.00) < 0.01,
            'message' => "Multiple fee payments. Total fees: {$totalFees}, Order total: {$order['total_price']}, Remaining: {$order['remaining']} (fees excluded)"
        ];
    }

    private function scenario29()
    {
        $data = $this->getTestData();

        // Create order with initial payment
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'paid' => 50.00,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];
        $remainingBeforeFee = $createResponse['body']['remaining'];

        // Add fee
        $feeResponse = $this->http('POST', "orders/{$orderId}/add-payment", [
            'amount' => 15.00,
            'payment_type' => 'fee',
            'status' => 'paid',
        ]);

        // Check order
        $orderResponse = $this->http('GET', "orders/{$orderId}");
        $order = $orderResponse['body'];
        $remainingAfterFee = $order['remaining'];

        return [
            'success' => abs($remainingAfterFee - $remainingBeforeFee) < 0.01,
            'message' => "Fee added after initial payment. Remaining before: {$remainingBeforeFee}, Remaining after: {$remainingAfterFee} (fees excluded)"
        ];
    }

    private function scenario30()
    {
        $data = $this->getTestData();

        // Create order
        $createResponse = $this->http('POST', 'orders', [
            'client_id' => $data['client']->id,
            'entity_type' => 'branch',
            'entity_id' => $data['branch']->id,
            'items' => [
                [
                    'cloth_id' => $data['cloth']->id,
                    'price' => 100.00,
                    'type' => 'buy',
                ]
            ]
        ]);

        if (!$createResponse['success']) {
            return ['success' => false, 'message' => 'Failed to create order'];
        }

        $orderId = $createResponse['body']['id'];

        // Add mixed payments
        $this->http('POST', "orders/{$orderId}/add-payment", ['amount' => 30.00, 'payment_type' => 'normal', 'status' => 'paid']);
        $this->http('POST', "orders/{$orderId}/add-payment", ['amount' => 10.00, 'payment_type' => 'fee', 'status' => 'paid']);
        $this->http('POST', "orders/{$orderId}/add-payment", ['amount' => 40.00, 'payment_type' => 'normal', 'status' => 'paid']);
        $this->http('POST', "orders/{$orderId}/add-payment", ['amount' => 20.00, 'payment_type' => 'fee', 'status' => 'paid']);

        // Check order
        $orderResponse = $this->http('GET', "orders/{$orderId}");
        $order = $orderResponse['body'];
        $payments = $order['payments'] ?? [];
        $nonFeePaid = collect($payments)->where('payment_type', '!=', 'fee')->where('status', 'paid')->sum('amount');
        $feePayments = collect($payments)->where('payment_type', 'fee')->where('status', 'paid')->sum('amount');

        return [
            'success' => abs($nonFeePaid - 70.00) < 0.01 && abs($feePayments - 30.00) < 0.01 && abs($order['remaining'] - 30.00) < 0.01,
            'message' => "Mixed payment types. Normal payments: {$nonFeePaid}, Fee payments: {$feePayments}, Remaining: {$order['remaining']} (fees excluded)"
        ];
    }
}
