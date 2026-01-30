<?php

/**
 * @OA\Schema(
 *   schema="Country",
 *   type="object",
 *   required={"name"},
 *   @OA\Property(property="id", type="integer", example=1, description="Country ID"),
 *   @OA\Property(property="name", type="string", example="Egypt", description="Country name"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="City",
 *   type="object",
 *   required={"name", "country_id"},
 *   @OA\Property(property="id", type="integer", example=1, description="City ID"),
 *   @OA\Property(property="name", type="string", example="Cairo", description="City name"),
 *   @OA\Property(property="country_id", type="integer", example=1, description="Country ID"),
 *   @OA\Property(property="country", ref="#/components/schemas/Country", description="City country"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Factory",
 *   type="object",
 *   required={"factory_code", "name"},
 *   @OA\Property(property="id", type="integer", example=1, description="Factory ID"),
 *   @OA\Property(property="factory_code", type="string", example="FA-001", description="Unique factory code"),
 *   @OA\Property(property="name", type="string", example="Main Factory", description="Factory name"),
 *   @OA\Property(property="address_id", type="integer", nullable=true, example=1, description="Address ID"),
 *   @OA\Property(property="address", ref="#/components/schemas/Address", nullable=true, description="Factory address"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Workshop",
 *   type="object",
 *   required={"name"},
 *   @OA\Property(property="id", type="integer", example=1, description="Workshop ID"),
 *   @OA\Property(property="name", type="string", example="Tailoring Workshop", description="Workshop name"),
 *   @OA\Property(property="address_id", type="integer", nullable=true, example=1, description="Address ID"),
 *   @OA\Property(property="address", ref="#/components/schemas/Address", nullable=true, description="Workshop address"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

/**
 * @OA\Schema(
 *   schema="Inventory",
 *   type="object",
 *   required={"name", "inventoriable_type", "inventoriable_id"},
 *   @OA\Property(property="id", type="integer", example=1, description="Inventory ID"),
 *   @OA\Property(property="name", type="string", example="Main Warehouse", description="Inventory name"),
 *   @OA\Property(property="inventoriable_type", type="string", enum={"branch", "workshop", "factory"}, example="branch", description="Entity type"),
 *   @OA\Property(property="inventoriable_id", type="integer", example=1, description="Entity ID"),
 *   @OA\Property(property="inventoriable", type="object", description="Related entity (branch, workshop, or factory)"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

// nothing to execute in this file; it's only for annotations
