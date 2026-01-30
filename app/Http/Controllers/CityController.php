<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\City;

class CityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/cities",
     *     summary="List all cities",
     *     tags={"Cities"},
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
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Cairo"),
     *                 @OA\Property(property="country", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Egypt")
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
        $items = City::with('country')->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cities/{id}",
     *     summary="Get a city by ID",
     *     tags={"Cities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Cairo"),
     *             @OA\Property(property="country", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Egypt")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = City::with('country')->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cities",
     *     summary="Create a new city",
     *     tags={"Cities"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"country_id", "name"},
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Cairo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="City created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Cairo")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string',
        ]);

        $item = City::create($data);
        return response()->json($item->load('country'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/cities/{id}",
     *     summary="Update a city",
     *     tags={"Cities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="New City Name")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="City updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="New City Name"),
     *             @OA\Property(property="country", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Egypt")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = City::findOrFail($id);
        $data = $request->validate([
            'country_id' => 'sometimes|required|exists:countries,id',
            'name' => 'sometimes|required|string',
        ]);
        $item->update($data);
        return response()->json($item->load('country'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/cities/{id}",
     *     summary="Delete a city",
     *     tags={"Cities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="City deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = City::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cities/export",
     *     summary="Export all cities to CSV",
     *     tags={"Cities"},
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
        $items = City::with('country')->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\CityExport::class, 'cities_' . date('Y-m-d_His') . '.csv');
    }
}
