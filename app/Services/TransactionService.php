<?php

namespace App\Services;

use App\Models\Cashbox;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * TransactionService
 * 
 * Central service for all financial transactions.
 * Ensures:
 * - Cashbox balance integrity (never negative)
 * - Proper audit trail
 * - Atomic operations
 * 
 * CRITICAL: All money movements MUST go through this service!
 */
class TransactionService
{
    /**
     * Record an income transaction (money coming IN)
     * 
     * @param Cashbox $cashbox
     * @param float $amount Must be positive
     * @param string $category Transaction category
     * @param string $description Human-readable description
     * @param User $createdBy User creating the transaction
     * @param string|null $referenceType Model class name (e.g., Payment::class)
     * @param int|null $referenceId Model ID
     * @param array|null $metadata Additional data
     * @return Transaction
     * @throws \InvalidArgumentException If amount is not positive
     * @throws \RuntimeException If cashbox is inactive
     */
    public function recordIncome(
        Cashbox $cashbox,
        float $amount,
        string $category,
        string $description,
        User $createdBy,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): Transaction {
        $this->validateAmount($amount);
        $this->validateCashbox($cashbox);

        return DB::transaction(function () use ($cashbox, $amount, $category, $description, $createdBy, $referenceType, $referenceId, $metadata) {
            // Lock the cashbox row for update
            $cashbox = Cashbox::lockForUpdate()->find($cashbox->id);
            
            $newBalance = $cashbox->current_balance + $amount;

            $transaction = Transaction::create([
                'cashbox_id' => $cashbox->id,
                'type' => Transaction::TYPE_INCOME,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'category' => $category,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $createdBy->id,
                'metadata' => $metadata,
            ]);

            // Update cashbox balance
            $cashbox->current_balance = $newBalance;
            $cashbox->save();

            return $transaction;
        });
    }

    /**
     * Record an expense transaction (money going OUT)
     * 
     * @param Cashbox $cashbox
     * @param float $amount Must be positive
     * @param string $category Transaction category
     * @param string $description Human-readable description
     * @param User $createdBy User creating the transaction
     * @param string|null $referenceType Model class name
     * @param int|null $referenceId Model ID
     * @param array|null $metadata Additional data
     * @return Transaction
     * @throws \InvalidArgumentException If amount is not positive
     * @throws \RuntimeException If cashbox is inactive or has insufficient balance
     */
    public function recordExpense(
        Cashbox $cashbox,
        float $amount,
        string $category,
        string $description,
        User $createdBy,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?array $metadata = null
    ): Transaction {
        $this->validateAmount($amount);
        $this->validateCashbox($cashbox);

        return DB::transaction(function () use ($cashbox, $amount, $category, $description, $createdBy, $referenceType, $referenceId, $metadata) {
            // Lock the cashbox row for update
            $cashbox = Cashbox::lockForUpdate()->find($cashbox->id);
            
            // CRITICAL: Check for sufficient balance
            if ($cashbox->current_balance < $amount) {
                throw new \RuntimeException(
                    "Insufficient cashbox balance. Available: {$cashbox->current_balance}, Required: {$amount}"
                );
            }

            $newBalance = $cashbox->current_balance - $amount;

            $transaction = Transaction::create([
                'cashbox_id' => $cashbox->id,
                'type' => Transaction::TYPE_EXPENSE,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'category' => $category,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $createdBy->id,
                'metadata' => $metadata,
            ]);

            // Update cashbox balance
            $cashbox->current_balance = $newBalance;
            $cashbox->save();

            return $transaction;
        });
    }

    /**
     * Reverse a transaction
     * 
     * Creates a new reversal transaction that undoes the effect of the original.
     * The original transaction remains unchanged (immutability).
     * 
     * @param Transaction $originalTransaction
     * @param string $reason Why the transaction is being reversed
     * @param User $createdBy User creating the reversal
     * @return Transaction The reversal transaction
     * @throws \RuntimeException If transaction is already reversed
     */
    public function reverseTransaction(
        Transaction $originalTransaction,
        string $reason,
        User $createdBy
    ): Transaction {
        // Check if already reversed
        if ($originalTransaction->isReversed()) {
            throw new \RuntimeException('This transaction has already been reversed.');
        }

        // Check if this is itself a reversal
        if ($originalTransaction->isReversal()) {
            throw new \RuntimeException('Cannot reverse a reversal transaction.');
        }

        $cashbox = $originalTransaction->cashbox;
        $this->validateCashbox($cashbox);

        return DB::transaction(function () use ($originalTransaction, $reason, $createdBy, $cashbox) {
            // Lock the cashbox row for update
            $cashbox = Cashbox::lockForUpdate()->find($cashbox->id);
            
            // Determine the reversal type and new balance
            // If original was income, reversal removes money (expense-like)
            // If original was expense, reversal adds money back (income-like)
            if ($originalTransaction->isIncome()) {
                // Original added money, reversal removes it
                if ($cashbox->current_balance < $originalTransaction->amount) {
                    throw new \RuntimeException(
                        "Cannot reverse: insufficient cashbox balance. Available: {$cashbox->current_balance}"
                    );
                }
                $newBalance = $cashbox->current_balance - $originalTransaction->amount;
            } else {
                // Original removed money, reversal adds it back
                $newBalance = $cashbox->current_balance + $originalTransaction->amount;
            }

            $description = "REVERSAL: {$originalTransaction->description}. Reason: {$reason}";

            $reversalTransaction = Transaction::create([
                'cashbox_id' => $cashbox->id,
                'type' => Transaction::TYPE_REVERSAL,
                'amount' => $originalTransaction->amount,
                'balance_after' => $newBalance,
                'category' => Transaction::CATEGORY_REVERSAL,
                'description' => $description,
                'reference_type' => $originalTransaction->reference_type,
                'reference_id' => $originalTransaction->reference_id,
                'reversed_transaction_id' => $originalTransaction->id,
                'created_by' => $createdBy->id,
                'metadata' => [
                    'original_transaction_id' => $originalTransaction->id,
                    'original_type' => $originalTransaction->type,
                    'original_category' => $originalTransaction->category,
                    'reversal_reason' => $reason,
                ],
            ]);

            // Update cashbox balance
            $cashbox->current_balance = $newBalance;
            $cashbox->save();

            return $reversalTransaction;
        });
    }

