<?php

namespace Tests\Feature\Payment;

use Tests\Feature\BaseTestCase;
use App\Models\Payment;
use App\Models\Order;

/**
 * Payment Status Transition Tests
 *
 * Tests payment status transitions according to TEST_COVERAGE.md specification
 * Payment statuses: pending, paid, canceled
 */
class PaymentStatusTest extends BaseTestCase
{
    // ==================== PAY PAYMENT (PENDING → PAID) ====================

    /**
     * Test: Pay Payment (Pending → Paid)
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: POST /api/v1/payments/{id}/pay
     * - Required Permission: payments.pay
     * - Expected Status: 200
     * - Description: Mark payment as paid (creates transaction, updates order)
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: Users without permission (403), payment already paid (422), payment canceled (422)
     */

    public function test_payment_pay_pending_payment_succeeds()
    {
        $order = $this->createCompleteOrder(); // Order with total_price = 100
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/payments/{$payment->id}/pay");

        $response->assertStatus(200)
            ->assertJson([
                'payment' => [
                    'id' => $payment->id,
                    'status' => 'paid',
                ],
            ]);

        // Verify payment status and payment_date
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertNotNull($payment->payment_date);

        // Verify order calculations updated
        $order->refresh();
        $this->assertEquals(50.00, $order->paid);
        $this->assertEquals(50.00, $order->remaining);
        $this->assertEquals('partially_paid', $order->status);
    }

    // ==================== PAY PAYMENT WITH INVALID STATUS ====================

    /**
     * Test: Pay Payment with Invalid Status (Should Fail)
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: POST /api/v1/payments/{id}/pay
     * - Expected Status: 422
     * - Description: Cannot pay payment that is already paid or canceled
     */

    public function test_payment_pay_already_paid_payment_fails_422()
    {
        $payment = Payment::factory()->create([
            'status' => 'paid',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/payments/{$payment->id}/pay");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        // Verify status remains paid
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
    }

    public function test_payment_pay_cancelled_payment_fails_422()
    {
        $payment = Payment::factory()->create([
            'status' => 'canceled',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/payments/{$payment->id}/pay");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        // Verify status remains canceled
        $payment->refresh();
        $this->assertEquals('canceled', $payment->status);
    }

    // ==================== CANCEL PAYMENT ====================

    /**
     * Test: Cancel Payment (Pending → Canceled)
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: POST /api/v1/payments/{id}/cancel
     * - Required Permission: payments.cancel
     * - Expected Status: 200
     * - Description: Cancel a pending payment (updates order)
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: Users without permission (403), payment already paid (422), payment already canceled (422)
     */

    public function test_payment_cancel_pending_payment_succeeds()
    {
        $order = $this->createCompleteOrder();
        // Create and pay a payment first
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
        ]);
        // Order is now partially_paid

        // Create another pending payment
        $pendingPayment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 30.00,
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/payments/{$pendingPayment->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'payment' => [
                    'id' => $pendingPayment->id,
                    'status' => 'canceled',
                ],
            ]);

        // Verify payment status
        $pendingPayment->refresh();
        $this->assertEquals('canceled', $pendingPayment->status);

        // Verify order calculations updated (payment not counted)
        $order->refresh();
        $this->assertEquals(50.00, $order->paid); // Only the paid payment counts
        $this->assertEquals(50.00, $order->remaining);
        $this->assertEquals('partially_paid', $order->status);
    }

    // ==================== CANCEL PAID PAYMENT ====================

    /**
     * Test: Cancel Paid Payment (Should Fail)
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: POST /api/v1/payments/{id}/cancel
     * - Expected Status: 422
     * - Description: Cannot cancel payment that is already paid
     */

    public function test_payment_cancel_paid_payment_fails_422()
    {
        $payment = Payment::factory()->create([
            'status' => 'paid',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/payments/{$payment->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        // Verify status remains paid
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
    }

    // ==================== PAYMENT ORDER INTEGRATION ====================

    /**
     * Test: Payment Creation Updates Order Status (Created → Partially Paid)
     * - Type: Integration Test
     * - Module: Payments, Orders
     * - Description: Creating paid payment updates order status
     */

    public function test_payment_creation_updates_order_status_to_partially_paid()
    {
        $order = $this->createCompleteOrder(); // Order with total_price = 100
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => 30.00,
            'status' => 'paid',
        ];

        $this->postJson('/api/v1/payments', $data);

        $order->refresh();
        $this->assertEquals(30.00, $order->paid);
        $this->assertEquals(70.00, $order->remaining);
        $this->assertEquals('partially_paid', $order->status);
    }

    /**
     * Test: Payment Creation Updates Order Status (Partially Paid → Paid)
     * - Type: Integration Test
     * - Module: Payments, Orders
     * - Description: Creating payment that completes order updates status to paid
     */

    public function test_payment_creation_updates_order_status_to_paid()
    {
        $order = $this->createCompleteOrder(); // Order with total_price = 100
        // Create first payment
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
        ]);

        $order->refresh();
        $this->assertEquals('partially_paid', $order->status);

        // Create second payment to complete the order
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
        ];

        $this->postJson('/api/v1/payments', $data);

        $order->refresh();
        $this->assertEquals(100.00, $order->paid);
        $this->assertEquals(0, $order->remaining);
        $this->assertEquals('paid', $order->status);
    }

