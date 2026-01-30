<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subcategory;

class SubcategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/subcategories",
     *     summary="List all subcategories",
     *     tags={"Subcategories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="category_id", in="query", required=false, description="Filter by category ID(s). Can be a single ID, comma-separated IDs, or array of IDs", @OA\Schema(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string"), @OA\Schema(type="array", @OA\Items(type="integer"))})),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="category_id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="New Subcategory"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Description"),
     *                 @OA\Property(property="category", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Category Name"),
     *                     @OA\Property(property="description", type="string", nullable=true)
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
        $query = Subcategory::with(['category','clothes']);

        // Filter by category_id (supports multiple values)
        if ($request->has('category_id')) {
            $categoryInput = $request->input('category_id');
            $categoryIds = is_array($categoryInput)
                ? $categoryInput
                : (is_string($categoryInput) ? explode(',', $categoryInput) : [$categoryInput]);

            // Filter out empty values and convert to integers
            $categoryIds = array_filter(array_map('intval', $categoryIds));

            if (!empty($categoryIds)) {
                $query->whereIn('category_id', $categoryIds);
            }
        }

        $items = $query->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/subcategories/{id}",
     *     summary="Get a subcategory by ID",
     *     tags={"Subcategories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="New Subcategory"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Description"),
     *             @OA\Property(property="category", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Category Name"),
     *                 @OA\Property(property="description", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Subcategory::with(['category','clothes'])->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/subcategories",
     *     summary="Create a new subcategory",
     *     tags={"Subcategories"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"category_id", "name"},
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="New Subcategory"),
     *             @OA\Property(property="description", type="string", example="Description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subcategory created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="New Subcategory"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Description"),
     *             @OA\Property(property="category", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Category Name"),
     *                 @OA\Property(property="description", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $item = Subcategory::create($data);
        return response()->json($item->load('category'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/subcategories/{id}",
     *     summary="Update a subcategory",
     *     tags={"Subcategories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated Subcategory"),
     *             @OA\Property(property="description", type="string", example="Updated Description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subcategory updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated Subcategory"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Updated Description"),
     *             @OA\Property(property="category", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Category Name"),
     *                 @OA\Property(property="description", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = Subcategory::findOrFail($id);
        $data = $request->validate([
            'category_id' => 'sometimes|required|exists:categories,id',
            'name' => 'sometimes|required|string',
            'description' => 'nullable|string',
        ]);
        $item->update($data);
        return response()->json($item->load('category'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/subcategories/{id}",
     *     summary="Delete a subcategory",
     *     tags={"Subcategories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Subcategory deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Subcategory::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/subcategories/export",
     *     summary="Export all subcategories to CSV",
     *     tags={"Subcategories"},
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
        $items = Subcategory::with('category')->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\SubcategoryExport::class, 'subcategories_' . date('Y-m-d_His') . '.csv');
    }
}
