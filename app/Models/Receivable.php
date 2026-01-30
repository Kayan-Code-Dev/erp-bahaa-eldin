<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Receivable extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'client_id',
        'order_id',
        'branch_id',
        'original_amount',
        'paid_amount',
        'remaining_amount',
        'due_date',
        'description',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    /**
     * Receivable status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_WRITTEN_OFF = 'written_off';

    /**
     * Boot method to automatically calculate remaining amount
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($receivable) {
            if (!isset($receivable->remaining_amount)) {
                $receivable->remaining_amount = $receivable->original_amount - ($receivable->paid_amount ?? 0);
            }
        });
    }

    /**
     * Get the client who owes this amount
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the order this receivable is for (if any)
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the branch this receivable belongs to
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who created this receivable
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the payments made against this receivable
     */
    public function payments()
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    /**
     * Record a payment against this receivable
     */
    public function recordPayment(float $amount, User $createdBy, ?int $paymentId = null, ?int $transactionId = null, string $paymentMethod = 'cash', ?string $notes = null): ReceivablePayment
    {
        $payment = $this->payments()->create([
            'amount' => $amount,
            'payment_date' => now(),
            'payment_method' => $paymentMethod,
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId,
            'notes' => $notes,
            'created_by' => $createdBy->id,
        ]);

        // Update receivable totals
        $this->paid_amount += $amount;
        $this->remaining_amount = $this->original_amount - $this->paid_amount;

        // Update status based on remaining amount
        if ($this->remaining_amount <= 0) {
            $this->remaining_amount = 0;
            $this->status = self::STATUS_PAID;
        } elseif ($this->paid_amount > 0) {
            $this->status = self::STATUS_PARTIAL;
        }

        $this->save();

        return $payment;
    }

    /**
     * Check if receivable is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->remaining_amount > 0;
    }

    /**
     * Check if receivable is fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID || $this->remaining_amount <= 0;
    }

    /**
     * Check if receivable is partially paid
     */
    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->remaining_amount > 0;
    }

    /**
     * Get the payment percentage
     */
    public function getPaymentPercentage(): float
    {
        if ($this->original_amount == 0) {
            return 100;
        }
        return round(($this->paid_amount / $this->original_amount) * 100, 2);
    }

    /**
     * Update status to overdue if past due date
     */
    public function checkAndUpdateOverdueStatus(): void
    {
        if ($this->isOverdue() && $this->status !== self::STATUS_OVERDUE && $this->status !== self::STATUS_PAID) {
            $this->status = self::STATUS_OVERDUE;
            $this->save();
        }
    }

    /**
     * Write off the receivable (mark as uncollectable)
     */
    public function writeOff(): void
    {
        $this->status = self::STATUS_WRITTEN_OFF;
        $this->save();
    }

    /**
     * Scope for receivables by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for receivables by client
     */
    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope for receivables by branch
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope for overdue receivables
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('remaining_amount', '>', 0)
            ->whereNotIn('status', [self::STATUS_PAID, self::STATUS_WRITTEN_OFF]);
    }

    /**
     * Scope for unpaid receivables
     */
    public function scopeUnpaid($query)
    {
        return $query->where('remaining_amount', '>', 0)
            ->whereNotIn('status', [self::STATUS_PAID, self::STATUS_WRITTEN_OFF]);
    }

    /**
     * Scope for due within days
     */
    public function scopeDueWithinDays($query, int $days)
    {
        return $query->whereBetween('due_date', [now(), now()->addDays($days)])
            ->where('remaining_amount', '>', 0);
    }
}



