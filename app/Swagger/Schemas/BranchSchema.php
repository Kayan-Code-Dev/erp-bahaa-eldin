<?php

/**
 * @OA\Schema(
 *   schema="Branch",
 *   type="object",
 *   required={"branch_code", "name"},
 *   @OA\Property(property="id", type="integer", example=1, description="Branch ID"),
 *   @OA\Property(property="branch_code", type="string", example="BR-001", description="Unique branch code"),
 *   @OA\Property(property="name", type="string", example="Downtown Branch", description="Branch name"),
 *   @OA\Property(property="address_id", type="integer", nullable=true, example=1, description="Address ID"),
 *   @OA\Property(property="address", ref="#/components/schemas/Address", nullable=true, description="Branch address"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true)
 * )
 */

// nothing to execute in this file; it's only for annotations
