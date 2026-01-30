<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Cashbox;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transactions",
     *     summary="List transactions",
     *     description="Get a paginated list of transactions with filters.",
     *     tags={"Transactions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="cashbox_id", in="query", required=false, description="Filter by cashbox", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", required=false, description="Filter by type (income, expense, reversal)", @OA\Schema(type="string", enum={"income", "expense", "reversal"})),
     *     @OA\Parameter(name="category", in="query", required=false, description="Filter by category", @OA\Schema(type="string")),
     *     @OA\Parameter(name="start_date", in="query", required=false, description="Filter from date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, description="Filter to date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="reference_type", in="query", required=false, description="Filter by reference type", @OA\Schema(type="string")),
     *     @OA\Parameter(name="reference_id", in="query", required=false, description="Filter by reference ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of transactions",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="cashbox_id", type="integer", example=1),
     *                 @OA\Property(property="type", type="string", enum={"income", "expense", "reversal"}, example="income"),
     *                 @OA\Property(property="amount", type="number", format="float", example=500.00),
     *                 @OA\Property(property="balance_after", type="number", format="float", example=5500.00),
     *                 @OA\Property(property="category", type="string", example="payment"),
     *                 @OA\Property(property="description", type="string", example="Payment #1 for Order #5"),
     *                 @OA\Property(property="reference_type", type="string", nullable=true, example="App\\Models\\Payment"),
     *                 @OA\Property(property="reference_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="is_reversed", type="boolean", example=false),
     *                 @OA\Property(property="created_at", type="string", format="datetime"),
     *                 @OA\Property(property="creator", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Admin")
     *                 )
     *             )),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $query = Transaction::with(['cashbox', 'creator', 'reversedTransaction', 'reversals']);

        // Filters
        if ($request->has('cashbox_id') && $request->cashbox_id) {
            $query->where('cashbox_id', $request->cashbox_id);
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('category') && $request->category) {
            $query->forCategory($request->category);
        }

        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->has('reference_type') && $request->reference_type) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->has('reference_id') && $request->reference_id) {
            $query->where('reference_id', $request->reference_id);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Add is_reversed flag to each transaction
        $transactions->getCollection()->transform(function ($transaction) {
            $transaction->is_reversed = $transaction->isReversed();
            return $transaction;
        });

        return $this->paginatedResponse($transactions);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transactions/{id}",
     *     summary="Get transaction details",
     *     description="Get detailed information about a specific transaction.",
     *     tags={"Transactions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="cashbox_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", example="income"),
     *             @OA\Property(property="amount", type="number", format="float", example=500.00),
     *             @OA\Property(property="balance_after", type="number", format="float", example=5500.00),
     *             @OA\Property(property="category", type="string", example="payment"),
     *             @OA\Property(property="description", type="string", example="Payment #1 for Order #5"),
     *             @OA\Property(property="reference_type", type="string", nullable=true),
     *             @OA\Property(property="reference_id", type="integer", nullable=true),
     *             @OA\Property(property="metadata", type="object", nullable=true),
     *             @OA\Property(property="is_reversed", type="boolean", example=false),
     *             @OA\Property(property="cashbox", type="object"),
     *             @OA\Property(property="creator", type="object"),
     *             @OA\Property(property="reversed_transaction", type="object", nullable=true),
     *             @OA\Property(property="reversals", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Transaction not found")
     * )
     */
    public function show($id)
    {
        $transaction = Transaction::with(['cashbox', 'creator', 'reversedTransaction', 'reversals'])
            ->findOrFail($id);

        $transaction->is_reversed = $transaction->isReversed();

        return response()->json($transaction);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transactions/{id}/reverse",
     *     summary="Reverse a transaction",
     *     description="Create a reversal transaction that undoes the effect of the original. Original transaction remains unchanged (immutability).",
     *     tags={"Transactions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Customer refund requested", description="Reason for reversal")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Reversal transaction created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Transaction reversed successfully"),
     *             @OA\Property(property="reversal_transaction", type="object"),
     *             @OA\Property(property="original_transaction", type="object"),
     *             @OA\Property(property="new_cashbox_balance", type="number", format="float", example=5000.00)
     *         )
     *     ),
     *     @OA\Response(response=400, description="Transaction already reversed or is a reversal"),
     *     @OA\Response(response=404, description="Transaction not found"),
     *     @OA\Response(response=422, description="Validation error or insufficient balance")
     * )
     */
    public function reverse(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $reversalTransaction = $this->transactionService->reverseTransaction(
                $transaction,
                $data['reason'],
                $request->user()
            );

            $transaction->load(['cashbox', 'creator']);

            return response()->json([
                'message' => 'Transaction reversed successfully',
                'reversal_transaction' => $reversalTransaction,
                'original_transaction' => $transaction,
                'new_cashbox_balance' => $reversalTransaction->balance_after,
            ], 201);

        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cashboxes/{cashbox_id}/transactions",
     *     summary="Get transactions for a specific cashbox",
     *     description="Get all transactions for a specific cashbox with pagination.",
     *     tags={"Transactions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="cashbox_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", enum={"income", "expense", "reversal"})),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of transactions",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Cashbox not found")
     * )
     */
    public function forCashbox(Request $request, $cashboxId)
    {
        $cashbox = Cashbox::findOrFail($cashboxId);
        
        $perPage = (int) $request->query('per_page', 15);
        $query = $cashbox->transactions()->with(['creator', 'reversals']);

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('category') && $request->category) {
            $query->forCategory($request->category);
        }

        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Add is_reversed flag
        $transactions->getCollection()->transform(function ($transaction) {
            $transaction->is_reversed = $transaction->isReversed();
            return $transaction;
        });

        return response()->json([
            'cashbox' => [
                'id' => $cashbox->id,
                'name' => $cashbox->name,
                'current_balance' => $cashbox->current_balance,
            ],
            'transactions' => $this->paginatedResponse($transactions)->original,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/transactions/categories",
     *     summary="Get available transaction categories",
     *     description="Get a list of all transaction categories used in the system.",
     *     tags={"Transactions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of categories",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="categories", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="name", type="string", example="payment"),
     *                 @OA\Property(property="display_name", type="string", example="Payment"),
     *                 @OA\Property(property="description", type="string", example="Customer payment for orders")
     *             ))
     *         )
     *     )
     * )
     */
    public function categories()
    {
        $categories = [
            ['name' => Transaction::CATEGORY_PAYMENT, 'display_name' => 'Payment', 'description' => 'Customer payment for orders'],
            ['name' => Transaction::CATEGORY_CUSTODY_DEPOSIT, 'display_name' => 'Custody Deposit', 'description' => 'Security deposit from customer'],
            ['name' => Transaction::CATEGORY_CUSTODY_RETURN, 'display_name' => 'Custody Return', 'description' => 'Security deposit returned to customer'],
            ['name' => Transaction::CATEGORY_CUSTODY_FORFEITURE, 'display_name' => 'Custody Forfeiture', 'description' => 'Security deposit forfeited (kept by business)'],
            ['name' => Transaction::CATEGORY_EXPENSE, 'display_name' => 'Expense', 'description' => 'Business expense'],
            ['name' => Transaction::CATEGORY_REVERSAL, 'display_name' => 'Reversal', 'description' => 'Transaction reversal/correction'],
            ['name' => Transaction::CATEGORY_INITIAL_BALANCE, 'display_name' => 'Initial Balance', 'description' => 'Opening balance when cashbox was created'],
            ['name' => Transaction::CATEGORY_ADJUSTMENT, 'display_name' => 'Adjustment', 'description' => 'Manual balance adjustment'],
        ];

        return response()->json(['categories' => $categories]);
    }
}






