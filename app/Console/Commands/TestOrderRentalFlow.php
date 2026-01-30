<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Custody;
use App\Models\Rent;
use App\Models\Inventory;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ClothController;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class TestOrderRentalFlow extends Command
{
    protected $signature = 'test:order-rental-flow';
    protected $description = 'Test complete order rental flow: availability checking, order creation, payments, custody, delivery, returns, and finishing';

    private $results = [];
    private $user;
    private $client;
    private $branch;
    private $inventory;
    private $cloth1;
    private $cloth2;
    private $order;

    public function handle()
    {
        $this->info('=== COMPLETE ORDER RENTAL FLOW TEST ===');
        $this->newLine();

        try {
            // Setup test data
            $this->setupTestData();

            // Test the complete flow
            $this->testStep('1', 'Check availability by cloth ID', [$this, 'testAvailabilityByClothId']);
            $this->testStep('2', 'Check availability by delivery date', [$this, 'testAvailabilityByDate']);
            $this->testStep('3', 'Create order with rent type items', [$this, 'testCreateOrder']);
            $this->testStep('4', 'Verify order total calculation with discounts', [$this, 'testOrderTotalCalculation']);
            $this->testStep('5', 'Verify initial payment creates payment record', [$this, 'testInitialPayment']);
            $this->testStep('6', 'Update order items (recalculation)', [$this, 'testUpdateOrderItems']);
            $this->testStep('7', 'Add payment to order', [$this, 'testAddPayment']);
            $this->testStep('8', 'Add custody to order', [$this, 'testAddCustody']);
            $this->testStep('9', 'Deliver order (creates rent records)', [$this, 'testDeliverOrder']);
            $this->testStep('10', 'Return items (cloth status to repairing)', [$this, 'testReturnItems']);
            $this->testStep('11', 'Return custody with proof', [$this, 'testReturnCustody']);
            $this->testStep('12', 'Finish order', [$this, 'testFinishOrder']);
            $this->testStep('13', 'Verify order history logging', [$this, 'testOrderHistory']);

            // Summary
            $this->newLine();
            $this->info('=== TEST SUMMARY ===');
            $passed = collect($this->results)->where('success', true)->count();
            $failed = collect($this->results)->where('success', false)->count();
            $this->info("Total Steps: " . count($this->results));
            $this->info("✓ Passed: {$passed}");
            if ($failed > 0) {
                $this->error("✗ Failed: {$failed}");
            }

            return $failed === 0 ? 0 : 1;
        } catch (\Exception $e) {
            $this->error("Fatal Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    private function setupTestData()
    {
        $this->info('Setting up test data...');

        // Step 1: Create base models (Country, City)
        $country = Country::firstOrCreate(['name' => 'Test Country']);
        $city = City::firstOrCreate(
            ['name' => 'Test City'],
            ['country_id' => $country->id]
        );

        // Refresh to ensure relationships are loaded
        $country->refresh();
        $city->refresh();

        // Step 2: Create addresses
        $clientAddress = Address::firstOrCreate(
            ['street' => 'Test Street', 'building' => '1', 'city_id' => $city->id]
        );
        $branchAddress = Address::firstOrCreate(
            ['street' => 'Branch Street', 'building' => '2', 'city_id' => $city->id]
        );

        // Refresh addresses
        $clientAddress->refresh();
        $branchAddress->refresh();

        // Step 3: Create user
        $this->user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'),
            ]
        );
        $this->user->refresh();

        // Step 4: Create client
        $this->client = Client::firstOrCreate(
            ['national_id' => '12345678901234'],
            [
                'first_name' => 'Test',
                'middle_name' => 'Order',
                'last_name' => 'Client',
                'date_of_birth' => '1990-01-01',
                'address_id' => $clientAddress->id,
            ]
        );
        $this->client->refresh();

        // Step 5: Create branch
        $this->branch = Branch::firstOrCreate(
            ['branch_code' => 'TEST-BR-001'],
            [
                'name' => 'Test Branch',
                'address_id' => $branchAddress->id,
            ]
        );
        $this->branch->refresh();

        // Step 6: Create inventory for branch using the relationship method
        // First, check if inventory already exists via relationship
        $this->branch->refresh();
        $existingInventory = $this->branch->inventory;

        if ($existingInventory) {
            $this->inventory = $existingInventory;
        } else {
            // Create inventory using the relationship method to ensure proper association
            $this->inventory = $this->branch->inventory()->create([
                'name' => 'Test Branch Inventory',
            ]);
        }

        // Ensure inventory is saved and refreshed
        $this->inventory->refresh();

        // Step 7: Refresh branch and reload inventory relationship
        $this->branch->refresh();
        $this->branch->load('inventory');

        // Verify inventory exists and can be accessed via relationship
        if (!$this->branch->inventory || $this->branch->inventory->id !== $this->inventory->id) {
            throw new \Exception("Inventory not properly associated with branch #{$this->branch->id}");
        }

        // Step 8: Create cloth type
        $clothType = ClothType::firstOrCreate(
            ['code' => 'TEST-CT-001'],
            ['name' => 'Test Cloth Type']
        );
        $clothType->refresh();

        // Step 9: Create clothes
        $this->cloth1 = Cloth::firstOrCreate(
            ['code' => 'TEST-CLOTH-001'],
            [
                'name' => 'Test Cloth 1',
                'description' => 'Test cloth for rental flow',
                'cloth_type_id' => $clothType->id,
                'status' => 'ready_for_rent',
            ]
        );
        $this->cloth1->refresh();

        $this->cloth2 = Cloth::firstOrCreate(
            ['code' => 'TEST-CLOTH-002'],
            [
                'name' => 'Test Cloth 2',
                'description' => 'Test cloth 2 for rental flow',
                'cloth_type_id' => $clothType->id,
                'status' => 'ready_for_rent',
            ]
        );
        $this->cloth2->refresh();

        // Step 10: Attach clothes to inventory
        $this->inventory->clothes()->syncWithoutDetaching([$this->cloth1->id, $this->cloth2->id]);

        // Step 11: Refresh inventory to load clothes relationship
        $this->inventory->refresh();
        $this->inventory->load('clothes');

        // Verify clothes are in inventory
        if (!$this->inventory->clothes->contains($this->cloth1->id)) {
            throw new \Exception("Cloth #{$this->cloth1->id} not found in inventory");
        }
        if (!$this->inventory->clothes->contains($this->cloth2->id)) {
            throw new \Exception("Cloth #{$this->cloth2->id} not found in inventory");
        }

        // Final verification: Ensure all relationships are properly loaded
        $this->branch->refresh();
        $this->inventory->refresh();

        $this->info('✓ Test data setup complete');
        $this->info("  - User ID: {$this->user->id}");
        $this->info("  - Client ID: {$this->client->id}");
        $this->info("  - Branch ID: {$this->branch->id}");
        $this->info("  - Inventory ID: {$this->inventory->id}");
        $this->info("  - Branch has inventory: " . ($this->branch->inventory ? 'Yes' : 'No'));
        $this->info("  - Cloth 1 ID: {$this->cloth1->id}");
        $this->info("  - Cloth 2 ID: {$this->cloth2->id}");
        $this->info("  - Clothes in inventory: " . $this->inventory->clothes->count());
        $this->newLine();
    }

    private function testStep($step, $description, $callback)
    {
        $this->info("Step {$step}: {$description}");
        try {
            $result = call_user_func($callback);
            $this->results[] = [
                'step' => $step,
                'description' => $description,
                'success' => $result !== false,
                'message' => is_string($result) ? $result : 'Success'
            ];
            if ($result !== false) {
                $this->info("  ✓ " . (is_string($result) ? $result : 'Passed'));
            } else {
                $this->error("  ✗ Failed");
            }
        } catch (\Exception $e) {
            $this->results[] = [
                'step' => $step,
                'description' => $description,
                'success' => false,
                'message' => $e->getMessage()
            ];
            $this->error("  ✗ Error: " . $e->getMessage());
        }
        $this->newLine();
    }

    private function testAvailabilityByClothId()
    {
        $controller = new ClothController();
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->unavailableDays($this->cloth1->id);
        $data = json_decode($result->getContent(), true);

        if (!isset($data['cloth_id']) || $data['cloth_id'] != $this->cloth1->id) {
            throw new \Exception('Invalid response from unavailableDays');
        }

        return 'Availability check by cloth ID works';
    }

    private function testAvailabilityByDate()
    {
        $deliveryDate = Carbon::now()->addDays(10)->format('Y-m-d');
        $controller = new ClothController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'delivery_date' => $deliveryDate,
            'days_of_rent' => 3,
            'inventory_id' => $this->inventory->id,
        ]);
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->availableForDate($request);
        $data = json_decode($result->getContent(), true);

        if (!isset($data['delivery_date']) || !isset($data['available_clothes'])) {
            throw new \Exception('Invalid response from availableForDate');
        }

        return 'Availability check by date works';
    }

    private function testCreateOrder()
    {
        // Ensure inventory exists and is accessible
        if (!$this->inventory) {
            throw new \Exception('Inventory not set up properly');
        }

        // Verify inventory exists in database and refresh branch
        $this->branch->refresh();
        $this->inventory->refresh();

        // Verify the relationship works
        $this->branch->load('inventory');
        if (!$this->branch->inventory || $this->branch->inventory->id !== $this->inventory->id) {
            throw new \Exception("Branch #{$this->branch->id} does not have inventory. Inventory ID: " . ($this->inventory ? $this->inventory->id : 'null'));
        }

        $deliveryDate = Carbon::now()->addDays(10)->format('Y-m-d');
        $occasionDate = Carbon::now()->addDays(10)->format('Y-m-d H:i:s');

        $orderData = [
            'client_id' => $this->client->id,
            'entity_type' => 'branch',
            'entity_id' => $this->branch->id,
            'items' => [
                [
                    'cloth_id' => $this->cloth1->id,
                    'price' => 100.00,
                    'type' => 'rent',
                    'days_of_rent' => 3,
                    'occasion_datetime' => $occasionDate,
                    'delivery_date' => $deliveryDate,
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                ],
                [
                    'cloth_id' => $this->cloth2->id,
                    'price' => 150.00,
                    'type' => 'rent',
                    'days_of_rent' => 5,
                    'occasion_datetime' => $occasionDate,
                    'delivery_date' => $deliveryDate,
                    'discount_type' => 'fixed',
                    'discount_value' => 20,
                ],
            ],
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'paid' => 50.00,
        ];

        $controller = new OrderController();
        $request = new \Illuminate\Http\Request();
        $request->merge($orderData);
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->store($request);
        $data = json_decode($result->getContent(), true);

        if (!isset($data['id'])) {
            $errorMsg = isset($data['message']) ? $data['message'] : 'Unknown error';
            $errors = isset($data['errors']) ? json_encode($data['errors']) : '';
            throw new \Exception("Order creation failed: {$errorMsg}. Errors: {$errors}");
        }

        $this->order = Order::find($data['id']);
        if (!$this->order) {
            throw new \Exception('Order not found after creation');
        }

        // Verify status is partially_paid (because paid > 0)
        if ($this->order->status !== 'partially_paid') {
            throw new \Exception("Expected status 'partially_paid', got '{$this->order->status}'");
        }

        // Verify payment was created
        $payment = Payment::where('order_id', $this->order->id)->first();
        if (!$payment || $payment->amount != 50.00) {
            throw new \Exception('Initial payment not created correctly');
        }

        return "Order #{$this->order->id} created with status '{$this->order->status}'";
    }

    private function testOrderTotalCalculation()
    {
        // Item 1: 100 - 10% = 90
        // Item 2: 150 - 20 = 130
        // Subtotal: 220
        // Order discount: 220 - 5% = 209
        $expectedTotal = 209.00;

        if (abs($this->order->total_price - $expectedTotal) > 0.01) {
            throw new \Exception("Expected total {$expectedTotal}, got {$this->order->total_price}");
        }

        return "Total price calculated correctly: {$this->order->total_price}";
    }

    private function testInitialPayment()
    {
        $payment = Payment::where('order_id', $this->order->id)
            ->where('payment_type', 'initial')
            ->first();

        if (!$payment) {
            throw new \Exception('Initial payment not found');
        }

        if ($payment->status !== 'paid') {
            throw new \Exception("Expected payment status 'paid', got '{$payment->status}'");
        }

        return "Initial payment #{$payment->id} created with status '{$payment->status}'";
    }

    private function testUpdateOrderItems()
    {
        $oldTotal = $this->order->total_price;

        $updateData = [
            'items' => [
                [
                    'cloth_id' => $this->cloth1->id,
                    'price' => 120.00, // Changed from 100
                    'type' => 'rent',
                    'days_of_rent' => 3,
                    'occasion_datetime' => Carbon::now()->addDays(10)->format('Y-m-d H:i:s'),
                    'delivery_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                ],
            ],
        ];

        $controller = new OrderController();
        $request = new \Illuminate\Http\Request();
        $request->merge($updateData);
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->update($request, $this->order->id);
        $this->order->refresh();

        if ($this->order->total_price == $oldTotal) {
            throw new \Exception('Order total not recalculated after item update');
        }

        return "Order total recalculated: {$oldTotal} → {$this->order->total_price}";
    }

    private function testAddPayment()
    {
        $controller = new OrderController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'amount' => 100.00,
            'status' => 'paid',
            'payment_type' => 'normal',
        ]);
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->addPayment($request, $this->order->id);
        $this->order->refresh();

        $totalPaid = Payment::where('order_id', $this->order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount');

        if ($totalPaid != 150.00) { // 50 initial + 100 new
            throw new \Exception("Expected total paid 150.00, got {$totalPaid}");
        }

        return "Payment added. Total paid: {$totalPaid}";
    }

    private function testAddCustody()
    {
        $controller = new OrderController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'type' => 'money',
            'description' => 'Cash deposit of 500 EGP',
            'value' => 500.00,
        ]);
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->addCustody($request, $this->order->id);
        $data = json_decode($result->getContent(), true);

        if (!isset($data['id']) || $data['status'] !== 'pending') {
            throw new \Exception('Custody not created correctly');
        }

        return "Custody #{$data['id']} added with status '{$data['status']}'";
    }

    private function testDeliverOrder()
    {
        $controller = new OrderController();
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->deliver($this->order->id);
        $this->order->refresh();

        if ($this->order->status !== 'delivered') {
            throw new \Exception("Expected status 'delivered', got '{$this->order->status}'");
        }

        // Verify rent records were created
        $rents = Rent::where('order_id', $this->order->id)->get();
        if ($rents->isEmpty()) {
            throw new \Exception('Rent records not created');
        }

        // Verify cloth status changed to rented
        $this->cloth1->refresh();
        if ($this->cloth1->status !== 'rented') {
            throw new \Exception("Expected cloth status 'rented', got '{$this->cloth1->status}'");
        }

        return "Order delivered. {$rents->count()} rent record(s) created";
    }

    private function testReturnItems()
    {
        $controller = new OrderController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'items' => [
                [
                    'cloth_id' => $this->cloth1->id,
                    'status' => 'repairing',
                    'notes' => 'Item returned for cleaning',
                ],
            ],
        ]);
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->returnItems($request, $this->order->id);
        $this->cloth1->refresh();

        if ($this->cloth1->status !== 'repairing') {
            throw new \Exception("Expected cloth status 'repairing', got '{$this->cloth1->status}'");
        }

        // Verify rent is completed
        $rent = Rent::where('cloth_id', $this->cloth1->id)
            ->where('order_id', $this->order->id)
            ->first();

        if (!$rent || $rent->status !== 'completed') {
            throw new \Exception('Rent not marked as completed');
        }

        return "Item returned. Cloth status: '{$this->cloth1->status}'";
    }

    private function testReturnCustody()
    {
        $custody = Custody::where('order_id', $this->order->id)->first();
        if (!$custody) {
            throw new \Exception('Custody not found');
        }

        $controller = new OrderController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'status' => 'returned',
            'return_proof_photo' => '/path/to/proof.jpg',
        ]);
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->updateCustody($request, $custody->id);
        $custody->refresh();

        if ($custody->status !== 'returned') {
            throw new \Exception("Expected custody status 'returned', got '{$custody->status}'");
        }

        if (!$custody->return_proof_photo) {
            throw new \Exception('Return proof photo not set');
        }

        return "Custody returned with proof";
    }

    private function testFinishOrder()
    {
        // Ensure all payments are paid
        Payment::where('order_id', $this->order->id)
            ->where('status', 'pending')
            ->update(['status' => 'paid']);

        $this->order->refresh();
        $controller = new OrderController();
        $request = new \Illuminate\Http\Request();
        $request->setUserResolver(function () {
            return $this->user;
        });

        $result = $controller->finish($this->order->id);
        $this->order->refresh();

        if ($this->order->status !== 'finished') {
            throw new \Exception("Expected status 'finished', got '{$this->order->status}'");
        }

        return "Order #{$this->order->id} finished successfully";
    }

    private function testOrderHistory()
    {
        $history = \App\Models\OrderHistory::where('order_id', $this->order->id)->get();

        if ($history->isEmpty()) {
            throw new \Exception('No order history found');
        }

        $changeTypes = $history->pluck('change_type')->toArray();
        $expectedTypes = ['created', 'payment_added', 'status_changed', 'delivered', 'item_returned', 'finished'];

        $found = array_intersect($expectedTypes, $changeTypes);
        if (count($found) < 3) {
            throw new \Exception('Order history missing expected change types');
        }

        return "Order history logged: " . $history->count() . " entries";
    }
}
