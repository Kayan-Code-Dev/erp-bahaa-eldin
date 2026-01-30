<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Phone;

class PhoneController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/phones",
     *     summary="List all phones",
     *     tags={"Phones"},
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
     *                 @OA\Property(property="client_id", type="integer", example=1),
     *                 @OA\Property(property="phone", type="string", example="01234567890"),
     *                 @OA\Property(property="type", type="string", enum={"mobile", "landline", "whatsapp"}, nullable=true, example="mobile"),
     *                 @OA\Property(property="client", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="first_name", type="string", example="Ahmed"),
     *                     @OA\Property(property="address_id", type="integer", example=1),
     *                     @OA\Property(property="address", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                         @OA\Property(property="city_id", type="integer", example=1),
     *                         @OA\Property(property="city", type="object", nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Cairo"),
     *                             @OA\Property(property="country_id", type="integer", example=1),
     *                             @OA\Property(property="country", type="object", nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Egypt")
     *                             )
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
        $items = Phone::with('client.address.city.country')->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/phones/{id}",
     *     summary="Get a phone by ID",
     *     tags={"Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="client_id", type="integer", example=1),
     *             @OA\Property(property="phone", type="string", example="01234567890"),
     *             @OA\Property(property="type", type="string", nullable=true, example="mobile"),
     *             @OA\Property(property="client", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Ahmed"),
     *                 @OA\Property(property="address_id", type="integer", example=1),
     *                 @OA\Property(property="address", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="street", type="string", example="Tahrir Square"),
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
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Phone::with('client.address.city.country')->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/phones",
     *     summary="Create a new phone",
     *     tags={"Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"client_id", "phone"},
     *             @OA\Property(property="client_id", type="integer", example=1),
     *             @OA\Property(property="phone", type="string", example="01234567890"),
     *             @OA\Property(property="type", type="string", enum={"mobile", "landline", "whatsapp"}, nullable=true, example="mobile", description="Optional. Phone type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Phone created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="client_id", type="integer", example=1),
     *             @OA\Property(property="phone", type="string", example="01234567890"),
     *             @OA\Property(property="type", type="string", nullable=true, example="mobile"),
     *             @OA\Property(property="client", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Ahmed"),
     *                 @OA\Property(property="address_id", type="integer", example=1),
     *                 @OA\Property(property="address", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="street", type="string", example="Tahrir Square"),
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
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'phone' => 'required|string',
            'type' => 'nullable|string|in:mobile,landline,whatsapp',
        ]);

        $item = Phone::create($data);
        return response()->json($item->load('client.address.city.country'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/phones/{id}",
     *     summary="Update a phone",
     *     tags={"Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="client_id", type="integer", example=1),
     *             @OA\Property(property="phone", type="string", example="01234567891"),
     *             @OA\Property(property="type", type="string", enum={"mobile", "landline", "whatsapp"}, nullable=true, example="mobile", description="Optional. Phone type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Phone updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="client_id", type="integer", example=1),
     *             @OA\Property(property="phone", type="string", example="01234567891"),
     *             @OA\Property(property="type", type="string", nullable=true, example="mobile"),
     *             @OA\Property(property="client", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Ahmed"),
     *                 @OA\Property(property="address_id", type="integer", example=1),
     *                 @OA\Property(property="address", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="street", type="string", example="Tahrir Square"),
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
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = Phone::findOrFail($id);
        $data = $request->validate([
            'client_id' => 'sometimes|required|exists:clients,id',
            'phone' => 'sometimes|required|string',
            'type' => 'sometimes|nullable|string',
        ]);
        $item->update($data);
        return response()->json($item->load('client.address.city.country'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/phones/{id}",
     *     summary="Delete a phone",
     *     tags={"Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Phone deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Phone::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/phones/export",
     *     summary="Export all phones to CSV",
     *     tags={"Phones"},
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
        $items = Phone::with('client')->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\PhoneExport::class, 'phones_' . date('Y-m-d_His') . '.csv');
    }
}
