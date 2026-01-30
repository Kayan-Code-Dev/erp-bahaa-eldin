<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\LogsActivity;

/**
 * IMMUTABLE Transaction Model
 * 
 * CRITICAL: Transactions cannot be updated or deleted!
 * To correct a transaction, create a reversal transaction instead.
 * This ensures:
 * - Perfect audit trail
 * - Accurate cashbox balance history
 * - No loss of financial data
 */
class Transaction extends Model
{
    use HasFactory, LogsActivity;

    // Disable timestamps auto-update since we don't update records
    const UPDATED_AT = null;

    protected $fillable = [
        'cashbox_id',
        'type',
        'amount',
        'balance_after',
        'category',
        'description',
        'reference_type',
        'reference_id',
        'reversed_transaction_id',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Transaction types
     */
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_REVERSAL = 'reversal';

    /**
     * Transaction categories
     */
    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_CUSTODY_DEPOSIT = 'custody_deposit';
    public const CATEGORY_CUSTODY_RETURN = 'custody_return';
    public const CATEGORY_CUSTODY_FORFEITURE = 'custody_forfeiture';
    public const CATEGORY_EXPENSE = 'expense';
    public const CATEGORY_RECEIVABLE_PAYMENT = 'receivable_payment';
    public const CATEGORY_REVERSAL = 'reversal';
    public const CATEGORY_INITIAL_BALANCE = 'initial_balance';
    public const CATEGORY_ADJUSTMENT = 'adjustment';
    public const CATEGORY_SALARY_EXPENSE = 'salary_expense';

    /**
     * Get all available categories
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_PAYMENT => 'Customer Payment',
            self::CATEGORY_CUSTODY_DEPOSIT => 'Custody Deposit',
            self::CATEGORY_CUSTODY_RETURN => 'Custody Return',
            self::CATEGORY_CUSTODY_FORFEITURE => 'Custody Forfeiture',
            self::CATEGORY_EXPENSE => 'Business Expense',
            self::CATEGORY_RECEIVABLE_PAYMENT => 'Receivable Payment',
            self::CATEGORY_REVERSAL => 'Reversal',
            self::CATEGORY_INITIAL_BALANCE => 'Initial Balance',
            self::CATEGORY_ADJUSTMENT => 'Balance Adjustment',
            self::CATEGORY_SALARY_EXPENSE => 'Salary Expense',
        ];
    }

    /**
     * Boot method to enforce immutability
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent updates - transactions are immutable
        static::updating(function ($transaction) {
            throw new \RuntimeException('Transactions are immutable and cannot be updated. Create a reversal transaction instead.');
        });

        // Prevent deletes - transactions are immutable
        static::deleting(function ($transaction) {
            throw new \RuntimeException('Transactions are immutable and cannot be deleted. Create a reversal transaction instead.');
        });
    }

    /**
     * Get the cashbox this transaction belongs to
     */
    public function cashbox()
    {
        return $this->belongsTo(Cashbox::class);
    }

    /**
     * Get the user who created this transaction
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the original transaction (for reversals)
     */
    public function reversedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reversed_transaction_id');
    }

    /**
     * Get any reversal transactions for this transaction
     */
    public function reversals()
    {
        return $this->hasMany(Transaction::class, 'reversed_transaction_id');
    }

    /**
     * Get the referenced model (Payment, Custody, etc.)
     */
    public function reference()
    {
        return $this->morphTo('reference');
    }

    /**
     * Check if this transaction has been reversed
     */
    public function isReversed(): bool
    {
        return $this->reversals()->exists();
    }

    /**
     * Check if this is a reversal transaction
     */
    public function isReversal(): bool
    {
        return $this->type === self::TYPE_REVERSAL;
    }

    /**
     * Check if this is an income transaction
     */
    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }

    /**
     * Check if this is an expense transaction
     */
    public function isExpense(): bool
    {
        return $this->type === self::TYPE_EXPENSE;
    }

    /**
     * Get the effective amount (positive for income, negative for expense)
     */
    public function getEffectiveAmount(): float
    {
        return $this->isIncome() ? $this->amount : -$this->amount;
    }

    /**
     * Scope for income transactions
     */
    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    /**
     * Scope for expense transactions
     */
    public function scopeExpense($query)
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    /**
     * Scope for reversals
     */
    public function scopeReversals($query)
    {
        return $query->where('type', self::TYPE_REVERSAL);
    }

    /**
     * Scope for a specific category
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for a date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for today's transactions
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}

