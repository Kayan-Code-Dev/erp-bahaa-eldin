<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Factory;
use App\Models\Inventory;
use App\Models\Address;
use App\Models\Order;
use App\Models\FactoryEvaluation;
use App\Models\FactoryUser;
use App\Models\User;
use App\Services\FactoryStatisticsService;

class FactoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/factories",
     *     summary="List all factories",
     *     tags={"Factories"},
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
     *                 @OA\Property(property="factory_code", type="string", example="FA-001"),
     *                 @OA\Property(property="name", type="string", example="Main Factory"),
     *                 @OA\Property(property="address_id", type="integer", example=1),
     *                 @OA\Property(property="address", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="street", type="string", example="Factory District"),
     *                     @OA\Property(property="building", type="string", example="Building 10"),
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
        $items = Factory::with(['inventory', 'address.city.country'])->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factories/{id}",
     *     summary="Get a factory by ID",
     *     tags={"Factories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="factory_code", type="string", example="FA-001"),
     *             @OA\Property(property="name", type="string", example="Main Factory"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Factory District"),
     *                 @OA\Property(property="building", type="string", example="Building 10"),
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
        $item = Factory::with(['inventory', 'address.city.country'])->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/factories",
     *     summary="Create a new factory",
     *     tags={"Factories"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"factory_code", "name", "address"},
     *             @OA\Property(property="factory_code", type="string", example="FA-001"),
     *             @OA\Property(property="name", type="string", example="Main Factory"),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 required={"street", "building", "city_id"},
     *                 @OA\Property(property="street", type="string", example="Factory District"),
     *                 @OA\Property(property="building", type="string", example="Building 10"),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="notes", type="string", example="Large warehouse facility")
     *             ),
     *             @OA\Property(property="inventory_name", type="string", example="Main Factory Inventory", description="Optional: name for the automatically created inventory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Factory created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="factory_code", type="string", example="FA-001"),
     *             @OA\Property(property="name", type="string", example="Main Factory"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Factory District"),
     *                 @OA\Property(property="building", type="string", example="Building 10"),
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
            'factory_code' => 'required|string|unique:factories,factory_code',
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

        $factory = Factory::create($data);

        // Automatically create inventory for the factory
        $factory->inventory()->create([
            'name' => $inventoryName,
        ]);

        return response()->json($factory->load(['inventory', 'address.city.country']), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/factories/{id}",
     *     summary="Update a factory",
     *     tags={"Factories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="factory_code", type="string", example="FA-001-UPDATED"),
     *             @OA\Property(property="name", type="string", example="Updated Factory"),
     *             @OA\Property(
     *                 property="address",
     *                 type="object",
     *                 required={"street", "building", "city_id"},
     *                 @OA\Property(property="street", type="string", example="Factory District"),
     *                 @OA\Property(property="building", type="string", example="Building 10"),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="notes", type="string", example="Large warehouse facility")
     *             ),
     *             @OA\Property(property="inventory_name", type="string", example="Updated Factory Inventory", description="Optional: name for the inventory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Factory updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="factory_code", type="string", example="FA-001-UPDATED"),
     *             @OA\Property(property="name", type="string", example="Updated Factory"),
     *             @OA\Property(property="address_id", type="integer", example=1),
     *             @OA\Property(property="address", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="street", type="string", example="Factory District"),
     *                 @OA\Property(property="building", type="string", example="Building 10"),
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
        $item = Factory::findOrFail($id);

        $validationRules = [
            'factory_code' => "sometimes|required|string|unique:factories,factory_code,{$id}",
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

        // Update factory data
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
     *     path="/api/v1/factories/{id}",
     *     summary="Delete a factory",
     *     tags={"Factories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Factory deleted"),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function destroy($id)
    {
        $item = Factory::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factories/export",
     *     summary="Export all factories to CSV",
     *     tags={"Factories"},
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
        $items = Factory::with(['address.city.country', 'inventory'])->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\FactoryExport::class, 'factories_' . date('Y-m-d_His') . '.csv');
    }

    // ==================== STATISTICS ENDPOINTS ====================

    /**
     * @OA\Get(
     *     path="/api/v1/factories/statistics",
     *     summary="Get overall factory statistics",
     *     tags={"Factory Statistics"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Overall statistics",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_factories", type="integer"),
     *             @OA\Property(property="active_factories", type="integer"),
     *             @OA\Property(property="total_current_orders", type="integer"),
     *             @OA\Property(property="total_orders_completed", type="integer"),
     *             @OA\Property(property="average_quality_rating", type="number"),
     *             @OA\Property(property="average_on_time_rate", type="number"),
     *             @OA\Property(property="total_capacity", type="integer"),
     *             @OA\Property(property="capacity_utilization", type="number")
     *         )
     *     )
     * )
     */
    public function statistics()
    {
        $service = new FactoryStatisticsService();
        return response()->json($service->getOverallStatistics());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factories/ranking",
     *     summary="Get factories ranked by performance",
     *     tags={"Factory Statistics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Factory ranking",
     *         @OA\JsonContent(type="array", @OA\Items(
     *             type="object",
     *             @OA\Property(property="rank", type="integer"),
     *             @OA\Property(property="factory_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="quality_rating", type="number"),
     *             @OA\Property(property="on_time_rate", type="number"),
     *             @OA\Property(property="performance_score", type="number")
     *         ))
     *     )
     * )
     */
    public function ranking(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $service = new FactoryStatisticsService();
        return response()->json($service->getFactoryRanking($limit));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factories/workload",
     *     summary="Get factory workload distribution",
     *     tags={"Factory Statistics"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Workload distribution",
     *         @OA\JsonContent(type="array", @OA\Items(
     *             type="object",
     *             @OA\Property(property="factory_id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="current_orders", type="integer"),
     *             @OA\Property(property="max_capacity", type="integer", nullable=true),
     *             @OA\Property(property="utilization", type="number", nullable=true)
     *         ))
     *     )
     * )
     */
    public function workload()
    {
        $service = new FactoryStatisticsService();
        return response()->json($service->getWorkloadDistribution());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factories/recommend",
     *     summary="Get recommended factory for a new order",
     *     tags={"Factory Statistics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="expected_days", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="priority", in="query", required=false, @OA\Schema(type="string", enum={"low", "normal", "high", "urgent"})),
     *     @OA\Response(
     *         response=200,
     *         description="Recommended factory",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="recommended_factory", type="object", nullable=true),
     *             @OA\Property(property="reason", type="string")
     *         )
     *     )
     * )
     */
    public function recommend(Request $request)
    {
        $expectedDays = $request->query('expected_days');
        $priority = $request->query('priority');

        $service = new FactoryStatisticsService();
        $factory = $service->recommendFactory($expectedDays, $priority);

        if (!$factory) {
            return response()->json([
                'recommended_factory' => null,
                'reason' => 'No suitable factory available with current criteria',
            ]);
        }

        return response()->json([
            'recommended_factory' => $factory->load('address.city.country'),
            'reason' => 'Best performing factory with available capacity',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factories/{id}/summary",
     *     summary="Get factory performance summary",
     *     tags={"Factory Statistics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Factory summary"),
     *     @OA\Response(response=404, description="Factory not found")
     * )
     */
    public function summary($id)
    {
        $factory = Factory::findOrFail($id);
        $service = new FactoryStatisticsService();
        return response()->json($service->getFactorySummary($factory));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factories/{id}/trends",
     *     summary="Get factory performance trends",
     *     tags={"Factory Statistics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="months", in="query", required=false, @OA\Schema(type="integer", default=6)),
     *     @OA\Response(response=200, description="Performance trends by month"),
     *     @OA\Response(response=404, description="Factory not found")
     * )
     */
    public function trends(Request $request, $id)
    {
        $factory = Factory::findOrFail($id);
        $months = (int) $request->query('months', 6);

        $service = new FactoryStatisticsService();
        return response()->json($service->getPerformanceTrends($factory, $months));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/factories/{id}/recalculate",
     *     summary="Recalculate factory statistics",
     *     tags={"Factory Statistics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Statistics recalculated"),
     *     @OA\Response(response=404, description="Factory not found")
     * )
     */
    public function recalculate($id)
    {
        $factory = Factory::findOrFail($id);
        $service = new FactoryStatisticsService();
        $factory = $service->recalculateForFactory($factory);

        return response()->json([
            'message' => 'Statistics recalculated successfully',
            'factory' => $factory,
        ]);
    }

    // ==================== EVALUATION ENDPOINTS ====================

    /**
     * @OA\Get(
     *     path="/api/v1/factories/{id}/evaluations",
     *     summary="Get evaluations for a factory",
     *     tags={"Factory Evaluations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="min_quality", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="on_time", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Paginated list of evaluations"),
     *     @OA\Response(response=404, description="Factory not found")
     * )
     */
    public function evaluations(Request $request, $id)
    {
        $factory = Factory::findOrFail($id);
        $perPage = (int) $request->query('per_page', 15);

        $query = $factory->evaluations()->with(['order.client', 'evaluator']);

        if ($request->filled('min_quality')) {
            $query->minQuality($request->min_quality);
        }

        if ($request->filled('on_time')) {
            $query->onTime($request->boolean('on_time'));
        }

        $evaluations = $query->orderBy('evaluated_at', 'desc')->paginate($perPage);

        return $this->paginatedResponse($evaluations);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/factories/{id}/evaluations",
     *     summary="Create an evaluation for a factory",
     *     tags={"Factory Evaluations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quality_rating"},
     *             @OA\Property(property="order_id", type="integer", nullable=true, description="Order this evaluation is for"),
     *             @OA\Property(property="quality_rating", type="integer", minimum=1, maximum=5, example=4),
     *             @OA\Property(property="craftsmanship_rating", type="integer", minimum=1, maximum=5, nullable=true),
     *             @OA\Property(property="communication_rating", type="integer", minimum=1, maximum=5, nullable=true),
     *             @OA\Property(property="packaging_rating", type="integer", minimum=1, maximum=5, nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true),
     *             @OA\Property(property="issues_found", type="string", nullable=true),
     *             @OA\Property(property="positive_feedback", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Evaluation created"),
     *     @OA\Response(response=404, description="Factory not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createEvaluation(Request $request, $id)
    {
        $factory = Factory::findOrFail($id);

        $data = $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'quality_rating' => 'required|integer|min:1|max:5',
            'craftsmanship_rating' => 'nullable|integer|min:1|max:5',
            'communication_rating' => 'nullable|integer|min:1|max:5',
            'packaging_rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string|max:2000',
            'issues_found' => 'nullable|string|max:2000',
            'positive_feedback' => 'nullable|string|max:2000',
        ]);

        // Check if evaluation already exists for this order
        if (!empty($data['order_id'])) {
            $existingEval = FactoryEvaluation::where('factory_id', $factory->id)
                ->where('order_id', $data['order_id'])
                ->first();

            if ($existingEval) {
                return response()->json([
                    'message' => 'An evaluation for this order already exists',
                    'existing_evaluation' => $existingEval,
                ], 422);
            }

            $order = Order::findOrFail($data['order_id']);
        } else {
            $order = null;
        }

        $service = new FactoryStatisticsService();

        if ($order) {
            $evaluation = $service->createEvaluation(
                $factory,
                $order,
                $data['quality_rating'],
                $request->user(),
                $data
            );
        } else {
            // Create evaluation without order
            $evaluation = FactoryEvaluation::create([
                'factory_id' => $factory->id,
                'order_id' => null,
                'quality_rating' => $data['quality_rating'],
                'craftsmanship_rating' => $data['craftsmanship_rating'] ?? null,
                'communication_rating' => $data['communication_rating'] ?? null,
                'packaging_rating' => $data['packaging_rating'] ?? null,
                'notes' => $data['notes'] ?? null,
                'issues_found' => $data['issues_found'] ?? null,
                'positive_feedback' => $data['positive_feedback'] ?? null,
                'evaluated_by' => $request->user()->id,
                'evaluated_at' => now(),
            ]);

            $factory->recalculateStatistics();
        }

        return response()->json([
            'message' => 'Evaluation created successfully',
            'evaluation' => $evaluation->load(['order', 'evaluator']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/evaluations/{id}",
     *     summary="Get evaluation details",
     *     tags={"Factory Evaluations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Evaluation details"),
     *     @OA\Response(response=404, description="Evaluation not found")
     * )
     */
    public function showEvaluation($id)
    {
        $evaluation = FactoryEvaluation::with(['factory', 'order.client', 'evaluator'])
            ->findOrFail($id);

        return response()->json($evaluation);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factories/{id}/orders",
     *     summary="Get orders for a factory",
     *     tags={"Factory Statistics"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="stage", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Paginated list of orders"),
     *     @OA\Response(response=404, description="Factory not found")
     * )
     */
    public function orders(Request $request, $id)
    {
        $factory = Factory::findOrFail($id);
        $perPage = (int) $request->query('per_page', 15);

        $query = $factory->orders()->with('client');

        if ($request->filled('stage')) {
            $query->inTailoringStage($request->stage);
        }

        $orders = $query->orderBy('expected_completion_date')->paginate($perPage);

        return $this->paginatedResponse($orders);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/factories/{id}/users/{userId}",
     *     summary="Assign user to factory",
     *     tags={"Factories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User assigned successfully"),
     *     @OA\Response(response=404, description="Factory or user not found"),
     *     @OA\Response(response=422, description="User already assigned to a factory")
     * )
     */
    public function assignUser($id, $userId)
    {
        $factory = Factory::findOrFail($id);
        $user = User::findOrFail($userId);

        // Check if user is already assigned to a factory
        if ($user->factoryUser) {
            return response()->json([
                'message' => 'User is already assigned to a factory',
                'errors' => ['user_id' => ['User is already assigned to factory ID: ' . $user->factoryUser->factory_id]]
            ], 422);
        }

        FactoryUser::create([
            'user_id' => $userId,
            'factory_id' => $id,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'User assigned to factory successfully',
            'data' => $user->load('factoryUser.factory')
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/factories/{id}/users/{userId}",
     *     summary="Remove user from factory",
     *     tags={"Factories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User removed successfully"),
     *     @OA\Response(response=404, description="Factory user assignment not found")
     * )
     */
    public function removeUser($id, $userId)
    {
        $factory = Factory::findOrFail($id);

        $factoryUser = FactoryUser::where('factory_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $factoryUser->delete();

        return response()->json(['message' => 'User removed from factory successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/factories/{id}/users",
     *     summary="List factory users",
     *     tags={"Factories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of factory users"),
     *     @OA\Response(response=404, description="Factory not found")
     * )
     */
    public function users(Request $request, $id)
    {
        $factory = Factory::findOrFail($id);
        $perPage = (int) $request->query('per_page', 15);

        $factoryUsers = FactoryUser::with('user:id,name,email')
            ->where('factory_id', $id)
            ->paginate($perPage);

        return $this->paginatedResponse($factoryUsers);
    }
}
