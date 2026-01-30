<?php

/**
 * @OA\Schema(
 *   schema="Expense",
 *   type="object",
 *   required={"amount", "description", "branch_id", "category"},
 *   @OA\Property(property="id", type="integer", example=1, description="Expense ID"),
 *   @OA\Property(property="amount", type="number", format="float", example=500.00, description="Expense amount"),
 *   @OA\Property(property="description", type="string", example="Office supplies purchase", description="Expense description"),
 *   @OA\Property(property="branch_id", type="integer", example=1, description="Branch ID"),
 *   @OA\Property(property="branch", ref="#/components/schemas/Branch", description="Expense branch"),
 *   @OA\Property(property="category", type="string", example="supplies", description="Expense category"),
 *   @OA\Property(property="expense_date", type="string", format="date", example="2025-12-02", description="Expense date"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Transaction",
 *   type="object",
 *   required={"amount", "type", "description"},
 *   @OA\Property(property="id", type="integer", example=1, description="Transaction ID"),
 *   @OA\Property(property="amount", type="number", format="float", example=1000.00, description="Transaction amount"),
 *   @OA\Property(property="type", type="string", enum={"income", "expense"}, example="income", description="Transaction type"),
 *   @OA\Property(property="description", type="string", example="Client payment", description="Transaction description"),
 *   @OA\Property(property="branch_id", type="integer", nullable=true, example=1, description="Branch ID"),
 *   @OA\Property(property="branch", ref="#/components/schemas/Branch", nullable=true, description="Transaction branch"),
 *   @OA\Property(property="transaction_date", type="string", format="date", example="2025-12-02", description="Transaction date"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Payroll",
 *   type="object",
 *   required={"employee_id", "month", "year", "basic_salary"},
 *   @OA\Property(property="id", type="integer", example=1, description="Payroll ID"),
 *   @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
 *   @OA\Property(property="employee", ref="#/components/schemas/Employee", description="Payroll employee"),
 *   @OA\Property(property="month", type="integer", example=12, description="Payroll month (1-12)"),
 *   @OA\Property(property="year", type="integer", example=2025, description="Payroll year"),
 *   @OA\Property(property="basic_salary", type="number", format="float", example=5000.00, description="Basic salary"),
 *   @OA\Property(property="allowances", type="number", format="float", example=500.00, description="Total allowances"),
 *   @OA\Property(property="deductions", type="number", format="float", example=200.00, description="Total deductions"),
 *   @OA\Property(property="net_salary", type="number", format="float", example=5300.00, description="Net salary"),
 *   @OA\Property(property="status", type="string", enum={"pending", "processed", "paid"}, example="pending", description="Payroll status"),
 *   @OA\Property(property="processed_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Processing timestamp"),
 *   @OA\Property(property="paid_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Payment timestamp"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Cashbox",
 *   type="object",
 *   required={"branch_id", "name"},
 *   @OA\Property(property="id", type="integer", example=1, description="Cashbox ID"),
 *   @OA\Property(property="branch_id", type="integer", example=1, description="Branch ID"),
 *   @OA\Property(property="branch", ref="#/components/schemas/Branch", description="Cashbox branch"),
 *   @OA\Property(property="name", type="string", example="Main Cashbox", description="Cashbox name"),
 *   @OA\Property(property="balance", type="number", format="float", example=15000.00, description="Current balance"),
 *   @OA\Property(property="status", type="string", enum={"active", "inactive"}, example="active", description="Cashbox status"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Receivable",
 *   type="object",
 *   required={"amount", "description", "client_id"},
 *   @OA\Property(property="id", type="integer", example=1, description="Receivable ID"),
 *   @OA\Property(property="client_id", type="integer", example=1, description="Client ID"),
 *   @OA\Property(property="client", ref="#/components/schemas/Client", description="Receivable client"),
 *   @OA\Property(property="amount", type="number", format="float", example=1000.00, description="Receivable amount"),
 *   @OA\Property(property="description", type="string", example="Outstanding payment", description="Receivable description"),
 *   @OA\Property(property="due_date", type="string", format="date", example="2025-12-15", description="Due date"),
 *   @OA\Property(property="status", type="string", enum={"pending", "partially_paid", "paid", "overdue"}, example="pending", description="Receivable status"),
 *   @OA\Property(property="paid_amount", type="number", format="float", example=0.00, description="Amount paid"),
 *   @OA\Property(property="remaining_amount", type="number", format="float", example=1000.00, description="Remaining amount"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

// nothing to execute in this file; it's only for annotations
