<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClothType;

class ClothTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/cloth-types",
     *     summary="List all cloth types",
     *     tags={"Cloth Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
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
        $items = ClothType::with('subcategories')->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cloth-types/{id}",
     *     summary="Get a cloth type by ID",
     *     tags={"Cloth Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = ClothType::with('subcategories')->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cloth-types",
     *     summary="Create a new cloth type",
     *     tags={"Cloth Types"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name"},
     *             @OA\Property(property="code", type="string", example="CT-101"),
     *             @OA\Property(property="name", type="string", example="Red Dress Model"),
     *             @OA\Property(property="description", type="string", example="Evening dress description"),
     *             @OA\Property(property="subcat_id", type="array", @OA\Items(type="integer"), example={1, 2})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Cloth type created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:cloth_types,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'subcat_id' => 'nullable|array',
            'subcat_id.*' => 'required|integer|exists:subcategories,id',
        ]);

        $subcatIds = $data['subcat_id'] ?? [];
        unset($data['subcat_id']);

        $item = ClothType::create($data);

        if (!empty($subcatIds)) {
            $item->subcategories()->sync($subcatIds);
        }

        return response()->json($item->load('subcategories'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/cloth-types/{id}",
     *     summary="Update a cloth type",
     *     tags={"Cloth Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", example="CT-101-UPDATED"),
     *             @OA\Property(property="name", type="string", example="Updated Red Dress Model"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="subcat_id", type="array", @OA\Items(type="integer"), example={1, 2})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cloth type updated"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = ClothType::findOrFail($id);
        $data = $request->validate([
            'code' => "sometimes|required|string|unique:cloth_types,code,{$id}",
            'name' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'subcat_id' => 'nullable|array',
            'subcat_id.*' => 'required|integer|exists:subcategories,id',
        ]);

        $subcatIds = null;
        if ($request->has('subcat_id')) {
            $subcatIds = $data['subcat_id'];
            unset($data['subcat_id']);
        }

        $item->update($data);

        if ($subcatIds !== null) {
            $item->subcategories()->sync($subcatIds);
        }

        return response()->json($item->load('subcategories'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/cloth-types/{id}",
     *     summary="Delete a cloth type",
     *     tags={"Cloth Types"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Cloth type deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = ClothType::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cloth-types/export",
     *     summary="Export all cloth types to CSV",
     *     tags={"Cloth Types"},
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
        $items = ClothType::with('subcategories')->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\ClothTypeExport::class, 'cloth-types_' . date('Y-m-d_His') . '.csv');
    }
}

