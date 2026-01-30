<?php

namespace App\Http\Controllers;

use App\Models\Receivable;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceivableController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/receivables",
     *     summary="List all receivables",
     *     description="Get a paginated list of receivables (customer debts) with filters.",
     *     tags={"Receivables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="client_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="order_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending", "partial", "paid", "overdue", "written_off"})),
     *     @OA\Parameter(name="overdue_only", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="due_within_days", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of receivables",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="client_id", type="integer"),
     *                 @OA\Property(property="order_id", type="integer", nullable=true),
     *                 @OA\Property(property="branch_id", type="integer"),
     *                 @OA\Property(property="original_amount", type="number"),
     *                 @OA\Property(property="paid_amount", type="number"),
     *                 @OA\Property(property="remaining_amount", type="number"),
     *                 @OA\Property(property="due_date", type="string", format="date", nullable=true),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="client", type="object"),
     *                 @OA\Property(property="branch", type="object")
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
        $query = Receivable::with(['client', 'branch', 'order', 'creator', 'payments']);

        // Filters
        if ($request->filled('client_id')) {
            $query->forClient($request->client_id);
        }

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        if ($request->filled('due_within_days')) {
            $query->dueWithinDays((int) $request->due_within_days);
        }

        $receivables = $query->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Add computed fields
        $receivables->getCollection()->transform(function ($receivable) {
            $receivable->is_overdue = $receivable->isOverdue();
            $receivable->payment_percentage = $receivable->getPaymentPercentage();
            return $receivable;
        });

        return $this->paginatedResponse($receivables);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/receivables/{id}",
     *     summary="Get receivable details",
     *     tags={"Receivables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Receivable details with payment history",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=404, description="Receivable not found")
     * )
     */
    public function show($id)
    {
        $receivable = Receivable::with(['client', 'branch', 'order', 'creator', 'payments.creator'])
            ->findOrFail($id);

        $receivable->is_overdue = $receivable->isOverdue();
        $receivable->payment_percentage = $receivable->getPaymentPercentage();

        return response()->json($receivable);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/receivables",
     *     summary="Create a new receivable",
     *     description="Create a new receivable (debt record) for a client.",
     *     tags={"Receivables"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"client_id", "branch_id", "original_amount", "description"},
     *             @OA\Property(property="client_id", type="integer", example=1),
     *             @OA\Property(property="branch_id", type="integer", example=1),
     *             @OA\Property(property="order_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="original_amount", type="number", example=1000.00),
     *             @OA\Property(property="due_date", type="string", format="date", nullable=true, example="2026-02-09"),
     *             @OA\Property(property="description", type="string", example="Order balance for rental #123"),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Receivable created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'branch_id' => 'required|exists:branches,id',
            'order_id' => 'nullable|exists:orders,id',
            'original_amount' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'description' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $receivable = Receivable::create([
            'client_id' => $data['client_id'],
            'branch_id' => $data['branch_id'],
            'order_id' => $data['order_id'] ?? null,
            'original_amount' => $data['original_amount'],
            'paid_amount' => 0,
            'remaining_amount' => $data['original_amount'],
            'due_date' => $data['due_date'] ?? null,
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'status' => Receivable::STATUS_PENDING,
            'created_by' => $request->user()->id,
        ]);

        $receivable->load(['client', 'branch', 'order', 'creator']);

        return response()->json([
            'message' => 'Receivable created successfully',
            'receivable' => $receivable,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/receivables/{id}",
     *     summary="Update a receivable",
     *     description="Update receivable details. Cannot change amounts if payments exist.",
     *     tags={"Receivables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="due_date", type="string", format="date", nullable=true),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Receivable updated"),
     *     @OA\Response(response=404, description="Receivable not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $receivable = Receivable::findOrFail($id);

        $data = $request->validate([
            'due_date' => 'nullable|date',
            'description' => 'sometimes|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $receivable->update($data);
        $receivable->load(['client', 'branch', 'order', 'creator']);

        return response()->json($receivable);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/receivables/{id}/record-payment",
     *     summary="Record a payment against a receivable",
     *     description="Record a payment received for this receivable. Creates a transaction in the cashbox.",
     *     tags={"Receivables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", example=500.00),
     *             @OA\Property(property="payment_method", type="string", enum={"cash", "card", "transfer", "check"}, example="cash"),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment recorded",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="receivable", type="object"),
     *             @OA\Property(property="payment", type="object"),
     *             @OA\Property(property="transaction", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Receivable not found"),
     *     @OA\Response(response=422, description="Validation error or receivable already paid")
     * )
     */
    public function recordPayment(Request $request, $id)
    {
        $receivable = Receivable::with('branch.cashbox')->findOrFail($id);

        if ($receivable->isPaid()) {
            return response()->json([
                'message' => 'Receivable is already fully paid',
                'errors' => ['status' => ['Receivable is already paid']]
            ], 422);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $receivable->remaining_amount,
            'payment_method' => 'sometimes|string|in:cash,card,transfer,check',
            'notes' => 'nullable|string|max:500',
        ]);

        $paymentMethod = $data['payment_method'] ?? 'cash';
        $transaction = null;
        $receivablePayment = null;

        DB::transaction(function () use ($receivable, $data, $request, $paymentMethod, &$transaction, &$receivablePayment) {
            // Create transaction in cashbox if available
            $branch = $receivable->branch;
            if ($branch && $branch->cashbox && $branch->cashbox->is_active) {
                $transaction = $this->transactionService->recordIncome(
                    $branch->cashbox,
                    $data['amount'],
                    'receivable_payment',
                    "Receivable payment #{$receivable->id} from client #{$receivable->client_id}",
                    $request->user(),
                    'App\\Models\\Receivable',
                    $receivable->id,
                    [
                        'client_id' => $receivable->client_id,
                        'payment_method' => $paymentMethod,
                    ]
                );
            }

            // Record the payment against the receivable
            $receivablePayment = $receivable->recordPayment(
                $data['amount'],
                $request->user(),
                null, // No linked Payment model
                $transaction?->id,
                $paymentMethod,
                $data['notes'] ?? null
            );
        });

        $receivable->refresh();
        $receivable->load(['client', 'branch', 'payments']);
        $receivable->is_overdue = $receivable->isOverdue();
        $receivable->payment_percentage = $receivable->getPaymentPercentage();

        $response = [
            'message' => 'Payment recorded successfully',
            'receivable' => $receivable,
            'payment' => $receivablePayment,
        ];

        if ($transaction) {
            $response['transaction'] = [
                'id' => $transaction->id,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
            ];
        }

        return response()->json($response);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/receivables/{id}/write-off",
     *     summary="Write off a receivable",
     *     description="Mark a receivable as uncollectable (written off).",
     *     tags={"Receivables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Customer bankrupt")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Receivable written off"),
     *     @OA\Response(response=404, description="Receivable not found"),
     *     @OA\Response(response=422, description="Receivable cannot be written off")
     * )
     */
    public function writeOff(Request $request, $id)
    {
        $receivable = Receivable::findOrFail($id);

        if ($receivable->isPaid()) {
            return response()->json([
                'message' => 'Paid receivables cannot be written off',
                'errors' => ['status' => ['Receivable is already paid']]
            ], 422);
        }

        $reason = $request->input('reason', 'Written off by user');

        $receivable->notes = ($receivable->notes ? $receivable->notes . "\n" : '') . "Written off: {$reason}";
        $receivable->writeOff();

        $receivable->load(['client', 'branch']);

        return response()->json([
            'message' => 'Receivable written off successfully',
            'receivable' => $receivable,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/receivables/summary",
     *     summary="Get receivables summary",
     *     description="Get summary of receivables by status and totals.",
     *     tags={"Receivables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="client_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Receivables summary",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_outstanding", type="number"),
     *             @OA\Property(property="total_overdue", type="number"),
     *             @OA\Property(property="by_status", type="object"),
     *             @OA\Property(property="overdue_count", type="integer")
     *         )
     *     )
     * )
     */
    public function summary(Request $request)
    {
        $query = Receivable::query();

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('client_id')) {
            $query->forClient($request->client_id);
        }

        // Total outstanding (unpaid)
        $totalOutstanding = (clone $query)->unpaid()->sum('remaining_amount');

        // Total overdue
        $totalOverdue = (clone $query)->overdue()->sum('remaining_amount');
        $overdueCount = (clone $query)->overdue()->count();

        // By status
        $byStatus = (clone $query)
            ->selectRaw('status, SUM(remaining_amount) as total_remaining, SUM(original_amount) as total_original, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        // Due within next 7 days
        $dueSoon = (clone $query)->dueWithinDays(7)->sum('remaining_amount');
        $dueSoonCount = (clone $query)->dueWithinDays(7)->count();

        return response()->json([
            'total_outstanding' => $totalOutstanding,
            'total_overdue' => $totalOverdue,
            'overdue_count' => $overdueCount,
            'due_soon' => $dueSoon,
            'due_soon_count' => $dueSoonCount,
            'by_status' => $byStatus,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/{client_id}/receivables",
     *     summary="Get receivables for a client",
     *     tags={"Receivables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="client_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Client's receivables"),
     *     @OA\Response(response=404, description="Client not found")
     * )
     */
    public function forClient(Request $request, $clientId)
    {
        Client::findOrFail($clientId);

        $query = Receivable::with(['branch', 'order', 'payments'])
            ->forClient($clientId);

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        $receivables = $query->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        $receivables->transform(function ($receivable) {
            $receivable->is_overdue = $receivable->isOverdue();
            $receivable->payment_percentage = $receivable->getPaymentPercentage();
            return $receivable;
        });

        // Calculate totals for this client
        $totalOutstanding = $receivables->where('status', '!=', Receivable::STATUS_PAID)
            ->where('status', '!=', Receivable::STATUS_WRITTEN_OFF)
            ->sum('remaining_amount');

        return response()->json([
            'data' => $receivables,
            'total_outstanding' => $totalOutstanding,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/receivables/{id}",
     *     summary="Delete a receivable",
     *     description="Soft delete a receivable. Only receivables with no payments can be deleted.",
     *     tags={"Receivables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Receivable deleted"),
     *     @OA\Response(response=404, description="Receivable not found"),
     *     @OA\Response(response=422, description="Receivable cannot be deleted")
     * )
     */
    public function destroy($id)
    {
        $receivable = Receivable::withCount('payments')->findOrFail($id);

        if ($receivable->payments_count > 0) {
            return response()->json([
                'message' => 'Receivables with payments cannot be deleted',
                'errors' => ['payments' => ['This receivable has ' . $receivable->payments_count . ' payments recorded']]
            ], 422);
        }

        $receivable->delete();

        return response()->json([
            'message' => 'Receivable deleted successfully',
        ]);
    }
}






