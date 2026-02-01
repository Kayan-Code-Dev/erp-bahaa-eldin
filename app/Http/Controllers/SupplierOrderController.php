<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SupplierOrderService;
use App\Http\Requests\SupplierOrderRequest;
use App\Http\Resources\SupplierOrderResource;
use App\Exports\SupplierOrderExport;
use App\Models\SupplierOrder;

class SupplierOrderController extends Controller
{
    protected SupplierOrderService $supplierOrderService;

    public function __construct(SupplierOrderService $supplierOrderService)
    {
        $this->supplierOrderService = $supplierOrderService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/supplier-orders",
     *     summary="List all supplier orders",
     *     tags={"Supplier Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $search = $request->query('search');
        $supplierId = $request->query('supplier_id');
        $items = $this->supplierOrderService->list($perPage, $search, $supplierId);
        return $this->paginatedResponse($items, SupplierOrderResource::class);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/supplier-orders/show/{id}",
     *     summary="Get a supplier order by ID",
     *     tags={"Supplier Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show($id)
    {
        $item = $this->supplierOrderService->find($id);
        return new SupplierOrderResource($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/supplier-orders/store",
     *     summary="Create supplier order with clothes (SAME params as ClothController store)",
     *     tags={"Supplier Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"supplier_id", "order_date", "clothes"},
     *             @OA\Property(property="supplier_id", type="integer", example=1),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="subcategory_id", type="integer", example=1),
     *             @OA\Property(property="branch_id", type="integer", example=1),
     *             @OA\Property(property="order_number", type="string", example="SO-20260201-0001"),
     *             @OA\Property(property="order_date", type="string", format="date", example="2026-02-01"),
     *             @OA\Property(property="payment_amount", type="number", example=500.00),
     *             @OA\Property(property="notes", type="string", example="Order notes"),
     *             @OA\Property(
     *                 property="clothes",
     *                 type="array",
     *                 description="Array of clothes - SAME params as ClothController store",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"code", "name", "cloth_type_id", "entity_type", "entity_id"},
     *                     @OA\Property(property="code", type="string", example="CLT-001"),
     *                     @OA\Property(property="name", type="string", example="Blue Thobe"),
     *                     @OA\Property(property="description", type="string", example="High quality"),
     *                     @OA\Property(property="cloth_type_id", type="integer", example=1),
     *                     @OA\Property(property="breast_size", type="string", example="XL"),
     *                     @OA\Property(property="waist_size", type="string", example="32"),
     *                     @OA\Property(property="sleeve_size", type="string", example="L"),
     *                     @OA\Property(property="notes", type="string", example="Special notes"),
     *                     @OA\Property(property="status", type="string", enum={"damaged","burned","scratched","ready_for_rent","rented","repairing","die","sold"}, example="ready_for_rent"),
     *                     @OA\Property(property="entity_type", type="string", enum={"branch","workshop","factory"}, example="branch"),
     *                     @OA\Property(property="entity_id", type="integer", example=1),
     *                     @OA\Property(property="price", type="number", example=150.00)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        // Validate supplier order data
        $orderData = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'category_id' => 'nullable|exists:categories,id',
            'subcategory_id' => 'nullable|exists:subcategories,id',
            'branch_id' => 'nullable|exists:branches,id',
            'order_number' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:100',
            'model_id' => 'nullable|exists:cloth_types,id',
            'order_date' => 'required|date',
            'total_amount' => 'nullable|numeric|min:0',
            'payment_amount' => 'nullable|numeric|min:0',
            'remaining_payment' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            // Clothes array - SAME validation as ClothController store
            'clothes' => 'required|array|min:1',
            'clothes.*.code' => 'required|string|unique:clothes,code',
            'clothes.*.name' => 'required|string',
            'clothes.*.description' => 'nullable|string',
            'clothes.*.cloth_type_id' => 'required|integer|exists:cloth_types,id',
            'clothes.*.breast_size' => 'nullable|string',
            'clothes.*.waist_size' => 'nullable|string',
            'clothes.*.sleeve_size' => 'nullable|string',
            'clothes.*.notes' => 'nullable|string',
            'clothes.*.status' => 'nullable|in:damaged,burned,scratched,ready_for_rent,rented,repairing,die,sold',
            'clothes.*.entity_type' => 'required|string|in:branch,workshop,factory',
            'clothes.*.entity_id' => 'required|integer',
            'clothes.*.price' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $this->supplierOrderService->storeWithClothes($orderData);

            return response()->json([
                'message' => 'Supplier order created successfully',
                'order' => new SupplierOrderResource($result['order']),
                'clothes' => $result['clothes'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['general' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/supplier-orders/update/{id}",
     *     summary="Update a supplier order",
     *     tags={"Supplier Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="supplier_id", type="integer", example=1),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="subcategory_id", type="integer", example=1),
     *             @OA\Property(property="branch_id", type="integer", example=1),
     *             @OA\Property(property="order_number", type="string", example="SO-20260201-0001"),
     *             @OA\Property(property="order_date", type="string", format="date", example="2026-02-01"),
     *             @OA\Property(property="status", type="string", enum={"pending","confirmed","shipped","delivered","cancelled"}, example="confirmed"),
     *             @OA\Property(property="total_amount", type="number", example=1500.00),
     *             @OA\Property(property="payment_amount", type="number", example=500.00),
     *             @OA\Property(property="notes", type="string", example="Updated notes")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated successfully"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(SupplierOrderRequest $request, $id)
    {
        $item = $this->supplierOrderService->update($id, $request->validated());
        return new SupplierOrderResource($item->load(['supplier', 'category', 'subcategory', 'branch', 'clothes']));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/supplier-orders/delete/{id}",
     *     summary="Delete a supplier order",
     *     tags={"Supplier Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=204, description="Deleted successfully")
     * )
     */
    public function destroy($id)
    {
        $this->supplierOrderService->delete($id);
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/supplier-orders/export",
     *     summary="Export supplier orders to CSV",
     *     tags={"Supplier Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="CSV file")
     * )
     */
    public function export(Request $request)
    {
        $supplierId = $request->query('supplier_id');
        $items = $this->supplierOrderService->all($supplierId);
        return $this->exportToCsv($items, SupplierOrderExport::class, 'supplier_orders_' . date('Y-m-d_His') . '.csv');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/supplier-orders/statuses",
     *     summary="Get all supplier order statuses",
     *     tags={"Supplier Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="List of statuses")
     * )
     */
    public function statuses()
    {
        return response()->json(SupplierOrder::getStatuses());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/supplier-orders/generate-number",
     *     summary="Generate a new order number",
     *     tags={"Supplier Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Generated order number")
     * )
     */
    public function generateNumber()
    {
        return response()->json([
            'order_number' => $this->supplierOrderService->generateOrderNumber()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/supplier-orders/{id}/clothes",
     *     summary="Get clothes for a supplier order",
     *     tags={"Supplier Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="List of clothes")
     * )
     */
    public function getClothes($id)
    {
        $order = $this->supplierOrderService->find($id);
        $clothes = $order->clothes()->with(['clothType', 'inventories.inventoriable'])->get();

        return response()->json([
            'data' => $clothes->map(function ($cloth) {
                $inventory = $cloth->inventories->first();
                $entity = $inventory?->inventoriable;

                return [
                    'id' => $cloth->id,
                    'code' => $cloth->code,
                    'name' => $cloth->name,
                    'description' => $cloth->description,
                    'breast_size' => $cloth->breast_size,
                    'waist_size' => $cloth->waist_size,
                    'sleeve_size' => $cloth->sleeve_size,
                    'notes' => $cloth->notes,
                    'status' => $cloth->status,
                    'cloth_type_id' => $cloth->cloth_type_id,
                    'cloth_type_name' => $cloth->clothType->name ?? null,
                    'entity_type' => $entity ? strtolower(class_basename($entity)) : null,
                    'entity_id' => $entity?->id,
                    'entity_name' => $entity?->name,
                    'price' => $cloth->pivot->price ?? 0,
                ];
            }),
        ]);
    }
}
