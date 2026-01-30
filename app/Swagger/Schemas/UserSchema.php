<?php

/**
 * @OA\Schema(
 *   schema="User",
 *   type="object",
 *   required={"name", "email"},
 *   @OA\Property(property="id", type="integer", example=1, description="User ID"),
 *   @OA\Property(property="name", type="string", example="John Doe", description="User full name"),
 *   @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email"),
 *   @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Email verification timestamp"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Employee",
 *   type="object",
 *   required={"user_id", "department_id", "job_title_id"},
 *   @OA\Property(property="id", type="integer", example=1, description="Employee ID"),
 *   @OA\Property(property="user_id", type="integer", example=1, description="User ID"),
 *   @OA\Property(property="user", ref="#/components/schemas/User", description="Employee user details"),
 *   @OA\Property(property="department_id", type="integer", example=1, description="Department ID"),
 *   @OA\Property(property="department", ref="#/components/schemas/Department", description="Employee department"),
 *   @OA\Property(property="job_title_id", type="integer", example=1, description="Job title ID"),
 *   @OA\Property(property="job_title", ref="#/components/schemas/JobTitle", description="Employee job title"),
 *   @OA\Property(property="salary", type="number", format="float", example=5000.00, description="Monthly salary"),
 *   @OA\Property(property="hire_date", type="string", format="date", example="2024-01-15", description="Hire date"),
 *   @OA\Property(property="status", type="string", enum={"active", "inactive", "terminated"}, example="active", description="Employee status"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Department",
 *   type="object",
 *   required={"name"},
 *   @OA\Property(property="id", type="integer", example=1, description="Department ID"),
 *   @OA\Property(property="name", type="string", example="Sales Department", description="Department name"),
 *   @OA\Property(property="description", type="string", nullable=true, example="Handles all sales operations", description="Department description"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="JobTitle",
 *   type="object",
 *   required={"name"},
 *   @OA\Property(property="id", type="integer", example=1, description="Job title ID"),
 *   @OA\Property(property="name", type="string", example="Sales Manager", description="Job title name"),
 *   @OA\Property(property="description", type="string", nullable=true, example="Manages sales team", description="Job title description"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Role",
 *   type="object",
 *   required={"name", "description"},
 *   @OA\Property(property="id", type="integer", example=1, description="Role ID"),
 *   @OA\Property(property="name", type="string", example="sales_employee", description="Role name"),
 *   @OA\Property(property="description", type="string", example="Sales Employee - Manages clients and sales", description="Role description"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

// nothing to execute in this file; it's only for annotations
