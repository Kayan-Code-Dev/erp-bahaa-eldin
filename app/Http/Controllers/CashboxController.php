<?php

namespace App\Http\Controllers;

use App\Models\Cashbox;
use App\Models\Branch;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class CashboxController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cashboxes",
     *     summary="List all cashboxes",
     *     description="Get a list of all cashboxes with their current balances. Can be filtered by branch.",
     *     tags={"Cashboxes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="branch_id", in="query", required=false, description="Filter by branch ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", required=false, description="Filter by active status", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="List of cashboxes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Main Branch Cashbox"),
     *                 @OA\Property(property="branch_id", type="integer", example=1),
     *                 @OA\Property(property="initial_balance", type="number", format="float", example=1000.00),
     *                 @OA\Property(property="current_balance", type="number", format="float", example=5500.50),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Primary cashbox"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="branch", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Main Branch")
     *                 ),
     *                 @OA\Property(property="today_income", type="number", format="float", example=1500.00),
     *                 @OA\Property(property="today_expense", type="number", format="float", example=300.00)
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
        $query = Cashbox::with('branch');

        if ($request->has('branch_id') && $request->branch_id) {
            $query->forBranch($request->branch_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $cashboxes = $query->orderBy('name')->paginate($perPage);

        // Add daily stats to each cashbox
        $transformedItems = $cashboxes->getCollection()->map(function ($cashbox) {
            return array_merge($cashbox->toArray(), [
                'today_income' => $cashbox->getTodayIncome(),
                'today_expense' => $cashbox->getTodayExpense(),
            ]);
        });

        // Replace the collection with transformed items
        $cashboxes->setCollection($transformedItems);

        return $this->paginatedResponse($cashboxes);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cashboxes/{id}",
     *     summary="Get cashbox details",
     *     description="Get detailed information about a specific cashbox including recent transactions.",
     *     tags={"Cashboxes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Cashbox details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Main Branch Cashbox"),
     *             @OA\Property(property="branch_id", type="integer", example=1),
     *             @OA\Property(property="initial_balance", type="number", format="float", example=1000.00),
     *             @OA\Property(property="current_balance", type="number", format="float", example=5500.50),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="branch", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Main Branch")
     *             ),
     *             @OA\Property(property="today_summary", type="object",
     *                 @OA\Property(property="income", type="number", format="float", example=1500.00),
     *                 @OA\Property(property="expense", type="number", format="float", example=300.00),
     *                 @OA\Property(property="net_change", type="number", format="float", example=1200.00)
     *             ),
     *             @OA\Property(property="recent_transactions", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Cashbox not found")
     * )
     */
    public function show($id)
    {
        $cashbox = Cashbox::with('branch')->findOrFail($id);

        $todayIncome = $cashbox->getTodayIncome();
        $todayExpense = $cashbox->getTodayExpense();

        // Get recent transactions (last 20)
        $recentTransactions = $cashbox->transactions()
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'id' => $cashbox->id,
            'name' => $cashbox->name,
            'branch_id' => $cashbox->branch_id,
            'initial_balance' => $cashbox->initial_balance,
            'current_balance' => $cashbox->current_balance,
            'description' => $cashbox->description,
            'is_active' => $cashbox->is_active,
            'branch' => $cashbox->branch,
            'today_summary' => [
                'income' => $todayIncome,
                'expense' => $todayExpense,
                'net_change' => $todayIncome - $todayExpense,
            ],
            'recent_transactions' => $recentTransactions,
            'created_at' => $cashbox->created_at,
            'updated_at' => $cashbox->updated_at,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/cashboxes/{id}",
     *     summary="Update cashbox",
     *     description="Update cashbox details. Note: Balance cannot be directly modified - use transactions.",
     *     tags={"Cashboxes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Cashbox Name"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Updated description"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cashbox updated",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Cashbox not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $cashbox = Cashbox::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        $cashbox->update($data);

        return response()->json($cashbox->load('branch'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cashboxes/{id}/daily-summary",
     *     summary="Get daily summary for cashbox",
     *     description="Get income, expense, and balance summary for a specific date.",
     *     tags={"Cashboxes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date", in="query", required=false, description="Date (YYYY-MM-DD). Defaults to today.", @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Daily summary",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="date", type="string", format="date", example="2025-01-09"),
     *             @OA\Property(property="cashbox_id", type="integer", example=1),
     *             @OA\Property(property="cashbox_name", type="string", example="Main Branch Cashbox"),
     *             @OA\Property(property="opening_balance", type="number", format="float", example=5000.00),
     *             @OA\Property(property="total_income", type="number", format="float", example=1500.00),
     *             @OA\Property(property="total_expense", type="number", format="float", example=300.00),
     *             @OA\Property(property="net_change", type="number", format="float", example=1200.00),
     *             @OA\Property(property="closing_balance", type="number", format="float", example=6200.00),
     *             @OA\Property(property="transaction_count", type="integer", example=15),
     *             @OA\Property(property="reversal_count", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Cashbox not found")
     * )
     */
    public function dailySummary(Request $request, $id)
    {
        $cashbox = Cashbox::findOrFail($id);

        $date = $request->has('date') 
            ? \Carbon\Carbon::parse($request->date)
            : today();

        $summary = $this->transactionService->getDailySummary($cashbox, $date);

        return response()->json($summary);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cashboxes/{id}/recalculate",
     *     summary="Recalculate cashbox balance",
     *     description="Recalculates the cashbox balance from all transactions. Use if balance seems incorrect.",
     *     tags={"Cashboxes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Balance recalculated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Balance recalculated successfully"),
     *             @OA\Property(property="previous_balance", type="number", format="float", example=5500.50),
     *             @OA\Property(property="calculated_balance", type="number", format="float", example=5500.50),
     *             @OA\Property(property="difference", type="number", format="float", example=0.00)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Cashbox not found")
     * )
     */
    public function recalculate($id)
    {
        $cashbox = Cashbox::findOrFail($id);
        
        $previousBalance = $cashbox->current_balance;
        $calculatedBalance = $cashbox->recalculateBalance();

        return response()->json([
            'message' => 'Balance recalculated successfully',
            'previous_balance' => $previousBalance,
            'calculated_balance' => $calculatedBalance,
            'difference' => abs($calculatedBalance - $previousBalance),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/branches/{id}/cashbox",
     *     summary="Get cashbox for a branch",
     *     description="Get the cashbox associated with a specific branch.",
     *     tags={"Cashboxes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Branch ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Branch cashbox",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Branch or cashbox not found")
     * )
     */
    public function branchCashbox($branchId)
    {
        $branch = Branch::findOrFail($branchId);
        $cashbox = $branch->cashbox;

        if (!$cashbox) {
            return response()->json(['message' => 'No cashbox found for this branch'], 404);
        }

        $todayIncome = $cashbox->getTodayIncome();
        $todayExpense = $cashbox->getTodayExpense();

        return response()->json([
            'cashbox' => $cashbox,
            'branch' => $branch,
            'today_summary' => [
                'income' => $todayIncome,
                'expense' => $todayExpense,
                'net_change' => $todayIncome - $todayExpense,
            ],
        ]);
    }
}






