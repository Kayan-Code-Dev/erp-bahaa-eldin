<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\SupplierService;
use App\Services\SupplierOrderService;
use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\SupplierOrderResource;
use App\Exports\SupplierExport;

class SupplierController extends Controller
{
    protected SupplierService $supplierService;
    protected SupplierOrderService $supplierOrderService;

    public function __construct(SupplierService $supplierService, SupplierOrderService $supplierOrderService)
    {
        $this->supplierService = $supplierService;
        $this->supplierOrderService = $supplierOrderService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/suppliers",
     *     summary="List all suppliers",
     *     description="Get a paginated list of all suppliers with optional search",
     *     operationId="suppliersIndex",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or code",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SupplierResource")),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=50),
     *             @OA\Property(property="total_pages", type="integer", example=4),
     *             @OA\Property(property="per_page", type="integer", example=15)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $search = $request->query('search');
        $items = $this->supplierService->list($perPage, $search);
        return $this->paginatedResponse($items, SupplierResource::class);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/suppliers/show/{id}",
     *     summary="Get a supplier by ID",
     *     description="Retrieve a single supplier by ID",
     *     operationId="suppliersShow",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/SupplierResource")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Supplier not found")
     * )
     */
    public function show($id)
    {
        $item = $this->supplierService->find($id);
        return new SupplierResource($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/suppliers/store",
     *     summary="Create supplier with optional order (SAME structure as SupplierOrder store)",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "code"},
     *             @OA\Property(property="name", type="string", example="ABC Suppliers Ltd"),
     *             @OA\Property(property="code", type="string", example="SUP001"),
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
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="CLT-001"),
     *                     @OA\Property(property="name", type="string", example="Blue Thobe"),
     *                     @OA\Property(property="cloth_type_id", type="integer", example=1),
     *                     @OA\Property(property="entity_type", type="string", example="branch"),
     *                     @OA\Property(property="entity_id", type="integer", example=1),
     *                     @OA\Property(property="price", type="number", example=150.00)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Supplier created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        // Validate - SAME structure as SupplierOrderController store
        $data = $request->validate([
            // Supplier fields
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:suppliers,code',
            // Order fields (optional - same as SupplierOrder store)
            'category_id' => 'nullable|exists:categories,id',
            'subcategory_id' => 'nullable|exists:subcategories,id',
            'branch_id' => 'nullable|exists:branches,id',
            'order_number' => 'nullable|string|max:50',
            'order_date' => 'nullable|date',
            'payment_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            // Clothes array - SAME as SupplierOrder store
            'clothes' => 'nullable|array|min:1',
            'clothes.*.code' => 'required_with:clothes|string|unique:clothes,code',
            'clothes.*.name' => 'required_with:clothes|string',
            'clothes.*.description' => 'nullable|string',
            'clothes.*.cloth_type_id' => 'required_with:clothes|integer|exists:cloth_types,id',
            'clothes.*.breast_size' => 'nullable|string',
            'clothes.*.waist_size' => 'nullable|string',
            'clothes.*.sleeve_size' => 'nullable|string',
            'clothes.*.notes' => 'nullable|string',
            'clothes.*.status' => 'nullable|in:damaged,burned,scratched,ready_for_rent,rented,repairing,die,sold',
            'clothes.*.entity_type' => 'required_with:clothes|string|in:branch,workshop,factory',
            'clothes.*.entity_id' => 'required_with:clothes|integer',
            'clothes.*.price' => 'nullable|numeric|min:0',
        ]);

        // Create supplier first
        $supplier = $this->supplierService->create([
            'name' => $data['name'],
            'code' => $data['code'],
        ]);

        $orderResult = null;

        // If clothes provided, create order with clothes (SAME as SupplierOrder store)
        if (!empty($data['clothes'])) {
            try {
                $orderData = [
                    'supplier_id' => $supplier->id,
                    'category_id' => $data['category_id'] ?? null,
                    'subcategory_id' => $data['subcategory_id'] ?? null,
                    'branch_id' => $data['branch_id'] ?? null,
                    'order_number' => $data['order_number'] ?? null,
                    'order_date' => $data['order_date'] ?? now()->format('Y-m-d'),
                    'payment_amount' => $data['payment_amount'] ?? 0,
                    'notes' => $data['notes'] ?? null,
                    'clothes' => $data['clothes'],
                ];

                $orderResult = $this->supplierOrderService->storeWithClothes($orderData);
            } catch (\Exception $e) {
                Log::error("Failed to create order for supplier {$supplier->id}: " . $e->getMessage());
                return response()->json([
                    'message' => 'Supplier created but order failed: ' . $e->getMessage(),
                    'supplier' => new SupplierResource($supplier),
                ], 201);
            }
        }

        // Load relationships
        $supplier->load('supplierOrders');

        $response = [
            'message' => 'Supplier created successfully',
            'supplier' => new SupplierResource($supplier),
        ];

        if ($orderResult) {
            $response['order'] = new SupplierOrderResource($orderResult['order']);
            $response['clothes'] = $orderResult['clothes'];
        }

        return response()->json($response, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/suppliers/update/{id}",
     *     summary="Update a supplier",
     *     description="Update an existing supplier with the provided data",
     *     operationId="suppliersUpdate",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Supplier data to update",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Supplier Name", description="Name of the supplier"),
     *             @OA\Property(property="code", type="string", example="SUP002", description="Unique supplier code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Supplier updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/SupplierResource")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Supplier not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(SupplierRequest $request, $id)
    {
        $item = $this->supplierService->update($id, $request->validated());
        return new SupplierResource($item);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/suppliers/delete/{id}",
     *     summary="Delete a supplier",
     *     description="Soft delete a supplier by ID",
     *     operationId="suppliersDestroy",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Supplier deleted successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Supplier not found")
     * )
     */
    public function destroy($id)
    {
        $this->supplierService->delete($id);
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/suppliers/export",
     *     summary="Export suppliers to CSV",
     *     description="Export all suppliers to a CSV file",
     *     operationId="suppliersExport",
     *     tags={"Suppliers"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="CSV file download",
     *         @OA\MediaType(mediaType="text/csv")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function export(Request $request)
    {
        $items = $this->supplierService->all();
        return $this->exportToCsv($items, SupplierExport::class, 'suppliers_' . date('Y-m-d_His') . '.csv');
    }
}
