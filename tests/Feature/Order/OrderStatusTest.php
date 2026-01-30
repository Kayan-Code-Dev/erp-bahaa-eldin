<?php

namespace Tests\Feature\Order;

use Tests\Feature\BaseTestCase;
use App\Models\Order;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Cloth;
use App\Models\Payment;
use App\Models\Custody;

/**
 * Order Status Transition Tests
 *
 * Tests order status transitions according to TEST_COVERAGE.md specification
 * Order statuses: created, partially_paid, paid, delivered, finished, cancelled
 */
class OrderStatusTest extends BaseTestCase
{
    // ==================== ORDER STATUS AUTO-CALCULATION ====================

    /**
     * Test: Order Status Auto-Calculation on Creation (Paid = 0)
     * - Type: Feature Test
     * - Module: Orders
     * - Description: Order status should be 'created' when paid = 0
     */
    public function test_order_status_created_when_paid_zero()
    {
        $client = $this->createCompleteClient();
        $branch = Branch::factory()->create();
        $cloth = Cloth::factory()->create();
        $this->authenticateAs('reception_employee');

        $data = [
            'client_id' => $client->id,
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
            'items' => [
                [
                    'cloth_id' => $cloth->id,
                    'type' => 'rent',
                    'quantity' => 1,
                    'price' => 100.00,
                ]
            ],
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'return_date' => now()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'created',
                'paid' => 0,
                'remaining' => 100.00,
            ]);

        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'status' => 'created',
            'paid' => 0,
            'remaining' => 100.00,
        ]);
    }

    /**
     * Test: Order Status Auto-Calculation on Creation (Paid < Total)
     * - Type: Feature Test
     * - Module: Orders
     * - Description: Order status should be 'partially_paid' when 0 < paid < total_price
     */
    public function test_order_status_partially_paid_when_paid_less_than_total()
    {
        $client = $this->createCompleteClient();
        $branch = Branch::factory()->create();
        $cloth = Cloth::factory()->create();
        $this->authenticateAs('reception_employee');

        $data = [
            'client_id' => $client->id,
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
            'items' => [
                [
                    'cloth_id' => $cloth->id,
                    'type' => 'rent',
                    'quantity' => 1,
                    'price' => 100.00,
                ]
            ],
            'paid' => 30.00, // Partial payment
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'return_date' => now()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'partially_paid',
                'paid' => 30.00,
                'remaining' => 70.00,
            ]);
    }

    /**
     * Test: Order Status Auto-Calculation on Creation (Paid = Total)
     * - Type: Feature Test
     * - Module: Orders
     * - Description: Order status should be 'paid' when paid = total_price
     */
    public function test_order_status_paid_when_paid_equals_total()
    {
        $client = $this->createCompleteClient();
        $branch = Branch::factory()->create();
        $cloth = Cloth::factory()->create();
        $this->authenticateAs('reception_employee');

        $data = [
            'client_id' => $client->id,
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
            'items' => [
                [
                    'cloth_id' => $cloth->id,
                    'type' => 'rent',
                    'quantity' => 1,
                    'price' => 100.00,
                ]
            ],
            'paid' => 100.00, // Full payment
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'return_date' => now()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'paid',
                'paid' => 100.00,
                'remaining' => 0,
            ]);
    }

    // ==================== DELIVER ORDER ====================

    /**
     * Test: Deliver Order (Should Fail without Custody)
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders/{id}/deliver
     * - Required Permission: orders.deliver
     * - Expected Status: 422
     * - Description: Cannot deliver order without custody (for orders with custody items)
     */
    public function test_order_deliver_without_custody_fails_422()
    {
        $order = $this->createCompleteOrder();
        // Set order to paid status
        $order->update(['status' => 'paid']);
        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/deliver");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test: Deliver Order (With Pending Custody)
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders/{id}/deliver
     * - Required Permission: orders.deliver
     * - Expected Status: 200
     * - Description: Can deliver order with pending custody
     */
    public function test_order_deliver_with_pending_custody_succeeds()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);

        // Create custody for the order
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/deliver");

        $response->assertStatus(200)
            ->assertJson(['status' => 'delivered']);

        $order->refresh();
        $this->assertEquals('delivered', $order->status);
    }

    // ==================== FINISH ORDER ====================

    /**
     * Test: Finish Order (Should Fail with Pending Payments)
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders/{id}/finish
     * - Required Permission: orders.finish
     * - Expected Status: 422
     * - Description: Cannot finish order with pending payments
     */
    public function test_order_finish_with_pending_payments_fails_422()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'delivered', 'paid' => 50, 'remaining' => 50]);

        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test: Finish Order (Should Fail with Pending Custody)
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders/{id}/finish
     * - Required Permission: orders.finish
     * - Expected Status: 422
     * - Description: Cannot finish order with pending custody
     */
    public function test_order_finish_with_pending_custody_fails_422()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'delivered', 'paid' => 100, 'remaining' => 0]);

        // Create pending custody
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test: Finish Order (With Kept Custody)
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders/{id}/finish
     * - Required Permission: orders.finish
     * - Expected Status: 200
     * - Description: Can finish order when custody is kept (forfeited)
     */
    public function test_order_finish_with_kept_custody_succeeds()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'delivered', 'paid' => 100, 'remaining' => 0]);

        // Create kept custody
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'kept',
        ]);

        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(200)
            ->assertJson(['status' => 'finished']);

        $order->refresh();
        $this->assertEquals('finished', $order->status);
    }

    /**
     * Test: Finish Order (With Returned Custody and Proof)
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders/{id}/finish
     * - Required Permission: orders.finish
     * - Expected Status: 200
     * - Description: Can finish order when custody is returned with proof
     */
    public function test_order_finish_with_returned_custody_succeeds()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'delivered', 'paid' => 100, 'remaining' => 0]);

        // Create returned custody
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'returned',
        ]);

        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(200)
            ->assertJson(['status' => 'finished']);

        $order->refresh();
        $this->assertEquals('finished', $order->status);
    }

    // ==================== CANCEL ORDER ====================

    /**
     * Test: Cancel Order
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders/{id}/cancel
     * - Required Permission: orders.cancel
     * - Expected Status: 200
     * - Description: Cancel an order (returns clothes to ready_for_rent)
     */
    public function test_order_cancel_succeeds()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel");

        $response->assertStatus(200)
            ->assertJson(['status' => 'cancelled']);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
    }

    // ==================== INVALID STATUS TRANSITIONS ====================

    /**
     * Test: Invalid Status Transition
     * - Type: Feature Test
     * - Module: Orders
     * - Description: Attempting invalid status transitions should fail
     */
    public function test_invalid_status_transition_deliver_finished_order_fails()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'finished']);
        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/deliver");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_invalid_status_transition_finish_created_order_fails()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'created']);
        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    // ==================== PERMISSION TESTS ====================

    public function test_order_deliver_by_reception_employee_succeeds()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/deliver");

        $response->assertStatus(200);
    }

    public function test_order_finish_by_sales_employee_succeeds()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'delivered', 'paid' => 100, 'remaining' => 0]);
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'returned',
        ]);

        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(200);
    }

    public function test_order_deliver_by_accountant_fails_403()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'paid']);
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('accountant');

        $response = $this->postJson("/api/v1/orders/{$order->id}/deliver");

        $this->assertPermissionDenied($response);
    }

    // ==================== COMPLETE ORDER LIFECYCLE ====================

    /**
     * Test: Complete Order Lifecycle (Created → Partially Paid → Paid → Delivered → Finished)
     * - Type: Integration Test
     * - Module: Orders
     * - Description: Complete order lifecycle from creation to completion
     */
    public function test_complete_order_lifecycle_works()
    {
        // 1. Create order (status: created)
        $order = $this->createCompleteOrder();
        $this->assertEquals('created', $order->status);

        // 2. Add payment (status: partially_paid)
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50,
            'status' => 'paid',
        ]);
        $order->refresh();
        $this->assertEquals('partially_paid', $order->status);
        $this->assertEquals(50, $order->paid);
        $this->assertEquals(50, $order->remaining);

        // 3. Add another payment (status: paid)
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => 50,
            'status' => 'paid',
        ]);
        $order->refresh();
        $this->assertEquals('paid', $order->status);
        $this->assertEquals(100, $order->paid);
        $this->assertEquals(0, $order->remaining);

        // 4. Create custody
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        // 5. Deliver order (status: delivered)
        $this->authenticateAs('reception_employee');
        $response = $this->postJson("/api/v1/orders/{$order->id}/deliver");
        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('delivered', $order->status);

        // 6. Return custody
        $custody = Custody::where('order_id', $order->id)->first();
        $custody->update(['status' => 'returned']);

        // 7. Finish order (status: finished)
        $this->authenticateAs('sales_employee');
        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");
        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('finished', $order->status);
    }
}