    /**
     * Test: Payment Cancellation Updates Order Status
     * - Type: Integration Test
     * - Module: Payments, Orders
     * - Description: Canceling payment updates order paid/remaining amounts
     */

    public function test_payment_cancellation_updates_order_calculations()
    {
        $order = $this->createCompleteOrder(); // Order with total_price = 100

        // Create and pay first payment
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
        ]);

        // Create and pay second payment (order now paid)
        $secondPayment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
        ]);

        $order->refresh();
        $this->assertEquals('paid', $order->status);
        $this->assertEquals(100.00, $order->paid);
        $this->assertEquals(0, $order->remaining);

        // Cancel the second payment
        $this->authenticateAs('reception_employee');
        $this->postJson("/api/v1/payments/{$secondPayment->id}/cancel");

        $order->refresh();
        $this->assertEquals('partially_paid', $order->status);
        $this->assertEquals(50.00, $order->paid);
        $this->assertEquals(50.00, $order->remaining);
    }

    /**
     * Test: Fee Payments Do Not Affect Order Remaining
     * - Type: Integration Test
     * - Module: Payments, Orders
     * - Description: Fee payments are tracked separately and don't affect order remaining
     */

    public function test_fee_payments_do_not_affect_order_remaining()
    {
        $order = $this->createCompleteOrder(); // Order with total_price = 100
        $order->update(['status' => 'paid', 'paid' => 100, 'remaining' => 0]);

        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => 25.00,
            'payment_type' => 'fee',
            'status' => 'paid',
        ];

        $this->postJson('/api/v1/payments', $data);

        // Verify order calculations unchanged
        $order->refresh();
        $this->assertEquals(100.00, $order->paid);
        $this->assertEquals(0, $order->remaining);
        $this->assertEquals('paid', $order->status);
    }

    // ==================== PERMISSION TESTS ====================

    public function test_payment_pay_by_reception_employee_succeeds()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);
        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/payments/{$payment->id}/pay");

        $response->assertStatus(200);
    }

    public function test_payment_pay_by_accountant_succeeds()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);
        $this->authenticateAs('accountant');

        $response = $this->postJson("/api/v1/payments/{$payment->id}/pay");

        $response->assertStatus(200);
    }

    public function test_payment_pay_by_factory_user_fails_403()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);
        $this->authenticateAs('factory_user');

        $response = $this->postJson("/api/v1/payments/{$payment->id}/pay");

        $this->assertPermissionDenied($response);
    }

    public function test_payment_cancel_by_accountant_succeeds()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);
        $this->authenticateAs('accountant');

        $response = $this->postJson("/api/v1/payments/{$payment->id}/cancel");

        $response->assertStatus(200);
    }

    // ==================== EDGE CASES ====================

    public function test_payment_pay_nonexistent_payment_fails_404()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->postJson('/api/v1/payments/99999/pay');

        $this->assertNotFound($response);
    }

    public function test_payment_cancel_nonexistent_payment_fails_404()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->postJson('/api/v1/payments/99999/cancel');

        $this->assertNotFound($response);
    }

    public function test_payment_pay_by_unauthenticated_fails_401()
    {
        $payment = Payment::factory()->create(['status' => 'pending']);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/pay");

        $this->assertUnauthorized($response);
    }
}
