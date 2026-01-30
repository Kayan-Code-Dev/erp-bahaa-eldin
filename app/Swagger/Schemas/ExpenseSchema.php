<?php

/**
 * @OA\Schema(
 *   schema="Expense",
 *   type="object",
 *   description="Expense model",
 *   @OA\Property(property="id", type="integer", example=1, description="Expense ID"),
 *   @OA\Property(property="cashbox_id", type="integer", example=1, description="Cashbox ID"),
 *   @OA\Property(property="branch_id", type="integer", example=1, description="Branch ID"),
 *   @OA\Property(property="category", type="string", enum={"rent", "utilities", "supplies", "maintenance", "salaries", "marketing", "transport", "cleaning", "other"}, example="utilities", description="Expense category"),
 *   @OA\Property(property="subcategory", type="string", nullable=true, example="Electricity", description="More specific categorization"),
 *   @OA\Property(property="amount", type="number", format="float", example=1500.00, description="Expense amount (decimal 15,2)"),
 *   @OA\Property(property="expense_date", type="string", format="date", example="2026-01-15", description="Date of the expense"),
 *   @OA\Property(property="vendor", type="string", nullable=true, example="Electric Company", description="Vendor/payee name"),
 *   @OA\Property(property="reference_number", type="string", nullable=true, example="INV-2026-001", description="Invoice or receipt number"),
 *   @OA\Property(property="description", type="string", example="Monthly electricity bill", description="Expense description (max 1000 chars)"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Urgent payment required", description="Additional notes (max 2000 chars)"),
 *   @OA\Property(property="status", type="string", enum={"pending", "approved", "paid", "cancelled"}, example="pending", description="Expense status"),
 *   @OA\Property(property="approved_by", type="integer", nullable=true, example=2, description="User ID who approved the expense"),
 *   @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example="2026-01-15T10:30:00Z", description="When the expense was approved"),
 *   @OA\Property(property="created_by", type="integer", example=1, description="User ID who created the expense"),
 *   @OA\Property(property="transaction_id", type="integer", nullable=true, example=123, description="Transaction ID (set when expense is paid)"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-15T08:00:00Z"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-15T10:30:00Z"),
 *   @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example="2026-01-20T10:00:00Z", description="Soft delete timestamp")
 * )
 */

/**
 * @OA\Schema(
 *   schema="ExpenseCreateRequest",
 *   type="object",
 *   required={"branch_id", "category", "amount", "expense_date", "description"},
 *   @OA\Property(property="branch_id", type="integer", example=1, description="Branch ID (required)"),
 *   @OA\Property(property="category", type="string", enum={"rent", "utilities", "supplies", "maintenance", "salaries", "marketing", "transport", "cleaning", "other"}, example="utilities", description="Expense category (required)"),
 *   @OA\Property(property="subcategory", type="string", nullable=true, example="Electricity", description="More specific categorization (optional, max 255 chars)"),
 *   @OA\Property(property="amount", type="number", format="float", example=1500.00, description="Expense amount, must be greater than 0.01 (required, decimal 15,2)"),
 *   @OA\Property(property="expense_date", type="string", format="date", example="2026-01-15", description="Date of the expense (required)"),
 *   @OA\Property(property="vendor", type="string", nullable=true, example="Electric Company", description="Vendor/payee name (optional, max 255 chars)"),
 *   @OA\Property(property="reference_number", type="string", nullable=true, example="INV-2026-001", description="Invoice or receipt number (optional, max 100 chars)"),
 *   @OA\Property(property="description", type="string", example="Monthly electricity bill", description="Expense description (required, max 1000 chars)"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Urgent payment required", description="Additional notes (optional, max 2000 chars)")
 * )
 */

/**
 * @OA\Schema(
 *   schema="ExpenseUpdateRequest",
 *   type="object",
 *   @OA\Property(property="category", type="string", enum={"rent", "utilities", "supplies", "maintenance", "salaries", "marketing", "transport", "cleaning", "other"}, example="utilities", description="Expense category"),
 *   @OA\Property(property="subcategory", type="string", nullable=true, example="Electricity", description="More specific categorization (max 255 chars)"),
 *   @OA\Property(property="amount", type="number", format="float", example=1500.00, description="Expense amount, must be greater than 0.01 (decimal 15,2)"),
 *   @OA\Property(property="expense_date", type="string", format="date", example="2026-01-15", description="Date of the expense"),
 *   @OA\Property(property="vendor", type="string", nullable=true, example="Electric Company", description="Vendor/payee name (max 255 chars)"),
 *   @OA\Property(property="reference_number", type="string", nullable=true, example="INV-2026-001", description="Invoice or receipt number (max 100 chars)"),
 *   @OA\Property(property="description", type="string", example="Monthly electricity bill", description="Expense description (max 1000 chars)"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Urgent payment required", description="Additional notes (max 2000 chars)")
 * )
 */

// nothing to execute in this file; it's only for annotations

