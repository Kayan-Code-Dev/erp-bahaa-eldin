<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;
use Carbon\Carbon;

class Order extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    protected $fillable = [
        'client_id',
        'employee_id',
        'inventory_id',
        'total_price',
        'status',
        'paid',
        'remaining',
        'visit_datetime',
        'delivery_date',
        'days_of_rent',
        'occasion_datetime',
        'order_notes',
        'discount_type',
        'discount_value',
        // Tailoring stage fields
        'tailoring_stage',
        'tailoring_stage_changed_at',
        'expected_completion_date',
        'actual_completion_date',
        'assigned_factory_id',
        'sent_to_factory_date',
        'received_from_factory_date',
        'factory_notes',
        'priority',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'employee_id' => 'integer',
        'days_of_rent' => 'integer',
        'occasion_datetime' => 'datetime',
        'tailoring_stage_changed_at' => 'datetime',
        'expected_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'sent_to_factory_date' => 'date',
        'received_from_factory_date' => 'date',
        'tailoring_stage' => 'string', // Enum values cast as string for compatibility
        'priority' => 'string', // Enum values cast as string for compatibility
    ];

    /**
     * Tailoring stage constants
     */
    public const STAGE_RECEIVED = 'received';
    public const STAGE_SENT_TO_FACTORY = 'sent_to_factory';
    public const STAGE_IN_PRODUCTION = 'in_production';
    public const STAGE_READY_FROM_FACTORY = 'ready_from_factory';
    public const STAGE_READY_FOR_CUSTOMER = 'ready_for_customer';
    public const STAGE_DELIVERED = 'delivered';

    /**
     * Priority levels
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * Get all tailoring stages with labels
     */
    public static function getTailoringStages(): array
    {
        return [
            self::STAGE_RECEIVED => 'Order Received',
            self::STAGE_SENT_TO_FACTORY => 'Sent to Factory',
            self::STAGE_IN_PRODUCTION => 'In Production',
            self::STAGE_READY_FROM_FACTORY => 'Ready from Factory',
            self::STAGE_READY_FOR_CUSTOMER => 'Ready for Customer',
            self::STAGE_DELIVERED => 'Delivered',
        ];
    }

    /**
     * Get priority levels with labels
     */
    public static function getPriorityLevels(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }

    /**
     * Get allowed next stages from current stage
     */
    public static function getAllowedNextStages(?string $currentStage): array
    {
        $transitions = [
            null => [self::STAGE_RECEIVED],
            self::STAGE_RECEIVED => [self::STAGE_SENT_TO_FACTORY],
            self::STAGE_SENT_TO_FACTORY => [self::STAGE_IN_PRODUCTION],
            self::STAGE_IN_PRODUCTION => [self::STAGE_READY_FROM_FACTORY],
            self::STAGE_READY_FROM_FACTORY => [self::STAGE_READY_FOR_CUSTOMER],
            self::STAGE_READY_FOR_CUSTOMER => [self::STAGE_DELIVERED],
            self::STAGE_DELIVERED => [],
        ];

        return $transitions[$currentStage] ?? [];
    }

    /**
     * Boot method to prevent address_id from being set
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (isset($order->attributes['address_id'])) {
                unset($order->attributes['address_id']);
            }
        });

        static::updating(function ($order) {
            if (isset($order->attributes['address_id'])) {
                unset($order->attributes['address_id']);
            }
        });

        static::saving(function ($order) {
            if (isset($order->attributes['address_id'])) {
                unset($order->attributes['address_id']);
            }
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function items()
    {
        return $this->belongsToMany(Cloth::class, 'cloth_order')
                    ->withPivot([
                        'price',
                        'type',
                        'quantity',
                        'paid',      // المبلغ المدفوع
                        'remaining', // المبلغ المتبقي
                        'status',
                        'notes',
                        'discount_type',
                        'discount_value',
                        'returnable',
                        'factory_status',
                        'factory_rejection_reason',
                        'factory_accepted_at',
                        'factory_rejected_at',
                        'factory_expected_delivery_date',
                        'factory_delivered_at',
                        'factory_notes',
                        // Measurements (مقاسات)
                        'sleeve_length',
                        'forearm',
                        'shoulder_width',
                        'cuffs',
                        'waist',
                        'chest_length',
                        'total_length',
                        'hinch',
                        'dress_size',
                    ])
                    ->withTimestamps();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function custodies()
    {
        return $this->hasMany(Custody::class);
    }

    public function returns()
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function rents()
    {
        return $this->hasMany(Rent::class);
    }

    public function history()
    {
        return $this->hasMany(OrderHistory::class);
    }

    /**
     * Get the assigned factory
     */
    public function assignedFactory()
    {
        return $this->belongsTo(Factory::class, 'assigned_factory_id');
    }

    /**
     * Get tailoring stage logs
     */
    public function tailoringStageLogs()
    {
        return $this->hasMany(TailoringStageLog::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get factory evaluations for this order
     */
    public function factoryEvaluations()
    {
        return $this->hasMany(FactoryEvaluation::class);
    }

    // ==================== TAILORING STAGE METHODS ====================

    /**
     * Check if this is a tailoring order
     */
    public function isTailoringOrder(): bool
    {
        return $this->items()->wherePivot('type', 'tailoring')->exists();
    }

    /**
     * Get current stage label
     */
    public function getTailoringStageLabelAttribute(): ?string
    {
        if (!$this->tailoring_stage) {
            return null;
        }

        $stages = self::getTailoringStages();
        return $stages[$this->tailoring_stage] ?? $this->tailoring_stage;
    }

    /**
     * Get priority label
     */
    public function getPriorityLabelAttribute(): string
    {
        $priorities = self::getPriorityLevels();
        return $priorities[$this->priority] ?? 'Normal';
    }

    /**
     * Check if stage transition is allowed
     */
    public function canTransitionTo(string $newStage): bool
    {
        $allowedStages = self::getAllowedNextStages($this->tailoring_stage);
        return in_array($newStage, $allowedStages);
    }

    /**
     * Update tailoring stage with logging
     */
    public function updateTailoringStage(string $newStage, User $user, ?string $notes = null, ?array $metadata = null): bool
    {
        $oldStage = $this->tailoring_stage;

        // Create stage log
        TailoringStageLog::create([
            'order_id' => $this->id,
            'from_stage' => $oldStage,
            'to_stage' => $newStage,
            'changed_by' => $user->id,
            'notes' => $notes,
            'metadata' => $metadata,
        ]);

        // Update order
        $this->tailoring_stage = $newStage;
        $this->tailoring_stage_changed_at = now();

        // Handle stage-specific logic
        if ($newStage === self::STAGE_SENT_TO_FACTORY && !$this->sent_to_factory_date) {
            $this->sent_to_factory_date = today();
        }

        if ($newStage === self::STAGE_READY_FROM_FACTORY && !$this->received_from_factory_date) {
            $this->received_from_factory_date = today();
            $this->actual_completion_date = today();
        }

        return $this->save();
    }

    /**
     * Assign factory to order
     */
    public function assignFactory(Factory $factory, ?int $expectedDays = null): bool
    {
        $this->assigned_factory_id = $factory->id;

        if ($expectedDays) {
            $this->expected_completion_date = today()->addDays($expectedDays);
        }

        return $this->save();
    }

    /**
     * Check if order is overdue from factory
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->expected_completion_date) {
            return false;
        }

        if ($this->actual_completion_date) {
            return false;
        }

        return $this->expected_completion_date->isPast();
    }

    /**
     * Get days until expected completion (negative if overdue)
     */
    public function getDaysUntilExpectedAttribute(): ?int
    {
        if (!$this->expected_completion_date) {
            return null;
        }

        if ($this->actual_completion_date) {
            return 0;
        }

        return today()->diffInDays($this->expected_completion_date, false);
    }

    /**
     * Get actual completion days (if completed)
     */
    public function getActualCompletionDaysAttribute(): ?int
    {
        if (!$this->sent_to_factory_date || !$this->actual_completion_date) {
            return null;
        }

        return $this->sent_to_factory_date->diffInDays($this->actual_completion_date);
    }

    // ==================== SCOPES ====================

    /**
     * Filter by tailoring stage
     */
    public function scopeInTailoringStage($query, $stage)
    {
        return $query->where('tailoring_stage', $stage);
    }

    /**
     * Filter by assigned factory
     */
    public function scopeForFactory($query, $factoryId)
    {
        return $query->where('assigned_factory_id', $factoryId);
    }

    /**
     * Filter tailoring orders
     */
    public function scopeTailoringOrders($query)
    {
        return $query->whereHas('items', function ($q) {
            $q->where('cloth_order.type', 'tailoring');
        });
    }

    /**
     * Filter overdue orders
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotNull('expected_completion_date')
            ->whereNull('actual_completion_date')
            ->where('expected_completion_date', '<', today());
    }

    /**
     * Filter by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Filter urgent and high priority
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Filter orders in production (at factory)
     */
    public function scopeInProduction($query)
    {
        return $query->whereIn('tailoring_stage', [
            self::STAGE_SENT_TO_FACTORY,
            self::STAGE_IN_PRODUCTION,
        ]);
    }

    /**
     * Filter orders pending pickup from factory
     */
    public function scopePendingPickup($query)
    {
        return $query->where('tailoring_stage', self::STAGE_READY_FROM_FACTORY);
    }

    /**
     * Filter orders ready for customer
     */
    public function scopeReadyForCustomer($query)
    {
        return $query->where('tailoring_stage', self::STAGE_READY_FOR_CUSTOMER);
    }

    /**
     * Filter orders visible to a factory user
     * Only shows orders assigned to the factory the user belongs to
     */
    public function scopeForFactoryUser($query, $userId)
    {
        return $query->whereHas('assignedFactory.factoryUsers', function ($q) use ($userId) {
            $q->where('user_id', $userId)->where('is_active', true);
        });
    }

    /**
     * Get tailoring items for factory (items with type='tailoring')
     */
    public function getFactoryItems()
    {
        return $this->items()->wherePivot('type', 'tailoring')->get();
    }

    // ==================== CALCULATIONS ====================

    /**
     * Calculate total price from items with discounts
     */
    public function calculateTotalPrice()
    {
        // Calculate item prices with item-level discounts
        $subtotal = $this->items()->get()->sum(function ($item) {
            $price = $item->pivot->price;
            $discountType = $item->pivot->discount_type ?? 'none';
            $discountValue = $item->pivot->discount_value ?? 0;

            if ($discountType === 'percentage') {
                return $price * (1 - $discountValue / 100);
            } elseif ($discountType === 'fixed') {
                return max(0, $price - $discountValue);
            }
            return $price;
        });

        // Apply order-level discount
        $discountType = $this->discount_type ?? 'none';
        $discountValue = $this->discount_value ?? 0;

        if ($discountType === 'percentage') {
            return $subtotal * (1 - $discountValue / 100);
        } elseif ($discountType === 'fixed') {
            return max(0, $subtotal - $discountValue);
        }

        return $subtotal;
    }
}
