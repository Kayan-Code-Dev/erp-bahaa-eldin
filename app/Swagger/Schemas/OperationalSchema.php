<?php

/**
 * @OA\Schema(
 *   schema="Attendance",
 *   type="object",
 *   required={"employee_id", "date", "check_in"},
 *   @OA\Property(property="id", type="integer", example=1, description="Attendance ID"),
 *   @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
 *   @OA\Property(property="employee", ref="#/components/schemas/Employee", description="Attendance employee"),
 *   @OA\Property(property="branch_id", type="integer", example=1, description="Branch ID"),
 *   @OA\Property(property="branch", ref="#/components/schemas/Branch", description="Attendance branch"),
 *   @OA\Property(property="date", type="string", format="date", example="2025-12-02", description="Attendance date"),
 *   @OA\Property(property="check_in", type="string", format="time", example="09:00:00", description="Check-in time"),
 *   @OA\Property(property="check_out", type="string", format="time", nullable=true, example="17:00:00", description="Check-out time"),
 *   @OA\Property(property="status", type="string", enum={"present", "absent", "half_day", "holiday", "weekend", "leave"}, example="present", description="Attendance status"),
 *   @OA\Property(property="is_late", type="boolean", example=false, description="Whether employee was late"),
 *   @OA\Property(property="worked_hours", type="number", format="float", nullable=true, example=8.0, description="Total worked hours"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Arrived on time", description="Attendance notes"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Notification",
 *   type="object",
 *   required={"title", "message", "type"},
 *   @OA\Property(property="id", type="integer", example=1, description="Notification ID"),
 *   @OA\Property(property="user_id", type="integer", example=1, description="User ID"),
 *   @OA\Property(property="user", ref="#/components/schemas/User", description="Notification recipient"),
 *   @OA\Property(property="title", type="string", example="Appointment Reminder", description="Notification title"),
 *   @OA\Property(property="message", type="string", example="You have an appointment tomorrow at 10:00 AM", description="Notification message"),
 *   @OA\Property(property="type", type="string", enum={"info", "warning", "error", "success"}, example="info", description="Notification type"),
 *   @OA\Property(property="is_read", type="boolean", example=false, description="Whether notification was read"),
 *   @OA\Property(property="read_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Read timestamp"),
 *   @OA\Property(property="data", type="object", nullable=true, description="Additional notification data"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="ActivityLog",
 *   type="object",
 *   required={"user_id", "action", "entity_type", "entity_id"},
 *   @OA\Property(property="id", type="integer", example=1, description="Activity log ID"),
 *   @OA\Property(property="user_id", type="integer", example=1, description="User ID"),
 *   @OA\Property(property="user", ref="#/components/schemas/User", description="Activity user"),
 *   @OA\Property(property="action", type="string", example="created", description="Action performed"),
 *   @OA\Property(property="entity_type", type="string", example="App\\Models\\Client", description="Entity type"),
 *   @OA\Property(property="entity_id", type="integer", example=1, description="Entity ID"),
 *   @OA\Property(property="old_values", type="object", nullable=true, description="Old values before change"),
 *   @OA\Property(property="new_values", type="object", nullable=true, description="New values after change"),
 *   @OA\Property(property="ip_address", type="string", nullable=true, example="192.168.1.1", description="User IP address"),
 *   @OA\Property(property="user_agent", type="string", nullable=true, example="Mozilla/5.0...", description="User agent string"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Custody",
 *   type="object",
 *   required={"type", "description", "employee_id", "amount"},
 *   @OA\Property(property="id", type="integer", example=1, description="Custody ID"),
 *   @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
 *   @OA\Property(property="employee", ref="#/components/schemas/Employee", description="Custody employee"),
 *   @OA\Property(property="type", ref="#/components/schemas/CustodyType", description="Custody type"),
 *   @OA\Property(property="description", type="string", example="Office supplies purchase", description="Custody description"),
 *   @OA\Property(property="amount", type="number", format="float", example=500.00, description="Custody amount"),
 *   @OA\Property(property="status", ref="#/components/schemas/CustodyStatus", description="Custody status"),
 *   @OA\Property(property="approved_by", type="integer", nullable=true, example=1, description="Approver user ID"),
 *   @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Approval timestamp"),
 *   @OA\Property(property="returned_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Return timestamp"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Approved for office use", description="Custody notes"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Rent",
 *   type="object",
 *   required={"appointment_type", "delivery_date"},
 *   @OA\Property(property="id", type="integer", example=1, description="Rent/Appointment ID"),
 *   @OA\Property(property="client_id", type="integer", nullable=true, example=1, description="Client ID"),
 *   @OA\Property(property="client", ref="#/components/schemas/Client", nullable=true, description="Rent client"),
 *   @OA\Property(property="cloth_id", type="integer", nullable=true, example=1, description="Cloth ID"),
 *   @OA\Property(property="cloth", ref="#/components/schemas/Cloth", nullable=true, description="Rent cloth"),
 *   @OA\Property(property="order_id", type="integer", nullable=true, example=1, description="Order ID"),
 *   @OA\Property(property="order", ref="#/components/schemas/Order", nullable=true, description="Rent order"),
 *   @OA\Property(property="branch_id", type="integer", nullable=true, example=1, description="Branch ID"),
 *   @OA\Property(property="branch", ref="#/components/schemas/Branch", nullable=true, description="Rent branch"),
 *   @OA\Property(property="appointment_type", type="string", enum={"rental_delivery", "rental_return", "measurement", "tailoring_pickup", "tailoring_delivery", "fitting", "other"}, example="rental_delivery", description="Appointment type"),
 *   @OA\Property(property="title", type="string", nullable=true, example="Dress Delivery", description="Appointment title"),
 *   @OA\Property(property="delivery_date", type="string", format="date", example="2025-12-15", description="Delivery/appointment date"),
 *   @OA\Property(property="appointment_time", type="string", format="time", nullable=true, example="10:00", description="Appointment time"),
 *   @OA\Property(property="return_date", type="string", format="date", nullable=true, example="2025-12-20", description="Return date"),
 *   @OA\Property(property="return_time", type="string", format="time", nullable=true, example="17:00", description="Return time"),
 *   @OA\Property(property="days_of_rent", type="integer", nullable=true, example=5, description="Number of rental days"),
 *   @OA\Property(property="status", type="string", enum={"scheduled", "confirmed", "in_progress", "completed", "cancelled", "no_show", "rescheduled"}, example="scheduled", description="Appointment status"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Customer requested specific time", description="Appointment notes"),
 *   @OA\Property(property="created_by", type="integer", nullable=true, example=1, description="Creator user ID"),
 *   @OA\Property(property="completed_by", type="integer", nullable=true, example=1, description="Completer user ID"),
 *   @OA\Property(property="completed_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Completion timestamp"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

// nothing to execute in this file; it's only for annotations
