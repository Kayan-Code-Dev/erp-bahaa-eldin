<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Branch;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Client;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExpensesAndReceivablesTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Branch $branch;
    protected Cashbox $cashbox;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'email' => User::SUPER_ADMIN_EMAIL,
        ]);

        // Create location hierarchy
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);

        // Create branch (auto-creates cashbox)
        $this->branch = Branch::factory()->create(['address_id' => $address->id]);
        $this->branch->refresh();
        $this->cashbox = $this->branch->cashbox;

        // Fund the cashbox for expense tests
        $this->cashbox->update(['current_balance' => 5000.00]);

        // Create a client for receivables
        $this->client = Client::factory()->create();
    }

    // ==================== EXPENSE TESTS ====================

    /** @test */
    public function can_create_expense()
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/expenses', [
            'branch_id' => $this->branch->id,
            'category' => 'utilities',
            'subcategory' => 'electricity',
            'amount' => 250.00,
            'expense_date' => now()->format('Y-m-d'),
            'vendor' => 'Electric Company',
            'reference_number' => 'INV-12345',
            'description' => 'Monthly electricity bill',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'expense' => ['id', 'cashbox_id', 'branch_id', 'category', 'amount', 'status'],
            ]);

        $this->assertDatabaseHas('expenses', [
            'branch_id' => $this->branch->id,
            'category' => 'utilities',
            'amount' => 250.00,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function expense_starts_in_pending_status()
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/expenses', [
            'branch_id' => $this->branch->id,
            'category' => 'rent',
            'amount' => 1000.00,
            'expense_date' => now()->format('Y-m-d'),
            'description' => 'Office rent',
        ]);

        $response->assertStatus(201);
        $expense = Expense::find($response->json('expense.id'));
        $this->assertEquals(Expense::STATUS_PENDING, $expense->status);
    }

    /** @test */
    public function can_approve_pending_expense()
    {
        $expense = Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'supplies',
            'amount' => 100.00,
            'expense_date' => now(),
            'description' => 'Office supplies',
            'status' => Expense::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/expenses/{$expense->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Expense approved successfully']);

        $expense->refresh();
        $this->assertEquals(Expense::STATUS_APPROVED, $expense->status);
        $this->assertEquals($this->superAdmin->id, $expense->approved_by);
        $this->assertNotNull($expense->approved_at);
    }

    /** @test */
    public function cannot_approve_non_pending_expense()
    {
        $expense = Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'supplies',
            'amount' => 100.00,
            'expense_date' => now(),
            'description' => 'Office supplies',
            'status' => Expense::STATUS_APPROVED,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/expenses/{$expense->id}/approve");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Expense cannot be approved']);
    }

    /** @test */
    public function can_pay_approved_expense()
    {
        $expense = Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'supplies',
            'amount' => 100.00,
            'expense_date' => now(),
            'description' => 'Office supplies',
            'status' => Expense::STATUS_APPROVED,
            'created_by' => $this->superAdmin->id,
        ]);

        $initialBalance = $this->cashbox->current_balance;

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/expenses/{$expense->id}/pay");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Expense paid successfully'])
            ->assertJsonStructure(['expense', 'transaction']);

        $expense->refresh();
        $this->assertEquals(Expense::STATUS_PAID, $expense->status);
        $this->assertNotNull($expense->transaction_id);

        // Check cashbox balance decreased
        $this->cashbox->refresh();
        $this->assertEquals($initialBalance - 100.00, $this->cashbox->current_balance);
    }

    /** @test */
    public function cannot_pay_pending_expense()
    {
        $expense = Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'supplies',
            'amount' => 100.00,
            'expense_date' => now(),
            'description' => 'Office supplies',
            'status' => Expense::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/expenses/{$expense->id}/pay");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Expense cannot be paid']);
    }

    /** @test */
    public function paying_expense_fails_with_insufficient_balance()
    {
        // Create an expense larger than cashbox balance
        $expense = Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'rent',
            'amount' => 10000.00, // More than 5000.00 balance
            'expense_date' => now(),
            'description' => 'Large expense',
            'status' => Expense::STATUS_APPROVED,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/expenses/{$expense->id}/pay");

        $response->assertStatus(422);
        // Check that the message contains the insufficient balance error
        $message = $response->json('message');
        $this->assertStringContainsString('Insufficient cashbox balance', $message);
    }

    /** @test */
    public function can_cancel_pending_expense()
    {
        $expense = Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'supplies',
            'amount' => 100.00,
            'expense_date' => now(),
            'description' => 'Office supplies',
            'status' => Expense::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/expenses/{$expense->id}/cancel", [
                'reason' => 'Duplicate entry',
            ]);

        $response->assertStatus(200);
        $expense->refresh();
        $this->assertEquals(Expense::STATUS_CANCELLED, $expense->status);
        $this->assertStringContains('Cancelled: Duplicate entry', $expense->notes);
    }

    /** @test */
    public function cannot_cancel_paid_expense()
    {
        $expense = Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'supplies',
            'amount' => 100.00,
            'expense_date' => now(),
            'description' => 'Office supplies',
            'status' => Expense::STATUS_PAID,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/expenses/{$expense->id}/cancel");

        $response->assertStatus(422);
    }

    /** @test */
    public function can_list_expenses_with_filters()
    {
        // Create multiple expenses
        Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'utilities',
            'amount' => 100.00,
            'expense_date' => now(),
            'description' => 'Electricity',
            'status' => Expense::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'rent',
            'amount' => 500.00,
            'expense_date' => now(),
            'description' => 'Rent',
            'status' => Expense::STATUS_PAID,
            'created_by' => $this->superAdmin->id,
        ]);

        // Filter by category
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/expenses?category=utilities');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Filter by status
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/expenses?status=paid');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function can_get_expense_categories()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/expenses/categories');

        $response->assertStatus(200)
            ->assertJsonStructure(['categories']);
    }

    /** @test */
    public function can_get_expense_summary()
    {
        // Create expenses with 'paid' status
        Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'utilities',
            'amount' => 100.00,
            'expense_date' => now(),
            'description' => 'Electricity',
            'status' => Expense::STATUS_PAID,
            'created_by' => $this->superAdmin->id,
        ]);

        Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'rent',
            'amount' => 500.00,
            'expense_date' => now(),
            'description' => 'Rent',
            'status' => Expense::STATUS_PAID,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create pending expense (should not count in total_paid)
        Expense::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'category' => 'supplies',
            'amount' => 50.00,
            'expense_date' => now(),
            'description' => 'Pending Supplies',
            'status' => Expense::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/expenses/summary?start_date=' . now()->subMonth()->format('Y-m-d') . '&end_date=' . now()->addDay()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonStructure(['period', 'total_paid', 'by_category', 'by_status']);

        // Only paid expenses should count
        $this->assertEquals(600.00, (float) $response->json('total_paid'));
    }

    // ==================== RECEIVABLE TESTS ====================

    /** @test */
    public function can_create_receivable()
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/receivables', [
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 1000.00,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'description' => 'Outstanding balance for rental order',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'receivable' => ['id', 'client_id', 'original_amount', 'paid_amount', 'remaining_amount', 'status'],
            ]);

        $this->assertDatabaseHas('receivables', [
            'client_id' => $this->client->id,
            'original_amount' => 1000.00,
            'remaining_amount' => 1000.00,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function receivable_auto_calculates_remaining_amount()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertEquals(500.00, $receivable->remaining_amount);
    }

    /** @test */
    public function can_record_payment_against_receivable()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $initialBalance = $this->cashbox->current_balance;

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/receivables/{$receivable->id}/record-payment", [
                'amount' => 200.00,
                'payment_method' => 'cash',
                'notes' => 'Partial payment',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'receivable', 'payment']);

        $receivable->refresh();
        $this->assertEquals(200.00, $receivable->paid_amount);
        $this->assertEquals(300.00, $receivable->remaining_amount);
        $this->assertEquals(Receivable::STATUS_PARTIAL, $receivable->status);

        // Check cashbox balance increased
        $this->cashbox->refresh();
        $this->assertEquals($initialBalance + 200.00, $this->cashbox->current_balance);
    }

    /** @test */
    public function receivable_becomes_paid_when_fully_paid()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/receivables/{$receivable->id}/record-payment", [
                'amount' => 500.00,
            ]);

        $response->assertStatus(200);

        $receivable->refresh();
        $this->assertEquals(500.00, $receivable->paid_amount);
        $this->assertEquals(0, $receivable->remaining_amount);
        $this->assertEquals(Receivable::STATUS_PAID, $receivable->status);
    }

    /** @test */
    public function cannot_overpay_receivable()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/receivables/{$receivable->id}/record-payment", [
                'amount' => 600.00, // More than remaining
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_record_payment_on_paid_receivable()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 500.00,
            'remaining_amount' => 0,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PAID,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/receivables/{$receivable->id}/record-payment", [
                'amount' => 100.00,
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Receivable is already fully paid']);
    }

    /** @test */
    public function can_write_off_receivable()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 100.00,
            'remaining_amount' => 400.00,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PARTIAL,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/receivables/{$receivable->id}/write-off", [
                'reason' => 'Customer unable to pay',
            ]);

        $response->assertStatus(200);

        $receivable->refresh();
        $this->assertEquals(Receivable::STATUS_WRITTEN_OFF, $receivable->status);
        $this->assertStringContains('Written off: Customer unable to pay', $receivable->notes);
    }

    /** @test */
    public function receivable_is_overdue_check_works()
    {
        // Not overdue (future date)
        $receivable1 = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'due_date' => now()->addDays(30),
            'description' => 'Future debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        // Overdue (past date)
        $receivable2 = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'due_date' => now()->subDays(10),
            'description' => 'Overdue debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertFalse($receivable1->isOverdue());
        $this->assertTrue($receivable2->isOverdue());
    }

    /** @test */
    public function can_list_overdue_receivables()
    {
        // Create overdue receivable
        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'due_date' => now()->subDays(10),
            'description' => 'Overdue debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        // Create non-overdue receivable
        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 300.00,
            'paid_amount' => 0,
            'remaining_amount' => 300.00,
            'due_date' => now()->addDays(30),
            'description' => 'Future debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/receivables?overdue_only=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function can_get_receivables_summary()
    {
        // Create receivables
        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'due_date' => now()->subDays(10),
            'description' => 'Overdue debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 300.00,
            'paid_amount' => 100.00,
            'remaining_amount' => 200.00,
            'description' => 'Partial debt',
            'status' => Receivable::STATUS_PARTIAL,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/receivables/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_outstanding',
                'total_overdue',
                'overdue_count',
                'due_soon',
                'due_soon_count',
                'by_status',
            ]);

        $this->assertEquals(700.00, $response->json('total_outstanding'));
    }

    /** @test */
    public function can_get_client_receivables()
    {
        // Create receivables for our client
        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'description' => 'Client debt 1',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 300.00,
            'paid_amount' => 300.00,
            'remaining_amount' => 0,
            'description' => 'Client debt 2',
            'status' => Receivable::STATUS_PAID,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/clients/{$this->client->id}/receivables");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
        
        // Use assertEquals for floating point comparison
        $this->assertEquals(500.00, (float) $response->json('total_outstanding'));
    }

    /** @test */
    public function receivable_payment_percentage_calculation()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 400.00,
            'paid_amount' => 100.00,
            'remaining_amount' => 300.00,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PARTIAL,
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertEquals(25.00, $receivable->getPaymentPercentage());
    }

    /** @test */
    public function receivable_payments_create_transactions()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $transactionCountBefore = Transaction::count();

        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/receivables/{$receivable->id}/record-payment", [
                'amount' => 200.00,
                'payment_method' => 'cash',
            ]);

        // A transaction should be created
        $this->assertEquals($transactionCountBefore + 1, Transaction::count());

        $transaction = Transaction::latest()->first();
        $this->assertEquals('receivable_payment', $transaction->category);
        $this->assertEquals(200.00, $transaction->amount);
        $this->assertEquals(Transaction::TYPE_INCOME, $transaction->type);
    }

    /** @test */
    public function cannot_delete_receivable_with_payments()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 100.00,
            'remaining_amount' => 400.00,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PARTIAL,
            'created_by' => $this->superAdmin->id,
        ]);

        // Add a payment record
        ReceivablePayment::create([
            'receivable_id' => $receivable->id,
            'amount' => 100.00,
            'payment_date' => now(),
            'payment_method' => 'cash',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/receivables/{$receivable->id}");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Receivables with payments cannot be deleted']);
    }

    /** @test */
    public function can_delete_receivable_without_payments()
    {
        $receivable = Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500.00,
            'paid_amount' => 0,
            'remaining_amount' => 500.00,
            'description' => 'Test debt',
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/receivables/{$receivable->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('receivables', ['id' => $receivable->id]);
    }

    // ==================== INTEGRATION TESTS ====================

    /** @test */
    public function expense_workflow_creates_proper_audit_trail()
    {
        // Create expense
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/expenses', [
            'branch_id' => $this->branch->id,
            'category' => 'utilities',
            'amount' => 150.00,
            'expense_date' => now()->format('Y-m-d'),
            'description' => 'Test expense',
        ]);

        $expense = Expense::find($response->json('expense.id'));
        $this->assertEquals(Expense::STATUS_PENDING, $expense->status);

        // Approve
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/expenses/{$expense->id}/approve");

        $expense->refresh();
        $this->assertEquals(Expense::STATUS_APPROVED, $expense->status);
        $this->assertNotNull($expense->approved_at);

        // Pay
        $initialBalance = $this->cashbox->current_balance;
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/expenses/{$expense->id}/pay");

        $expense->refresh();
        $this->assertEquals(Expense::STATUS_PAID, $expense->status);
        $this->assertNotNull($expense->transaction_id);

        // Check transaction
        $transaction = Transaction::find($expense->transaction_id);
        $this->assertEquals(Transaction::CATEGORY_EXPENSE, $transaction->category);
        $this->assertEquals(150.00, $transaction->amount);
        $this->assertEquals($initialBalance - 150.00, $transaction->balance_after);
    }

    /** @test */
    public function receivable_workflow_creates_proper_audit_trail()
    {
        // Create receivable
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/receivables', [
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 1000.00,
            'description' => 'Test receivable',
        ]);

        $receivable = Receivable::find($response->json('receivable.id'));
        $this->assertEquals(1000.00, $receivable->remaining_amount);

        // First payment
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/receivables/{$receivable->id}/record-payment", [
                'amount' => 400.00,
            ]);

        $receivable->refresh();
        $this->assertEquals(400.00, $receivable->paid_amount);
        $this->assertEquals(600.00, $receivable->remaining_amount);
        $this->assertEquals(Receivable::STATUS_PARTIAL, $receivable->status);
        $this->assertEquals(1, $receivable->payments()->count());

        // Second payment
        $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/receivables/{$receivable->id}/record-payment", [
                'amount' => 600.00,
            ]);

        $receivable->refresh();
        $this->assertEquals(1000.00, $receivable->paid_amount);
        $this->assertEquals(0, $receivable->remaining_amount);
        $this->assertEquals(Receivable::STATUS_PAID, $receivable->status);
        $this->assertEquals(2, $receivable->payments()->count());
    }

    /**
     * Helper to check if string contains substring
     */
    protected function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack ?? '', $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}


