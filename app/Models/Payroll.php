<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

class Payroll extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    protected $fillable = [
        'employee_id',
        'period',
        'period_start',
        'period_end',
        'base_salary',
        'transport_allowance',
        'housing_allowance',
        'other_allowances',
        'total_allowances',
        'overtime_hours',
        'overtime_rate',
        'overtime_amount',
        'orders_count',
        'orders_total',
        'commission_rate',
        'commission_amount',
        'working_days',
        'days_present',
        'days_absent',
        'days_late',
        'leave_days',
        'absence_deductions',
        'late_deductions',
        'penalty_deductions',
        'other_deductions',
        'total_deductions',
        'gross_salary',
        'net_salary',
        'status',
        'generated_by',
        'submitted_by',
        'approved_by',
        'paid_by',
        'cancelled_by',
        'submitted_at',
        'approved_at',
        'paid_at',
        'cancelled_at',
        'cashbox_id',
        'transaction_id',
        'payment_method',
        'payment_reference',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'base_salary' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'housing_allowance' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'orders_count' => 'integer',
        'orders_total' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'working_days' => 'integer',
        'days_present' => 'integer',
        'days_absent' => 'integer',
        'days_late' => 'integer',
        'leave_days' => 'integer',
        'absence_deductions' => 'decimal:2',
        'late_deductions' => 'decimal:2',
        'penalty_deductions' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Pending Approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_PAID => 'Paid',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * Employee
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Payroll items (breakdown)
     */
    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * Earning items
     */
    public function earnings()
    {
        return $this->items()->where('type', PayrollItem::TYPE_EARNING);
    }

    /**
     * Deduction items
     */
    public function deductionItems()
    {
        return $this->items()->where('type', PayrollItem::TYPE_DEDUCTION);
    }

    /**
     * Deductions applied to this payroll
     */
    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }

    /**
     * Cashbox used for payment
     */
    public function cashbox()
    {
        return $this->belongsTo(Cashbox::class);
    }

    /**
     * Transaction created when paid
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    // Audit relationships
    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Scope by employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope for period
     */
    public function scopeForPeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope for pending
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for paid
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Check if can be edited
     */
    public function getCanEditAttribute(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if can be submitted
     */
    public function getCanSubmitAttribute(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if can be approved
     */
    public function getCanApproveAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if can be paid
     */
    public function getCanPayAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if can be cancelled
     */
    public function getCanCancelAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING]);
    }

    /**
     * Submit for approval
     */
    public function submit(int $submittedByUserId): self
    {
        if (!$this->can_submit) {
            throw new \Exception('Payroll cannot be submitted in current status.');
        }

        $this->update([
            'status' => self::STATUS_PENDING,
            'submitted_by' => $submittedByUserId,
            'submitted_at' => now(),
        ]);

        return $this;
    }

    /**
     * Approve payroll
     */
    public function approve(int $approvedByUserId): self
    {
        if (!$this->can_approve) {
            throw new \Exception('Payroll cannot be approved in current status.');
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approvedByUserId,
            'approved_at' => now(),
        ]);

        return $this;
    }

    /**
     * Reject payroll (back to draft)
     */
    public function reject(string $reason, int $rejectedByUserId): self
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \Exception('Only pending payrolls can be rejected.');
        }

        $this->update([
            'status' => self::STATUS_DRAFT,
            'rejection_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(
        int $paidByUserId, 
        int $cashboxId, 
        int $transactionId, 
        string $paymentMethod,
        ?string $paymentReference = null
    ): self {
        if (!$this->can_pay) {
            throw new \Exception('Payroll cannot be paid in current status.');
        }

        $this->update([
            'status' => self::STATUS_PAID,
            'paid_by' => $paidByUserId,
            'paid_at' => now(),
            'cashbox_id' => $cashboxId,
            'transaction_id' => $transactionId,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
        ]);

        // Mark all deductions as applied
        $this->deductions()->update(['is_applied' => true]);

        return $this;
    }

    /**
     * Cancel payroll
     */
    public function cancel(int $cancelledByUserId, ?string $reason = null): self
    {
        if (!$this->can_cancel) {
            throw new \Exception('Payroll cannot be cancelled in current status.');
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_by' => $cancelledByUserId,
            'cancelled_at' => now(),
            'notes' => $reason ? ($this->notes . "\nCancellation reason: " . $reason) : $this->notes,
        ]);

        return $this;
    }

    /**
     * Calculate totals
     */
    public function calculateTotals(): self
    {
        $this->total_allowances = $this->transport_allowance + 
                                   $this->housing_allowance + 
                                   $this->other_allowances;

        $this->overtime_amount = $this->overtime_hours * 
                                  ($this->employee->hourly_salary_rate * $this->overtime_rate);

        $this->total_deductions = $this->absence_deductions + 
                                   $this->late_deductions + 
                                   $this->penalty_deductions + 
                                   $this->other_deductions;

        $this->gross_salary = $this->base_salary + 
                               $this->total_allowances + 
                               $this->overtime_amount + 
                               $this->commission_amount;

        $this->net_salary = $this->gross_salary - $this->total_deductions;

        return $this;
    }
}


