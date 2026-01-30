<?php

/**
 * @OA\Schema(
 *   schema="OrderStatus",
 *   type="string",
 *   enum={"created", "partially_paid", "paid", "delivered", "finished", "canceled"},
 *   description="Order status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="ClothOrderType",
 *   type="string",
 *   enum={"buy", "rent", "tailoring"},
 *   description="Cloth order item type enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="ClothOrderStatus",
 *   type="string",
 *   enum={"created", "partially_paid", "paid", "delivered", "finished", "canceled", "rented"},
 *   description="Cloth order item status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="ClothStatus",
 *   type="string",
 *   enum={"damaged", "burned", "scratched", "ready_for_rent", "rented", "repairing", "die"},
 *   description="Cloth status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="DiscountType",
 *   type="string",
 *   enum={"percentage", "fixed"},
 *   description="Discount type enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="PaymentStatus",
 *   type="string",
 *   enum={"pending", "paid", "canceled"},
 *   description="Payment status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="PaymentType",
 *   type="string",
 *   enum={"initial", "fee", "normal"},
 *   description="Payment type enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="TransferStatus",
 *   type="string",
 *   enum={"pending", "partially_pending", "partially_approved", "approved", "rejected"},
 *   description="Transfer status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="EntityType",
 *   type="string",
 *   enum={"branch", "workshop", "factory"},
 *   description="Entity type enum (branch, workshop, factory)"
 * )
 */

/**
 * @OA\Schema(
 *   schema="TransferItemStatus",
 *   type="string",
 *   enum={"pending", "approved", "rejected"},
 *   description="Transfer item status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="TransferActionType",
 *   type="string",
 *   enum={"created", "updated", "approved", "approved_items", "rejected", "rejected_items", "deleted"},
 *   description="Transfer action type enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="RentStatus",
 *   type="string",
 *   enum={"scheduled", "confirmed", "in_progress", "completed", "cancelled", "no_show", "rescheduled", "active"},
 *   description="Rent/Appointment status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="TailoringStage",
 *   type="string",
 *   enum={"received", "sent_to_factory", "in_production", "ready_from_factory", "ready_for_customer", "delivered"},
 *   description="Tailoring order stage enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="PriorityLevel",
 *   type="string",
 *   enum={"low", "normal", "high", "urgent"},
 *   description="Priority level enum (for orders and notifications)"
 * )
 */

/**
 * @OA\Schema(
 *   schema="FactoryStatus",
 *   type="string",
 *   enum={"active", "inactive", "suspended", "closed"},
 *   description="Factory status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="AppointmentType",
 *   type="string",
 *   enum={"rental_delivery", "rental_return", "measurement", "tailoring_pickup", "tailoring_delivery", "fitting", "other"},
 *   description="Appointment type enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="EmployeeStatus",
 *   type="string",
 *   enum={"active", "inactive", "terminated"},
 *   description="Employee status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="AttendanceStatus",
 *   type="string",
 *   enum={"present", "absent", "half_day", "holiday", "weekend", "leave"},
 *   description="Attendance status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="PayrollStatus",
 *   type="string",
 *   enum={"pending", "processed", "paid"},
 *   description="Payroll status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="CashboxStatus",
 *   type="string",
 *   enum={"active", "inactive"},
 *   description="Cashbox status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="ReceivableStatus",
 *   type="string",
 *   enum={"pending", "partially_paid", "paid", "overdue"},
 *   description="Receivable status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="ExpenseCategory",
 *   type="string",
 *   enum={"rent", "utilities", "supplies", "maintenance", "salaries", "marketing", "transport", "cleaning", "other"},
 *   description="Expense category enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="ExpenseStatus",
 *   type="string",
 *   enum={"pending", "approved", "paid", "cancelled"},
 *   description="Expense status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="TransactionType",
 *   type="string",
 *   enum={"income", "expense"},
 *   description="Transaction type enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="NotificationType",
 *   type="string",
 *   enum={"info", "warning", "error", "success"},
 *   description="Notification type enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="CustodyType",
 *   type="string",
 *   enum={"money", "physical_item", "document"},
 *   description="Custody type enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="CustodyStatus",
 *   type="string",
 *   enum={"pending", "returned", "forfeited"},
 *   description="Custody status enum"
 * )
 */

/**
 * @OA\Schema(
 *   schema="CustodyPhotoType",
 *   type="string",
 *   enum={"custody_photo", "id_photo"},
 *   description="Custody photo type enum"
 * )
 */

// nothing to execute in this file; it's only for annotations

