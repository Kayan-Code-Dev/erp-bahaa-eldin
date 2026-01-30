<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

class Deduction extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    protected $fillable = [
        'employee_id',
        'type',
        'reason',
        'description',
        'amount',
        'date',
        'period',
        'payroll_id',
        'is_applied',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_applied' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // Type constants
    public const TYPE_ABSENCE = 'absence';
    public const TYPE_LATE = 'late';
    public const TYPE_PENALTY = 'penalty';
    public const TYPE_LOAN_REPAYMENT = 'loan_repayment';
    public const TYPE_ADVANCE_REPAYMENT = 'advance_repayment';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_ABSENCE => 'Absence',
        self::TYPE_LATE => 'Late Arrival',
        self::TYPE_PENALTY => 'Penalty',
        self::TYPE_LOAN_REPAYMENT => 'Loan Repayment',
        self::TYPE_ADVANCE_REPAYMENT => 'Advance Repayment',
        self::TYPE_OTHER => 'Other',
    ];

    /**
     * Employee who received this deduction
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Payroll this deduction was applied to
     */
    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    /**
     * User who created this deduction
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who approved this deduction
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope by employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for period
     */
    public function scopeForPeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Scope for unapplied deductions
     */
    public function scopeUnapplied($query)
    {
        return $query->where('is_applied', false);
    }

    /**
     * Scope for applied deductions
     */
    public function scopeApplied($query)
    {
        return $query->where('is_applied', true);
    }

    /**
     * Scope for approved deductions
     */
    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    /**
     * Scope for pending approval
     */
    public function scopePendingApproval($query)
    {
        return $query->whereNull('approved_at');
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Check if approved
     */
    public function getIsApprovedAttribute(): bool
    {
        return !is_null($this->approved_at);
    }

    /**
     * Approve the deduction
     */
    public function approve(int $approvedByUserId): self
    {
        $this->update([
            'approved_by' => $approvedByUserId,
            'approved_at' => now(),
        ]);

        return $this;
    }

    /**
     * Mark as applied to payroll
     */
    public function markAsApplied(int $payrollId): self
    {
        $this->update([
            'is_applied' => true,
            'payroll_id' => $payrollId,
        ]);

        return $this;
    }

    /**
     * Create absence deduction based on employee's daily rate
     */
    public static function createAbsenceDeduction(
        Employee $employee, 
        string $date, 
        ?string $reason = null,
        ?int $createdByUserId = null
    ): self {
        $dailyRate = $employee->daily_salary_rate;
        
        return self::create([
            'employee_id' => $employee->id,
            'type' => self::TYPE_ABSENCE,
            'reason' => $reason ?? 'Absence',
            'amount' => $dailyRate,
            'date' => $date,
            'period' => date('Y-m', strtotime($date)),
            'created_by' => $createdByUserId,
        ]);
    }

    /**
     * Create late deduction based on minutes late
     */
    public static function createLateDeduction(
        Employee $employee,
        string $date,
        int $lateMinutes,
        ?int $createdByUserId = null
    ): self {
        // Calculate deduction: (hourly rate / 60) * late minutes
        $hourlyRate = $employee->hourly_salary_rate;
        $amount = ($hourlyRate / 60) * $lateMinutes;
        
        return self::create([
            'employee_id' => $employee->id,
            'type' => self::TYPE_LATE,
            'reason' => "Late arrival by {$lateMinutes} minutes",
            'amount' => round($amount, 2),
            'date' => $date,
            'period' => date('Y-m', strtotime($date)),
            'created_by' => $createdByUserId,
        ]);
    }
}


