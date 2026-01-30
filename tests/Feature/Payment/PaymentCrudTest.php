<?php

namespace Tests\Feature\Payment;

use Tests\Feature\BaseTestCase;
use App\Models\Payment;
use App\Models\Order;

/**
 * Payment CRUD Tests
 *
 * Tests all basic CRUD operations for payments according to TEST_COVERAGE.md specification
 */
class PaymentCrudTest extends BaseTestCase
{
    // ==================== LIST PAYMENTS ====================

    /**
     * Test: List Payments
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: GET /api/v1/payments
     * - Required Permission: payments.view
     * - Expected Status: 200
     * - Description: List all payments with pagination and filtering
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_payment_list_by_general_manager_succeeds()
    {
        Payment::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/payments');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_payment_list_by_accountant_succeeds()
    {
        Payment::factory()->count(3)->create();
        $this->authenticateAs('accountant');

        $response = $this->getJson('/api/v1/payments');

        $response->assertStatus(200);
    }

    public function test_payment_list_by_factory_user_fails_403()
    {
        Payment::factory()->count(3)->create();
        $this->authenticateAs('factory_user');

        $response = $this->getJson('/api/v1/payments');

        $this->assertPermissionDenied($response);
    }

    // ==================== CREATE PAYMENT (PENDING STATUS) ====================

    /**
     * Test: Create Payment (Pending Status)
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: POST /api/v1/payments
     * - Required Permission: payments.create
     * - Expected Status: 201
     * - Description: Create a new payment with pending status (default)
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: Users without payments.create (403), invalid data (422)
     */

    public function test_payment_create_with_default_pending_status_succeeds()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $response->assertStatus(201)
            ->assertJson([
                'payment' => [
                    'status' => 'pending',
                    'amount' => 50.00,
                    'order_id' => $order->id,
                ],
            ]);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
        ]);
    }

    // ==================== CREATE PAYMENT WITH PAID STATUS ====================

    /**
     * Test: Create Payment with Paid Status
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: POST /api/v1/payments
     * - Required Permission: payments.create
     * - Expected Status: 201
     * - Description: Create a payment with paid status (affects order calculations)
     */

    public function test_payment_create_with_paid_status_updates_order()
    {
        $order = $this->createCompleteOrder(); // Order with total_price = 100
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $response->assertStatus(201)
            ->assertJson([
                'payment' => [
                    'status' => 'paid',
                    'amount' => 50.00,
                    'order_id' => $order->id,
                ],
            ]);

        // Verify order paid amount increased
        $order->refresh();
        $this->assertEquals(50.00, $order->paid);
        $this->assertEquals(50.00, $order->remaining);
        $this->assertEquals('partially_paid', $order->status);

        // Verify payment_date is set for paid payments
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
        ]);
    }

    // ==================== CREATE PAYMENT WITH FEE TYPE ====================

    /**
     * Test: Create Payment with Fee Type (Should Not Affect Remaining)
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: POST /api/v1/payments
     * - Required Permission: payments.create
     * - Expected Status: 201
     * - Description: Fee payments are tracked separately and don't affect order remaining
     */

    public function test_payment_create_fee_type_does_not_affect_order_remaining()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid', 'paid' => 100, 'remaining' => 0]);
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => 25.00,
            'payment_type' => 'fee',
            'status' => 'paid',
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $response->assertStatus(201);

        // Verify order remaining is still 0 (fees don't affect remaining)
        $order->refresh();
        $this->assertEquals(100.00, $order->paid);
        $this->assertEquals(0, $order->remaining);
        $this->assertEquals('paid', $order->status);
    }

    // ==================== SHOW PAYMENT ====================

    /**
     * Test: Show Payment
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: GET /api/v1/payments/{id}
     * - Required Permission: payments.view
     * - Expected Status: 200
     * - Description: Get single payment details with relationships
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_payment_show_by_general_manager_succeeds()
    {
        $payment = Payment::factory()->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $payment->id])
            ->assertJsonStructure([
                'id', 'order_id', 'amount', 'status', 'payment_type', 'payment_date',
                'order' => ['id', 'status', 'total_price']
            ]);
    }

    public function test_payment_show_by_accountant_succeeds()
    {
        $payment = Payment::factory()->create();
        $this->authenticateAs('accountant');

        $response = $this->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(200);
    }

    // ==================== EXPORT PAYMENTS ====================

    /**
     * Test: Export Payments
     * - Type: Feature Test
     * - Module: Payments
     * - Endpoint: GET /api/v1/payments/export
     * - Required Permission: payments.export
     * - Expected Status: 200
     * - Description: Export payments to file
     * - Should Pass For: general_manager, reception_employee, sales_employee, accountant
     * - Should Fail For: Users without permission (403)
     */

    public function test_payment_export_by_accountant_succeeds()
    {
        Payment::factory()->count(3)->create();
        $this->authenticateAs('accountant');

        $response = $this->get('/api/v1/payments/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
    }

    // ==================== VALIDATION TESTS ====================

    public function test_payment_create_without_required_fields_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->postJson('/api/v1/payments', []);

        $this->assertValidationError($response, ['order_id', 'amount']);
    }

    public function test_payment_create_with_invalid_order_id_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => 99999, // Non-existent order
            'amount' => 50.00,
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $this->assertValidationError($response, ['order_id']);
    }

    public function test_payment_create_with_zero_amount_fails_422()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => 0,
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $this->assertValidationError($response, ['amount']);
    }

    public function test_payment_create_with_negative_amount_fails_422()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => -50.00,
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $this->assertValidationError($response, ['amount']);
    }

    public function test_payment_create_with_invalid_payment_type_fails_422()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
            'payment_type' => 'invalid_type',
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $this->assertValidationError($response, ['payment_type']);
    }

    public function test_payment_create_with_invalid_status_fails_422()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('reception_employee');

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'invalid_status',
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $this->assertValidationError($response, ['status']);
    }
}
