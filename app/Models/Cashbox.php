<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Cashbox extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'branch_id',
        'initial_balance',
        'current_balance',
        'description',
        'is_active',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the branch that owns this cashbox
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get all transactions for this cashbox
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Recalculate balance from all transactions
     * This is a safety method to verify and fix any balance discrepancies
     */
    public function recalculateBalance(): float
    {
        $transactionSum = $this->transactions()
            ->selectRaw('SUM(CASE WHEN type = "income" THEN amount ELSE -amount END) as total')
            ->value('total') ?? 0;

        $calculatedBalance = $this->initial_balance + $transactionSum;
        
        if ($this->current_balance != $calculatedBalance) {
            $this->current_balance = $calculatedBalance;
            $this->save();
        }

        return $calculatedBalance;
    }

    /**
     * Check if cashbox has sufficient balance for a withdrawal
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->current_balance >= $amount;
    }

    /**
     * Get today's income total
     */
    public function getTodayIncome(): float
    {
        return $this->transactions()
            ->where('type', 'income')
            ->whereDate('created_at', today())
            ->sum('amount');
    }

    /**
     * Get today's expense total
     */
    public function getTodayExpense(): float
    {
        return $this->transactions()
            ->where('type', 'expense')
            ->whereDate('created_at', today())
            ->sum('amount');
    }

    /**
     * Get balance at a specific date (end of day)
     */
    public function getBalanceAtDate(\DateTimeInterface $date): float
    {
        $transactionSum = $this->transactions()
            ->whereDate('created_at', '<=', $date)
            ->selectRaw('SUM(CASE WHEN type = "income" THEN amount ELSE -amount END) as total')
            ->value('total') ?? 0;

        return $this->initial_balance + $transactionSum;
    }

    /**
     * Scope for active cashboxes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for cashbox by branch
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}



