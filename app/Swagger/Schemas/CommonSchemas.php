<?php

/**
 * @OA\Schema(
 *   schema="Address",
 *   type="object",
 *   required={"street", "building", "city_id"},
 *   @OA\Property(property="id", type="integer", example=1, description="Address ID"),
 *   @OA\Property(property="street", type="string", example="Main Street", description="Street name"),
 *   @OA\Property(property="building", type="string", example="Building 123", description="Building name/number"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Near the park", description="Additional address notes"),
 *   @OA\Property(property="city_id", type="integer", example=1, description="City ID"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Phone",
 *   type="object",
 *   required={"phone"},
 *   @OA\Property(property="id", type="integer", example=1, description="Phone ID"),
 *   @OA\Property(property="client_id", type="integer", example=1, description="Client ID"),
 *   @OA\Property(property="phone", type="string", example="+1234567890", description="Phone number"),
 *   @OA\Property(property="type", type="string", nullable=true, example="mobile", description="Phone type (e.g., mobile, home, work)"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Entity",
 *   type="object",
 *   required={"entity_type", "entity_id"},
 *   @OA\Property(property="entity_type", ref="#/components/schemas/EntityType", description="Entity type"),
 *   @OA\Property(property="entity_id", type="integer", example=1, description="Entity ID"),
 *   @OA\Property(property="entity_name", type="string", nullable=true, example="Branch 1", description="Entity name (for responses)")
 * )
 */

/**
 * @OA\Schema(
 *   schema="Permission",
 *   type="object",
 *   required={"name", "display_name", "module", "action"},
 *   @OA\Property(property="id", type="integer", example=1, description="Permission ID"),
 *   @OA\Property(property="name", type="string", example="clients.view", description="Permission name"),
 *   @OA\Property(property="display_name", type="string", example="View Clients", description="Human-readable permission name"),
 *   @OA\Property(property="description", type="string", nullable=true, example="Allows viewing client information", description="Permission description"),
 *   @OA\Property(property="module", type="string", example="clients", description="Permission module"),
 *   @OA\Property(property="action", type="string", example="view", description="Permission action"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Category",
 *   type="object",
 *   required={"name"},
 *   @OA\Property(property="id", type="integer", example=1, description="Category ID"),
 *   @OA\Property(property="name", type="string", example="Wedding Dresses", description="Category name"),
 *   @OA\Property(property="description", type="string", nullable=true, example="Elegant wedding dress collection", description="Category description"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Subcategory",
 *   type="object",
 *   required={"name", "category_id"},
 *   @OA\Property(property="id", type="integer", example=1, description="Subcategory ID"),
 *   @OA\Property(property="category_id", type="integer", example=1, description="Parent category ID"),
 *   @OA\Property(property="category", ref="#/components/schemas/Category", description="Parent category"),
 *   @OA\Property(property="name", type="string", example="Ball Gown", description="Subcategory name"),
 *   @OA\Property(property="description", type="string", nullable=true, example="Full skirt wedding dresses", description="Subcategory description"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="PaginationMeta",
 *   type="object",
 *   @OA\Property(property="current_page", type="integer", example=1, description="Current page number"),
 *   @OA\Property(property="per_page", type="integer", example=15, description="Items per page"),
 *   @OA\Property(property="total", type="integer", example=100, description="Total number of items"),
 *   @OA\Property(property="total_pages", type="integer", example=7, description="Total number of pages"),
 *   @OA\Property(property="from", type="integer", nullable=true, example=1, description="First item number on current page"),
 *   @OA\Property(property="to", type="integer", nullable=true, example=15, description="Last item number on current page")
 * )
 */

/**
 * @OA\Schema(
 *   schema="PaginationResponse",
 *   type="object",
 *   @OA\Property(property="data", type="array", @OA\Items(type="object"), description="Array of items"),
 *   @OA\Property(property="current_page", type="integer", example=1),
 *   @OA\Property(property="per_page", type="integer", example=15),
 *   @OA\Property(property="total", type="integer", example=100),
 *   @OA\Property(property="total_pages", type="integer", example=7),
 *   @OA\Property(property="from", type="integer", nullable=true, example=1),
 *   @OA\Property(property="to", type="integer", nullable=true, example=15)
 * )
 */

// nothing to execute in this file; it's only for annotations

