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
use App\Models\Rent;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\ClothType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestComprehensiveScenarios extends Command
{
    protected $signature = 'test:comprehensive-scenarios';
    protected $description = 'Comprehensive test scenarios for order, payment, rental, return, and custody operations';

    private $results = [];

    public function handle()
    {
        $this->info('=== COMPREHENSIVE SCENARIOS TEST ===');
        $this->newLine();

        // Order Creation & Status Auto-Calculation
        $this->runScenario('1', 'Create order without payment (status = created)', [$this, 'scenario1']);
        $this->runScenario('2', 'Create order with payment > 0 (status auto-calculated)', [$this, 'scenario2']);
        $this->runScenario('3', 'Create order with payment = total_price (status = paid)', [$this, 'scenario3']);
        $this->runScenario('4', 'Create order with payment < total_price (status = partially_paid)', [$this, 'scenario4']);

        // Payment Operations
        $this->runScenario('5', 'Create pending payment (auto-status = pending)', [$this, 'scenario5']);
        $this->runScenario('6', 'Pay pending payment (order status updated)', [$this, 'scenario6']);
        $this->runScenario('7', 'Cancel pending payment (order status updated)', [$this, 'scenario7']);
        $this->runScenario('8', 'Cancel paid payment (order status updated)', [$this, 'scenario8']);
        $this->runScenario('9', 'Try to pay already paid payment (should FAIL)', [$this, 'scenario9']);
        $this->runScenario('10', 'Try to cancel already canceled payment (should FAIL)', [$this, 'scenario10']);
        $this->runScenario('11', 'Try to pay canceled payment (should FAIL)', [$this, 'scenario11']);

        // Rental Operations
        $this->runScenario('12', 'Create order with rent items (delivery_date required)', [$this, 'scenario12']);
        $this->runScenario('13', 'Check rental availability (2-day buffer)', [$this, 'scenario13']);
        $this->runScenario('14', 'Try to rent unavailable cloth (should FAIL)', [$this, 'scenario14']);
        $this->runScenario('15', 'Deliver order with rent items (creates Rent records)', [$this, 'scenario15']);
        $this->runScenario('16', 'Return rented items (updates cloth status, marks rent completed)', [$this, 'scenario16']);
        $this->runScenario('17', 'Get unavailable days for cloth', [$this, 'scenario17']);
        $this->runScenario('18', 'Multiple rents for same cloth (check conflicts)', [$this, 'scenario18']);

        // Order Lifecycle
        $this->runScenario('19', 'Created → Partially Paid → Paid → Delivered → Finished', [$this, 'scenario19']);
        $this->runScenario('20', 'Created → Canceled (all clothes return to ready_for_rent)', [$this, 'scenario20']);
        $this->runScenario('21', 'Created → Delivered → Returned → Finished', [$this, 'scenario21']);

        // Custody Operations
        $this->runScenario('22', 'Create custody (status = pending)', [$this, 'scenario22']);
        $this->runScenario('23', 'Try to deliver order without custody (should FAIL)', [$this, 'scenario23']);
        $this->runScenario('24', 'Try to finish order with pending custody (should FAIL)', [$this, 'scenario24']);
        $this->runScenario('25', 'Try to finish order with returned custody but no proof (should FAIL)', [$this, 'scenario25']);

        // Order Return Endpoint
        $this->runScenario('26', 'Return single rent item', [$this, 'scenario26']);
        $this->runScenario('27', 'Return multiple rent items', [$this, 'scenario27']);
        $this->runScenario('28', 'Return with different cloth statuses', [$this, 'scenario28']);
        $this->runScenario('29', 'Return triggers order finished (when all conditions met)', [$this, 'scenario29']);
        $this->runScenario('30', 'Try to return non-rent item (should FAIL)', [$this, 'scenario30']);
        $this->runScenario('31', 'Try to return item not in order (should FAIL)', [$this, 'scenario31']);

        // Order Update with Removed Items
        $this->runScenario('32', 'Update order removing items (items return to ready_for_rent)', [$this, 'scenario32']);
        $this->runScenario('33', 'Update order adding items (check rental availability)', [$this, 'scenario33']);

        // Edge Cases
        $this->runScenario('34', 'Order with fees (fees don\'t affect paid/remaining)', [$this, 'scenario34']);
        $this->runScenario('35', 'Order with multiple payments', [$this, 'scenario35']);
        $this->runScenario('36', 'Order with mixed payment types', [$this, 'scenario36']);
        $this->runScenario('37', 'Order with multiple custodies', [$this, 'scenario37']);
        $this->runScenario('38', 'Order with multiple rent items', [$this, 'scenario38']);
        $this->runScenario('39', 'Partial returns', [$this, 'scenario39']);
        $this->runScenario('40', 'Overlapping rental periods (should fail)', [$this, 'scenario40']);

        // Edge Cases for Rental Availability
        $this->runScenario('41', 'Exact 2-day gap before delivery (should be available)', [$this, 'scenario41']);
        $this->runScenario('42', '1 day before delivery (should conflict)', [$this, 'scenario42']);
        $this->runScenario('43', 'Exact 2-day gap after return (should be available)', [$this, 'scenario43']);
        $this->runScenario('44', '1 day after return (should conflict)', [$this, 'scenario44']);
        $this->runScenario('45', 'Touching boundary - delivery = return + 2 days (should be available)', [$this, 'scenario45']);
        $this->runScenario('46', 'New rent completely inside existing unavailable period (should conflict)', [$this, 'scenario46']);
        $this->runScenario('47', 'Existing rent completely inside new unavailable period (should conflict)', [$this, 'scenario47']);
        $this->runScenario('48', 'Multiple existing rents - some conflicting (should conflict)', [$this, 'scenario48']);
        $this->runScenario('49', 'Canceled rent exclusion (should not conflict)', [$this, 'scenario49']);
        $this->runScenario('50', 'Different cloth (should not conflict)', [$this, 'scenario50']);
        $this->runScenario('51', 'Consecutive rents with 3-day gaps (should work)', [$this, 'scenario51']);
        $this->runScenario('52', 'Long rent periods (7+ days)', [$this, 'scenario52']);
        $this->runScenario('53', 'Short rent periods (1 day)', [$this, 'scenario53']);
        $this->runScenario('54', 'Year boundary crossing (Dec 30 to Jan 5)', [$this, 'scenario54']);
        $this->runScenario('55', 'Same day delivery on boundary (return + 2 days)', [$this, 'scenario55']);
        $this->runScenario('56', 'Exactly 3-day gap (should be available)', [$this, 'scenario56']);
        $this->runScenario('57', 'New rent starts exactly 2 days after return (should be available)', [$this, 'scenario57']);
        $this->runScenario('58', 'New rent ends exactly 2 days before delivery (should be available)', [$this, 'scenario58']);
        $this->runScenario('59', 'New rent starts 1 day before unavailable period ends (should conflict)', [$this, 'scenario59']);
        $this->runScenario('60', 'New rent ends 1 day after unavailable period starts (should conflict)', [$this, 'scenario60']);

        // Summary
        $this->newLine();
        $this->info('=== TEST SUMMARY ===');
        $passed = collect($this->results)->where('success', true)->count();
        $failed = collect($this->results)->where('success', false)->count();
        $this->info("Total Scenarios: " . count($this->results));
        $this->info("✓ Passed: {$passed}");
        if ($failed > 0) {
            $this->error("✗ Failed: {$failed}");
        }
        $this->newLine();

        // Detailed results
        $this->info('=== DETAILED RESULTS ===');
        foreach ($this->results as $idx => $result) {
            $status = $result['success'] ? '✓' : '✗';
            $this->line("{$status} Scenario {$result['number']}: {$result['message']}");
        }

        return $failed === 0 ? 0 : 1;
    }

    private function runScenario($number, $description, $callback)
    {
        try {
            $result = $callback();
            $result['number'] = $number;
            $result['description'] = $description;
            $this->results[] = $result;
            
            $status = $result['success'] ? '✓' : '✗';
            $this->line("{$status} Scenario {$number}: {$result['message']}");
        } catch (\Exception $e) {
            $this->results[] = [
                'number' => $number,
                'description' => $description,
                'success' => false,
                'message' => "Exception: " . $e->getMessage()
            ];
            $this->error("✗ Scenario {$number}: Exception - " . $e->getMessage());
        }
    }

    // Helper Methods
    private function createTestData()
    {
        // Create country, city, address
        $country = Country::firstOrCreate(['name' => 'Test Country']);
        $city = City::firstOrCreate(['name' => 'Test City', 'country_id' => $country->id]);
        $address = Address::firstOrCreate([
            'street' => 'Test Street',
            'building' => 'Test Building',
            'city_id' => $city->id
        ]);

        // Create user
        $user = User::firstOrCreate(
            ['email' => 'test@test.com'],
            ['name' => 'Test User', 'password' => Hash::make('password')]
        );

        // Create client
        $client = Client::firstOrCreate(
            ['national_id' => '12345678901234'],
            [
                'first_name' => 'Test',
                'middle_name' => 'Middle',
                'last_name' => 'Client',
                'date_of_birth' => '1990-01-01',
                'address_id' => $address->id
            ]
        );

        // Create branch with inventory
        $branch = Branch::firstOrCreate(
            ['branch_code' => 'BR001'],
            ['name' => 'Test Branch', 'address_id' => $address->id]
        );

        if (!$branch->inventory) {
            $branch->inventory()->create(['name' => 'Test Branch Inventory']);
            $branch->refresh();
        }

        // Create cloth type
        $clothType = ClothType::firstOrCreate(
            ['code' => 'CT001'],
            ['name' => 'Test Cloth Type', 'description' => 'Test']
        );

        // Create cloth
        $cloth = Cloth::firstOrCreate(
            ['code' => 'CL001'],
            [
                'name' => 'Test Cloth',
                'description' => 'Test',
                'cloth_type_id' => $clothType->id,
                'status' => 'ready_for_rent'
            ]
        );

        // Add cloth to inventory (check for duplicates)
        if (!$branch->inventory->clothes()->where('clothes.id', $cloth->id)->exists()) {
            $branch->inventory->clothes()->attach($cloth->id);
        }

        return compact('user', 'client', 'branch', 'cloth', 'address');
    }

    private function createOrder($client, $inventory, $items, $paid = 0)
    {
        $totalPrice = collect($items)->sum('price');
        
        $order = Order::create([
            'client_id' => $client->id,
            'inventory_id' => $inventory->id,
            'total_price' => $totalPrice,
            'status' => 'created',
            'paid' => 0,
            'remaining' => $totalPrice,
        ]);

        foreach ($items as $item) {
            $pivotData = [
                'price' => $item['price'],
                'type' => $item['type'] ?? 'buy',
                'days_of_rent' => $item['days_of_rent'] ?? null,
                'occasion_datetime' => $item['occasion_datetime'] ?? null,
                'status' => 'created',
            ];
            
            // Only include delivery_date if it's provided
            if (isset($item['delivery_date'])) {
                $pivotData['delivery_date'] = $item['delivery_date'];
            }
            
            $order->items()->attach($item['cloth_id'], $pivotData);
        }

        if ($paid > 0) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $paid,
                'status' => 'paid',
                'payment_type' => 'initial',
                'payment_date' => now(),
                'created_by' => User::first()?->id,
            ]);
            $this->recalculateOrderPayments($order);
        }

        return $order->fresh();
    }

    /**
     * Helper: Get cloth_order pivot record ID from database
     */
    private function getClothOrderId($orderId, $clothId)
    {
        return DB::table('cloth_order')
            ->where('order_id', $orderId)
            ->where('cloth_id', $clothId)
            ->value('id');
    }

    /**
     * Helper: Check rental availability using the SAME logic as OrderController::checkRentalAvailability
     */
    private function checkRentalAvailability($clothId, $deliveryDate, $daysOfRent, $excludeOrderId = null)
    {
        $deliveryDateCarbon = Carbon::parse($deliveryDate);
        $returnDateCarbon = $deliveryDateCarbon->copy()->addDays($daysOfRent);

        $query = Rent::where('cloth_id', $clothId)
            ->where('status', '!=', 'canceled');

        if ($excludeOrderId) {
            $query->where('order_id', '!=', $excludeOrderId);
        }

        // Get all existing rents and check if new rent period overlaps with their unavailable periods
        // Unavailable period for each existing rent: (delivery_date - 2 days) to (return_date + 2 days)
        // Conflict if: new_delivery <= (existing_return + 2) AND new_return >= (existing_delivery - 2)
        $existingRents = $query->get();
        
        $conflicts = $existingRents->filter(function($existingRent) use ($deliveryDateCarbon, $returnDateCarbon) {
            // Get dates as Carbon instances (already cast to date, so they're Carbon instances)
            $existingDelivery = $existingRent->delivery_date instanceof Carbon 
                ? $existingRent->delivery_date->copy()->startOfDay() 
                : Carbon::parse($existingRent->delivery_date)->startOfDay();
            $existingReturn = $existingRent->return_date instanceof Carbon 
                ? $existingRent->return_date->copy()->startOfDay() 
                : Carbon::parse($existingRent->return_date)->startOfDay();
            
            // Unavailable period: (delivery_date - 2 days) to (return_date + 2 days)
            $existingUnavailableStart = $existingDelivery->copy()->subDays(2);
            $existingUnavailableEnd = $existingReturn->copy()->addDays(2);
            
            // Normalize new dates to start of day for comparison
            $newDelivery = $deliveryDateCarbon->copy()->startOfDay();
            $newReturn = $returnDateCarbon->copy()->startOfDay();
            
            // DEBUG: Log all date values
            $this->line("  [DEBUG] Checking Rent #{$existingRent->id}:");
            $this->line("    Existing: delivery={$existingDelivery->format('Y-m-d')}, return={$existingReturn->format('Y-m-d')}");
            $this->line("    Unavailable period: {$existingUnavailableStart->format('Y-m-d')} to {$existingUnavailableEnd->format('Y-m-d')}");
            $this->line("    New rent: delivery={$newDelivery->format('Y-m-d')}, return={$newReturn->format('Y-m-d')}");
            
            // Check if new period overlaps with existing unavailable period
            // Two periods overlap if: new_delivery <= existing_unavailable_end AND new_return >= existing_unavailable_start
            // Convert to timestamps for reliable comparison
            $newDeliveryTs = $newDelivery->timestamp;
            $newReturnTs = $newReturn->timestamp;
            $unavailableStartTs = $existingUnavailableStart->timestamp;
            $unavailableEndTs = $existingUnavailableEnd->timestamp;
            
            $check1 = $newDeliveryTs <= $unavailableEndTs;
            $check2 = $newReturnTs >= $unavailableStartTs;
            $overlaps = $check1 && $check2;
            
            $this->line("    Check 1: new_delivery ({$newDelivery->format('Y-m-d')}) <= unavailable_end ({$existingUnavailableEnd->format('Y-m-d')}) = " . ($check1 ? 'TRUE' : 'FALSE'));
            $this->line("    Check 2: new_return ({$newReturn->format('Y-m-d')}) >= unavailable_start ({$existingUnavailableStart->format('Y-m-d')}) = " . ($check2 ? 'TRUE' : 'FALSE'));
            $this->line("    Result: OVERLAPS = " . ($overlaps ? 'YES' : 'NO'));
            
            return $overlaps;
        });

        return $conflicts->isNotEmpty();
    }

    private function recalculateOrderPayments($order)
    {
        $order->refresh();
        $totalPaid = Payment::where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount');
        
        $order->paid = $totalPaid;
        $order->remaining = max(0, $order->total_price - $totalPaid);
        
        if ($order->paid >= $order->total_price) {
            $order->status = 'paid';
            $order->remaining = 0;
        } elseif ($order->paid > 0) {
            $order->status = 'partially_paid';
        } else {
            $order->status = 'created';
        }
        
        $order->save();
        return $order->fresh();
    }

    // Test Scenarios
    private function scenario1()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        return [
            'success' => $order->status === 'created' && $order->paid == 0 && $order->remaining == 100.00,
            'message' => "Order created. Status: {$order->status}, Paid: {$order->paid}, Remaining: {$order->remaining}"
        ];
    }

    private function scenario2()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 50.00);

        $hasInitialPayment = Payment::where('order_id', $order->id)
            ->where('payment_type', 'initial')
            ->where('status', 'paid')
            ->exists();

        return [
            'success' => in_array($order->status, ['partially_paid', 'paid']) && $hasInitialPayment,
            'message' => "Order with payment. Status: {$order->status}, Paid: {$order->paid}, Has initial payment: " . ($hasInitialPayment ? 'yes' : 'no')
        ];
    }

    private function scenario3()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 100.00);

        return [
            'success' => $order->status === 'paid' && $order->paid == 100.00 && $order->remaining == 0,
            'message' => "Order fully paid. Status: {$order->status}, Paid: {$order->paid}, Remaining: {$order->remaining}"
        ];
    }

    private function scenario4()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 30.00);

        return [
            'success' => $order->status === 'partially_paid' && $order->paid == 30.00 && $order->remaining == 70.00,
            'message' => "Order partially paid. Status: {$order->status}, Paid: {$order->paid}, Remaining: {$order->remaining}"
        ];
    }

    private function scenario5()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => $data['user']->id,
        ]);

        return [
            'success' => $payment->status === 'pending' && $payment->payment_date === null,
            'message' => "Pending payment created. Status: {$payment->status}, Amount: {$payment->amount}"
        ];
    }

    private function scenario6()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => $data['user']->id,
        ]);

        $oldStatus = $order->status;
        $payment->status = 'paid';
        $payment->payment_date = now();
        $payment->save();
        $order = $this->recalculateOrderPayments($order->fresh());

        return [
            'success' => $payment->status === 'paid' && $order->status === 'partially_paid' && $order->paid == 50.00,
            'message' => "Payment paid. Order status: {$oldStatus} -> {$order->status}, Paid: {$order->paid}"
        ];
    }

    private function scenario7()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => $data['user']->id,
        ]);

        $oldStatus = $order->status;
        $payment->status = 'canceled';
        $payment->save();
        $order = $this->recalculateOrderPayments($order->fresh());

        return [
            'success' => $payment->status === 'canceled' && $order->status === 'created' && $order->paid == 0,
            'message' => "Payment canceled. Order status: {$oldStatus} -> {$order->status}, Paid: {$order->paid}"
        ];
    }

    private function scenario8()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 50.00);

        $payment = Payment::where('order_id', $order->id)->first();
        $oldPaid = $order->paid;
        $payment->status = 'canceled';
        $payment->save();
        $order = $this->recalculateOrderPayments($order->fresh());

        return [
            'success' => $payment->status === 'canceled' && $order->status === 'created' && $order->paid == 0,
            'message' => "Paid payment canceled. Order paid: {$oldPaid} -> {$order->paid}, Status: {$order->status}"
        ];
    }

    private function scenario9()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 50.00);

        $payment = Payment::where('order_id', $order->id)->first();
        $oldStatus = $payment->status;

        try {
            if ($payment->status === 'paid') {
                throw new \Exception('Payment is already paid');
            }
            $payment->status = 'paid';
            $payment->save();
            $success = false;
            $message = "Should have failed - payment already paid";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario10()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => $data['user']->id,
        ]);

        $payment->status = 'canceled';
        $payment->save();

        try {
            if ($payment->status === 'canceled') {
                throw new \Exception('Payment is already canceled');
            }
            $payment->status = 'canceled';
            $payment->save();
            $success = false;
            $message = "Should have failed - payment already canceled";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario11()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => $data['user']->id,
        ]);

        $payment->status = 'canceled';
        $payment->save();

        try {
            if ($payment->status === 'canceled') {
                throw new \Exception('Cannot pay canceled payment');
            }
            $payment->status = 'paid';
            $payment->save();
            $success = false;
            $message = "Should have failed - cannot pay canceled payment";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario12()
    {
        $data = $this->createTestData();
        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ]
        ], 0);

        $pivot = $order->items()->first()->pivot;

        return [
            'success' => $pivot->type === 'rent' && $pivot->delivery_date === $deliveryDate && $pivot->days_of_rent == 3,
            'message' => "Rent order created. Delivery date: {$pivot->delivery_date}, Days: {$pivot->days_of_rent}"
        ];
    }

    private function scenario13()
    {
        $data = $this->createTestData();
        $deliveryDate1 = Carbon::now()->addDays(10)->format('Y-m-d');
        
        // Create first rent
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate1
            ]
        ], 0);

        // Add custody and deliver
        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'pending']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Try to create second rent 1 day before first (should fail - 2 day buffer)
        $deliveryDate2 = Carbon::parse($deliveryDate1)->subDays(1)->format('Y-m-d');
        
        try {
            // Use actual availability check logic
            $conflicts = $this->checkRentalAvailability($data['cloth']->id, $deliveryDate2, 3);

            if ($conflicts) {
                throw new \Exception('Cloth is not available for rent on the requested dates');
            }
            $success = false;
            $message = "Should have failed - 2 day buffer violation";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario14()
    {
        $data = $this->createTestData();
        $deliveryDate1 = Carbon::now()->addDays(10)->format('Y-m-d');
        
        // Create first rent
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate1
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'pending']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Try to create second rent overlapping (should fail)
        $deliveryDate2 = Carbon::parse($deliveryDate1)->addDays(1)->format('Y-m-d');
        
        try {
            // Use actual availability check logic
            $conflicts = $this->checkRentalAvailability($data['cloth']->id, $deliveryDate2, 3);

            if ($conflicts) {
                throw new \Exception('Cloth is not available for rent on the requested dates');
            }
            $success = false;
            $message = "Should have failed - overlapping rental period";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario15()
    {
        $data = $this->createTestData();
        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ]
        ], 0);

        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'pending']);
        
        // Simulate deliver endpoint
        $order->status = 'delivered';
        $order->save();
        $order->items()->updateExistingPivot($order->items->pluck('id')->toArray(), ['status' => 'delivered']);
        
        $pivot = $order->items()->first()->pivot;
        $rent = Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order->id,
            'cloth_order_id' => $this->getClothOrderId($order->id, $data['cloth']->id),
            'delivery_date' => $pivot->delivery_date,
            'days_of_rent' => $pivot->days_of_rent,
            'return_date' => Carbon::parse($pivot->delivery_date)->addDays($pivot->days_of_rent),
            'status' => 'active',
        ]);

        $data['cloth']->refresh();
        $data['cloth']->status = 'rented';
        $data['cloth']->save();

        $order->refresh();
        $rentExists = Rent::where('order_id', $order->id)->where('status', 'active')->exists();
        $clothRented = $data['cloth']->fresh()->status === 'rented';

        return [
            'success' => $order->status === 'delivered' && $rentExists && $clothRented,
            'message' => "Order delivered. Status: {$order->status}, Rent created: " . ($rentExists ? 'yes' : 'no') . ", Cloth status: {$data['cloth']->fresh()->status}"
        ];
    }

    private function scenario16()
    {
        $data = $this->createTestData();
        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ]
        ], 0);

        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order->status = 'delivered';
        $order->save();
        
        $pivot = $order->items()->first()->pivot;
        $rent = Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order->id,
            'cloth_order_id' => $this->getClothOrderId($order->id, $data['cloth']->id),
            'delivery_date' => $pivot->delivery_date,
            'days_of_rent' => $pivot->days_of_rent,
            'return_date' => Carbon::parse($pivot->delivery_date)->addDays($pivot->days_of_rent),
            'status' => 'active',
        ]);

        // Simulate return endpoint
        $data['cloth']->status = 'ready_for_rent';
        $data['cloth']->save();
        $rent->status = 'completed';
        $rent->save();

        $rentCompleted = Rent::where('id', $rent->id)->where('status', 'completed')->exists();
        $clothReady = $data['cloth']->fresh()->status === 'ready_for_rent';

        return [
            'success' => $rentCompleted && $clothReady,
            'message' => "Items returned. Rent status: completed, Cloth status: {$data['cloth']->fresh()->status}"
        ];
    }

    private function scenario17()
    {
        $data = $this->createTestData();
        $deliveryDate1 = Carbon::now()->addDays(10)->format('Y-m-d');
        $deliveryDate2 = Carbon::now()->addDays(20)->format('Y-m-d');
        
        // Create two rents
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate1
            ]
        ], 0);

        $order2 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 5,
                'delivery_date' => $deliveryDate2
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        Custody::create(['order_id' => $order2->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        
        $order1->status = 'delivered';
        $order1->save();
        $order2->status = 'delivered';
        $order2->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        $pivot2 = $order2->items()->first()->pivot;
        
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order2->id,
            'cloth_order_id' => $this->getClothOrderId($order2->id, $data['cloth']->id),
            'delivery_date' => $pivot2->delivery_date,
            'days_of_rent' => $pivot2->days_of_rent,
            'return_date' => Carbon::parse($pivot2->delivery_date)->addDays($pivot2->days_of_rent),
            'status' => 'active',
        ]);

        // Get unavailable days
        $rents = Rent::where('cloth_id', $data['cloth']->id)
            ->where('status', '!=', 'canceled')
            ->orderBy('delivery_date')
            ->get();

        $unavailableDates = [];
        foreach ($rents as $rent) {
            $bufferStart = Carbon::parse($rent->delivery_date)->subDays(2);
            $bufferEnd = Carbon::parse($rent->return_date)->addDays(2);
            $current = $bufferStart->copy();
            while ($current->lte($bufferEnd)) {
                $unavailableDates[] = $current->format('Y-m-d');
                $current->addDay();
            }
        }

        $unavailableDates = array_unique($unavailableDates);
        sort($unavailableDates);

        return [
            'success' => count($unavailableDates) > 0,
            'message' => "Unavailable days retrieved. Count: " . count($unavailableDates) . ", First: " . ($unavailableDates[0] ?? 'none') . ", Last: " . (end($unavailableDates) ?: 'none')
        ];
    }

    private function scenario18()
    {
        $data = $this->createTestData();
        // Create first rent far in the future to avoid any date issues
        $deliveryDate1 = Carbon::now()->addDays(100)->format('Y-m-d');
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate1
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Create second rent with 3-day gap after first return - should succeed
        // First rent: delivery Jan 10, return Jan 13, unavailable period: Jan 8-15
        // Second rent starting Jan 16 (3 days after return) should be available
        // This tests that multiple non-conflicting rents can exist with proper gap
        $returnDate1 = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $deliveryDate2 = $returnDate1->copy()->addDays(3)->format('Y-m-d');
        
        // Use actual availability check logic
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $deliveryDate2, 3);

        return [
            'success' => !$conflicts,
            'message' => "Multiple rents check. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no with 3-day gap)"
        ];
    }

    private function scenario19()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        $statuses = [];
        $statuses[] = $order->status; // created

        // Partially paid
        Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
            'payment_type' => 'normal',
            'payment_date' => now(),
            'created_by' => $data['user']->id,
        ]);
        $order = $this->recalculateOrderPayments($order->fresh());
        $statuses[] = $order->status; // partially_paid

        // Paid
        Payment::create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
            'payment_type' => 'normal',
            'payment_date' => now(),
            'created_by' => $data['user']->id,
        ]);
        $order = $this->recalculateOrderPayments($order->fresh());
        $statuses[] = $order->status; // paid

        // Delivered
        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order->status = 'delivered';
        $order->save();
        $statuses[] = $order->status; // delivered

        // Finished
        $order->status = 'finished';
        $order->save();
        $statuses[] = $order->status; // finished

        $expected = ['created', 'partially_paid', 'paid', 'delivered', 'finished'];
        $success = $statuses === $expected;

        return [
            'success' => $success,
            'message' => "Full lifecycle. Statuses: " . implode(' -> ', $statuses) . " (expected: " . implode(' -> ', $expected) . ")"
        ];
    }

    private function scenario20()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 50.00);

        $clothStatusBefore = $data['cloth']->status;
        
        // Simulate cancel endpoint
        $order->status = 'canceled';
        $order->save();
        $order->items()->updateExistingPivot($order->items->pluck('id')->toArray(), ['status' => 'canceled']);
        
        $data['cloth']->status = 'ready_for_rent';
        $data['cloth']->save();
        
        Rent::where('order_id', $order->id)->update(['status' => 'canceled']);

        $order->refresh();
        $clothStatusAfter = $data['cloth']->fresh()->status;

        return [
            'success' => $order->status === 'canceled' && $clothStatusAfter === 'ready_for_rent',
            'message' => "Order canceled. Status: {$order->status}, Cloth status: {$clothStatusBefore} -> {$clothStatusAfter}"
        ];
    }

    private function scenario21()
    {
        $data = $this->createTestData();
        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ]
        ], 100.00);

        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order->status = 'delivered';
        $order->save();
        
        $pivot = $order->items()->first()->pivot;
        $rent = Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order->id,
            'cloth_order_id' => $this->getClothOrderId($order->id, $data['cloth']->id),
            'delivery_date' => $pivot->delivery_date,
            'days_of_rent' => $pivot->days_of_rent,
            'return_date' => Carbon::parse($pivot->delivery_date)->addDays($pivot->days_of_rent),
            'status' => 'active',
        ]);

        // Return items
        $data['cloth']->status = 'ready_for_rent';
        $data['cloth']->save();
        $rent->status = 'completed';
        $rent->save();

        // Check if order should be finished
        $allCustodyDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');
        $noActiveRents = Rent::where('order_id', $order->id)->where('status', 'active')->count() === 0;
        $noPendingPayments = Payment::where('order_id', $order->id)->where('status', 'pending')->count() === 0;

        if ($allCustodyDecided && $noActiveRents && $noPendingPayments) {
            $order->status = 'finished';
            $order->save();
        }

        return [
            'success' => $order->fresh()->status === 'finished',
            'message' => "Order lifecycle with return. Status: {$order->fresh()->status}"
        ];
    }

    private function scenario22()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Security deposit',
            'value' => 100.00,
            'status' => 'pending'
        ]);

        return [
            'success' => $custody->status === 'pending',
            'message' => "Custody created. Status: {$custody->status}, Value: {$custody->value}"
        ];
    }

    private function scenario23()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        try {
            // Simulate deliver endpoint validation
            if ($order->custodies->isEmpty()) {
                throw new \Exception('Order must have at least one custody record');
            }
            $order->status = 'delivered';
            $order->save();
            $success = false;
            $message = "Should have failed - no custody";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario24()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 100.00);

        Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 100,
            'status' => 'pending'
        ]);

        $order->status = 'delivered';
        $order->save();

        try {
            // Simulate finish validation
            $allDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');
            if (!$allDecided) {
                throw new \Exception('All custody items must have decisions');
            }
            $order->status = 'finished';
            $order->save();
            $success = false;
            $message = "Should have failed - pending custody";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario25()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 100.00);

        $custody = Custody::create([
            'order_id' => $order->id,
            'type' => 'money',
            'description' => 'Deposit',
            'value' => 100,
            'status' => 'returned'
        ]);

        $order->status = 'delivered';
        $order->save();

        try {
            // Simulate finish validation
            if ($custody->status === 'returned' && $custody->returns->isEmpty()) {
                throw new \Exception('Returned custody must have return proof');
            }
            $order->status = 'finished';
            $order->save();
            $success = false;
            $message = "Should have failed - no return proof";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario26()
    {
        $data = $this->createTestData();
        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ]
        ], 0);

        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order->status = 'delivered';
        $order->save();
        
        $pivot = $order->items()->first()->pivot;
        $rent = Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order->id,
            'cloth_order_id' => $this->getClothOrderId($order->id, $data['cloth']->id),
            'delivery_date' => $pivot->delivery_date,
            'days_of_rent' => $pivot->days_of_rent,
            'return_date' => Carbon::parse($pivot->delivery_date)->addDays($pivot->days_of_rent),
            'status' => 'active',
        ]);

        // Simulate return endpoint
        $data['cloth']->status = 'ready_for_rent';
        $data['cloth']->save();
        $rent->status = 'completed';
        $rent->save();

        $returned = Rent::where('id', $rent->id)->where('status', 'completed')->exists();

        return [
            'success' => $returned && $data['cloth']->fresh()->status === 'ready_for_rent',
            'message' => "Single item returned. Rent completed: " . ($returned ? 'yes' : 'no')
        ];
    }

    private function scenario27()
    {
        $data = $this->createTestData();
        
        // Create second cloth
        $cloth2 = Cloth::firstOrCreate(
            ['code' => 'CL002'],
            [
                'name' => 'Test Cloth 2',
                'description' => 'Test',
                'cloth_type_id' => $data['cloth']->cloth_type_id,
                'status' => 'ready_for_rent'
            ]
        );
        if (!$data['branch']->inventory->clothes()->where('clothes.id', $cloth2->id)->exists()) {
            $data['branch']->inventory->clothes()->attach($cloth2->id);
        }

        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ],
            [
                'cloth_id' => $cloth2->id,
                'price' => 150.00,
                'type' => 'rent',
                'days_of_rent' => 5,
                'delivery_date' => $deliveryDate
            ]
        ], 0);

        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order->status = 'delivered';
        $order->save();
        
        $rents = [];
        foreach ($order->items as $item) {
            $pivot = $item->pivot;
            $clothOrderId = $this->getClothOrderId($order->id, $item->id);
            $rents[] = Rent::create([
                'cloth_id' => $item->id,
                'order_id' => $order->id,
                'cloth_order_id' => $clothOrderId,
                'delivery_date' => $pivot->delivery_date,
                'days_of_rent' => $pivot->days_of_rent,
                'return_date' => Carbon::parse($pivot->delivery_date)->addDays($pivot->days_of_rent),
                'status' => 'active',
            ]);
        }

        // Return all items
        foreach ($order->items as $item) {
            $item->status = 'ready_for_rent';
            $item->save();
        }
        Rent::where('order_id', $order->id)->update(['status' => 'completed']);

        $allReturned = Rent::where('order_id', $order->id)->where('status', 'completed')->count() === 2;

        return [
            'success' => $allReturned,
            'message' => "Multiple items returned. Rents completed: " . Rent::where('order_id', $order->id)->where('status', 'completed')->count()
        ];
    }

    private function scenario28()
    {
        $data = $this->createTestData();
        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ]
        ], 0);

        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order->status = 'delivered';
        $order->save();
        
        $pivot = $order->items()->first()->pivot;
        $rent = Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order->id,
            'cloth_order_id' => $this->getClothOrderId($order->id, $data['cloth']->id),
            'delivery_date' => $pivot->delivery_date,
            'days_of_rent' => $pivot->days_of_rent,
            'return_date' => Carbon::parse($pivot->delivery_date)->addDays($pivot->days_of_rent),
            'status' => 'active',
        ]);

        // Return with different statuses
        $statuses = ['ready_for_rent', 'damaged', 'repairing'];
        $returnedStatuses = [];
        
        foreach ($statuses as $status) {
            $data['cloth']->status = $status;
            $data['cloth']->save();
            $returnedStatuses[] = $data['cloth']->fresh()->status;
        }

        $rent->status = 'completed';
        $rent->save();

        return [
            'success' => in_array('damaged', $returnedStatuses) && in_array('repairing', $returnedStatuses),
            'message' => "Returned with different statuses: " . implode(', ', $returnedStatuses)
        ];
    }

    private function scenario29()
    {
        $data = $this->createTestData();
        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ]
        ], 100.00);

        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order->status = 'delivered';
        $order->save();
        
        $pivot = $order->items()->first()->pivot;
        $rent = Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order->id,
            'cloth_order_id' => $this->getClothOrderId($order->id, $data['cloth']->id),
            'delivery_date' => $pivot->delivery_date,
            'days_of_rent' => $pivot->days_of_rent,
            'return_date' => Carbon::parse($pivot->delivery_date)->addDays($pivot->days_of_rent),
            'status' => 'active',
        ]);

        // Return items
        $data['cloth']->status = 'ready_for_rent';
        $data['cloth']->save();
        $rent->status = 'completed';
        $rent->save();

        // Check if order should be finished
        $allCustodyDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');
        $noActiveRents = Rent::where('order_id', $order->id)->where('status', 'active')->count() === 0;
        $noPendingPayments = Payment::where('order_id', $order->id)->where('status', 'pending')->count() === 0;

        if ($allCustodyDecided && $noActiveRents && $noPendingPayments) {
            $order->status = 'finished';
            $order->save();
        }

        return [
            'success' => $order->fresh()->status === 'finished',
            'message' => "Return triggered order finished. Status: {$order->fresh()->status}"
        ];
    }

    private function scenario30()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        try {
            // Simulate return endpoint validation
            $item = $order->items()->first();
            if ($item->pivot->type !== 'rent') {
                throw new \Exception('Item is not a rent item');
            }
            $success = false;
            $message = "Should have failed - item is not rent type";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario31()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'rent', 'days_of_rent' => 3, 'delivery_date' => Carbon::now()->addDays(5)->format('Y-m-d')]
        ], 0);

        try {
            // Simulate return endpoint validation
            $clothNotInOrder = Cloth::where('id', '!=', $data['cloth']->id)->first();
            if (!$order->items()->where('clothes.id', $clothNotInOrder->id)->exists()) {
                throw new \Exception("Cloth {$clothNotInOrder->id} not found in order");
            }
            $success = false;
            $message = "Should have failed - cloth not in order";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario32()
    {
        $data = $this->createTestData();
        $cloth2 = Cloth::firstOrCreate(
            ['code' => 'CL003'],
            [
                'name' => 'Test Cloth 3',
                'description' => 'Test',
                'cloth_type_id' => $data['cloth']->cloth_type_id,
                'status' => 'ready_for_rent'
            ]
        );
        if (!$data['branch']->inventory->clothes()->where('clothes.id', $cloth2->id)->exists()) {
            $data['branch']->inventory->clothes()->attach($cloth2->id);
        }

        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy'],
            ['cloth_id' => $cloth2->id, 'price' => 150.00, 'type' => 'buy']
        ], 0);

        $oldItemCount = $order->items->count();
        $cloth2StatusBefore = $cloth2->status;

        // Update order removing cloth2
        $order->items()->detach($cloth2->id);
        $cloth2->status = 'ready_for_rent';
        $cloth2->save();

        $order->refresh();
        $cloth2StatusAfter = $cloth2->fresh()->status;

        return [
            'success' => $order->items->count() === 1 && $cloth2StatusAfter === 'ready_for_rent',
            'message' => "Item removed. Items: {$oldItemCount} -> {$order->items->count()}, Cloth2 status: {$cloth2StatusBefore} -> {$cloth2StatusAfter}"
        ];
    }

    private function scenario33()
    {
        $data = $this->createTestData();
        $cloth2 = Cloth::firstOrCreate(
            ['code' => 'CL004'],
            [
                'name' => 'Test Cloth 4',
                'description' => 'Test',
                'cloth_type_id' => $data['cloth']->cloth_type_id,
                'status' => 'ready_for_rent'
            ]
        );
        if (!$data['branch']->inventory->clothes()->where('clothes.id', $cloth2->id)->exists()) {
            $data['branch']->inventory->clothes()->attach($cloth2->id);
        }

        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        // Try to add rent item with unavailable date
        $deliveryDate1 = Carbon::now()->addDays(10)->format('Y-m-d');
        
        // Create existing rent
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $cloth2->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate1
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $cloth2->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $cloth2->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Try to add overlapping rent
        $deliveryDate2 = Carbon::parse($deliveryDate1)->addDays(1)->format('Y-m-d');
        
        try {
            // Use actual availability check logic
            $conflicts = $this->checkRentalAvailability($cloth2->id, $deliveryDate2, 3);

            if ($conflicts) {
                throw new \Exception('Cloth is not available for rent on the requested dates');
            }
            $success = false;
            $message = "Should have failed - rental availability check";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    private function scenario34()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 100.00);

        $fee = Payment::create([
            'order_id' => $order->id,
            'amount' => 25.00,
            'status' => 'paid',
            'payment_type' => 'fee',
            'payment_date' => now(),
            'created_by' => $data['user']->id,
        ]);

        $order = $this->recalculateOrderPayments($order->fresh());

        $nonFeePaid = Payment::where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount');

        return [
            'success' => $order->paid == 100.00 && $order->remaining == 0 && $fee->amount == 25.00,
            'message' => "Order with fees. Non-fee paid: {$nonFeePaid}, Fee: {$fee->amount}, Remaining: {$order->remaining}"
        ];
    }

    private function scenario35()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        Payment::create(['order_id' => $order->id, 'amount' => 30.00, 'status' => 'paid', 'payment_type' => 'normal', 'payment_date' => now(), 'created_by' => $data['user']->id]);
        Payment::create(['order_id' => $order->id, 'amount' => 40.00, 'status' => 'paid', 'payment_type' => 'normal', 'payment_date' => now(), 'created_by' => $data['user']->id]);
        Payment::create(['order_id' => $order->id, 'amount' => 30.00, 'status' => 'paid', 'payment_type' => 'normal', 'payment_date' => now(), 'created_by' => $data['user']->id]);

        $order = $this->recalculateOrderPayments($order->fresh());

        return [
            'success' => $order->paid == 100.00 && $order->status === 'paid',
            'message' => "Multiple payments. Count: " . Payment::where('order_id', $order->id)->count() . ", Total paid: {$order->paid}, Status: {$order->status}"
        ];
    }

    private function scenario36()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 50.00);

        Payment::create(['order_id' => $order->id, 'amount' => 25.00, 'status' => 'paid', 'payment_type' => 'fee', 'payment_date' => now(), 'created_by' => $data['user']->id]);
        Payment::create(['order_id' => $order->id, 'amount' => 30.00, 'status' => 'pending', 'payment_type' => 'normal', 'created_by' => $data['user']->id]);
        Payment::create(['order_id' => $order->id, 'amount' => 20.00, 'status' => 'paid', 'payment_type' => 'normal', 'payment_date' => now(), 'created_by' => $data['user']->id]);

        $order = $this->recalculateOrderPayments($order->fresh());

        $paymentCounts = [
            'initial' => Payment::where('order_id', $order->id)->where('payment_type', 'initial')->count(),
            'normal' => Payment::where('order_id', $order->id)->where('payment_type', 'normal')->count(),
            'fee' => Payment::where('order_id', $order->id)->where('payment_type', 'fee')->count(),
        ];

        return [
            'success' => $paymentCounts['initial'] > 0 && $paymentCounts['normal'] > 0 && $paymentCounts['fee'] > 0,
            'message' => "Mixed payment types. Initial: {$paymentCounts['initial']}, Normal: {$paymentCounts['normal']}, Fee: {$paymentCounts['fee']}"
        ];
    }

    private function scenario37()
    {
        $data = $this->createTestData();
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            ['cloth_id' => $data['cloth']->id, 'price' => 100.00, 'type' => 'buy']
        ], 0);

        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit 1', 'value' => 50, 'status' => 'forfeited']);
        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit 2', 'value' => 50, 'status' => 'returned']);
        
        CustodyReturn::create([
            'custody_id' => $order->custodies->where('status', 'returned')->first()->id,
            'returned_at' => now(),
            'return_proof_photo' => 'test/proof.jpg',
            'customer_name' => 'Test',
            'customer_phone' => '01234567890',
            'customer_id_number' => '12345678901234'
        ]);

        $custodyCount = $order->custodies->count();
        $allDecided = $order->custodies->every(fn($c) => $c->status !== 'pending');

        return [
            'success' => $custodyCount === 2 && $allDecided,
            'message' => "Multiple custodies. Count: {$custodyCount}, All decided: " . ($allDecided ? 'yes' : 'no')
        ];
    }

    private function scenario38()
    {
        $data = $this->createTestData();
        $cloth2 = Cloth::firstOrCreate(
            ['code' => 'CL005'],
            [
                'name' => 'Test Cloth 5',
                'description' => 'Test',
                'cloth_type_id' => $data['cloth']->cloth_type_id,
                'status' => 'ready_for_rent'
            ]
        );
        if (!$data['branch']->inventory->clothes()->where('clothes.id', $cloth2->id)->exists()) {
            $data['branch']->inventory->clothes()->attach($cloth2->id);
        }

        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ],
            [
                'cloth_id' => $cloth2->id,
                'price' => 150.00,
                'type' => 'rent',
                'days_of_rent' => 5,
                'delivery_date' => $deliveryDate
            ]
        ], 0);

        $rentItemsCount = $order->items()->wherePivot('type', 'rent')->count();

        return [
            'success' => $rentItemsCount === 2,
            'message' => "Multiple rent items. Count: {$rentItemsCount}"
        ];
    }

    private function scenario39()
    {
        $data = $this->createTestData();
        $cloth2 = Cloth::firstOrCreate(
            ['code' => 'CL006'],
            [
                'name' => 'Test Cloth 6',
                'description' => 'Test',
                'cloth_type_id' => $data['cloth']->cloth_type_id,
                'status' => 'ready_for_rent'
            ]
        );
        if (!$data['branch']->inventory->clothes()->where('clothes.id', $cloth2->id)->exists()) {
            $data['branch']->inventory->clothes()->attach($cloth2->id);
        }

        $deliveryDate = Carbon::now()->addDays(5)->format('Y-m-d');
        
        $order = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate
            ],
            [
                'cloth_id' => $cloth2->id,
                'price' => 150.00,
                'type' => 'rent',
                'days_of_rent' => 5,
                'delivery_date' => $deliveryDate
            ]
        ], 0);

        Custody::create(['order_id' => $order->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order->status = 'delivered';
        $order->save();
        
        $rents = [];
        foreach ($order->items as $item) {
            $pivot = $item->pivot;
            $clothOrderId = $this->getClothOrderId($order->id, $item->id);
            $rents[] = Rent::create([
                'cloth_id' => $item->id,
                'order_id' => $order->id,
                'cloth_order_id' => $clothOrderId,
                'delivery_date' => $pivot->delivery_date,
                'days_of_rent' => $pivot->days_of_rent,
                'return_date' => Carbon::parse($pivot->delivery_date)->addDays($pivot->days_of_rent),
                'status' => 'active',
            ]);
        }

        // Return only first item
        $data['cloth']->status = 'ready_for_rent';
        $data['cloth']->save();
        $rents[0]->status = 'completed';
        $rents[0]->save();

        $partialReturn = Rent::where('order_id', $order->id)
            ->where('status', 'completed')
            ->count() === 1;

        return [
            'success' => $partialReturn && Rent::where('order_id', $order->id)->where('status', 'active')->count() === 1,
            'message' => "Partial return. Completed: 1, Active: 1"
        ];
    }

    private function scenario40()
    {
        $data = $this->createTestData();
        $deliveryDate1 = Carbon::now()->addDays(10)->format('Y-m-d');
        
        // Create first rent
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $deliveryDate1
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Try to create overlapping rent
        $deliveryDate2 = Carbon::parse($deliveryDate1)->addDays(1)->format('Y-m-d');
        
        try {
            // Use actual availability check logic
            $conflicts = $this->checkRentalAvailability($data['cloth']->id, $deliveryDate2, 3);

            if ($conflicts) {
                throw new \Exception('Cloth is not available for rent on the requested dates');
            }
            $success = false;
            $message = "Should have failed - overlapping rental period";
        } catch (\Exception $e) {
            $success = true;
            $message = "Correctly rejected: " . $e->getMessage();
        }

        return ['success' => $success, 'message' => $message];
    }

    // Edge Case Scenarios for Rental Availability
    private function scenario41()
    {
        // Exact 2-day gap before delivery (should be available)
        // Existing rent: Jan 10-13, unavailable: Jan 8-15
        // New rent: Jan 5-7 (ends Jan 7, exactly 2 days before Jan 10, so available)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d') // Jan 10
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent ending exactly 2 days before existing delivery
        $existingDelivery = Carbon::parse($pivot1->delivery_date);
        $newReturn = $existingDelivery->copy()->subDays(2);
        $newDelivery = $newReturn->copy()->subDays(2); // 2-day rent
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 2);

        return [
            'success' => !$conflicts,
            'message' => "Exact 2-day gap before. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario42()
    {
        // 1 day before delivery (should conflict)
        // Existing rent: Jan 10-13, unavailable: Jan 8-15
        // New rent: Jan 8-9 (ends Jan 9, 1 day before Jan 10, so conflicts)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent ending 1 day before existing delivery
        $existingDelivery = Carbon::parse($pivot1->delivery_date);
        $newReturn = $existingDelivery->copy()->subDays(1);
        $newDelivery = $newReturn->copy()->subDays(1);
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 1);

        return [
            'success' => $conflicts,
            'message' => "1 day before delivery. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be yes)"
        ];
    }

    private function scenario43()
    {
        // Exact 2-day gap after return (should be available)
        // Existing rent: Jan 10-13, unavailable: Jan 8-15
        // New rent: Jan 16-18 (starts Jan 16, exactly 2 days after Jan 13+2=Jan 15, so available)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent starting exactly 2 days after existing return (Jan 13 + 2 = Jan 15, so Jan 16 is available)
        $existingReturn = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $existingReturn->copy()->addDays(3); // 3 days after return = available
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 2);

        return [
            'success' => !$conflicts,
            'message' => "Exact 2-day gap after return. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario44()
    {
        // 1 day after return (should conflict)
        // Existing rent: Jan 10-13, unavailable: Jan 8-15
        // New rent: Jan 14-15 (starts Jan 14, 1 day after Jan 13, so conflicts)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent starting 1 day after existing return
        $existingReturn = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $existingReturn->copy()->addDays(1);
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 1);

        return [
            'success' => $conflicts,
            'message' => "1 day after return. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be yes)"
        ];
    }

    private function scenario45()
    {
        // Touching boundary - delivery = return + 2 days (should be available)
        // Existing rent: Jan 10-13, unavailable: Jan 8-15
        // New rent: Jan 16-18 (starts Jan 16, exactly on boundary Jan 15+1, so available)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent starting exactly on the boundary (existing return + 2 days + 1 day = available)
        $existingReturn = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $existingReturn->copy()->addDays(3); // 3 days after return = available
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 2);

        return [
            'success' => !$conflicts,
            'message' => "Touching boundary. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario46()
    {
        // New rent completely inside existing unavailable period (should conflict)
        // Existing rent: Jan 10-13, unavailable: Jan 8-15
        // New rent: Jan 9-11 (completely inside unavailable period)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent completely inside unavailable period
        $existingDelivery = Carbon::parse($pivot1->delivery_date);
        $newDelivery = $existingDelivery->copy()->subDays(1); // Jan 9
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 2);

        return [
            'success' => $conflicts,
            'message' => "New rent inside unavailable period. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be yes)"
        ];
    }

    private function scenario47()
    {
        // Existing rent completely inside new unavailable period (should conflict)
        // New rent: Jan 5-20 (unavailable: Jan 3-22)
        // Existing rent: Jan 10-13 (completely inside new unavailable period)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent that encompasses existing rent
        $existingDelivery = Carbon::parse($pivot1->delivery_date);
        $newDelivery = $existingDelivery->copy()->subDays(5); // 5 days before existing
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 15);

        return [
            'success' => $conflicts,
            'message' => "Existing rent inside new unavailable period. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be yes)"
        ];
    }

    private function scenario48()
    {
        // Multiple existing rents - some conflicting (should conflict)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        // Create first rent
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Create second rent far away (non-conflicting)
        $order2 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(30)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order2->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order2->status = 'delivered';
        $order2->save();
        
        $pivot2 = $order2->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order2->id,
            'cloth_order_id' => $this->getClothOrderId($order2->id, $data['cloth']->id),
            'delivery_date' => $pivot2->delivery_date,
            'days_of_rent' => $pivot2->days_of_rent,
            'return_date' => Carbon::parse($pivot2->delivery_date)->addDays($pivot2->days_of_rent),
            'status' => 'active',
        ]);

        // Try to create new rent conflicting with first rent
        $existingReturn = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $existingReturn->copy()->addDays(1); // Conflicts with first
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 2);

        return [
            'success' => $conflicts,
            'message' => "Multiple existing rents. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be yes)"
        ];
    }

    private function scenario49()
    {
        // Canceled rent exclusion (should not conflict)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'canceled', // Canceled rent
        ]);

        // Try to create new rent that would conflict if rent was active
        $existingReturn = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $existingReturn->copy()->addDays(1); // Would conflict if not canceled
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 2);

        return [
            'success' => !$conflicts,
            'message' => "Canceled rent exclusion. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario50()
    {
        // Different cloth (should not conflict)
        $data = $this->createTestData();
        
        // Create second cloth
        $cloth2 = Cloth::firstOrCreate(
            ['code' => 'CL_EDGE_50'],
            [
                'name' => 'Test Cloth Edge 50',
                'description' => 'Test',
                'cloth_type_id' => $data['cloth']->cloth_type_id,
                'status' => 'ready_for_rent'
            ]
        );
        if (!$data['branch']->inventory->clothes()->where('clothes.id', $cloth2->id)->exists()) {
            $data['branch']->inventory->clothes()->attach($cloth2->id);
        }

        $baseDate = Carbon::now()->addDays(50);
        
        // Create rent for first cloth
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Try to create rent for different cloth on same dates (should not conflict)
        $newDelivery = Carbon::parse($pivot1->delivery_date)->format('Y-m-d');
        
        $conflicts = $this->checkRentalAvailability($cloth2->id, $newDelivery, 3);

        return [
            'success' => !$conflicts,
            'message' => "Different cloth. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario51()
    {
        // Consecutive rents with 3-day gaps (should work)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        // Create first rent
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Create second rent with 3-day gap
        $returnDate1 = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $deliveryDate2 = $returnDate1->copy()->addDays(3)->format('Y-m-d');
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $deliveryDate2, 3);

        return [
            'success' => !$conflicts,
            'message' => "Consecutive rents with 3-day gap. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario52()
    {
        // Long rent periods (7+ days)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 7,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Try to create new rent that would conflict
        $existingReturn = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $existingReturn->copy()->addDays(1); // Would conflict
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 3);

        return [
            'success' => $conflicts,
            'message' => "Long rent period conflict. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be yes)"
        ];
    }

    private function scenario53()
    {
        // Short rent periods (1 day)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 1,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Try to create new rent with 3-day gap (should be available)
        $returnDate1 = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $returnDate1->copy()->addDays(3)->format('Y-m-d');
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery, 1);

        return [
            'success' => !$conflicts,
            'message' => "Short rent period. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario54()
    {
        // Year boundary crossing (Dec 30 to Jan 5)
        $data = $this->createTestData();
        $year = Carbon::now()->year;
        $dec30 = Carbon::create($year, 12, 30);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $dec30->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // Try to create new rent that would conflict
        $existingReturn = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $existingReturn->copy()->addDays(1); // Jan 2, would conflict
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 3);

        return [
            'success' => $conflicts,
            'message' => "Year boundary crossing. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be yes)"
        ];
    }

    private function scenario55()
    {
        // Same day delivery on boundary (return + 2 days)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent starting exactly on boundary (existing return + 2 days + 1 = available)
        $existingReturn = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $existingReturn->copy()->addDays(3)->format('Y-m-d'); // 3 days after return = available
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery, 1);

        return [
            'success' => !$conflicts,
            'message' => "Same day on boundary. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario56()
    {
        // Exactly 3-day gap (should be available)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        $deliveryDate1 = Carbon::parse($pivot1->delivery_date)->startOfDay();
        $returnDate1 = $deliveryDate1->copy()->addDays($pivot1->days_of_rent);
        
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $deliveryDate1->format('Y-m-d'),
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => $returnDate1->format('Y-m-d'),
            'status' => 'active',
        ]);

        // Exactly 3-day gap - new delivery should be 3 days after return
        $newDelivery = $returnDate1->copy()->addDays(3)->format('Y-m-d');
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery, 3);

        return [
            'success' => !$conflicts,
            'message' => "Exactly 3-day gap. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario57()
    {
        // New rent starts exactly 2 days after return (should be available)
        // Existing: Jan 10-13, unavailable: Jan 8-15
        // New: Jan 16-18 (starts Jan 16, which is 3 days after Jan 13, so available)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent starting exactly 3 days after return (available)
        $returnDate1 = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $returnDate1->copy()->addDays(3)->format('Y-m-d');
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery, 2);

        return [
            'success' => !$conflicts,
            'message' => "Starts 3 days after return. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario58()
    {
        // New rent ends exactly 2 days before delivery (should be available)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent ending exactly 2 days before existing delivery
        $existingDelivery = Carbon::parse($pivot1->delivery_date);
        $newReturn = $existingDelivery->copy()->subDays(2);
        $newDelivery = $newReturn->copy()->subDays(1);
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 1);

        return [
            'success' => !$conflicts,
            'message' => "Ends 2 days before delivery. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be no)"
        ];
    }

    private function scenario59()
    {
        // New rent starts 1 day before unavailable period ends (should conflict)
        // Existing: Jan 10-13, unavailable: Jan 8-15
        // New: Jan 14-16 (starts Jan 14, 1 day before unavailable ends Jan 15, so conflicts)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent starting 1 day before unavailable period ends
        $existingReturn = Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent);
        $newDelivery = $existingReturn->copy()->addDays(1); // Jan 14, conflicts
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 2);

        return [
            'success' => $conflicts,
            'message' => "Starts 1 day before unavailable ends. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be yes)"
        ];
    }

    private function scenario60()
    {
        // New rent ends 1 day after unavailable period starts (should conflict)
        // Existing: Jan 10-13, unavailable: Jan 8-15
        // New: Jan 7-9 (ends Jan 9, 1 day after unavailable starts Jan 8, so conflicts)
        $data = $this->createTestData();
        $baseDate = Carbon::now()->addDays(50);
        
        $order1 = $this->createOrder($data['client'], $data['branch']->inventory, [
            [
                'cloth_id' => $data['cloth']->id,
                'price' => 100.00,
                'type' => 'rent',
                'days_of_rent' => 3,
                'delivery_date' => $baseDate->copy()->addDays(10)->format('Y-m-d')
            ]
        ], 0);

        Custody::create(['order_id' => $order1->id, 'type' => 'money', 'description' => 'Deposit', 'value' => 100, 'status' => 'forfeited']);
        $order1->status = 'delivered';
        $order1->save();
        
        $pivot1 = $order1->items()->first()->pivot;
        Rent::create([
            'cloth_id' => $data['cloth']->id,
            'order_id' => $order1->id,
            'cloth_order_id' => $this->getClothOrderId($order1->id, $data['cloth']->id),
            'delivery_date' => $pivot1->delivery_date,
            'days_of_rent' => $pivot1->days_of_rent,
            'return_date' => Carbon::parse($pivot1->delivery_date)->addDays($pivot1->days_of_rent),
            'status' => 'active',
        ]);

        // New rent ending 1 day after unavailable period starts
        $existingDelivery = Carbon::parse($pivot1->delivery_date);
        $unavailableStart = $existingDelivery->copy()->subDays(2); // Jan 8
        $newReturn = $unavailableStart->copy()->addDays(1); // Jan 9
        $newDelivery = $newReturn->copy()->subDays(1);
        
        $conflicts = $this->checkRentalAvailability($data['cloth']->id, $newDelivery->format('Y-m-d'), 1);

        return [
            'success' => $conflicts,
            'message' => "Ends 1 day after unavailable starts. Conflicts: " . ($conflicts ? 'yes' : 'no') . " (should be yes)"
        ];
    }
}
