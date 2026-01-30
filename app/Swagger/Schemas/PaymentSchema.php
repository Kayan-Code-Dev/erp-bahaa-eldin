<?php

/**
 * @OA\Schema(
 *   schema="PaymentCreateRequest",
 *   type="object",
 *   required={"order_id", "amount"},
 *   @OA\Property(property="order_id", type="integer", example=1, description="Order ID"),
 *   @OA\Property(property="amount", type="number", format="float", example=100.00, description="Payment amount (decimal 10,2)"),
 *   @OA\Property(property="status", ref="#/components/schemas/PaymentStatus", nullable=true, description="Payment status"),
 *   @OA\Property(property="payment_type", ref="#/components/schemas/PaymentType", nullable=true, description="Payment type"),
 *   @OA\Property(property="payment_date", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Payment date. MySQL datetime format: Y-m-d H:i:s"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Payment notes", description="Payment notes"),
 *   @OA\Property(property="created_by", type="integer", nullable=true, example=1, description="User ID who created the payment")
 * )
 */

/**
 * @OA\Schema(
 *   schema="PaymentUpdateRequest",
 *   type="object",
 *   @OA\Property(property="amount", type="number", format="float", nullable=true, example=100.00, description="Payment amount (decimal 10,2)"),
 *   @OA\Property(property="status", ref="#/components/schemas/PaymentStatus", nullable=true, description="Payment status"),
 *   @OA\Property(property="payment_type", ref="#/components/schemas/PaymentType", nullable=true, description="Payment type"),
 *   @OA\Property(property="payment_date", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Payment date. MySQL datetime format: Y-m-d H:i:s"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Payment notes", description="Payment notes")
 * )
 */

/**
 * @OA\Schema(
 *   schema="PaymentResponse",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1, description="Payment ID"),
 *   @OA\Property(property="order_id", type="integer", example=1),
 *   @OA\Property(property="amount", type="number", format="float", example=100.00, description="Payment amount (decimal 10,2)"),
 *   @OA\Property(property="status", ref="#/components/schemas/PaymentStatus"),
 *   @OA\Property(property="payment_type", ref="#/components/schemas/PaymentType"),
 *   @OA\Property(property="payment_date", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Payment notes"),
 *   @OA\Property(property="created_by", type="integer", nullable=true, example=1),
 *   @OA\Property(property="order", type="object", nullable=true, description="Order object"),
 *   @OA\Property(property="user", type="object", nullable=true, description="User who created the payment"),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25")
 * )
 */

// nothing to execute in this file; it's only for annotations

