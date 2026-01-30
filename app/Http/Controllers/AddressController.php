<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Address;

class AddressController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/addresses",
     *     summary="List all addresses",
     *     tags={"Addresses"},
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
     *                 @OA\Property(property="street", type="string", example="Tahrir St"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Next to bank"),
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
        $items = Address::with('city.country')->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/addresses/{id}",
     *     summary="Get an address by ID",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="street", type="string", example="Tahrir St"),
     *             @OA\Property(property="building", type="string", example="2A"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Next to bank"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="city", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Cairo"),
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="country", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Egypt")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Address::with('city.country')->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/addresses",
     *     summary="Create a new address",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"street", "building", "city_id"},
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="street", type="string", example="Tahrir St"),
     *             @OA\Property(property="building", type="string", example="2A"),
     *             @OA\Property(property="notes", type="string", example="Next to bank")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Address created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="street", type="string", example="Tahrir St"),
     *             @OA\Property(property="building", type="string", example="2A"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Next to bank"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="city", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Cairo"),
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="country", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Egypt")
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
            'street' => 'required|string',
            'building' => 'required|string',
            'notes' => 'nullable|string',
            'city_id' => 'required|exists:cities,id',
        ]);

        $item = Address::create($data);
        return response()->json($item->load('city.country'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/addresses/{id}",
     *     summary="Update an address",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="street", type="string", example="New Street"),
     *             @OA\Property(property="building", type="string", example="5B"),
     *             @OA\Property(property="notes", type="string", example="Updated notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="street", type="string", example="New Street"),
     *             @OA\Property(property="building", type="string", example="5B"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Updated notes"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="city", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Cairo"),
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="country", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Egypt")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = Address::findOrFail($id);
        $data = $request->validate([
            'street' => 'sometimes|required|string',
            'building' => 'sometimes|required|string',
            'notes' => 'nullable|string',
            'city_id' => 'sometimes|required|exists:cities,id',
        ]);
        $item->update($data);
        return response()->json($item->load('city.country'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/addresses/{id}",
     *     summary="Delete an address",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Address deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Address::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/addresses/export",
     *     summary="Export all addresses to CSV",
     *     tags={"Addresses"},
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
        $items = Address::with('city.country')->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\AddressExport::class, 'addresses_' . date('Y-m-d_His') . '.csv');
    }
}
