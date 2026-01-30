<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Order;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Factory;
use App\Models\FactoryEvaluation;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Cashbox;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\Receivable;
use App\Models\Rent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Client $client;
    protected Cloth $cloth;
    protected Factory $factory;
    protected Order $order;
    protected Cashbox $cashbox;

    protected function setUp(): void
    {
        parent::setUp();

        // Create base entities
        $country = Country::create(['name' => 'Egypt']);
        $city = City::create(['name' => 'Cairo', 'country_id' => $country->id]);
        $address = Address::create([
            'street' => 'Test Street',
            'building' => 'Building 1',
            'city_id' => $city->id,
        ]);

        // Create branch with cashbox
        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'branch_code' => 'TB001',
            'address_id' => $address->id,
        ]);

        $this->branch->inventory()->create(['name' => 'Branch Inventory']);
        
        // Get or create cashbox for the branch
        $this->cashbox = Cashbox::firstOrCreate(
            ['branch_id' => $this->branch->id],
            [
                'name' => 'Branch Cashbox',
                'current_balance' => 10000,
                'initial_balance' => 10000,
            ]
        );
        
        // Update balance if it was auto-created with 0
        $this->cashbox->update([
            'current_balance' => 10000,
            'initial_balance' => 10000,
        ]);

        // Create super admin user
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
        ]);

        // Create client
        $this->client = Client::factory()->create();

        // Create cloth type and cloth
        $clothType = ClothType::create([
            'code' => 'DR001',
            'name' => 'Dress',
            'description' => 'Test dress type',
        ]);

        $this->cloth = Cloth::factory()->create([
            'cloth_type_id' => $clothType->id,
            'status' => 'ready_for_rent',
        ]);

        $this->cloth->inventories()->attach($this->branch->inventory->id);

        // Create order
        $this->order = Order::create([
            'client_id' => $this->client->id,
            'inventory_id' => $this->branch->inventory->id,
            'total_price' => 1000,
            'paid' => 500,
            'remaining' => 500,
            'status' => 'pending',
        ]);

        // Attach cloth to order
        $this->order->items()->attach($this->cloth->id, [
            'price' => 1000,
            'type' => 'rent',
            'status' => 'created',
        ]);
    }

    // ==================== AVAILABLE DRESSES REPORT ====================

    /** @test */
    public function can_get_available_dresses_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/available-dresses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_available',
                'by_status',
                'items',
                'generated_at',
            ]);
    }

    /** @test */
    public function can_filter_available_dresses_by_branch()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reports/available-dresses?branch_id={$this->branch->id}");

        $response->assertStatus(200);
    }

    // ==================== OUT OF BRANCH REPORT ====================

    /** @test */
    public function can_get_out_of_branch_report()
    {
        // Set cloth as rented
        $this->cloth->update(['status' => 'rented']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/out-of-branch');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_out',
                'items',
                'generated_at',
            ]);
    }

    // ==================== OVERDUE RETURNS REPORT ====================

    /** @test */
    public function can_get_overdue_returns_report()
    {
        // Create overdue rental
        Rent::create([
            'order_id' => $this->order->id,
            'cloth_id' => $this->cloth->id,
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'delivery_date' => now()->subDays(10),
            'return_date' => now()->subDays(3),
            'appointment_type' => 'rental_return',
            'status' => 'scheduled',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/overdue-returns');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_overdue',
                'items',
                'generated_at',
            ]);
    }

    // ==================== MOST RENTED REPORT ====================

    /** @test */
    public function can_get_most_rented_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/most-rented');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period' => ['start_date', 'end_date'],
                'total_items',
                'items',
                'generated_at',
            ]);
    }

    /** @test */
    public function can_filter_most_rented_by_date_range()
    {
        $startDate = now()->subMonths(3)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reports/most-rented?start_date={$startDate}&end_date={$endDate}&limit=10");

        $response->assertStatus(200)
            ->assertJsonPath('period.start_date', $startDate)
            ->assertJsonPath('period.end_date', $endDate);
    }

    // ==================== MOST SOLD REPORT ====================

    /** @test */
    public function can_get_most_sold_report()
    {
        // Add tailoring order
        $this->order->items()->attach($this->cloth->id, [
            'price' => 500,
            'type' => 'tailoring',
            'status' => 'created',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/most-sold');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'total_items',
                'items',
                'generated_at',
            ]);
    }

    // ==================== RENTAL PROFITS REPORT ====================

    /** @test */
    public function can_get_rental_profits_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/rental-profits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period' => ['start_date', 'end_date', 'grouped_by'],
                'summary' => ['total_rentals', 'gross_revenue', 'total_discounts', 'net_revenue'],
                'breakdown',
                'generated_at',
            ]);
    }

    /** @test */
    public function can_group_rental_profits_by_day()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/rental-profits?group_by=day');

        $response->assertStatus(200)
            ->assertJsonPath('period.grouped_by', 'day');
    }

    // ==================== TAILORING PROFITS REPORT ====================

    /** @test */
    public function can_get_tailoring_profits_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/tailoring-profits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'summary',
                'breakdown',
                'generated_at',
            ]);
    }

    // ==================== FACTORY EVALUATIONS REPORT ====================

    /** @test */
    public function can_get_factory_evaluations_report()
    {
        // Create evaluation
        FactoryEvaluation::create([
            'factory_id' => $this->factory->id,
            'quality_rating' => 4,
            'on_time' => true,
            'evaluated_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/factory-evaluations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'summary',
                'factories',
                'generated_at',
            ]);
    }

    /** @test */
    public function can_filter_factory_evaluations_by_factory()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reports/factory-evaluations?factory_id={$this->factory->id}");

        $response->assertStatus(200);
    }

    // ==================== EMPLOYEE ORDERS REPORT ====================

    /** @test */
    public function can_get_employee_orders_report()
    {
        // Create payment with created_by
        Payment::create([
            'order_id' => $this->order->id,
            'amount' => 500,
            'type' => 'cash',
            'status' => 'paid',
            'paid_at' => now(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/employee-orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'summary' => ['total_employees', 'total_payments', 'total_revenue'],
                'employees',
                'generated_at',
            ]);
    }

    // ==================== DAILY CASHBOX REPORT ====================

    /** @test */
    public function can_get_daily_cashbox_report()
    {
        // Create transactions
        Transaction::create([
            'cashbox_id' => $this->cashbox->id,
            'branch_id' => $this->branch->id,
            'type' => 'income',
            'amount' => 500,
            'balance_after' => 10500,
            'description' => 'Test payment',
            'category' => 'payment',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/daily-cashbox');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'summary' => ['total_income', 'total_expense', 'net_change'],
                'cashboxes',
                'generated_at',
            ]);
    }

    /** @test */
    public function can_get_daily_cashbox_for_specific_date()
    {
        $yesterday = now()->subDay()->toDateString();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/reports/daily-cashbox?date={$yesterday}");

        $response->assertStatus(200)
            ->assertJsonPath('date', $yesterday);
    }

    // ==================== MONTHLY FINANCIAL REPORT ====================

    /** @test */
    public function can_get_monthly_financial_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/monthly-financial');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period' => ['year', 'month', 'month_name', 'start_date', 'end_date'],
                'revenue',
                'expenses',
                'cashflow',
                'generated_at',
            ]);
    }

    /** @test */
    public function can_get_monthly_financial_for_specific_month()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/monthly-financial?year=2025&month=12');

        $response->assertStatus(200)
            ->assertJsonPath('period.year', 2025)
            ->assertJsonPath('period.month', 12);
    }

    // ==================== EXPENSES REPORT ====================

    /** @test */
    public function can_get_expenses_report()
    {
        // Create expense
        Expense::create([
            'branch_id' => $this->branch->id,
            'cashbox_id' => $this->cashbox->id,
            'category' => 'maintenance',
            'amount' => 200,
            'expense_date' => now(),
            'description' => 'Test expense',
            'status' => 'paid',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/expenses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'period',
                'summary' => ['total_count', 'total_amount', 'paid_amount'],
                'by_category',
                'by_branch',
                'by_status',
                'generated_at',
            ]);
    }

    /** @test */
    public function can_filter_expenses_by_category()
    {
        Expense::create([
            'branch_id' => $this->branch->id,
            'cashbox_id' => $this->cashbox->id,
            'category' => 'cleaning',
            'amount' => 100,
            'expense_date' => now(),
            'description' => 'Cleaning expense',
            'status' => 'paid',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/expenses?category=cleaning');

        $response->assertStatus(200);
    }

    // ==================== DEPOSITS REPORT ====================

    /** @test */
    public function can_get_deposits_report()
    {
        // Create custody
        Custody::create([
            'order_id' => $this->order->id,
            'value' => 500,
            'type' => 'money',
            'status' => 'pending',
            'description' => 'Security deposit',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/deposits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'summary' => ['total_custodies', 'total_amount', 'currently_held'],
                'by_status',
                'held_deposits',
                'generated_at',
            ]);
    }

    /** @test */
    public function can_filter_deposits_by_status()
    {
        Custody::create([
            'order_id' => $this->order->id,
            'value' => 300,
            'type' => 'money',
            'status' => 'pending',
            'description' => 'Security deposit',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/deposits?status=pending');

        $response->assertStatus(200);
    }

    // ==================== DEBTS REPORT ====================

    /** @test */
    public function can_get_debts_report()
    {
        // Create receivable
        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'order_id' => $this->order->id,
            'original_amount' => 500,
            'paid_amount' => 100,
            'remaining_amount' => 400,
            'status' => 'partial',
            'due_date' => now()->addDays(7),
            'description' => 'Outstanding balance for order',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/debts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'summary' => ['total_receivables', 'total_amount', 'total_paid', 'total_outstanding'],
                'by_status',
                'aging',
                'top_debtors',
                'generated_at',
            ]);
    }

    /** @test */
    public function debts_report_includes_aging_analysis()
    {
        // Create overdue receivable
        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'order_id' => $this->order->id,
            'original_amount' => 1000,
            'paid_amount' => 0,
            'remaining_amount' => 1000,
            'status' => 'overdue',
            'due_date' => now()->subDays(45),
            'description' => 'Overdue payment',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/debts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'aging' => [
                    'current',
                    '1_30_days',
                    '31_60_days',
                    '61_90_days',
                    'over_90_days',
                ],
            ]);
    }

    /** @test */
    public function can_filter_debts_by_overdue_only()
    {
        // Create current receivable
        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'order_id' => $this->order->id,
            'original_amount' => 200,
            'paid_amount' => 0,
            'remaining_amount' => 200,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'description' => 'Upcoming payment',
            'created_by' => $this->user->id,
        ]);

        // Create overdue receivable
        Receivable::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'original_amount' => 500,
            'paid_amount' => 0,
            'remaining_amount' => 500,
            'status' => 'overdue',
            'due_date' => now()->subDays(10),
            'description' => 'Overdue payment',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/reports/debts?overdue_only=true');

        $response->assertStatus(200);
    }
}


