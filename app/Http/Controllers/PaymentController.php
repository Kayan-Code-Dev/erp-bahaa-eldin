<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Rules\MySqlDateTime;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Recalculate order paid and remaining amounts based on payments
     */
    private function recalculateOrderPayments($order)
    {
        // Refresh order to get latest payments from database
        $order->refresh();

        // Recalculate total paid from non-fee payments only (fees are tracked separately)
        $totalPaid = Payment::where('order_id', $order->id)
            ->where('status', 'paid')
            ->where('payment_type', '!=', 'fee')
            ->sum('amount');
        $order->paid = $totalPaid;

        // Calculate remaining: total_price - paid (fees do not affect remaining)
        $order->remaining = max(0, $order->total_price - $totalPaid);

        // Update order status based on paid amount (compared to total_price only, fees excluded)
        if ($order->paid >= $order->total_price) {
            $order->status = 'paid';
            $order->remaining = 0;
        } elseif ($order->paid > 0) {
            $order->status = 'partially_paid';
        } else {
            $order->status = 'created';
        }

        $order->save();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments",
     *     summary="List all payments with filters and search",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"pending", "paid", "canceled"})),
     *     @OA\Parameter(name="payment_type", in="query", required=false, description="Filter by payment type", @OA\Schema(type="string", enum={"initial", "fee", "normal"})),
     *     @OA\Parameter(name="order_id", in="query", required=false, description="Filter by order ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="client_id", in="query", required=false, description="Filter by client ID (through order)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", required=false, description="Filter payments from date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=false, description="Filter payments to date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="amount_min", in="query", required=false, description="Minimum payment amount", @OA\Schema(type="number")),
     *     @OA\Parameter(name="amount_max", in="query", required=false, description="Maximum payment amount", @OA\Schema(type="number")),
     *     @OA\Parameter(name="created_by", in="query", required=false, description="Filter by user ID who created the payment", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, description="Search in notes field", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_id", type="integer", example=1),
     *                 @OA\Property(property="order", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="client_id", type="integer", example=1),
     *                     @OA\Property(property="total_price", type="number", example=100.50),
     *                     @OA\Property(property="status", type="string", example="paid")
     *                 ),
     *                 @OA\Property(property="amount", type="number", example=50.00),
                 *                 @OA\Property(property="status", type="string", enum={"pending", "paid", "canceled"}, example="paid"),
                 *                 @OA\Property(property="payment_type", type="string", enum={"initial", "fee", "normal"}, example="normal"),
                 *                 @OA\Property(property="payment_date", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true, description="MySQL datetime format: Y-m-d H:i:s"),
                 *                 @OA\Property(property="notes", type="string", nullable=true, example="Payment notes"),
                 *                 @OA\Property(property="created_by", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="user", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com")
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
        $query = Payment::with(['order.client', 'user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by payment_type
        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->query('payment_type'));
        }

        // Filter by order_id
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->query('order_id'));
        }

        // Filter by client_id (through order relationship)
        if ($request->filled('client_id')) {
            $query->whereHas('order', function($q) use ($request) {
                $q->where('client_id', $request->query('client_id'));
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->query('date_to'));
        }

        // Filter by amount range
        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', $request->query('amount_min'));
        }
        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', $request->query('amount_max'));
        }

        // Filter by created_by
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->query('created_by'));
        }

        // Search in notes
        if ($request->filled('search')) {
            $query->where('notes', 'like', '%' . $request->query('search') . '%');
        }

        $items = $query->orderBy('created_at', 'desc')->paginate($perPage);
        return $this->paginatedResponse($items);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/{id}",
     *     summary="Get a payment by ID",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="order", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="client_id", type="integer", example=1),
     *                 @OA\Property(property="total_price", type="number", example=100.50),
     *                 @OA\Property(property="status", type="string", example="paid")
     *             ),
     *             @OA\Property(property="amount", type="number", example=50.00),
             *             @OA\Property(property="status", type="string", enum={"pending", "paid", "canceled"}, example="paid"),
             *             @OA\Property(property="payment_type", type="string", enum={"initial", "fee", "normal"}, example="normal"),
     *             @OA\Property(property="payment_date", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true, description="MySQL datetime format: Y-m-d H:i:s"),
     *             @OA\Property(property="notes", type="string", nullable=true),
     *             @OA\Property(property="created_by", type="integer", nullable=true),
     *             @OA\Property(property="user", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found")
     * )
     */
    public function show($id)
    {
        $item = Payment::with(['order.client', 'user'])->findOrFail($id);
        return response()->json($item);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments",
     *     summary="Create a new payment",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id", "amount"},
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="amount", type="number", format="float", example=100.00, description="Payment amount (decimal 10,2)"),
     *             @OA\Property(property="status", type="string", enum={"pending", "paid"}, nullable=true, example="pending", description="Payment status (defaults to pending). Note: created_by is auto-filled from authenticated user and cannot be set via API."),
     *             @OA\Property(property="payment_type", type="string", enum={"initial", "fee", "normal"}, nullable=true, description="Payment type (defaults to normal)"),
     *             @OA\Property(property="payment_date", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25", description="Payment date. MySQL datetime format: Y-m-d H:i:s"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Payment notes", description="Payment notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="amount", type="number", example=50.00),
 *             @OA\Property(property="status", type="string", enum={"pending", "paid", "canceled"}, example="paid"),
 *             @OA\Property(property="payment_type", type="string", enum={"initial", "fee", "normal"}, example="normal"),
     *             @OA\Property(property="order", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="paid", type="number", example=100.00, description="Total amount paid (excludes fee payments - fees are tracked separately)"),
     *                 @OA\Property(property="remaining", type="number", example=20.50, description="Remaining amount = total_price - paid (fees do not affect this calculation)"),
     *                 @OA\Property(property="status", type="string", example="partially_paid")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Order not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => ['nullable', new MySqlDateTime()],
            'notes' => 'nullable|string',
            'payment_type' => 'nullable|string|in:initial,fee,normal',
            'status' => 'nullable|string|in:pending,paid',
        ]);

        // Verify order exists
        $order = Order::findOrFail($data['order_id']);

        // Create payment record - status defaults to pending if not provided
        $payment = Payment::create([
            'order_id' => $data['order_id'],
            'amount' => $data['amount'],
            'status' => $data['status'] ?? 'pending', // Default to pending
            'payment_type' => $data['payment_type'] ?? 'normal',
            'payment_date' => null, // Will be set when payment is paid
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()?->id, // Auto-filled from request user
        ]);

        // Recalculate order paid and remaining (only counts paid payments)
        $this->recalculateOrderPayments($order);

        $payment->load(['order', 'user']);
        $order->refresh();

        return response()->json([
            'message' => 'Payment created successfully',
            'payment' => $payment,
            'order' => [
                'id' => $order->id,
                'paid' => $order->paid,
                'remaining' => $order->remaining,
                'status' => $order->status,
            ],
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/{id}/pay",
     *     summary="Mark a payment as paid",
     *     description="Mark a payment as paid and create a corresponding transaction in the branch cashbox.",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), description="Payment ID"),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="payment_date", type="string", format="date-time", example="2025-12-02 23:33:25", nullable=true, description="Payment date (defaults to now). MySQL format: Y-m-d H:i:s"),
     *             @OA\Property(property="branch_id", type="integer", nullable=true, example=1, description="Branch ID for cashbox transaction. If not provided, uses order's branch if available.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment marked as paid successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Payment marked as paid successfully"),
     *             @OA\Property(property="payment", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="amount", type="number", example=50.00),
     *                 @OA\Property(property="status", type="string", example="paid"),
     *                 @OA\Property(property="payment_type", type="string", example="normal")
     *             ),
     *             @OA\Property(property="order", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="paid", type="number", example=100.00, description="Total amount paid (excludes fee payments - fees are tracked separately)"),
     *                 @OA\Property(property="remaining", type="number", example=20.50, description="Remaining amount = total_price - paid (fees do not affect this calculation)"),
     *                 @OA\Property(property="status", type="string", example="partially_paid")
     *             ),
     *             @OA\Property(property="transaction", type="object", nullable=true, description="The cashbox transaction created (if cashbox is available)")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Payment not found"),
     *     @OA\Response(response=422, description="Validation error or payment already paid/canceled")
     * )
     */
    public function pay(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        $order = $payment->order;

        // Validate payment can be marked as paid
        if ($payment->status === 'paid') {
            return response()->json([
                'message' => 'Payment is already marked as paid',
                'errors' => ['status' => ['Payment is already paid']]
            ], 422);
        }

        if ($payment->status === 'canceled') {
            return response()->json([
                'message' => 'Cannot mark canceled payment as paid',
                'errors' => ['status' => ['Payment is canceled and cannot be paid']]
            ], 422);
        }

        $data = $request->validate([
            'payment_date' => ['nullable', new MySqlDateTime()],
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $transaction = null;

        DB::transaction(function () use ($payment, $order, $data, $request, &$transaction) {
            // Update payment status
            $payment->status = 'paid';
            if (isset($data['payment_date'])) {
                $payment->payment_date = $data['payment_date'];
            } else {
                $payment->payment_date = now();
            }
            $payment->save();

            // Recalculate order paid and remaining
            $this->recalculateOrderPayments($order);

            // Create transaction in cashbox if a branch is specified or available
            $branchId = $data['branch_id'] ?? $order->branch_id ?? null;
            
            if ($branchId) {
                $branch = \App\Models\Branch::find($branchId);
                if ($branch && $branch->cashbox && $branch->cashbox->is_active) {
                    $transaction = $this->transactionService->recordPayment(
                        $branch->cashbox,
                        $payment->amount,
                        $payment->id,
                        $order->id,
                        $request->user(),
                        'cash' // Default to cash, could be extended to accept payment_method
                    );
                }
            }
        });

        $payment->load(['order', 'user']);
        $order->refresh();

        $response = [
            'message' => 'Payment marked as paid successfully',
            'payment' => $payment,
            'order' => [
                'id' => $order->id,
                'paid' => $order->paid,
                'remaining' => $order->remaining,
                'status' => $order->status,
            ],
        ];

        if ($transaction) {
            $response['transaction'] = [
                'id' => $transaction->id,
                'cashbox_id' => $transaction->cashbox_id,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
            ];
        }

        return response()->json($response);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/{id}/cancel",
     *     summary="Cancel a payment",
     *     description="Cancel a payment. If the payment was already paid, any associated transaction will be reversed.",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), description="Payment ID"),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", nullable=true, example="Cancellation notes (optional)", description="Cancellation notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment canceled successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Payment canceled successfully"),
     *             @OA\Property(property="payment", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="amount", type="number", example=50.00),
     *                 @OA\Property(property="status", type="string", example="canceled"),
     *                 @OA\Property(property="payment_type", type="string", example="normal")
     *             ),
     *             @OA\Property(property="order", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="paid", type="number", example=50.00, description="Total amount paid (excludes fee payments - fees are tracked separately)"),
     *                 @OA\Property(property="remaining", type="number", example=70.50, description="Remaining amount = total_price - paid (fees do not affect this calculation)"),
     *                 @OA\Property(property="status", type="string", example="partially_paid")
     *             ),
     *             @OA\Property(property="reversal_transaction", type="object", nullable=true, description="The reversal transaction created if payment was paid")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Payment not found"),
     *     @OA\Response(response=422, description="Validation error, payment already canceled, or insufficient cashbox balance for reversal")
     * )
     */
    public function cancel(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        $order = $payment->order;
        $wasPaid = $payment->status === 'paid';

        // Validate payment can be canceled
        if ($payment->status === 'canceled') {
            return response()->json([
                'message' => 'Payment is already canceled',
                'errors' => ['status' => ['Payment is already canceled']]
            ], 422);
        }

        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $reversalTransaction = null;
        $cancelNotes = $data['notes'] ?? 'Payment canceled';

        try {
            DB::transaction(function () use ($payment, $order, $wasPaid, $cancelNotes, $request, &$reversalTransaction) {
                // If payment was paid, reverse any associated transaction
                if ($wasPaid) {
                    // Find the transaction associated with this payment
                    $transaction = Transaction::where('reference_type', 'App\\Models\\Payment')
                        ->where('reference_id', $payment->id)
                        ->where('type', Transaction::TYPE_INCOME)
                        ->whereDoesntHave('reversals')
                        ->first();

                    if ($transaction) {
                        $reversalTransaction = $this->transactionService->reverseTransaction(
                            $transaction,
                            $cancelNotes,
                            $request->user()
                        );
                    }
                }

                // Update payment status
                $payment->status = 'canceled';
                $payment->notes = ($payment->notes ? $payment->notes . "\n" : '') . 'Canceled: ' . $cancelNotes;
                $payment->save();

                // Recalculate order paid and remaining (canceled payments don't count)
                $this->recalculateOrderPayments($order);
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Cannot cancel payment: ' . $e->getMessage(),
                'errors' => ['transaction' => [$e->getMessage()]]
            ], 422);
        }

        $payment->load(['order', 'user']);
        $order->refresh();

        $response = [
            'message' => 'Payment canceled successfully',
            'payment' => $payment,
            'order' => [
                'id' => $order->id,
                'paid' => $order->paid,
                'remaining' => $order->remaining,
                'status' => $order->status,
            ],
        ];

        if ($reversalTransaction) {
            $response['reversal_transaction'] = [
                'id' => $reversalTransaction->id,
                'cashbox_id' => $reversalTransaction->cashbox_id,
                'amount' => $reversalTransaction->amount,
                'balance_after' => $reversalTransaction->balance_after,
            ];
        }

        return response()->json($response);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/export",
     *     summary="Export all payments to CSV",
     *     tags={"Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="payment_type", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="order_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="client_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="amount_min", in="query", required=false, @OA\Schema(type="number")),
     *     @OA\Parameter(name="amount_max", in="query", required=false, @OA\Schema(type="number")),
     *     @OA\Parameter(name="created_by", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
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
        $query = Payment::with(['order.client', 'user']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by payment_type
        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->query('payment_type'));
        }

        // Filter by order_id
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->query('order_id'));
        }

        // Filter by client_id (through order relationship)
        if ($request->filled('client_id')) {
            $query->whereHas('order', function($q) use ($request) {
                $q->where('client_id', $request->query('client_id'));
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->query('date_to'));
        }

        // Filter by amount range
        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', $request->query('amount_min'));
        }
        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', $request->query('amount_max'));
        }

        // Filter by created_by
        if ($request->filled('created_by')) {
            $query->where('created_by', $request->query('created_by'));
        }

        // Search in notes
        if ($request->filled('search')) {
            $query->where('notes', 'like', '%' . $request->query('search') . '%');
        }

        $items = $query->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\PaymentExport::class, 'payments_' . date('Y-m-d_His') . '.csv');
    }

}

