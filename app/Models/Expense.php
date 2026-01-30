<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Expense extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'cashbox_id',
        'branch_id',
        'category',
        'subcategory',
        'amount',
        'expense_date',
        'vendor',
        'reference_number',
        'description',
        'notes',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Expense status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Expense category constants
     */
    public const CATEGORY_RENT = 'rent';
    public const CATEGORY_UTILITIES = 'utilities';
    public const CATEGORY_SUPPLIES = 'supplies';
    public const CATEGORY_MAINTENANCE = 'maintenance';
    public const CATEGORY_SALARIES = 'salaries';
    public const CATEGORY_MARKETING = 'marketing';
    public const CATEGORY_TRANSPORT = 'transport';
    public const CATEGORY_CLEANING = 'cleaning';
    public const CATEGORY_OTHER = 'other';

    /**
     * Get all available categories
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_RENT => 'Rent',
            self::CATEGORY_UTILITIES => 'Utilities (Electricity, Water, Gas)',
            self::CATEGORY_SUPPLIES => 'Supplies & Materials',
            self::CATEGORY_MAINTENANCE => 'Maintenance & Repairs',
            self::CATEGORY_SALARIES => 'Salaries & Wages',
            self::CATEGORY_MARKETING => 'Marketing & Advertising',
            self::CATEGORY_TRANSPORT => 'Transportation',
            self::CATEGORY_CLEANING => 'Cleaning Services',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    /**
     * Get the cashbox this expense is from
     */
    public function cashbox()
    {
        return $this->belongsTo(Cashbox::class);
    }

    /**
     * Get the branch this expense belongs to
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who created this expense
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this expense
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the transaction associated with this expense
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Check if expense can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if expense can be paid
     */
    public function canBePaid(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if expense can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Check if expense is paid
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Scope for expenses by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for expenses by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for expenses by date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    /**
     * Scope for expenses by branch
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope for pending expenses
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved expenses
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for paid expenses
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }
}



