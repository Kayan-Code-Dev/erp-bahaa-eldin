<?php

namespace Tests\Coverage\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Order;
use App\Models\Payment;
use Laravel\Sanctum\Sanctum;

class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = [
            'payments.view', 'payments.create', 'payments.pay', 'payments.cancel', 'payments.export',
        ];
        foreach ($permissions as $perm) {
            Permission::findOrCreateByName($perm);
        }
    }

    protected function createUserWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test Role']);
        $role->assignPermission($permission);
        $user->assignRole($role);
        return $user;
    }

    public function test_list_payments()
    {
        Payment::factory()->count(5)->create();
        $user = $this->createUserWithPermission('payments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/payments');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_payment()
    {
        $order = Order::factory()->create();
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $data = [
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => 'pending',
            'payment_type' => 'normal',
        ];
        $response = $this->postJson('/api/v1/payments', $data);
        $response->assertStatus(201);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'amount' => 100.00]);
    }

    public function test_show_payment()
    {
        $payment = Payment::factory()->create();
        $user = $this->createUserWithPermission('payments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/payments/{$payment->id}");
        $response->assertStatus(200)->assertJson(['id' => $payment->id]);
    }

    public function test_mark_payment_as_paid()
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
            'amount' => 100.00,
        ]);
        $user = $this->createUserWithPermission('payments.pay');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/pay");
        $response->assertStatus(200);
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
    }

    public function test_cancel_payment()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);
        $user = $this->createUserWithPermission('payments.cancel');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/cancel");
        $response->assertStatus(200);
        $payment->refresh();
        $this->assertEquals('canceled', $payment->status);
    }

    public function test_export_payments()
    {
        Payment::factory()->count(3)->create();
        $user = $this->createUserWithPermission('payments.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/payments/export');
        $response->assertStatus(200);
    }

    public function test_create_payment_updates_order_status()
    {
        $order = Order::factory()->create(['total_price' => 200.00, 'paid' => 0]);
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 200.00,
            'status' => 'paid',
        ]);
        $order->refresh();
        $this->assertEquals('paid', $order->status);
    }

    public function test_create_payment_with_paid_status()
    {
        $order = Order::factory()->create(['total_price' => 1000.00, 'paid' => 0, 'status' => 'created']);
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 500.00,
            'status' => 'paid',
            'payment_type' => 'normal',
        ]);

        $response->assertStatus(201);
        $order->refresh();
        $this->assertEquals(500.00, $order->paid);
        $this->assertEquals(500.00, $order->remaining);
        $this->assertEquals('partially_paid', $order->status);
        $this->assertNotNull(Payment::where('order_id', $order->id)->first()->payment_date);
    }

    public function test_create_payment_with_fee_type_does_not_affect_remaining()
    {
        $order = Order::factory()->create(['total_price' => 1000.00, 'paid' => 1000.00, 'status' => 'paid', 'remaining' => 0]);
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => 'paid',
            'payment_type' => 'fee',
        ]);

        $response->assertStatus(201);
        $order->refresh();
        $this->assertEquals(1000.00, $order->paid);
        $this->assertEquals(0, $order->remaining);
        $this->assertEquals('paid', $order->status);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'payment_type' => 'fee', 'amount' => 100.00]);
    }

    public function test_pay_payment_with_invalid_status_fails()
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => 'paid',
            'amount' => 100.00,
        ]);
        $user = $this->createUserWithPermission('payments.pay');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/pay");
        $response->assertStatus(422);
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
    }

    public function test_cancel_paid_payment_updates_order()
    {
        $order = Order::factory()->create(['total_price' => 1000.00, 'paid' => 0]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'status' => 'paid',
            'amount' => 500.00,
        ]);
        // Update order paid manually to simulate paid payment
        $order->paid = 500.00;
        $order->remaining = 500.00;
        $order->status = 'partially_paid';
        $order->save();
        
        $user = $this->createUserWithPermission('payments.cancel');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/cancel");
        // Note: The actual implementation allows canceling paid payments (reverses transaction)
        // This test verifies cancellation works and updates order correctly
        $response->assertStatus(200);
        $payment->refresh();
        $this->assertEquals('canceled', $payment->status);
        $order->refresh();
        // Order should be recalculated (paid should decrease)
        $this->assertEquals(0, $order->paid);
    }

    public function test_payment_creation_updates_order_status_created_to_partially_paid()
    {
        $order = Order::factory()->create(['total_price' => 1000.00, 'paid' => 0, 'status' => 'created']);
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 300.00,
            'status' => 'paid',
        ]);

        $response->assertStatus(201);
        $order->refresh();
        $this->assertEquals(300.00, $order->paid);
        $this->assertEquals(700.00, $order->remaining);
        $this->assertEquals('partially_paid', $order->status);
    }

    public function test_payment_creation_updates_order_status_partially_paid_to_paid()
    {
        $order = Order::factory()->create(['total_price' => 1000.00, 'paid' => 300.00, 'status' => 'partially_paid', 'remaining' => 700.00]);
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 700.00,
            'status' => 'paid',
        ]);

        $response->assertStatus(201);
        $order->refresh();
        $this->assertEquals(1000.00, $order->paid);
        $this->assertEquals(0, $order->remaining);
        $this->assertEquals('paid', $order->status);
    }

    public function test_payment_cancellation_updates_order_status()
    {
        $order = Order::factory()->create(['total_price' => 1000.00, 'paid' => 0, 'status' => 'created']);
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        // Create and pay first payment
        $response1 = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 500.00,
            'status' => 'paid',
        ]);
        $order->refresh();

        // Create and pay second payment (order now paid)
        $response2 = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 500.00,
            'status' => 'paid',
        ]);
        $order->refresh();
        $this->assertEquals('paid', $order->status);

        // Cancel second payment
        $payment2 = Payment::where('order_id', $order->id)->where('amount', 500.00)->orderBy('id', 'desc')->first();
        $user = $this->createUserWithPermission('payments.cancel');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/payments/{$payment2->id}/cancel");
        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(500.00, $order->paid);
        $this->assertEquals(500.00, $order->remaining);
        $this->assertEquals('partially_paid', $order->status);
    }

    public function test_create_payment_with_invalid_order_id_fails()
    {
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payments', [
            'order_id' => 99999,
            'amount' => 100.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);
    }

    public function test_create_payment_with_zero_amount_fails()
    {
        $order = Order::factory()->create();
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_payment_with_negative_amount_fails()
    {
        $order = Order::factory()->create();
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => -100.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_create_payment_with_invalid_payment_type_fails()
    {
        $order = Order::factory()->create();
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 100.00,
            'payment_type' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_type']);
    }

    public function test_create_payment_with_invalid_status_fails()
    {
        $order = Order::factory()->create();
        $user = $this->createUserWithPermission('payments.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $order->id,
            'amount' => 100.00,
            'status' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}

