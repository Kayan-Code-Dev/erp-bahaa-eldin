<?php

/**
 * @OA\Schema(
 *   schema="OrderItemRequest",
 *   type="object",
 *   required={"cloth_id", "price", "type"},
 *   @OA\Property(property="cloth_id", type="integer", example=1, description="Cloth ID"),
 *   @OA\Property(property="price", type="number", format="float", example=100.00, description="Item price (decimal 10,2)"),
 *   @OA\Property(property="type", ref="#/components/schemas/ClothOrderType", description="Item type"),
 *   @OA\Property(property="days_of_rent", type="integer", nullable=true, example=3, description="Days of rent (required if type is rent)"),
 *   @OA\Property(property="occasion_datetime", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Occasion datetime (required if type is rent). MySQL datetime format: Y-m-d H:i:s"),
 *   @OA\Property(property="delivery_date", type="string", format="date", nullable=true, example="2025-12-25", description="Delivery date (required if type is rent). Format: Y-m-d"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Item notes", description="Item notes"),
 *   @OA\Property(property="discount_type", ref="#/components/schemas/DiscountType", nullable=true, description="Item-level discount type. If provided, discount_value must be > 0"),
 *   @OA\Property(property="discount_value", type="number", format="float", nullable=true, example=5.00, description="Item-level discount value. Required if discount_type is provided, must be > 0. If discount_type is percentage, value should be 0-100. If fixed, value is the discount amount (decimal 10,2)")
 * )
 */

/**
 * @OA\Schema(
 *   schema="OrderCreateRequest",
 *   type="object",
 *   required={"client_id", "entity_type", "entity_id", "items"},
 *   @OA\Property(property="client_id", type="integer", example=1, description="Client ID"),
 *   @OA\Property(property="entity_type", ref="#/components/schemas/EntityType", description="Entity type"),
 *   @OA\Property(property="entity_id", type="integer", example=1, description="Entity ID"),
 *   @OA\Property(property="paid", type="number", format="float", nullable=true, example=50.00, description="Initial paid amount (decimal 10,2)"),
 *   @OA\Property(property="visit_datetime", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Visit datetime. MySQL datetime format: Y-m-d H:i:s"),
 *   @OA\Property(property="order_notes", type="string", nullable=true, example="Order notes", description="Order notes"),
 *   @OA\Property(property="discount_type", ref="#/components/schemas/DiscountType", nullable=true, description="Order-level discount type"),
 *   @OA\Property(property="discount_value", type="number", format="float", nullable=true, example=10.00, description="Order-level discount value. If discount_type is percentage, value should be 0-100. If fixed, value is the discount amount (decimal 10,2)"),
 *   @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/OrderItemRequest"), description="Order items array")
 * )
 */

/**
 * @OA\Schema(
 *   schema="OrderUpdateRequest",
 *   type="object",
 *   @OA\Property(property="client_id", type="integer", nullable=true, example=1, description="Client ID"),
 *   @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}, nullable=true, example="branch", description="Entity type"),
 *   @OA\Property(property="entity_id", type="integer", nullable=true, example=1, description="Entity ID"),
 *   @OA\Property(property="paid", type="number", format="float", nullable=true, example=50.00, description="Paid amount (decimal 10,2)"),
 *   @OA\Property(property="visit_datetime", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Visit datetime. MySQL datetime format: Y-m-d H:i:s"),
 *   @OA\Property(property="order_notes", type="string", nullable=true, example="Order notes", description="Order notes"),
 *   @OA\Property(property="discount_type", ref="#/components/schemas/DiscountType", nullable=true, description="Order-level discount type. If provided, discount_value must be > 0"),
 *   @OA\Property(property="discount_value", type="number", format="float", nullable=true, example=10.00, description="Order-level discount value. Required if discount_type is provided, must be > 0. If discount_type is percentage, value should be 0-100. If fixed, value is the discount amount (decimal 10,2)"),
 *   @OA\Property(property="items", type="array", nullable=true, @OA\Items(ref="#/components/schemas/OrderItemRequest"), description="Order items array")
 * )
 */

/**
 * @OA\Schema(
 *   schema="OrderItemResponse",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1, description="Cloth ID"),
 *   @OA\Property(property="code", type="string", example="CL-101-001", description="Cloth code"),
 *   @OA\Property(property="name", type="string", example="Red Dress Piece 1", description="Cloth name"),
 *   @OA\Property(property="pivot", type="object",
 *     @OA\Property(property="order_id", type="integer", example=1),
 *     @OA\Property(property="cloth_id", type="integer", example=1),
 *     @OA\Property(property="price", type="number", format="float", example=100.00),
 *     @OA\Property(property="type", ref="#/components/schemas/ClothOrderType"),
 *     @OA\Property(property="days_of_rent", type="integer", nullable=true, example=3),
 *     @OA\Property(property="occasion_datetime", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *     @OA\Property(property="delivery_date", type="string", format="date", nullable=true, example="2025-12-25"),
 *     @OA\Property(property="status", ref="#/components/schemas/ClothOrderStatus"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Item notes"),
 *     @OA\Property(property="discount_type", ref="#/components/schemas/DiscountType"),
 *     @OA\Property(property="discount_value", type="number", format="float", nullable=true, example=5.00),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25")
 *   )
 * )
 */

/**
 * @OA\Schema(
 *   schema="OrderResponse",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1, description="Order ID"),
 *   @OA\Property(property="client_id", type="integer", example=1),
 *   @OA\Property(property="inventory_id", type="integer", example=1),
 *   @OA\Property(property="total_price", type="number", format="float", example=100.00, description="Total price (decimal 10,2)"),
 *   @OA\Property(property="status", ref="#/components/schemas/OrderStatus"),
 *   @OA\Property(property="delivery_date", type="string", format="date", nullable=true, example="2025-12-25"),
 *   @OA\Property(property="paid", type="number", format="float", example=50.00, description="Paid amount (decimal 10,2)"),
 *   @OA\Property(property="remaining", type="number", format="float", example=50.00, description="Remaining amount (decimal 10,2)"),
 *   @OA\Property(property="visit_datetime", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *   @OA\Property(property="order_notes", type="string", nullable=true, example="Order notes"),
 *   @OA\Property(property="discount_type", ref="#/components/schemas/DiscountType"),
 *   @OA\Property(property="discount_value", type="number", format="float", nullable=true, example=10.00),
 *   @OA\Property(property="client", type="object", nullable=true, description="Client object"),
 *   @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/OrderItemResponse"), description="Order items"),
 *   @OA\Property(property="payments", type="array", @OA\Items(ref="#/components/schemas/PaymentResponse"), nullable=true, description="Order payments"),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25")
 * )
 */

// nothing to execute in this file; it's only for annotations

