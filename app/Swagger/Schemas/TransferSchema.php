<?php

/**
 * @OA\Schema(
 *   schema="TransferCreateRequest",
 *   type="object",
 *   required={"from_entity_type", "from_entity_id", "to_entity_type", "to_entity_id", "transfer_date", "cloth_ids"},
 *   @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch", description="Source entity type"),
 *   @OA\Property(property="from_entity_id", type="integer", example=1, description="Source entity ID"),
 *   @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, example="branch", description="Destination entity type"),
 *   @OA\Property(property="to_entity_id", type="integer", example=1, description="Destination entity ID"),
 *   @OA\Property(property="transfer_date", type="string", format="date", example="2025-12-25", description="Transfer date (Y-m-d format)"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Transfer notes", description="Transfer notes"),
 *   @OA\Property(property="cloth_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3}, description="Array of cloth IDs to transfer")
 * )
 */

/**
 * @OA\Schema(
 *   schema="TransferUpdateRequest",
 *   type="object",
 *   @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, nullable=true, example="branch", description="Source entity type"),
 *   @OA\Property(property="from_entity_id", type="integer", nullable=true, example=1, description="Source entity ID"),
 *   @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, nullable=true, example="branch", description="Destination entity type"),
 *   @OA\Property(property="to_entity_id", type="integer", nullable=true, example=1, description="Destination entity ID"),
 *   @OA\Property(property="transfer_date", type="string", format="date", nullable=true, example="2025-12-25", description="Transfer date (Y-m-d format)"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Transfer notes", description="Transfer notes"),
 *   @OA\Property(property="cloth_ids", type="array", nullable=true, @OA\Items(type="integer"), example={1, 2, 3}, description="Array of cloth IDs to transfer")
 * )
 */

/**
 * @OA\Schema(
 *   schema="TransferItemResponse",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="transfer_id", type="integer", example=1),
 *   @OA\Property(property="cloth_id", type="integer", example=1),
 *   @OA\Property(property="status", ref="#/components/schemas/TransferItemStatus"),
 *   @OA\Property(property="cloth", ref="#/components/schemas/ClothResponse", nullable=true),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25")
 * )
 */

/**
 * @OA\Schema(
 *   schema="TransferResponse",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1, description="Transfer ID"),
 *   @OA\Property(property="from_entity_type", ref="#/components/schemas/EntityType"),
 *   @OA\Property(property="from_entity_id", type="integer", example=1),
 *   @OA\Property(property="to_entity_type", ref="#/components/schemas/EntityType"),
 *   @OA\Property(property="to_entity_id", type="integer", example=1),
 *   @OA\Property(property="transfer_date", type="string", format="date", example="2025-12-25"),
 *   @OA\Property(property="notes", type="string", nullable=true, example="Transfer notes"),
 *   @OA\Property(property="status", ref="#/components/schemas/TransferStatus"),
 *   @OA\Property(property="from_entity", type="object", nullable=true, description="Source entity object"),
 *   @OA\Property(property="to_entity", type="object", nullable=true, description="Destination entity object"),
 *   @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/TransferItemResponse"), nullable=true, description="Transfer items"),
 *   @OA\Property(property="actions", type="array", @OA\Items(type="object"), nullable=true, description="Transfer actions"),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25")
 * )
 */

// nothing to execute in this file; it's only for annotations

