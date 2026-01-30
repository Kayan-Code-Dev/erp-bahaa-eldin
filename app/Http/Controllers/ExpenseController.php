<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Branch;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/expenses",
     *     summary="List all expenses",
     *     description="Get a paginated list of expenses with filters.",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="cashbox_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string", enum={"rent", "utilities", "supplies", "maintenance", "salaries", "marketing", "transport", "cleaning", "other"})),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending", "approved", "paid", "cancelled"})),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="vendor", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", required=false, description="Search in description", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of expenses",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="cashbox_id", type="integer", example=1, description="Cashbox ID"),
     *                 @OA\Property(property="branch_id", type="integer", example=1, description="Branch ID"),
     *                 @OA\Property(property="category", type="string", enum={"rent", "utilities", "supplies", "maintenance", "salaries", "marketing", "transport", "cleaning", "other"}, example="utilities", description="Expense category"),
     *                 @OA\Property(property="subcategory", type="string", nullable=true, example="Electricity", description="More specific categorization"),
     *                 @OA\Property(property="amount", type="number", format="float", example=1500.00, description="Expense amount (decimal 15,2)"),
     *                 @OA\Property(property="expense_date", type="string", format="date", example="2026-01-15", description="Date of the expense"),
     *                 @OA\Property(property="vendor", type="string", nullable=true, example="Electric Company", description="Vendor/payee name"),
     *                 @OA\Property(property="reference_number", type="string", nullable=true, example="INV-2026-001", description="Invoice or receipt number"),
     *                 @OA\Property(property="description", type="string", example="Monthly electricity bill", description="Expense description"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Urgent payment required", description="Additional notes"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "approved", "paid", "cancelled"}, example="pending", description="Expense status"),
     *                 @OA\Property(property="approved_by", type="integer", nullable=true, example=2, description="User ID who approved the expense"),
     *                 @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example="2026-01-15T10:30:00Z", description="When the expense was approved"),
     *                 @OA\Property(property="created_by", type="integer", example=1, description="User ID who created the expense"),
     *                 @OA\Property(property="transaction_id", type="integer", nullable=true, example=123, description="Transaction ID (set when expense is paid)"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-15T08:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-15T10:30:00Z"),
     *                 @OA\Property(property="branch", type="object", nullable=true, description="Branch details"),
     *                 @OA\Property(property="cashbox", type="object", nullable=true, description="Cashbox details"),
     *                 @OA\Property(property="creator", type="object", nullable=true, description="User who created the expense"),
     *                 @OA\Property(property="approver", type="object", nullable=true, description="User who approved the expense"),
     *                 @OA\Property(property="transaction", type="object", nullable=true, description="Associated transaction (when paid)")
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
        $query = Expense::with(['branch', 'cashbox', 'creator', 'approver']);

        // Filters
        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('cashbox_id')) {
            $query->where('cashbox_id', $request->cashbox_id);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->forDateRange($request->start_date, $request->end_date);
        } elseif ($request->filled('start_date')) {
            $query->where('expense_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('expense_date', '<=', $request->end_date);
        }

        if ($request->filled('vendor')) {
            $query->where('vendor', 'LIKE', '%' . $request->vendor . '%');
        }

        if ($request->filled('search')) {
            $query->where('description', 'LIKE', '%' . $request->search . '%');
        }

        $expenses = $query->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->paginatedResponse($expenses);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/expenses/{id}",
     *     summary="Get expense details",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Expense details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="cashbox_id", type="integer", example=1),
     *             @OA\Property(property="branch_id", type="integer", example=1),
     *             @OA\Property(property="category", type="string", enum={"rent", "utilities", "supplies", "maintenance", "salaries", "marketing", "transport", "cleaning", "other"}, example="utilities"),
     *             @OA\Property(property="subcategory", type="string", nullable=true, example="Electricity"),
     *             @OA\Property(property="amount", type="number", format="float", example=1500.00),
     *             @OA\Property(property="expense_date", type="string", format="date", example="2026-01-15"),
     *             @OA\Property(property="vendor", type="string", nullable=true, example="Electric Company"),
     *             @OA\Property(property="reference_number", type="string", nullable=true, example="INV-2026-001"),
     *             @OA\Property(property="description", type="string", example="Monthly electricity bill"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Urgent payment required"),
     *             @OA\Property(property="status", type="string", enum={"pending", "approved", "paid", "cancelled"}, example="pending"),
     *             @OA\Property(property="approved_by", type="integer", nullable=true, example=2),
     *             @OA\Property(property="approved_at", type="string", format="date-time", nullable=true, example="2026-01-15T10:30:00Z"),
     *             @OA\Property(property="created_by", type="integer", example=1),
     *             @OA\Property(property="transaction_id", type="integer", nullable=true, example=123),
     *             @OA\Property(property="branch", type="object", nullable=true),
     *             @OA\Property(property="cashbox", type="object", nullable=true),
     *             @OA\Property(property="creator", type="object", nullable=true),
     *             @OA\Property(property="approver", type="object", nullable=true),
     *             @OA\Property(property="transaction", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Expense not found")
     * )
     */
    public function show($id)
    {
        $expense = Expense::with(['branch', 'cashbox', 'creator', 'approver', 'transaction'])
            ->findOrFail($id);

        return response()->json($expense);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/expenses",
     *     summary="Create a new expense",
     *     description="Create a new expense record. Expense starts in 'pending' status.",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"branch_id", "category", "amount", "expense_date", "description"},
     *             @OA\Property(property="branch_id", type="integer", example=1, description="Branch ID (required)"),
     *             @OA\Property(property="category", type="string", enum={"rent", "utilities", "supplies", "maintenance", "salaries", "marketing", "transport", "cleaning", "other"}, example="utilities", description="Expense category (required)"),
     *             @OA\Property(property="subcategory", type="string", nullable=true, example="Electricity", description="More specific categorization (optional, max 255 chars)"),
     *             @OA\Property(property="amount", type="number", format="float", example=1500.00, description="Expense amount, must be greater than 0.01 (required, decimal 15,2)"),
     *             @OA\Property(property="expense_date", type="string", format="date", example="2026-01-15", description="Date of the expense (required)"),
     *             @OA\Property(property="vendor", type="string", nullable=true, example="Electric Company", description="Vendor/payee name (optional, max 255 chars)"),
     *             @OA\Property(property="reference_number", type="string", nullable=true, example="INV-2026-001", description="Invoice or receipt number (optional, max 100 chars)"),
     *             @OA\Property(property="description", type="string", example="Monthly electricity bill", description="Expense description (required, max 1000 chars)"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Urgent payment required", description="Additional notes (optional, max 2000 chars)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Expense created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'category' => 'required|string|in:rent,utilities,supplies,maintenance,salaries,marketing,transport,cleaning,other',
            'subcategory' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'vendor' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'description' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Get the branch's cashbox
        $branch = Branch::with('cashbox')->findOrFail($data['branch_id']);
        
        if (!$branch->cashbox) {
            return response()->json([
                'message' => 'Branch does not have a cashbox configured',
                'errors' => ['branch_id' => ['Branch does not have a cashbox']]
            ], 422);
        }

        $expense = Expense::create([
            'cashbox_id' => $branch->cashbox->id,
            'branch_id' => $data['branch_id'],
            'category' => $data['category'],
            'subcategory' => $data['subcategory'] ?? null,
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'vendor' => $data['vendor'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'status' => Expense::STATUS_PENDING,
            'created_by' => $request->user()->id,
        ]);

        $expense->load(['branch', 'cashbox', 'creator']);

        return response()->json([
            'message' => 'Expense created successfully',
            'expense' => $expense,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/expenses/{id}",
     *     summary="Update an expense",
     *     description="Update expense details. Only pending expenses can be fully updated.",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="category", type="string", enum={"rent", "utilities", "supplies", "maintenance", "salaries", "marketing", "transport", "cleaning", "other"}, example="utilities", description="Expense category"),
     *             @OA\Property(property="subcategory", type="string", nullable=true, example="Electricity", description="More specific categorization (max 255 chars)"),
     *             @OA\Property(property="amount", type="number", format="float", example=1500.00, description="Expense amount, must be greater than 0.01 (decimal 15,2)"),
     *             @OA\Property(property="expense_date", type="string", format="date", example="2026-01-15", description="Date of the expense"),
     *             @OA\Property(property="vendor", type="string", nullable=true, example="Electric Company", description="Vendor/payee name (max 255 chars)"),
     *             @OA\Property(property="reference_number", type="string", nullable=true, example="INV-2026-001", description="Invoice or receipt number (max 100 chars)"),
     *             @OA\Property(property="description", type="string", example="Monthly electricity bill", description="Expense description (max 1000 chars)"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Urgent payment required", description="Additional notes (max 2000 chars)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Expense updated"),
     *     @OA\Response(response=404, description="Expense not found"),
     *     @OA\Response(response=422, description="Validation error or expense cannot be updated")
     * )
     */
    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        // Only pending expenses can be fully updated
        if ($expense->status !== Expense::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending expenses can be updated',
                'errors' => ['status' => ['Expense status is ' . $expense->status]]
            ], 422);
        }

        $data = $request->validate([
            'category' => 'sometimes|string|in:rent,utilities,supplies,maintenance,salaries,marketing,transport,cleaning,other',
            'subcategory' => 'nullable|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'expense_date' => 'sometimes|date',
            'vendor' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'description' => 'sometimes|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $expense->update($data);
        $expense->load(['branch', 'cashbox', 'creator']);

        return response()->json($expense);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/expenses/{id}/approve",
     *     summary="Approve an expense",
     *     description="Approve a pending expense. Only pending expenses can be approved.",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Expense approved",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="expense", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Expense not found"),
     *     @OA\Response(response=422, description="Expense cannot be approved")
     * )
     */
    public function approve(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        if (!$expense->canBeApproved()) {
            return response()->json([
                'message' => 'Expense cannot be approved',
                'errors' => ['status' => ['Expense status is ' . $expense->status]]
            ], 422);
        }

        $expense->status = Expense::STATUS_APPROVED;
        $expense->approved_by = $request->user()->id;
        $expense->approved_at = now();
        $expense->save();

        $expense->load(['branch', 'cashbox', 'creator', 'approver']);

        return response()->json([
            'message' => 'Expense approved successfully',
            'expense' => $expense,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/expenses/{id}/pay",
     *     summary="Pay an expense",
     *     description="Pay an approved expense. Creates a transaction in the cashbox.",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Expense paid",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="expense", type="object"),
     *             @OA\Property(property="transaction", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Expense not found"),
     *     @OA\Response(response=422, description="Expense cannot be paid or insufficient balance")
     * )
     */
    public function pay(Request $request, $id)
    {
        $expense = Expense::with('cashbox')->findOrFail($id);

        if (!$expense->canBePaid()) {
            return response()->json([
                'message' => 'Expense cannot be paid',
                'errors' => ['status' => ['Expense must be approved first. Current status: ' . $expense->status]]
            ], 422);
        }

        if (!$expense->cashbox || !$expense->cashbox->is_active) {
            return response()->json([
                'message' => 'Cashbox is not available or inactive',
            ], 422);
        }

        try {
            $transaction = null;

            DB::transaction(function () use ($expense, $request, &$transaction) {
                // Create expense transaction
                $transaction = $this->transactionService->recordExpense(
                    $expense->cashbox,
                    $expense->amount,
                    Transaction::CATEGORY_EXPENSE,
                    "Expense #{$expense->id}: {$expense->description}",
                    $request->user(),
                    'App\\Models\\Expense',
                    $expense->id,
                    [
                        'category' => $expense->category,
                        'vendor' => $expense->vendor,
                        'expense_date' => $expense->expense_date->format('Y-m-d'),
                    ]
                );

                // Update expense status and link transaction
                $expense->status = Expense::STATUS_PAID;
                $expense->transaction_id = $transaction->id;
                $expense->save();
            });

            $expense->load(['branch', 'cashbox', 'creator', 'approver', 'transaction']);

            return response()->json([
                'message' => 'Expense paid successfully',
                'expense' => $expense,
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'balance_after' => $transaction->balance_after,
                ],
            ]);

        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Cannot pay expense: ' . $e->getMessage(),
                'errors' => ['amount' => [$e->getMessage()]]
            ], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/expenses/{id}/cancel",
     *     summary="Cancel an expense",
     *     description="Cancel a pending or approved expense. Paid expenses cannot be cancelled.",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Duplicate entry")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Expense cancelled"),
     *     @OA\Response(response=404, description="Expense not found"),
     *     @OA\Response(response=422, description="Expense cannot be cancelled")
     * )
     */
    public function cancel(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        if (!$expense->canBeCancelled()) {
            return response()->json([
                'message' => 'Expense cannot be cancelled',
                'errors' => ['status' => ['Paid expenses cannot be cancelled. Current status: ' . $expense->status]]
            ], 422);
        }

        $reason = $request->input('reason', 'Cancelled by user');

        $expense->status = Expense::STATUS_CANCELLED;
        $expense->notes = ($expense->notes ? $expense->notes . "\n" : '') . "Cancelled: {$reason}";
        $expense->save();

        $expense->load(['branch', 'cashbox', 'creator']);

        return response()->json([
            'message' => 'Expense cancelled successfully',
            'expense' => $expense,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/expenses/categories",
     *     summary="Get expense categories",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of expense categories",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="categories", type="object")
     *         )
     *     )
     * )
     */
    public function categories()
    {
        return response()->json([
            'categories' => Expense::getCategories(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/expenses/summary",
     *     summary="Get expense summary",
     *     description="Get expense totals by category for a date range.",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Expense summary",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="period", type="object",
     *                 @OA\Property(property="start_date", type="string", format="date", example="2026-01-01"),
     *                 @OA\Property(property="end_date", type="string", format="date", example="2026-01-31")
     *             ),
     *             @OA\Property(property="total_paid", type="number", format="float", example=45000.00, description="Total amount of paid expenses in the period"),
     *             @OA\Property(property="by_category", type="object", example={"rent": 15000.00, "utilities": 5000.00, "supplies": 8000.00}, description="Total paid expenses grouped by category"),
     *             @OA\Property(property="by_status", type="object", example={"pending": {"total": 3000.00, "count": 5}, "approved": {"total": 5000.00, "count": 3}, "paid": {"total": 45000.00, "count": 15}}, description="Total amount and count grouped by status")
     *         )
     *     )
     * )
     */
    public function summary(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $query = Expense::forDateRange($request->start_date, $request->end_date);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        // Total paid expenses
        $totalPaid = (clone $query)->paid()->sum('amount');

        // By category (paid only)
        $byCategory = (clone $query)->paid()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        // By status (all)
        $byStatus = (clone $query)
            ->selectRaw('status, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        return response()->json([
            'period' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ],
            'total_paid' => $totalPaid,
            'by_category' => $byCategory,
            'by_status' => $byStatus,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/expenses/{id}",
     *     summary="Delete an expense",
     *     description="Soft delete an expense. Only pending expenses can be deleted.",
     *     tags={"Expenses"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Expense deleted"),
     *     @OA\Response(response=404, description="Expense not found"),
     *     @OA\Response(response=422, description="Expense cannot be deleted")
     * )
     */
    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);

        if ($expense->status !== Expense::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending expenses can be deleted',
                'errors' => ['status' => ['Expense status is ' . $expense->status]]
            ], 422);
        }

        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully',
        ]);
    }
}






