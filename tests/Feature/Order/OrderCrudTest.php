<?php

namespace Tests\Feature\Order;

use Tests\Feature\BaseTestCase;
use App\Models\Order;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Cloth;
use App\Models\ClothType;

/**
 * Order CRUD Tests
 *
 * Tests all basic CRUD operations for orders according to TEST_COVERAGE.md specification
 */
class OrderCrudTest extends BaseTestCase
{
    // ==================== LIST ORDERS ====================

    /**
     * Test: List Orders
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: GET /api/v1/orders
     * - Required Permission: orders.view
     * - Expected Status: 200
     * - Description: List all orders with pagination and filtering
     * - Should Pass For: general_manager, reception_employee, sales_employee, factory_manager, accountant
     * - Should Fail For: factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_order_list_by_general_manager_succeeds()
    {
        Order::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_order_list_by_reception_employee_succeeds()
    {
        Order::factory()->count(3)->create();
        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(200);
    }

    public function test_order_list_by_sales_employee_succeeds()
    {
        Order::factory()->count(3)->create();
        $this->authenticateAs('sales_employee');

        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(200);
    }

    public function test_order_list_by_factory_user_fails_403()
    {
        Order::factory()->count(3)->create();
        $this->authenticateAs('factory_user');

        $response = $this->getJson('/api/v1/orders');

        $this->assertPermissionDenied($response);
    }

    // ==================== CREATE RENTAL ORDER ====================

    /**
     * Test: Create Rental Order
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders
     * - Required Permission: orders.create
     * - Expected Status: 201
     * - Description: Create a new rental order with items
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: Users without orders.create (403), invalid data (422), cloth not available (422)
     */

