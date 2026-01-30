<?php

namespace App\Http\Controllers;

use App\Models\Cloth;
use App\Models\ClothHistory;

class ClothTraceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/clothes/{id}/trace",
     *     summary="Get full history/trace of a cloth piece",
     *     tags={"Clothes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="cloth_id", type="integer", example=1),
     *             @OA\Property(property="cloth_code", type="string", example="CL-101-001"),
     *             @OA\Property(property="cloth_name", type="string", example="Red Dress Piece 1"),
     *             @OA\Property(property="cloth_type_id", type="integer", example=5),
     *             @OA\Property(property="cloth_type_name", type="string", example="Red Dress Model"),
     *             @OA\Property(property="history", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="action", type="string", example="created"),
     *                 @OA\Property(property="date", type="string", format="date-time", example="2025-12-02 23:33:25", description="MySQL datetime format: Y-m-d H:i:s"),
     *                 @OA\Property(property="entity_type", type="string", enum={"branch", "workshop", "factory"}, nullable=true, example="branch"),
     *                 @OA\Property(property="entity_id", type="integer", nullable=true, example="1 (optional)"),
     *                 @OA\Property(property="entity_name", type="string", nullable=true, example="Branch 1 (optional)"),
     *                 @OA\Property(property="from_entity_type", type="string", enum={"branch", "workshop", "factory"}, nullable=true, example="branch"),
     *                 @OA\Property(property="from_entity_id", type="integer", nullable=true, example="1 (optional)"),
     *                 @OA\Property(property="from_entity_name", type="string", nullable=true, example="Branch 1 (optional)"),
     *                 @OA\Property(property="to_entity_type", type="string", enum={"branch", "workshop", "factory"}, nullable=true, example="factory"),
     *                 @OA\Property(property="to_entity_id", type="integer", nullable=true, example="1 (optional)"),
     *                 @OA\Property(property="to_entity_name", type="string", nullable=true, example="Factory 1 (optional)"),
     *                 @OA\Property(property="transfer_id", type="integer", nullable=true, example="5 (optional)"),
     *                 @OA\Property(property="order_id", type="integer", nullable=true, example="3 (optional)"),
     *                 @OA\Property(property="status", type="string", nullable=true, example="ready_for_rent (optional)"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
     *                 @OA\Property(property="user_id", type="integer", nullable=true, example="1 (optional)"),
     *                 @OA\Property(property="user_name", type="string", nullable=true, example="John Doe (optional)")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function trace($id)
    {
        $cloth = Cloth::with('clothType')->findOrFail($id);

        $history = ClothHistory::where('cloth_id', $cloth->id)
            ->with(['transfer.fromEntity', 'transfer.toEntity', 'user'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($record) {
                $data = [
                    'id' => $record->id,
                    'action' => $record->action,
                    'date' => $record->created_at->format('Y-m-d H:i:s'),
                    'entity_type' => $record->entity_type,
                    'entity_id' => $record->entity_id,
                    'entity_name' => null,
                    'transfer_id' => $record->transfer_id,
                    'order_id' => $record->order_id,
                    'status' => $record->status,
                    'notes' => $record->notes,
                    'user_id' => $record->user_id,
                    'user_name' => $record->user ? $record->user->name : null,
                ];

                // Get entity name if entity exists
                if ($record->entity_type && $record->entity_id) {
                    $entity = $this->getEntity($record->entity_type, $record->entity_id);
                    if ($entity) {
                        $data['entity_name'] = $entity->name;
                    }
                }

                // For transferred action, get from/to entity info
                if ($record->action === 'transferred' && $record->transfer) {
                    $transfer = $record->transfer;
                    $fromEntity = $this->getEntity($transfer->from_entity_type, $transfer->from_entity_id);
                    $toEntity = $this->getEntity($transfer->to_entity_type, $transfer->to_entity_id);

                    if ($fromEntity) {
                        $data['from_entity_type'] = $transfer->from_entity_type;
                        $data['from_entity_id'] = $transfer->from_entity_id;
                        $data['from_entity_name'] = $fromEntity->name;
                    }

                    if ($toEntity) {
                        $data['to_entity_type'] = $transfer->to_entity_type;
                        $data['to_entity_id'] = $transfer->to_entity_id;
                        $data['to_entity_name'] = $toEntity->name;
                    }
                }

                return $data;
            });

        return response()->json([
            'cloth_id' => $cloth->id,
            'cloth_code' => $cloth->code,
            'cloth_name' => $cloth->name,
            'cloth_type_id' => $cloth->clothType->id ?? null,
            'cloth_type_name' => $cloth->clothType->name ?? null,
            'history' => $history,
        ]);
    }

    /**
     * Get entity by type and id
     */
    private function getEntity($entityType, $entityId)
    {
        if (!$entityType || !$entityId) {
            return null;
        }

        $map = [
            'branch' => \App\Models\Branch::class,
            'workshop' => \App\Models\Workshop::class,
            'factory' => \App\Models\Factory::class,
        ];

        $class = $map[$entityType] ?? null;
        if (!$class) {
            return null;
        }

        return $class::find($entityId);
    }
}

