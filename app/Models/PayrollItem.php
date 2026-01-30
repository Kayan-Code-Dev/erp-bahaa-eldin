<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;

class PayrollItem extends Model
{
    use HasFactory, SerializesDates;

    protected $fillable = [
        'payroll_id',
        'type',
        'category',
        'description',
        'amount',
        'quantity',
        'rate',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'decimal:2',
        'rate' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Type constants
    public const TYPE_EARNING = 'earning';
    public const TYPE_DEDUCTION = 'deduction';

    public const TYPES = [
        self::TYPE_EARNING => 'Earning',
        self::TYPE_DEDUCTION => 'Deduction',
    ];

    // Category constants for earnings
    public const CATEGORY_BASE_SALARY = 'base_salary';
    public const CATEGORY_TRANSPORT = 'transport';
    public const CATEGORY_HOUSING = 'housing';
    public const CATEGORY_OTHER_ALLOWANCE = 'other_allowance';
    public const CATEGORY_OVERTIME = 'overtime';
    public const CATEGORY_COMMISSION = 'commission';
    public const CATEGORY_BONUS = 'bonus';

    // Category constants for deductions
    public const CATEGORY_ABSENCE = 'absence';
    public const CATEGORY_LATE = 'late';
    public const CATEGORY_PENALTY = 'penalty';
    public const CATEGORY_LOAN = 'loan';
    public const CATEGORY_ADVANCE = 'advance';
    public const CATEGORY_OTHER_DEDUCTION = 'other_deduction';

    public const EARNING_CATEGORIES = [
        self::CATEGORY_BASE_SALARY => 'Base Salary',
        self::CATEGORY_TRANSPORT => 'Transport Allowance',
        self::CATEGORY_HOUSING => 'Housing Allowance',
        self::CATEGORY_OTHER_ALLOWANCE => 'Other Allowance',
        self::CATEGORY_OVERTIME => 'Overtime',
        self::CATEGORY_COMMISSION => 'Commission',
        self::CATEGORY_BONUS => 'Bonus',
    ];

    public const DEDUCTION_CATEGORIES = [
        self::CATEGORY_ABSENCE => 'Absence',
        self::CATEGORY_LATE => 'Late Arrival',
        self::CATEGORY_PENALTY => 'Penalty',
        self::CATEGORY_LOAN => 'Loan Repayment',
        self::CATEGORY_ADVANCE => 'Advance Repayment',
        self::CATEGORY_OTHER_DEDUCTION => 'Other Deduction',
    ];

    /**
     * Payroll this item belongs to
     */
    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    /**
     * Scope by payroll
     */
    public function scopeForPayroll($query, $payrollId)
    {
        return $query->where('payroll_id', $payrollId);
    }

    /**
     * Scope for earnings
     */
    public function scopeEarnings($query)
    {
        return $query->where('type', self::TYPE_EARNING);
    }

    /**
     * Scope for deductions
     */
    public function scopeDeductions($query)
    {
        return $query->where('type', self::TYPE_DEDUCTION);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get category label
     */
    public function getCategoryLabelAttribute(): string
    {
        $categories = array_merge(self::EARNING_CATEGORIES, self::DEDUCTION_CATEGORIES);
        return $categories[$this->category] ?? $this->category;
    }

    /**
     * Check if this is an earning
     */
    public function getIsEarningAttribute(): bool
    {
        return $this->type === self::TYPE_EARNING;
    }

    /**
     * Check if this is a deduction
     */
    public function getIsDeductionAttribute(): bool
    {
        return $this->type === self::TYPE_DEDUCTION;
    }

    /**
     * Create earning item
     */
    public static function createEarning(
        int $payrollId,
        string $category,
        string $description,
        float $amount,
        float $quantity = 1,
        ?float $rate = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'payroll_id' => $payrollId,
            'type' => self::TYPE_EARNING,
            'category' => $category,
            'description' => $description,
            'amount' => $amount,
            'quantity' => $quantity,
            'rate' => $rate,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create deduction item
     */
    public static function createDeduction(
        int $payrollId,
        string $category,
        string $description,
        float $amount,
        float $quantity = 1,
        ?float $rate = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'payroll_id' => $payrollId,
            'type' => self::TYPE_DEDUCTION,
            'category' => $category,
            'description' => $description,
            'amount' => $amount,
            'quantity' => $quantity,
            'rate' => $rate,
            'metadata' => $metadata,
        ]);
    }
}





