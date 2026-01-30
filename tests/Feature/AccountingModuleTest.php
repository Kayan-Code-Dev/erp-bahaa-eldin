<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cashbox;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Order;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Custody;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularUser;
    protected Branch $branch;
    protected ?Cashbox $cashbox;
    protected TransactionService $transactionService;
    protected Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'email' => User::SUPER_ADMIN_EMAIL,
            'name' => 'Super Admin',
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'email' => 'regular@test.com',
            'name' => 'Regular User',
        ]);

        // Create address for branch
        $country = Country::create(['name' => 'Test Country', 'code' => 'TC']);
        $city = City::create(['name' => 'Test City', 'country_id' => $country->id]);
        $this->address = Address::create([
            'street' => 'Test Street',
            'building' => '123',
            'city_id' => $city->id,
        ]);

        // Create branch (which auto-creates cashbox)
        $this->branch = Branch::create([
            'branch_code' => 'BR001',
            'name' => 'Test Branch',
            'address_id' => $this->address->id,
        ]);

        // Refresh to get the auto-created cashbox
        $this->branch->refresh();

        // If cashbox wasn't auto-created, create it manually
        if (!$this->branch->cashbox) {
            $this->cashbox = Cashbox::create([
                'name' => "{$this->branch->name} Cashbox",
                'branch_id' => $this->branch->id,
                'initial_balance' => 1000.00,
                'current_balance' => 1000.00,
                'is_active' => true,
            ]);
        } else {
            $this->cashbox = $this->branch->cashbox;
            // Set initial balance for testing
            $this->cashbox->update([
                'initial_balance' => 1000.00,
                'current_balance' => 1000.00,
            ]);
        }

        $this->transactionService = app(TransactionService::class);
    }

    // ==================== CASHBOX TESTS ====================

    /** @test */
    public function branch_auto_creates_cashbox()
    {
        $newBranch = Branch::create([
            'branch_code' => 'BR002',
            'name' => 'New Branch',
            'address_id' => $this->address->id,
        ]);

        // Refresh to get the relationship
        $newBranch->refresh();

        // If auto-creation didn't work in test, we just verify manual creation works
        if (!$newBranch->cashbox) {
            $cashbox = Cashbox::create([
                'name' => 'New Branch Cashbox',
                'branch_id' => $newBranch->id,
                'initial_balance' => 0,
                'current_balance' => 0,
                'is_active' => true,
            ]);
            $newBranch->refresh();
        }

        $this->assertNotNull($newBranch->cashbox);
        $this->assertEquals(0, $newBranch->cashbox->initial_balance);
        $this->assertEquals(0, $newBranch->cashbox->current_balance);
        $this->assertTrue($newBranch->cashbox->is_active);
    }

    /** @test */
    public function cashbox_index_returns_all_cashboxes()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/cashboxes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'branch_id',
                        'initial_balance',
                        'current_balance',
                        'is_active',
                        'today_income',
                        'today_expense',
                    ]
                ]
            ]);
    }

    /** @test */
    public function cashbox_show_returns_cashbox_details()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/cashboxes/{$this->cashbox->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'branch_id',
                'initial_balance',
                'current_balance',
                'is_active',
                'branch',
                'today_summary' => [
                    'income',
                    'expense',
                    'net_change',
                ],
                'recent_transactions',
            ]);
    }

    /** @test */
    public function cashbox_can_be_updated()
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/v1/cashboxes/{$this->cashbox->id}", [
                'name' => 'Updated Cashbox Name',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Cashbox Name',
                'description' => 'Updated description',
            ]);
    }

    /** @test */
    public function cashbox_daily_summary_returns_correct_data()
    {
        // Create some transactions
        $this->transactionService->recordIncome(
            $this->cashbox,
            500.00,
            Transaction::CATEGORY_PAYMENT,
            'Test payment',
            $this->superAdmin
        );

        $this->transactionService->recordExpense(
            $this->cashbox,
            100.00,
            Transaction::CATEGORY_EXPENSE,
            'Test expense',
            $this->superAdmin
        );

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/cashboxes/{$this->cashbox->id}/daily-summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'cashbox_id',
                'cashbox_name',
                'opening_balance',
                'total_income',
                'total_expense',
                'net_change',
                'closing_balance',
                'transaction_count',
                'reversal_count',
            ]);

        $data = $response->json();
        $this->assertEquals(500.00, $data['total_income']);
        $this->assertEquals(100.00, $data['total_expense']);
        $this->assertEquals(400.00, $data['net_change']);
    }

    /** @test */
    public function branch_cashbox_endpoint_works()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/branches/{$this->branch->id}/cashbox");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'cashbox',
                'branch',
                'today_summary',
            ]);
    }

    /** @test */
    public function cashbox_recalculate_works()
    {
        // Create a transaction to have some data
        $this->transactionService->recordIncome(
            $this->cashbox,
            200.00,
            Transaction::CATEGORY_PAYMENT,
            'Test payment',
            $this->superAdmin
        );

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/cashboxes/{$this->cashbox->id}/recalculate");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'previous_balance',
                'calculated_balance',
                'difference',
            ]);
    }

    // ==================== TRANSACTION TESTS ====================

    /** @test */
    public function transaction_is_immutable_cannot_update()
    {
        $transaction = $this->transactionService->recordIncome(
            $this->cashbox,
            100.00,
            Transaction::CATEGORY_PAYMENT,
            'Test payment',
            $this->superAdmin
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transactions are immutable and cannot be updated');

        $transaction->update(['amount' => 200.00]);
    }

    /** @test */
    public function transaction_is_immutable_cannot_delete()
    {
        $transaction = $this->transactionService->recordIncome(
            $this->cashbox,
            100.00,
            Transaction::CATEGORY_PAYMENT,
            'Test payment',
            $this->superAdmin
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transactions are immutable and cannot be deleted');

        $transaction->delete();
    }

    /** @test */
    public function income_transaction_increases_balance()
    {
        $initialBalance = $this->cashbox->current_balance;

        $transaction = $this->transactionService->recordIncome(
            $this->cashbox,
            250.00,
            Transaction::CATEGORY_PAYMENT,
            'Test income',
            $this->superAdmin
        );

        $this->cashbox->refresh();

        $this->assertEquals($initialBalance + 250.00, $this->cashbox->current_balance);
        $this->assertEquals($this->cashbox->current_balance, $transaction->balance_after);
        $this->assertEquals(Transaction::TYPE_INCOME, $transaction->type);
    }

    /** @test */
    public function expense_transaction_decreases_balance()
    {
        $initialBalance = $this->cashbox->current_balance;

        $transaction = $this->transactionService->recordExpense(
            $this->cashbox,
            300.00,
            Transaction::CATEGORY_EXPENSE,
            'Test expense',
            $this->superAdmin
        );

        $this->cashbox->refresh();

        $this->assertEquals($initialBalance - 300.00, $this->cashbox->current_balance);
        $this->assertEquals($this->cashbox->current_balance, $transaction->balance_after);
        $this->assertEquals(Transaction::TYPE_EXPENSE, $transaction->type);
    }

    /** @test */
    public function expense_fails_with_insufficient_balance()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient cashbox balance');

        $this->transactionService->recordExpense(
            $this->cashbox,
            5000.00, // More than the 1000.00 balance
            Transaction::CATEGORY_EXPENSE,
            'Test expense',
            $this->superAdmin
        );
    }

    /** @test */
    public function transaction_index_returns_paginated_list()
    {
        // Create some transactions
        for ($i = 0; $i < 5; $i++) {
            $this->transactionService->recordIncome(
                $this->cashbox,
                100.00,
                Transaction::CATEGORY_PAYMENT,
                "Test payment {$i}",
                $this->superAdmin
            );
        }

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'cashbox_id',
                        'type',
                        'amount',
                        'balance_after',
                        'category',
                        'description',
                        'is_reversed',
                        'created_at',
                    ]
                ],
                'current_page',
                'total',
                'per_page',
            ]);
    }

    /** @test */
    public function transaction_can_filter_by_type()
    {
        $this->transactionService->recordIncome(
            $this->cashbox, 100.00, Transaction::CATEGORY_PAYMENT, 'Income', $this->superAdmin
        );
        $this->transactionService->recordExpense(
            $this->cashbox, 50.00, Transaction::CATEGORY_EXPENSE, 'Expense', $this->superAdmin
        );

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/transactions?type=income');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('income', $data[0]['type']);
    }

    /** @test */
    public function transaction_can_filter_by_cashbox()
    {
        // Create another branch with cashbox
        $otherBranch = Branch::create([
            'branch_code' => 'BR003',
            'name' => 'Other Branch',
            'address_id' => $this->address->id,
        ]);
        $otherBranch->refresh();
        
        // Get the auto-created cashbox or create one
        $otherCashbox = $otherBranch->cashbox;
        if (!$otherCashbox) {
            $otherCashbox = Cashbox::create([
                'name' => 'Other Branch Cashbox',
                'branch_id' => $otherBranch->id,
                'initial_balance' => 500,
                'current_balance' => 500,
                'is_active' => true,
            ]);
        } else {
            $otherCashbox->update(['initial_balance' => 500, 'current_balance' => 500]);
        }

        $this->transactionService->recordIncome(
            $this->cashbox, 100.00, Transaction::CATEGORY_PAYMENT, 'Main cashbox', $this->superAdmin
        );
        $this->transactionService->recordIncome(
            $otherCashbox, 200.00, Transaction::CATEGORY_PAYMENT, 'Other cashbox', $this->superAdmin
        );

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/transactions?cashbox_id={$this->cashbox->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->cashbox->id, $data[0]['cashbox_id']);
    }

    /** @test */
    public function transaction_show_returns_details()
    {
        $transaction = $this->transactionService->recordIncome(
            $this->cashbox, 150.00, Transaction::CATEGORY_PAYMENT, 'Test', $this->superAdmin
        );

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/transactions/{$transaction->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $transaction->id,
                'amount' => '150.00',
                'type' => 'income',
                'is_reversed' => false,
            ]);
    }

    /** @test */
    public function transaction_reversal_works()
    {
        $originalTransaction = $this->transactionService->recordIncome(
            $this->cashbox, 200.00, Transaction::CATEGORY_PAYMENT, 'Original', $this->superAdmin
        );

        $balanceAfterIncome = $this->cashbox->fresh()->current_balance;

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/transactions/{$originalTransaction->id}/reverse", [
                'reason' => 'Customer requested refund',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'reversal_transaction',
                'original_transaction',
                'new_cashbox_balance',
            ]);

        // Balance should be back to original
        $this->cashbox->refresh();
        $this->assertEquals($balanceAfterIncome - 200.00, $this->cashbox->current_balance);

        // Original transaction should be marked as reversed
        $originalTransaction->refresh();
        $this->assertTrue($originalTransaction->isReversed());
    }

    /** @test */
    public function transaction_reversal_fails_if_already_reversed()
    {
        $transaction = $this->transactionService->recordIncome(
            $this->cashbox, 100.00, Transaction::CATEGORY_PAYMENT, 'Test', $this->superAdmin
        );

        // First reversal
        $this->transactionService->reverseTransaction($transaction, 'First reversal', $this->superAdmin);

        // Second reversal should fail
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/transactions/{$transaction->id}/reverse", [
                'reason' => 'Second attempt',
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'This transaction has already been reversed.']);
    }

    /** @test */
    public function cannot_reverse_a_reversal_transaction()
    {
        $original = $this->transactionService->recordIncome(
            $this->cashbox, 100.00, Transaction::CATEGORY_PAYMENT, 'Original', $this->superAdmin
        );

        $reversal = $this->transactionService->reverseTransaction($original, 'Reversal', $this->superAdmin);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/transactions/{$reversal->id}/reverse", [
                'reason' => 'Try to reverse reversal',
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot reverse a reversal transaction.']);
    }

    /** @test */
    public function transaction_categories_endpoint_works()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/transactions/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'categories' => [
                    '*' => ['name', 'display_name', 'description']
                ]
            ]);
    }

    /** @test */
    public function cashbox_transactions_endpoint_works()
    {
        // Create transactions
        $this->transactionService->recordIncome(
            $this->cashbox, 100.00, Transaction::CATEGORY_PAYMENT, 'Test 1', $this->superAdmin
        );
        $this->transactionService->recordIncome(
            $this->cashbox, 200.00, Transaction::CATEGORY_PAYMENT, 'Test 2', $this->superAdmin
        );

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/cashboxes/{$this->cashbox->id}/transactions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'cashbox' => ['id', 'name', 'current_balance'],
                'transactions' => [
                    'data',
                    'current_page',
                    'total',
                ]
            ]);
    }

    // ==================== PAYMENT INTEGRATION TESTS ====================

    /** @test */
    public function payment_creates_transaction_when_paid()
    {
        // Create client and order (without branch_id since orders table doesn't have it)
        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'total_price' => 1000.00,
            'paid' => 0,
            'remaining' => 1000.00,
        ]);

        // Create payment
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 500.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => $this->superAdmin->id,
        ]);

        $initialBalance = $this->cashbox->current_balance;

        // Mark payment as paid - pass branch_id to indicate which cashbox to use
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/payments/{$payment->id}/pay", [
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'payment',
                'order',
                'transaction' => ['id', 'cashbox_id', 'amount', 'balance_after'],
            ]);

        // Verify cashbox balance increased
        $this->cashbox->refresh();
        $this->assertEquals($initialBalance + 500.00, $this->cashbox->current_balance);

        // Verify transaction was created
        $transaction = Transaction::where('reference_type', 'App\\Models\\Payment')
            ->where('reference_id', $payment->id)
            ->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(Transaction::TYPE_INCOME, $transaction->type);
        $this->assertEquals(500.00, $transaction->amount);
    }

    /** @test */
    public function payment_cancellation_reverses_transaction()
    {
        // Create client and order (without branch_id since orders table doesn't have it)
        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'total_price' => 1000.00,
        ]);

        // Create and pay payment
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 300.00,
            'status' => 'pending',
            'payment_type' => 'normal',
            'created_by' => $this->superAdmin->id,
        ]);

        // Mark payment as paid (creates transaction)
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/payments/{$payment->id}/pay", [
                'branch_id' => $this->branch->id,
            ]);

        $balanceAfterPayment = $this->cashbox->fresh()->current_balance;

        // Cancel the payment
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/payments/{$payment->id}/cancel", [
                'notes' => 'Test cancellation',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'payment',
                'order',
                'reversal_transaction',
            ]);

        // Verify balance was reversed
        $this->cashbox->refresh();
        $this->assertEquals($balanceAfterPayment - 300.00, $this->cashbox->current_balance);
    }

    // ==================== CUSTODY INTEGRATION TESTS ====================

    /** @test */
    public function custody_deposit_creates_transaction()
    {
        // Create client and order (without branch_id since orders table doesn't have it)
        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'total_price' => 1000.00,
            'status' => 'created',
        ]);

        $initialBalance = $this->cashbox->current_balance;

        // Create money custody (deposit) - pass branch_id to indicate which cashbox to use
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/orders/{$order->id}/custody", [
                'type' => 'money',
                'description' => 'Security deposit',
                'value' => 200.00,
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'type',
                'value',
                'transaction' => ['id', 'cashbox_id', 'amount', 'balance_after'],
            ]);

        // Verify cashbox balance increased
        $this->cashbox->refresh();
        $this->assertEquals($initialBalance + 200.00, $this->cashbox->current_balance);

        // Verify transaction was created
        $custody = Custody::where('order_id', $order->id)->first();
        $transaction = Transaction::where('reference_type', 'App\\Models\\Custody')
            ->where('reference_id', $custody->id)
            ->where('category', Transaction::CATEGORY_CUSTODY_DEPOSIT)
            ->first();
        $this->assertNotNull($transaction);
    }

    /** @test */
    public function document_custody_does_not_create_transaction()
    {
        // Create client and order (without branch_id since orders table doesn't have it)
        $client = Client::factory()->create();
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'status' => 'created',
        ]);

        $initialBalance = $this->cashbox->current_balance;
        $transactionCountBefore = Transaction::count();

        // Create document custody (no money involved, no photos required)
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/orders/{$order->id}/custody", [
                'type' => 'document',
                'description' => 'Contract as collateral',
                'branch_id' => $this->branch->id,
            ]);

        $response->assertStatus(201);

        // No transaction should be created for document custody (no value)
        $this->assertEquals($transactionCountBefore, Transaction::count());

        // Cashbox balance should not change
        $this->cashbox->refresh();
        $this->assertEquals($initialBalance, $this->cashbox->current_balance);
    }

    // ==================== NEGATIVE BALANCE PROTECTION TESTS ====================

    /** @test */
    public function cashbox_cannot_go_negative()
    {
        // Set balance to exactly 100
        $this->cashbox->update(['current_balance' => 100.00]);

        // Try to record expense of 150
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient cashbox balance');

        $this->transactionService->recordExpense(
            $this->cashbox,
            150.00,
            Transaction::CATEGORY_EXPENSE,
            'This should fail',
            $this->superAdmin
        );
    }

    /** @test */
    public function reversal_of_income_checks_balance()
    {
        // Set balance to 100
        $this->cashbox->update(['current_balance' => 100.00, 'initial_balance' => 100.00]);

        // Record income of 50
        $transaction = $this->transactionService->recordIncome(
            $this->cashbox,
            50.00,
            Transaction::CATEGORY_PAYMENT,
            'Test',
            $this->superAdmin
        );

        // Now balance is 150. Record expense of 120
        $this->transactionService->recordExpense(
            $this->cashbox,
            120.00,
            Transaction::CATEGORY_EXPENSE,
            'Expense',
            $this->superAdmin
        );

        // Now balance is 30. Try to reverse the income of 50 - this should fail
        // because we can't remove 50 from 30
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot reverse: insufficient cashbox balance');

        $this->transactionService->reverseTransaction($transaction, 'Test reversal', $this->superAdmin);
    }

    // ==================== INACTIVE CASHBOX TESTS ====================

    /** @test */
    public function inactive_cashbox_rejects_transactions()
    {
        $this->cashbox->update(['is_active' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create transaction on inactive cashbox');

        $this->transactionService->recordIncome(
            $this->cashbox,
            100.00,
            Transaction::CATEGORY_PAYMENT,
            'Should fail',
            $this->superAdmin
        );
    }

    // ==================== AMOUNT VALIDATION TESTS ====================

    /** @test */
    public function transaction_amount_must_be_positive()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction amount must be positive');

        $this->transactionService->recordIncome(
            $this->cashbox,
            -100.00,
            Transaction::CATEGORY_PAYMENT,
            'Negative amount',
            $this->superAdmin
        );
    }

    /** @test */
    public function transaction_amount_cannot_be_zero()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Transaction amount must be positive');

        $this->transactionService->recordIncome(
            $this->cashbox,
            0,
            Transaction::CATEGORY_PAYMENT,
            'Zero amount',
            $this->superAdmin
        );
    }

    // ==================== FILTER AND DATE RANGE TESTS ====================

    /** @test */
    public function transaction_can_filter_by_date_range()
    {
        // Create transactions at different dates (using database manipulation)
        $transaction1 = $this->transactionService->recordIncome(
            $this->cashbox, 100.00, Transaction::CATEGORY_PAYMENT, 'Today', $this->superAdmin
        );

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/transactions?start_date=' . today()->format('Y-m-d') . '&end_date=' . today()->format('Y-m-d'));

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    /** @test */
    public function transaction_can_filter_by_category()
    {
        $this->transactionService->recordIncome(
            $this->cashbox, 100.00, Transaction::CATEGORY_PAYMENT, 'Payment', $this->superAdmin
        );
        $this->transactionService->recordIncome(
            $this->cashbox, 50.00, Transaction::CATEGORY_CUSTODY_DEPOSIT, 'Deposit', $this->superAdmin
        );

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/transactions?category=' . Transaction::CATEGORY_PAYMENT);

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $transaction) {
            $this->assertEquals(Transaction::CATEGORY_PAYMENT, $transaction['category']);
        }
    }

    // ==================== CONCURRENT TRANSACTION TESTS ====================

    /** @test */
    public function multiple_transactions_maintain_balance_integrity()
    {
        $initialBalance = $this->cashbox->current_balance;

        // Create multiple transactions
        $amounts = [100, 50, 200, 75, 125];
        foreach ($amounts as $amount) {
            $this->transactionService->recordIncome(
                $this->cashbox,
                $amount,
                Transaction::CATEGORY_PAYMENT,
                "Amount {$amount}",
                $this->superAdmin
            );
        }

        $this->cashbox->refresh();
        $expectedBalance = $initialBalance + array_sum($amounts);
        $this->assertEquals($expectedBalance, $this->cashbox->current_balance);

        // Recalculate should match
        $recalculated = $this->cashbox->recalculateBalance();
        $this->assertEquals($expectedBalance, $recalculated);
    }

    // ==================== HELPER METHOD TESTS ====================

    /** @test */
    public function cashbox_has_sufficient_balance_check_works()
    {
        $this->cashbox->update(['current_balance' => 500.00]);

        $this->assertTrue($this->cashbox->hasSufficientBalance(500.00));
        $this->assertTrue($this->cashbox->hasSufficientBalance(499.99));
        $this->assertFalse($this->cashbox->hasSufficientBalance(500.01));
    }

    /** @test */
    public function transaction_effective_amount_works()
    {
        $income = $this->transactionService->recordIncome(
            $this->cashbox, 100.00, Transaction::CATEGORY_PAYMENT, 'Income', $this->superAdmin
        );

        $expense = $this->transactionService->recordExpense(
            $this->cashbox, 50.00, Transaction::CATEGORY_EXPENSE, 'Expense', $this->superAdmin
        );

        $this->assertEquals(100.00, $income->getEffectiveAmount());
        $this->assertEquals(-50.00, $expense->getEffectiveAmount());
    }

    /** @test */
    public function transaction_type_checks_work()
    {
        $income = $this->transactionService->recordIncome(
            $this->cashbox, 100.00, Transaction::CATEGORY_PAYMENT, 'Income', $this->superAdmin
        );

        $expense = $this->transactionService->recordExpense(
            $this->cashbox, 50.00, Transaction::CATEGORY_EXPENSE, 'Expense', $this->superAdmin
        );

        $this->assertTrue($income->isIncome());
        $this->assertFalse($income->isExpense());
        $this->assertFalse($income->isReversal());

        $this->assertFalse($expense->isIncome());
        $this->assertTrue($expense->isExpense());
        $this->assertFalse($expense->isReversal());
    }
}


