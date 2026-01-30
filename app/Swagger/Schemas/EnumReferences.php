<?php

/**
 * This file forces enum schemas to appear in the components section
 * by creating a dummy schema that references all enum types.
 * 
 * @OA\Schema(
 *   schema="EnumReferences",
 *   type="object",
 *   description="Dummy schema to force enum schemas into components section",
 *   @OA\Property(property="order_status", ref="#/components/schemas/OrderStatus"),
 *   @OA\Property(property="cloth_order_type", ref="#/components/schemas/ClothOrderType"),
 *   @OA\Property(property="cloth_order_status", ref="#/components/schemas/ClothOrderStatus"),
 *   @OA\Property(property="cloth_status", ref="#/components/schemas/ClothStatus"),
 *   @OA\Property(property="discount_type", ref="#/components/schemas/DiscountType"),
 *   @OA\Property(property="payment_status", ref="#/components/schemas/PaymentStatus"),
 *   @OA\Property(property="payment_type", ref="#/components/schemas/PaymentType"),
 *   @OA\Property(property="transfer_status", ref="#/components/schemas/TransferStatus"),
 *   @OA\Property(property="entity_type", ref="#/components/schemas/EntityType"),
 *   @OA\Property(property="transfer_item_status", ref="#/components/schemas/TransferItemStatus"),
 *   @OA\Property(property="transfer_action_type", ref="#/components/schemas/TransferActionType"),
 *   @OA\Property(property="rent_status", ref="#/components/schemas/RentStatus"),
 *   @OA\Property(property="custody_type", ref="#/components/schemas/CustodyType"),
 *   @OA\Property(property="custody_status", ref="#/components/schemas/CustodyStatus"),
 *   @OA\Property(property="custody_photo_type", ref="#/components/schemas/CustodyPhotoType"),
 *   @OA\Property(property="tailoring_stage", ref="#/components/schemas/TailoringStage"),
 *   @OA\Property(property="priority_level", ref="#/components/schemas/PriorityLevel"),
 *   @OA\Property(property="factory_status", ref="#/components/schemas/FactoryStatus"),
 *   @OA\Property(property="expense_category", ref="#/components/schemas/ExpenseCategory"),
 *   @OA\Property(property="expense_status", ref="#/components/schemas/ExpenseStatus"),
 *   @OA\Property(property="expense", ref="#/components/schemas/Expense"),
 *   @OA\Property(property="expense_create_request", ref="#/components/schemas/ExpenseCreateRequest"),
 *   @OA\Property(property="expense_update_request", ref="#/components/schemas/ExpenseUpdateRequest")
 * )
 */

// nothing to execute in this file; it's only for annotations

