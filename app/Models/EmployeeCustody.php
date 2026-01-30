<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

class EmployeeCustody extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    protected $fillable = [
        'employee_id',
        'type',
        'name',
        'description',
        'serial_number',
        'asset_tag',
        'value',
        'condition_on_assignment',
        'condition_on_return',
        'status',
        'assigned_date',
        'expected_return_date',
        'returned_date',
        'assigned_by',
        'returned_to',
        'notes',
        'return_notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'assigned_date' => 'date',
        'expected_return_date' => 'date',
        'returned_date' => 'date',
    ];

    // Type constants
    public const TYPE_LAPTOP = 'laptop';
    public const TYPE_PHONE = 'phone';
    public const TYPE_TABLET = 'tablet';
    public const TYPE_KEYS = 'keys';
    public const TYPE_TOOLS = 'tools';
    public const TYPE_UNIFORM = 'uniform';
    public const TYPE_VEHICLE = 'vehicle';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_LAPTOP => 'Laptop',
        self::TYPE_PHONE => 'Phone',
        self::TYPE_TABLET => 'Tablet',
        self::TYPE_KEYS => 'Keys',
        self::TYPE_TOOLS => 'Tools',
        self::TYPE_UNIFORM => 'Uniform',
        self::TYPE_VEHICLE => 'Vehicle',
        self::TYPE_OTHER => 'Other',
    ];

    // Status constants
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_DAMAGED = 'damaged';
    public const STATUS_LOST = 'lost';

    public const STATUSES = [
        self::STATUS_ASSIGNED => 'Assigned',
        self::STATUS_RETURNED => 'Returned',
        self::STATUS_DAMAGED => 'Damaged',
        self::STATUS_LOST => 'Lost',
    ];

    // Condition constants
    public const CONDITION_NEW = 'new';
    public const CONDITION_GOOD = 'good';
    public const CONDITION_FAIR = 'fair';
    public const CONDITION_POOR = 'poor';
    public const CONDITION_DAMAGED = 'damaged';
    public const CONDITION_LOST = 'lost';

    public const CONDITIONS = [
        self::CONDITION_NEW => 'New',
        self::CONDITION_GOOD => 'Good',
        self::CONDITION_FAIR => 'Fair',
        self::CONDITION_POOR => 'Poor',
        self::CONDITION_DAMAGED => 'Damaged',
        self::CONDITION_LOST => 'Lost',
    ];

    /**
     * Employee who has this custody item
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * User who assigned this item
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * User who received the return
     */
    public function returnedTo()
    {
        return $this->belongsTo(User::class, 'returned_to');
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
     * Scope for assigned items
     */
    public function scopeAssigned($query)
    {
        return $query->where('status', self::STATUS_ASSIGNED);
    }

    /**
     * Scope for returned items
     */
    public function scopeReturned($query)
    {
        return $query->where('status', self::STATUS_RETURNED);
    }

    /**
     * Scope for overdue items
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_ASSIGNED)
                     ->whereNotNull('expected_return_date')
                     ->where('expected_return_date', '<', today());
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get condition on assignment label
     */
    public function getConditionOnAssignmentLabelAttribute(): string
    {
        return self::CONDITIONS[$this->condition_on_assignment] ?? $this->condition_on_assignment;
    }

    /**
     * Get condition on return label
     */
    public function getConditionOnReturnLabelAttribute(): ?string
    {
        if (!$this->condition_on_return) {
            return null;
        }
        return self::CONDITIONS[$this->condition_on_return] ?? $this->condition_on_return;
    }

    /**
     * Check if overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status !== self::STATUS_ASSIGNED) {
            return false;
        }
        
        if (!$this->expected_return_date) {
            return false;
        }
        
        return $this->expected_return_date->isPast();
    }

    /**
     * Mark as returned
     */
    public function markAsReturned(string $condition, ?int $returnedToUserId = null, ?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_RETURNED,
            'condition_on_return' => $condition,
            'returned_date' => today(),
            'returned_to' => $returnedToUserId,
            'return_notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Mark as damaged
     */
    public function markAsDamaged(?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_DAMAGED,
            'condition_on_return' => self::CONDITION_DAMAGED,
            'notes' => $this->notes . ($notes ? "\n" . $notes : ''),
        ]);

        return $this;
    }

    /**
     * Mark as lost
     */
    public function markAsLost(?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_LOST,
            'condition_on_return' => self::CONDITION_LOST,
            'notes' => $this->notes . ($notes ? "\n" . $notes : ''),
        ]);

        return $this;
    }
}