    public function test_order_create_rental_with_valid_data_returns_201()
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
                'client_id' => $client->id,
                'entity_type' => 'branch',
                'entity_id' => $branch->id,
                'status' => 'created',
            ]);

        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
            'status' => 'created',
            'total_price' => 100.00,
        ]);

        // Verify order items were created
        $order = Order::where('client_id', $client->id)->first();
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'cloth_id' => $cloth->id,
            'type' => 'rent',
            'quantity' => 1,
            'price' => 100.00,
        ]);
    }

    // ==================== CREATE SALE ORDER ====================

    /**
     * Test: Create Sale Order
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders
     * - Required Permission: orders.create
     * - Expected Status: 201
     * - Description: Create a new sale order (no delivery_date required)
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_order_create_sale_with_valid_data_returns_201()
    {
        $client = $this->createCompleteClient();
        $branch = Branch::factory()->create();
        $cloth = Cloth::factory()->create();
        $this->authenticateAs('sales_employee');

        $data = [
            'client_id' => $client->id,
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
            'items' => [
                [
                    'cloth_id' => $cloth->id,
                    'type' => 'sale',
                    'quantity' => 1,
                    'price' => 200.00,
                ]
            ],
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $response->assertStatus(201)
            ->assertJson([
                'client_id' => $client->id,
                'entity_type' => 'branch',
                'entity_id' => $branch->id,
                'status' => 'created',
            ]);

        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'status' => 'created',
            'total_price' => 200.00,
        ]);
    }

    // ==================== CREATE ORDER WITH ITEM-LEVEL DISCOUNT ====================

    /**
     * Test: Create Order with Item-Level Discount
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders
     * - Required Permission: orders.create
     * - Expected Status: 201
     * - Description: Create order with item-level discount (percentage or fixed)
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_order_create_with_item_level_discount_succeeds()
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
                    'discount_type' => 'percentage',
                    'discount_value' => 10, // 10% discount
                ]
            ],
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'return_date' => now()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $response->assertStatus(201);

        // Verify total price is discounted (100 - 10% = 90)
        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'total_price' => 90.00,
        ]);
    }

    // ==================== CREATE ORDER WITH ORDER-LEVEL DISCOUNT ====================

    /**
     * Test: Create Order with Order-Level Discount
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: POST /api/v1/orders
     * - Required Permission: orders.create
     * - Expected Status: 201
     * - Description: Create order with order-level discount
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_order_create_with_order_level_discount_succeeds()
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
            'discount_type' => 'fixed',
            'discount_value' => 15.00, // $15 fixed discount
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'return_date' => now()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $response->assertStatus(201);

        // Verify total price is discounted (100 - 15 = 85)
        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'total_price' => 85.00,
        ]);
    }

    // ==================== SHOW ORDER ====================

    /**
     * Test: Show Order
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: GET /api/v1/orders/{id}
     * - Required Permission: orders.view
     * - Expected Status: 200
     * - Description: Get single order details with all relationships
     * - Should Pass For: general_manager, reception_employee, sales_employee, factory_manager, accountant
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_order_show_by_general_manager_succeeds()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $order->id])
            ->assertJsonStructure([
                'id', 'client_id', 'entity_type', 'entity_id', 'status', 'total_price', 'paid', 'remaining',
                'client' => ['id', 'first_name', 'last_name'],
                'items' => [
                    '*' => ['id', 'cloth_id', 'type', 'quantity', 'price']
                ]
            ]);
    }

    public function test_order_show_by_reception_employee_succeeds()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('reception_employee');

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
    }

    public function test_order_show_by_accountant_succeeds()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('accountant');

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
    }

    public function test_order_show_by_factory_user_fails_403()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('factory_user');

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $this->assertPermissionDenied($response);
    }

    // ==================== UPDATE ORDER ====================

    /**
     * Test: Update Order
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: PUT /api/v1/orders/{id}
     * - Required Permission: orders.update
     * - Expected Status: 200
     * - Description: Update order details (notes, visit_datetime, etc.)
     * - Should Pass For: general_manager, reception_employee, sales_employee, factory_manager
     * - Should Fail For: Users without permission (403), invalid status transitions (422)
     */

    public function test_order_update_with_valid_data_succeeds()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('reception_employee');

        $updateData = [
            'notes' => 'Updated order notes',
            'visit_datetime' => now()->addDays(1)->format('Y-m-d H:i:s'),
        ];

        $response = $this->putJson("/api/v1/orders/{$order->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'notes' => 'Updated order notes',
        ]);
    }

    // ==================== DELETE ORDER ====================

    /**
     * Test: Delete Order
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: DELETE /api/v1/orders/{id}
     * - Required Permission: orders.delete
     * - Expected Status: 200/204
     * - Description: Soft delete an order
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: Users without permission (403), order in certain statuses (should prevent or allow with business rules)
     */

    public function test_order_delete_created_order_succeeds()
    {
        $order = $this->createCompleteOrder();
        $this->authenticateAs('reception_employee');

        $response = $this->deleteJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($order);
    }

    // ==================== EXPORT ORDERS ====================

    /**
     * Test: Export Orders
     * - Type: Feature Test
     * - Module: Orders
     * - Endpoint: GET /api/v1/orders/export
     * - Required Permission: orders.export
     * - Expected Status: 200
     * - Description: Export orders to file
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: Users without permission (403)
     */

    public function test_order_export_by_reception_employee_succeeds()
    {
        Order::factory()->count(3)->create();
        $this->authenticateAs('reception_employee');

        $response = $this->get('/api/v1/orders/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
    }

    // ==================== VALIDATION TESTS ====================

    public function test_order_create_without_required_fields_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->postJson('/api/v1/orders', []);

        $this->assertValidationError($response, ['client_id', 'entity_type', 'entity_id', 'items']);
    }

    public function test_order_create_with_invalid_client_id_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $data = [
            'client_id' => 99999, // Non-existent client
            'entity_type' => 'branch',
            'entity_id' => 1,
            'items' => [
                [
                    'cloth_id' => 1,
                    'type' => 'rent',
                    'quantity' => 1,
                    'price' => 100.00,
                ]
            ],
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $this->assertValidationError($response, ['client_id']);
    }

    public function test_order_create_with_empty_items_fails_422()
    {
        $client = $this->createCompleteClient();
        $branch = Branch::factory()->create();
        $this->authenticateAs('reception_employee');

        $data = [
            'client_id' => $client->id,
            'entity_type' => 'branch',
            'entity_id' => $branch->id,
            'items' => [], // Empty items
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $this->assertValidationError($response, ['items']);
    }

    public function test_order_create_with_invalid_item_type_fails_422()
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
                    'type' => 'invalid_type', // Invalid type
                    'quantity' => 1,
                    'price' => 100.00,
                ]
            ],
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $this->assertValidationError($response, ['items.0.type']);
    }

    public function test_order_create_rental_without_delivery_date_fails_422()
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
            // Missing delivery_date for rental
            'return_date' => now()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/orders', $data);

        $this->assertValidationError($response, ['delivery_date']);
    }
}
