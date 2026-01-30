<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\Address;

class BranchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/branches",
     *     summary="List all branches",
     *     tags={"Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="branch_code", type="string", example="BR-001"),
     *                 @OA\Property(property="name", type="string", example="Downtown Branch"),
     *                 @OA\Property(property="address_id", type="integer", example=1),
     *                 @OA\Property(property="address", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                     @OA\Property(property="building", type="string", example="2A"),
     *                     @OA\Property(property="notes", type="string", nullable=true),
     *                     @OA\Property(property="city_id", type="integer", example=1),
     *                     @OA\Property(property="city", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Cairo"),
     *                         @OA\Property(property="country_id", type="integer", example=1),
     *                         @OA\Property(property="country", type="object", nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Egypt")
     *                         )
     *                     )
     *                 )
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="total_pages", type="integer", example=7),
     *             @OA\Property(property="per_page", type="integer", example=15)
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = Branch::with(['inventory','address.city.country'])->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/branches/{id}",
     *     summary="Get a branch by ID",
     *     tags={"Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="branch_code", type="string", example="BR-001"),
     *             @OA\Property(property="name", type="string", example="Downtown Branch"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="city", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Cairo"),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="country", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Egypt")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Branch::with(['inventory','address.city.country'])->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/branches",
     *     summary="Create a new branch",
     *     tags={"Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"branch_code", "name", "address"},
     *             @OA\Property(property="branch_code", type="string", example="BR-001"),
     *             @OA\Property(property="name", type="string", example="Downtown Branch"),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 required={"street", "building", "city_id"},
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="notes", type="string", example="Next to the bank, 3rd floor")
     *             ),
     *             @OA\Property(property="inventory_name", type="string", example="Downtown Branch Inventory", description="Optional: name for the automatically created inventory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Branch created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="branch_code", type="string", example="BR-001"),
     *             @OA\Property(property="name", type="string", example="Downtown Branch"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="city", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Cairo"),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="country", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Egypt")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_code' => 'required|string|unique:branches,branch_code',
            'name' => 'required|string',
            'address' => 'required|array',
            'address.street' => 'required|string',
            'address.building' => 'required|string',
            'address.city_id' => 'required|exists:cities,id',
            'address.notes' => 'nullable|string',
            'inventory_name' => 'nullable|string',
        ]);

        $inventoryName = $data['inventory_name'] ?? $data['name'] . ' Inventory';
        unset($data['inventory_name']);

        // Create address first
        $address = Address::create($data['address']);
        $data['address_id'] = $address->id;
        unset($data['address']);

        $branch = Branch::create($data);

        // Automatically create inventory for the branch
        $branch->inventory()->create([
            'name' => $inventoryName,
        ]);

        return response()->json($branch->load(['inventory', 'address.city.country']), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/branches/{id}",
     *     summary="Update a branch",
     *     tags={"Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="branch_code", type="string", example="BR-001-UPDATED"),
     *             @OA\Property(property="name", type="string", example="Uptown Branch"),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 required={"street", "building", "city_id"},
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="notes", type="string", example="Next to the bank, 3rd floor")
     *             ),
     *             @OA\Property(property="inventory_name", type="string", example="Uptown Branch Inventory", description="Optional: name for the inventory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Branch updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="branch_code", type="string", example="BR-001-UPDATED"),
     *             @OA\Property(property="name", type="string", example="Uptown Branch"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="city", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Cairo"),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="country", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Egypt")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = Branch::findOrFail($id);

        $validationRules = [
            'branch_code' => "sometimes|required|string|unique:branches,branch_code,{$id}",
            'name' => 'sometimes|required|string',
            'address' => 'sometimes|required|array',
            'address.street' => 'required_with:address|string',
            'address.building' => 'required_with:address|string',
            'address.city_id' => 'required_with:address|exists:cities,id',
            'address.notes' => 'nullable|string',
            'inventory_name' => 'nullable|string',
        ];

        $data = $request->validate($validationRules);

        // Extract address from data if provided
        $address = null;
        if ($request->has('address')) {
            $address = $data['address'];
            unset($data['address']);
        }

        // Extract inventory_name from data if provided
        $inventoryName = null;
        if ($request->has('inventory_name')) {
            $inventoryName = $data['inventory_name'];
            unset($data['inventory_name']);
        }

        // Update or create address if provided
        if ($address !== null) {
            if ($item->address_id) {
                // Update existing address
                $item->address->update($address);
            } else {
                // Create new address
                $addressModel = Address::create($address);
                $data['address_id'] = $addressModel->id;
            }
        }

        // Update branch data
        if (!empty($data)) {
            $item->update($data);
        }

        // Update inventory name if provided and inventory exists
        if ($inventoryName !== null && $item->inventory) {
            $item->inventory->update(['name' => $inventoryName]);
        }

        return response()->json($item->load(['inventory', 'address.city.country']));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/branches/{id}",
     *     summary="Delete a branch",
     *     tags={"Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Branch deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Branch::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/branches/export",
     *     summary="Export all branches to CSV",
     *     tags={"Branches"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="CSV file download",
     *         @OA\MediaType(
     *             mediaType="text/csv"
     *         )
     *     )
     * )
     */
    public function export(Request $request)
    {
        $items = Branch::with(['address.city.country', 'inventory'])->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\BranchExport::class, 'branches_' . date('Y-m-d_His') . '.csv');
    }
}
