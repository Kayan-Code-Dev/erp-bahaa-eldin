<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Client;
use App\Models\Address;
use App\Models\Phone;

class ClientController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/clients",
     *     summary="List all clients",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="search", in="query", required=false, description="Search in first_name, middle_name, last_name, and national_id", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Ahmed"),
     *                 @OA\Property(property="middle_name", type="string", example="Mohamed"),
     *                 @OA\Property(property="last_name", type="string", example="Ali"),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="1990-05-15"),
     *                 @OA\Property(property="national_id", type="string", example="12345678901234"),
     *                 @OA\Property(property="source", type="string", nullable=true, example="website (optional)"),
     *                 @OA\Property(property="address_id", type="integer", example=1),
     *                 @OA\Property(property="address", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                     @OA\Property(property="building", type="string", example="2A"),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
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
     *                 ),
                 *                 @OA\Property(property="phones", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="phone", type="string", example="01234567890"),
     *                     @OA\Property(property="type", type="string", nullable=true, enum={"mobile", "landline", "whatsapp"}, example="mobile (optional) (allowed: mobile, landline, whatsapp)", description="Possible values: mobile, landline, whatsapp")
     *                 )),
     *                 @OA\Property(property="breast_size", type="string", nullable=true, example="90", description="Client body measurement"),
     *                 @OA\Property(property="waist_size", type="string", nullable=true, example="70", description="Client body measurement"),
     *                 @OA\Property(property="sleeve_size", type="string", nullable=true, example="60", description="Client body measurement"),
     *                 @OA\Property(property="hip_size", type="string", nullable=true, example="95", description="Client body measurement"),
     *                 @OA\Property(property="shoulder_size", type="string", nullable=true, example="40", description="Client body measurement"),
     *                 @OA\Property(property="length_size", type="string", nullable=true, example="160", description="Client body measurement"),
     *                 @OA\Property(property="measurement_notes", type="string", nullable=true, example="Prefers loose fit", description="Notes about measurements"),
     *                 @OA\Property(property="last_measurement_date", type="string", format="date", nullable=true, example="2025-01-09", description="Date of last measurement"),
     *                 @OA\Property(property="orders", type="array", @OA\Items(type="object"))
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
        $query = Client::with(['phones','orders','address.city.country']);

        // Search in first_name, middle_name, last_name, and national_id
        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('middle_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('national_id', 'LIKE', '%' . $search . '%');
            });
        }

        $items = $query->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/{id}",
     *     summary="Get a client by ID",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="Ahmed"),
     *             @OA\Property(property="middle_name", type="string", example="Mohamed"),
     *             @OA\Property(property="last_name", type="string", example="Ali"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-05-15"),
     *             @OA\Property(property="national_id", type="string", example="12345678901234"),
     *             @OA\Property(property="source", type="string", nullable=true, example="website (optional)"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
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
     *             ),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="90", description="Client body measurement"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="70", description="Client body measurement"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="60", description="Client body measurement"),
     *             @OA\Property(property="hip_size", type="string", nullable=true, example="95", description="Client body measurement"),
     *             @OA\Property(property="shoulder_size", type="string", nullable=true, example="40", description="Client body measurement"),
     *             @OA\Property(property="length_size", type="string", nullable=true, example="160", description="Client body measurement"),
     *             @OA\Property(property="measurement_notes", type="string", nullable=true, example="Prefers loose fit", description="Notes about measurements"),
     *             @OA\Property(property="last_measurement_date", type="string", format="date", nullable=true, example="2025-01-09", description="Date of last measurement"),
     *             @OA\Property(property="phones", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="orders", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Client::with(['phones','orders','address.city.country'])->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/clients",
     *     summary="Create a new client",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "middle_name", "last_name", "date_of_birth", "national_id", "address", "phones"},
     *             @OA\Property(property="first_name", type="string", example="Ahmed"),
     *             @OA\Property(property="middle_name", type="string", example="Mohamed"),
     *             @OA\Property(property="last_name", type="string", example="Ali"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-05-15"),
     *             @OA\Property(property="national_id", type="string", example="12345678901234", description="Must be exactly 14 digits and unique"),
     *             @OA\Property(property="source", type="string", nullable=true, example="website (optional)", description="Optional. Source of the client"),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 required={"street", "building", "city_id"},
     *                 @OA\Property(property="city_id", type="integer", example=1, description="ID of an existing city"),
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Next to the bank, 3rd floor (optional)", description="Optional")
     *             ),
     *             @OA\Property(
     *                 property="phones",
     *                 type="array",
     *                 description="At least one phone is required. Phone numbers must be unique within request and globally.",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"phone"},
     *                     @OA\Property(property="phone", type="string", example="01234567890", description="Phone number (must be unique globally)"),
     *                     @OA\Property(property="type", type="string", enum={"mobile", "landline", "whatsapp"}, nullable=true, example="mobile (optional) (allowed: mobile, landline, whatsapp)", description="Possible values: mobile, landline, whatsapp")
     *                 ),
     *                 minItems=1
     *             ),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="90", description="Client body measurement (optional)"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="70", description="Client body measurement (optional)"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="60", description="Client body measurement (optional)"),
     *             @OA\Property(property="hip_size", type="string", nullable=true, example="95", description="Client body measurement (optional)"),
     *             @OA\Property(property="shoulder_size", type="string", nullable=true, example="40", description="Client body measurement (optional)"),
     *             @OA\Property(property="length_size", type="string", nullable=true, example="160", description="Client body measurement (optional)"),
     *             @OA\Property(property="measurement_notes", type="string", nullable=true, example="Prefers loose fit", description="Notes about measurements (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Client created with phones",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="Ahmed"),
     *             @OA\Property(property="middle_name", type="string", example="Mohamed"),
     *             @OA\Property(property="last_name", type="string", example="Ali"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-05-15"),
     *             @OA\Property(property="national_id", type="string", example="12345678901234"),
     *             @OA\Property(property="source", type="string", nullable=true, example="website (optional)"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
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
     *             ),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="90"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="70"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="60"),
     *             @OA\Property(property="hip_size", type="string", nullable=true, example="95"),
     *             @OA\Property(property="shoulder_size", type="string", nullable=true, example="40"),
     *             @OA\Property(property="length_size", type="string", nullable=true, example="160"),
     *             @OA\Property(property="measurement_notes", type="string", nullable=true, example="Prefers loose fit"),
     *             @OA\Property(property="last_measurement_date", type="string", format="date", nullable=true, example="2025-01-09"),
     *             @OA\Property(property="phones", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        // Validate client data
        $data = $request->validate([
            'first_name' => 'required|string',
            'middle_name' => 'required|string',
            'last_name' => 'required|string',
            'date_of_birth' => 'required|date',
            'national_id' => 'required|string|digits:14|unique:clients,national_id',
            'source' => 'nullable|string',
            // Body measurements (optional)
            'breast_size' => 'nullable|string|max:20',
            'waist_size' => 'nullable|string|max:20',
            'sleeve_size' => 'nullable|string|max:20',
            'hip_size' => 'nullable|string|max:20',
            'shoulder_size' => 'nullable|string|max:20',
            'length_size' => 'nullable|string|max:20',
            'measurement_notes' => 'nullable|string|max:1000',
            'address' => 'required|array',
            'address.street' => 'required|string',
            'address.building' => 'required|string',
            'address.notes' => 'nullable|string',
            'address.city_id' => 'required|exists:cities,id',
            'phones' => 'required|array|min:1',
            'phones.*.phone' => 'required|string',
            'phones.*.type' => 'nullable|string',
        ]);

        // Validate phone uniqueness within request
        $phoneNumbers = collect($request->phones)->pluck('phone');
        if ($phoneNumbers->count() !== $phoneNumbers->unique()->count()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'phones' => ['Duplicate phone numbers are not allowed in the same request.']
                ]
            ], 422);
        }

        // Validate phone uniqueness globally
        $existingPhones = Phone::whereIn('phone', $phoneNumbers->toArray())
            ->pluck('phone')
            ->toArray();

        if (!empty($existingPhones)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'phones' => ['One or more phone numbers already exist: ' . implode(', ', $existingPhones)]
                ]
            ], 422);
        }

        // Extract address and phones from data
        $address = $data['address'];
        $phones = $data['phones'];
        unset($data['address'], $data['phones']);

        // Auto-set last_measurement_date if any measurement is provided
        $measurementFields = ['breast_size', 'waist_size', 'sleeve_size', 'hip_size', 'shoulder_size', 'length_size'];
        $hasMeasurements = collect($measurementFields)->some(fn($field) => !empty($data[$field]));
        if ($hasMeasurements) {
            $data['last_measurement_date'] = now()->toDateString();
        }

        // Create client, address, and phones in transaction
        $item = DB::transaction(function () use ($data, $address, $phones) {
            // Create address first
            $addressModel = Address::create($address);

            // Create client with address_id
            $data['address_id'] = $addressModel->id;
            $client = Client::create($data);

            // Create phones
            foreach ($phones as $phoneData) {
                $client->phones()->create([
                    'phone' => $phoneData['phone'],
                    'type' => $phoneData['type'] ?? null,
                ]);
            }

            return $client->load(['phones', 'address.city.country']);
        });

        return response()->json($item, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/clients/{id}",
     *     summary="Update a client",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="Ahmed"),
     *             @OA\Property(property="middle_name", type="string", example="Mohamed"),
     *             @OA\Property(property="last_name", type="string", example="Ali"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-05-15"),
     *             @OA\Property(property="national_id", type="string", example="12345678901234", description="Must be exactly 14 digits and unique (if provided)"),
     *             @OA\Property(property="source", type="string", example="website", description="Optional. Source of the client"),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 description="Optional. If provided, address will be updated (if id matches) or created (if no id or id doesn't match).",
     *                 @OA\Property(property="id", type="integer", example=1, description="Optional. Include to update existing address, omit to create new"),
     *                 @OA\Property(property="city_id", type="integer", example=1, description="ID of an existing city"),
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="notes", type="string", example="Next to the bank, 3rd floor", description="Optional")
     *             ),
     *             @OA\Property(
     *                 property="phones",
     *                 type="array",
     *                 description="Optional. If provided, phones will be synced (missing phones deleted, new phones added, existing phones updated). Phone numbers must be unique within request and globally (excluding current client's phones).",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"phone", "type"},
     *                     @OA\Property(property="id", type="integer", example=1, description="Optional. Include to update existing phone, omit to create new"),
     *                     @OA\Property(property="phone", type="string", example="01234567890", description="Phone number (must be unique globally, excluding current client's phones)"),
     *                     @OA\Property(property="type", type="string", enum={"mobile", "landline", "whatsapp"}, example="mobile (allowed: mobile, landline, whatsapp)", description="Possible values: mobile, landline, whatsapp")
     *                 ),
     *                 minItems=1
     *             ),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="90", description="Client body measurement (optional)"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="70", description="Client body measurement (optional)"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="60", description="Client body measurement (optional)"),
     *             @OA\Property(property="hip_size", type="string", nullable=true, example="95", description="Client body measurement (optional)"),
     *             @OA\Property(property="shoulder_size", type="string", nullable=true, example="40", description="Client body measurement (optional)"),
     *             @OA\Property(property="length_size", type="string", nullable=true, example="160", description="Client body measurement (optional)"),
     *             @OA\Property(property="measurement_notes", type="string", nullable=true, example="Prefers loose fit", description="Notes about measurements (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Client updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="Ahmed"),
     *             @OA\Property(property="middle_name", type="string", example="Mohamed"),
     *             @OA\Property(property="last_name", type="string", example="Ali"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-05-15"),
     *             @OA\Property(property="national_id", type="string", example="12345678901234"),
     *             @OA\Property(property="source", type="string", nullable=true, example="website (optional)"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Tahrir Square"),
     *                 @OA\Property(property="building", type="string", example="2A"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Notes (optional)"),
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
     *             ),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="90"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="70"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="60"),
     *             @OA\Property(property="hip_size", type="string", nullable=true, example="95"),
     *             @OA\Property(property="shoulder_size", type="string", nullable=true, example="40"),
     *             @OA\Property(property="length_size", type="string", nullable=true, example="160"),
     *             @OA\Property(property="measurement_notes", type="string", nullable=true, example="Prefers loose fit"),
     *             @OA\Property(property="last_measurement_date", type="string", format="date", nullable=true, example="2025-01-09"),
     *             @OA\Property(property="phones", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $item = Client::findOrFail($id);

        // Validate client data
        $validationRules = [
            'first_name' => 'sometimes|required|string',
            'middle_name' => 'sometimes|required|string',
            'last_name' => 'sometimes|required|string',
            'date_of_birth' => 'sometimes|required|date',
            'national_id' => "sometimes|required|string|digits:14|unique:clients,national_id,{$id}",
            'source' => 'nullable|string',
            // Body measurements (optional)
            'breast_size' => 'nullable|string|max:20',
            'waist_size' => 'nullable|string|max:20',
            'sleeve_size' => 'nullable|string|max:20',
            'hip_size' => 'nullable|string|max:20',
            'shoulder_size' => 'nullable|string|max:20',
            'length_size' => 'nullable|string|max:20',
            'measurement_notes' => 'nullable|string|max:1000',
            'address' => 'sometimes|required|array',
            'address.street' => 'required_with:address|string',
            'address.building' => 'required_with:address|string',
            'address.notes' => 'nullable|string',
            'address.city_id' => 'required_with:address|exists:cities,id',
        ];

        // If phones are provided, validate them
        if ($request->has('phones')) {
            $validationRules['phones'] = 'required|array|min:1';
            $validationRules['phones.*.phone'] = 'required|string';
            $validationRules['phones.*.type'] = 'nullable|string';
        }

        $data = $request->validate($validationRules);

        // Extract address from data if provided
        $address = null;
        if ($request->has('address')) {
            $address = $data['address'];
            unset($data['address']);
        }

        // Extract phones from data if provided
        $phones = null;
        if ($request->has('phones')) {
            $phones = $data['phones'];
            unset($data['phones']);

            // Validate phone uniqueness within request
            $phoneNumbers = collect($phones)->pluck('phone');
            if ($phoneNumbers->count() !== $phoneNumbers->unique()->count()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'phones' => ['Duplicate phone numbers are not allowed in the same request.']
                    ]
                ], 422);
            }

            // Get existing phone IDs and numbers for this client
            $existingPhoneIds = $item->phones()->pluck('id')->toArray();
            $existingPhoneNumbers = $item->phones()->pluck('phone')->toArray();

            // Validate that any phone IDs provided belong to this client
            $providedPhoneIds = collect($phones)
                ->pluck('id')
                ->filter()
                ->toArray();

            if (!empty($providedPhoneIds)) {
                $invalidPhoneIds = array_diff($providedPhoneIds, $existingPhoneIds);
                if (!empty($invalidPhoneIds)) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'phones' => ['One or more phone IDs do not belong to this client: ' . implode(', ', $invalidPhoneIds)]
                        ]
                    ], 422);
                }
            }

            // Validate phone uniqueness globally (excluding current client's phones)
            $newPhoneNumbers = $phoneNumbers->toArray();
            $phonesToCheck = array_diff($newPhoneNumbers, $existingPhoneNumbers);

            if (!empty($phonesToCheck)) {
                $conflictingPhones = Phone::whereIn('phone', $phonesToCheck)
                    ->whereNotIn('id', $existingPhoneIds)
                    ->pluck('phone')
                    ->toArray();

                if (!empty($conflictingPhones)) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'phones' => ['One or more phone numbers already exist: ' . implode(', ', $conflictingPhones)]
                        ]
                    ], 422);
                }
            }
        }

        // Validate that address ID belongs to this client if provided
        if ($address !== null && isset($address['id'])) {
            if ($item->address_id != $address['id']) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'address' => ['The address ID does not belong to this client.']
                    ]
                ], 422);
            }
        }

        // Check if any measurement field is being updated, auto-set last_measurement_date
        $measurementFields = ['breast_size', 'waist_size', 'sleeve_size', 'hip_size', 'shoulder_size', 'length_size'];
        $hasMeasurementUpdate = collect($measurementFields)->some(fn($field) => array_key_exists($field, $data) && $data[$field] !== null);
        if ($hasMeasurementUpdate) {
            $data['last_measurement_date'] = now()->toDateString();
        }

        // Update client, address, and sync phones in transaction
        $item = DB::transaction(function () use ($item, $data, $address, $phones) {
            // Update or create address if provided
            if ($address !== null) {
                if (isset($address['id']) && $item->address_id == $address['id']) {
                    // Update existing address
                    $addressModel = $item->address;
                    $addressData = $address;
                    unset($addressData['id']);
                    $addressModel->update($addressData);
                } else {
                    // Create new address
                    $addressData = $address;
                    unset($addressData['id']);
                    $addressModel = Address::create($addressData);
                    $data['address_id'] = $addressModel->id;
                }
            }

            // Update client data
            if (!empty($data)) {
                $item->update($data);
            }

            // Sync phones if provided
            if ($phones !== null) {
                // Get existing phone IDs from request (if phones have 'id' field)
                $existingPhoneIds = collect($phones)
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                // Delete phones not in request
                $item->phones()->whereNotIn('id', $existingPhoneIds)->delete();

                // Create/update phones
                foreach ($phones as $phoneData) {
                    if (isset($phoneData['id']) && in_array($phoneData['id'], $existingPhoneIds)) {
                        // Update existing phone
                        $item->phones()->where('id', $phoneData['id'])->update([
                            'phone' => $phoneData['phone'],
                            'type' => $phoneData['type'] ?? null,
                        ]);
                    } else {
                        // Create new phone
                        $item->phones()->create([
                            'phone' => $phoneData['phone'],
                            'type' => $phoneData['type'] ?? null,
                        ]);
                    }
                }
            }

            return $item->load(['phones', 'address.city.country']);
        });

        return response()->json($item);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/clients/{id}",
     *     summary="Delete a client",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Client deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Client::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/export",
     *     summary="Export all clients to CSV",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="Search in first_name, middle_name, last_name, and national_id", @OA\Schema(type="string")),
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
        $query = Client::with(['phones','orders','address.city.country']);

        // Search in first_name, middle_name, last_name, and national_id
        if ($request->has('search') && $request->input('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('middle_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('national_id', 'LIKE', '%' . $search . '%');
            });
        }

        $items = $query->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\ClientExport::class, 'clients_' . date('Y-m-d_His') . '.csv');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/clients/{id}/measurements",
     *     summary="Update client body measurements",
     *     description="Update only the body measurement fields for a client. Automatically updates last_measurement_date.",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="90", description="Client body measurement"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="70", description="Client body measurement"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="60", description="Client body measurement"),
     *             @OA\Property(property="hip_size", type="string", nullable=true, example="95", description="Client body measurement"),
     *             @OA\Property(property="shoulder_size", type="string", nullable=true, example="40", description="Client body measurement"),
     *             @OA\Property(property="length_size", type="string", nullable=true, example="160", description="Client body measurement"),
     *             @OA\Property(property="measurement_notes", type="string", nullable=true, example="Prefers loose fit", description="Notes about measurements")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Measurements updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Measurements updated successfully"),
     *             @OA\Property(property="client", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Ahmed"),
     *                 @OA\Property(property="last_name", type="string", example="Ali"),
     *                 @OA\Property(property="breast_size", type="string", nullable=true, example="90"),
     *                 @OA\Property(property="waist_size", type="string", nullable=true, example="70"),
     *                 @OA\Property(property="sleeve_size", type="string", nullable=true, example="60"),
     *                 @OA\Property(property="hip_size", type="string", nullable=true, example="95"),
     *                 @OA\Property(property="shoulder_size", type="string", nullable=true, example="40"),
     *                 @OA\Property(property="length_size", type="string", nullable=true, example="160"),
     *                 @OA\Property(property="measurement_notes", type="string", nullable=true, example="Prefers loose fit"),
     *                 @OA\Property(property="last_measurement_date", type="string", format="date", example="2025-01-09")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Client not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateMeasurements(Request $request, $id)
    {
        $client = Client::findOrFail($id);

        $data = $request->validate([
            'breast_size' => 'nullable|string|max:20',
            'waist_size' => 'nullable|string|max:20',
            'sleeve_size' => 'nullable|string|max:20',
            'hip_size' => 'nullable|string|max:20',
            'shoulder_size' => 'nullable|string|max:20',
            'length_size' => 'nullable|string|max:20',
            'measurement_notes' => 'nullable|string|max:1000',
        ]);

        // Use the model method to update measurements (auto-sets last_measurement_date)
        $client->updateMeasurements($data);

        return response()->json([
            'message' => 'Measurements updated successfully',
            'client' => $client->only([
                'id', 'first_name', 'last_name',
                'breast_size', 'waist_size', 'sleeve_size',
                'hip_size', 'shoulder_size', 'length_size',
                'measurement_notes', 'last_measurement_date'
            ]),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/{id}/measurements",
     *     summary="Get client body measurements",
     *     description="Get only the body measurement fields for a client.",
     *     tags={"Clients"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Measurements retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="client_id", type="integer", example=1),
     *             @OA\Property(property="client_name", type="string", example="Ahmed Ali"),
     *             @OA\Property(property="breast_size", type="string", nullable=true, example="90"),
     *             @OA\Property(property="waist_size", type="string", nullable=true, example="70"),
     *             @OA\Property(property="sleeve_size", type="string", nullable=true, example="60"),
     *             @OA\Property(property="hip_size", type="string", nullable=true, example="95"),
     *             @OA\Property(property="shoulder_size", type="string", nullable=true, example="40"),
     *             @OA\Property(property="length_size", type="string", nullable=true, example="160"),
     *             @OA\Property(property="measurement_notes", type="string", nullable=true, example="Prefers loose fit"),
     *             @OA\Property(property="last_measurement_date", type="string", format="date", nullable=true, example="2025-01-09"),
     *             @OA\Property(property="has_measurements", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Client not found")
     * )
     */
    public function getMeasurements($id)
    {
        $client = Client::findOrFail($id);

        return response()->json([
            'client_id' => $client->id,
            'client_name' => trim("{$client->first_name} {$client->last_name}"),
            'breast_size' => $client->breast_size,
            'waist_size' => $client->waist_size,
            'sleeve_size' => $client->sleeve_size,
            'hip_size' => $client->hip_size,
            'shoulder_size' => $client->shoulder_size,
            'length_size' => $client->length_size,
            'measurement_notes' => $client->measurement_notes,
            'last_measurement_date' => $client->last_measurement_date?->format('Y-m-d'),
            'has_measurements' => $client->hasMeasurements(),
        ]);
    }
}
