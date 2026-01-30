<?php

/**
 * @OA\Schema(
 *   schema="ClothCreateRequest",
 *   type="object",
 *   required={"code", "name", "cloth_type_id"},
 *   @OA\Property(property="code", type="string", example="CL-101-001", description="Unique cloth code"),
 *   @OA\Property(property="name", type="string", example="Red Dress Piece 1", description="Cloth name"),
 *   @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model", description="Cloth description"),
 *   @OA\Property(property="cloth_type_id", type="integer", example=1, description="Cloth type ID"),
 *   @OA\Property(property="breast_size", type="string", nullable=true, example="M", description="Breast size"),
 *   @OA\Property(property="waist_size", type="string", nullable=true, example="M", description="Waist size"),
 *   @OA\Property(property="sleeve_size", type="string", nullable=true, example="M", description="Sleeve size"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Cloth notes", description="Additional notes"),
 *   @OA\Property(property="status", ref="#/components/schemas/ClothStatus", nullable=true, description="Cloth status")
 * )
 */

/**
 * @OA\Schema(
 *   schema="ClothUpdateRequest",
 *   type="object",
 *   @OA\Property(property="code", type="string", nullable=true, example="CL-101-001", description="Unique cloth code"),
 *   @OA\Property(property="name", type="string", nullable=true, example="Red Dress Piece 1", description="Cloth name"),
 *   @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model", description="Cloth description"),
 *   @OA\Property(property="cloth_type_id", type="integer", nullable=true, example=1, description="Cloth type ID"),
 *   @OA\Property(property="breast_size", type="string", nullable=true, example="M", description="Breast size"),
 *   @OA\Property(property="waist_size", type="string", nullable=true, example="M", description="Waist size"),
 *   @OA\Property(property="sleeve_size", type="string", nullable=true, example="M", description="Sleeve size"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Cloth notes", description="Additional notes"),
 *   @OA\Property(property="status", ref="#/components/schemas/ClothStatus", nullable=true, description="Cloth status")
 * )
 */

/**
 * @OA\Schema(
 *   schema="ClothResponse",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1, description="Cloth ID"),
 *   @OA\Property(property="code", type="string", example="CL-101-001", description="Unique cloth code"),
 *   @OA\Property(property="name", type="string", example="Red Dress Piece 1", description="Cloth name"),
 *   @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model", description="Cloth description"),
 *   @OA\Property(property="cloth_type_id", type="integer", example=1, description="Cloth type ID"),
 *   @OA\Property(property="breast_size", type="string", nullable=true, example="M", description="Breast size"),
 *   @OA\Property(property="waist_size", type="string", nullable=true, example="M", description="Waist size"),
 *   @OA\Property(property="sleeve_size", type="string", nullable=true, example="M", description="Sleeve size"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Cloth notes", description="Additional notes"),
 *   @OA\Property(property="status", ref="#/components/schemas/ClothStatus", description="Cloth status"),
 *   @OA\Property(property="cloth_type", type="object", nullable=true, description="Cloth type object"),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25")
 * )
 */

/**
 * @OA\Schema(
 *   schema="ClothWithEntityResponse",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="code", type="string", example="CL-101-001"),
 *   @OA\Property(property="name", type="string", example="Red Dress Piece 1"),
 *   @OA\Property(property="description", type="string", nullable=true, example="First piece of red dress model"),
 *   @OA\Property(property="cloth_type_id", type="integer", example=1),
 *   @OA\Property(property="breast_size", type="string", nullable=true, example="M"),
 *   @OA\Property(property="waist_size", type="string", nullable=true, example="M"),
 *   @OA\Property(property="sleeve_size", type="string", nullable=true, example="M"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Cloth notes"),
 *   @OA\Property(property="status", ref="#/components/schemas/ClothStatus"),
 *   @OA\Property(property="entity_type", ref="#/components/schemas/EntityType", nullable=true),
 *   @OA\Property(property="entity_id", type="integer", nullable=true, example=1),
 *   @OA\Property(property="entity_name", type="string", nullable=true, example="Branch 1"),
 *   @OA\Property(property="available_from", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Available from datetime"),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25")
 * )
 */

// nothing to execute in this file; it's only for annotations