    /**
     * Record a payment transaction (income)
     */
    public function recordPayment(
        Cashbox $cashbox,
        float $amount,
        int $paymentId,
        int $orderId,
        User $createdBy,
        string $paymentMethod = 'cash'
    ): Transaction {
        return $this->recordIncome(
            $cashbox,
            $amount,
            Transaction::CATEGORY_PAYMENT,
            "Payment #{$paymentId} for Order #{$orderId} via {$paymentMethod}",
            $createdBy,
            'App\\Models\\Payment',
            $paymentId,
            ['order_id' => $orderId, 'payment_method' => $paymentMethod]
        );
    }

    /**
     * Record a custody deposit (income)
     */
    public function recordCustodyDeposit(
        Cashbox $cashbox,
        float $amount,
        int $custodyId,
        int $orderId,
        User $createdBy
    ): Transaction {
        return $this->recordIncome(
            $cashbox,
            $amount,
            Transaction::CATEGORY_CUSTODY_DEPOSIT,
            "Custody deposit #{$custodyId} for Order #{$orderId}",
            $createdBy,
            'App\\Models\\Custody',
            $custodyId,
            ['order_id' => $orderId]
        );
    }

    /**
     * Record a custody return (expense - money going back to customer)
     */
    public function recordCustodyReturn(
        Cashbox $cashbox,
        float $amount,
        int $custodyId,
        int $orderId,
        User $createdBy
    ): Transaction {
        return $this->recordExpense(
            $cashbox,
            $amount,
            Transaction::CATEGORY_CUSTODY_RETURN,
            "Custody return #{$custodyId} for Order #{$orderId}",
            $createdBy,
            'App\\Models\\Custody',
            $custodyId,
            ['order_id' => $orderId]
        );
    }

    /**
     * Record custody forfeiture (when customer loses their deposit)
     * This converts the deposit to income (no money moves, just reclassification)
     * Note: This doesn't create a transaction since no money moves - the deposit was already recorded as income
     */
    public function recordCustodyForfeiture(
        Cashbox $cashbox,
        float $amount,
        int $custodyId,
        int $orderId,
        User $createdBy,
        string $reason
    ): Transaction {
        // Record as income (forfeiture means we keep the money)
        // This creates an audit trail even though no new money comes in
        return $this->recordIncome(
            $cashbox,
            0.01, // Minimal amount for audit trail - the actual deposit was already recorded
            Transaction::CATEGORY_CUSTODY_FORFEITURE,
            "Custody forfeiture #{$custodyId} for Order #{$orderId}. Reason: {$reason}. Original amount: {$amount}",
            $createdBy,
            'App\\Models\\Custody',
            $custodyId,
            ['order_id' => $orderId, 'forfeited_amount' => $amount, 'reason' => $reason]
        );
    }

    /**
     * Get daily summary for a cashbox
     */
    public function getDailySummary(Cashbox $cashbox, ?\DateTimeInterface $date = null): array
    {
        $date = $date ?? today();

        $transactions = $cashbox->transactions()
            ->whereDate('created_at', $date)
            ->get();

        $income = $transactions->where('type', Transaction::TYPE_INCOME)->sum('amount');
        $expense = $transactions->where('type', Transaction::TYPE_EXPENSE)->sum('amount');
        $reversals = $transactions->where('type', Transaction::TYPE_REVERSAL)->count();

        // Get opening balance (balance at start of day)
        $openingBalance = $cashbox->getBalanceAtDate($date->copy()->subDay());

        return [
            'date' => $date->format('Y-m-d'),
            'cashbox_id' => $cashbox->id,
            'cashbox_name' => $cashbox->name,
            'opening_balance' => $openingBalance,
            'total_income' => $income,
            'total_expense' => $expense,
            'net_change' => $income - $expense,
            'closing_balance' => $openingBalance + $income - $expense,
            'transaction_count' => $transactions->count(),
            'reversal_count' => $reversals,
        ];
    }

    /**
     * Validate amount is positive
     */
    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transaction amount must be positive.');
        }
    }

    /**
     * Validate cashbox is active
     */
    private function validateCashbox(Cashbox $cashbox): void
    {
        if (!$cashbox->is_active) {
            throw new \RuntimeException('Cannot create transaction on inactive cashbox.');
        }
    }
}






