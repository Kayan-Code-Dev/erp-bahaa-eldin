<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

class Factory extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\FactoryModelFactory::new();
    }

    protected $fillable = [
        'factory_code',
        'name',
        'address_id',
        // Statistics fields
        'current_orders_count',
        'total_orders_completed',
        'average_completion_days',
        'quality_rating',
        'on_time_rate',
        'total_evaluations',
        'factory_status',
        'max_capacity',
        // Contact fields
        'contact_person',
        'contact_phone',
        'contact_email',
        'notes',
        'stats_calculated_at',
    ];

    protected $casts = [
        'current_orders_count' => 'integer',
        'total_orders_completed' => 'integer',
        'average_completion_days' => 'float',
        'quality_rating' => 'float',
        'on_time_rate' => 'float',
        'total_evaluations' => 'integer',
        'max_capacity' => 'integer',
        'stats_calculated_at' => 'datetime',
        'factory_status' => 'string', // Enum values cast as string for compatibility
    ];

    /**
     * Factory status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    /**
     * Get factory statuses with labels
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_CLOSED => 'Closed',
        ];
    }

    // ==================== RELATIONSHIPS ====================

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function inventory()
    {
        return $this->morphOne(Inventory::class, 'inventoriable');
    }

    /**
     * Get orders assigned to this factory
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'assigned_factory_id');
    }

    /**
     * Get evaluations for this factory
     */
    public function evaluations()
    {
        return $this->hasMany(FactoryEvaluation::class);
    }

    /**
     * Get factory users (users assigned to this factory)
     */
    public function factoryUsers()
    {
        return $this->hasMany(FactoryUser::class);
    }

    /**
     * Get users assigned to this factory
     */
    public function users()
    {
        return $this->hasManyThrough(User::class, FactoryUser::class, 'factory_id', 'id', 'id', 'user_id');
    }

    /**
     * Scope to eager load users
     */
    public function scopeWithUsers($query)
    {
        return $query->with('factoryUsers.user');
    }

    public function clothes()
    {
        return Cloth::whereHas('inventories', function($query) {
            $query->where('inventoriable_type', 'factory')
                  ->where('inventoriable_id', $this->id);
        });
    }

    // ==================== ACCESSORS ====================

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        $statuses = self::getStatuses();
        return $statuses[$this->factory_status] ?? 'Unknown';
    }

    /**
     * Get quality rating label (1-5 stars)
     */
    public function getQualityStarsAttribute(): string
    {
        $rating = round($this->quality_rating);
        return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    }

    /**
     * Check if factory is at capacity
     */
    public function getIsAtCapacityAttribute(): bool
    {
        if ($this->max_capacity === null) {
            return false;
        }
        return $this->current_orders_count >= $this->max_capacity;
    }

    /**
     * Get available capacity
     */
    public function getAvailableCapacityAttribute(): ?int
    {
        if ($this->max_capacity === null) {
            return null;
        }
        return max(0, $this->max_capacity - $this->current_orders_count);
    }

    /**
     * Check if factory is active
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->factory_status === self::STATUS_ACTIVE;
    }

    // ==================== SCOPES ====================

    /**
     * Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('factory_status', $status);
    }

    /**
     * Filter active factories
     */
    public function scopeActive($query)
    {
        return $query->where('factory_status', self::STATUS_ACTIVE);
    }

    /**
     * Filter factories with available capacity
     */
    public function scopeWithCapacity($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('max_capacity')
              ->orWhereRaw('current_orders_count < max_capacity');
        });
    }

    /**
     * Filter by minimum quality rating
     */
    public function scopeMinQuality($query, $minRating)
    {
        return $query->where('quality_rating', '>=', $minRating);
    }

    /**
     * Filter by minimum on-time rate
     */
    public function scopeMinOnTimeRate($query, $minRate)
    {
        return $query->where('on_time_rate', '>=', $minRate);
    }

    /**
     * Order by performance (quality * on_time_rate)
     */
    public function scopeOrderByPerformance($query, $direction = 'desc')
    {
        return $query->orderByRaw('(quality_rating * on_time_rate) ' . $direction);
    }

    /**
     * Order by quality rating
     */
    public function scopeOrderByQuality($query, $direction = 'desc')
    {
        return $query->orderBy('quality_rating', $direction);
    }

    /**
     * Order by current workload (ascending = least busy first)
     */
    public function scopeOrderByWorkload($query, $direction = 'asc')
    {
        return $query->orderBy('current_orders_count', $direction);
    }

    // ==================== STATISTICS METHODS ====================

    /**
     * Recalculate statistics from evaluations and orders
     */
    public function recalculateStatistics(): void
    {
        // Current orders count
        $this->current_orders_count = $this->orders()
            ->whereIn('tailoring_stage', [
                Order::STAGE_SENT_TO_FACTORY,
                Order::STAGE_IN_PRODUCTION,
            ])
            ->count();

        // Total completed orders
        $completedOrders = $this->orders()
            ->whereNotNull('actual_completion_date')
            ->get();
        
        $this->total_orders_completed = $completedOrders->count();

        // Average completion days
        $completionDays = $completedOrders
            ->filter(fn($o) => $o->actual_completion_days !== null)
            ->pluck('actual_completion_days');
        
        $this->average_completion_days = $completionDays->isNotEmpty() 
            ? round($completionDays->avg(), 2) 
            : 0;

        // From evaluations
        $evaluations = $this->evaluations;
        $this->total_evaluations = $evaluations->count();

        if ($this->total_evaluations > 0) {
            $this->quality_rating = round($evaluations->avg('quality_rating'), 2);
            $this->on_time_rate = round($evaluations->where('on_time', true)->count() / $this->total_evaluations * 100, 2);
        } else {
            $this->quality_rating = 0;
            $this->on_time_rate = 0;
        }

        $this->stats_calculated_at = now();
        $this->save();
    }

    /**
     * Increment current orders count
     */
    public function incrementOrdersCount(): void
    {
        $this->increment('current_orders_count');
    }

    /**
     * Decrement current orders count
     */
    public function decrementOrdersCount(): void
    {
        if ($this->current_orders_count > 0) {
            $this->decrement('current_orders_count');
        }
    }

    /**
     * Get performance score (weighted average of quality and on-time)
     */
    public function getPerformanceScoreAttribute(): float
    {
        // 60% weight on quality, 40% on timeliness
        $qualityWeight = 0.6;
        $timeWeight = 0.4;
        
        // Normalize on_time_rate to 5-point scale
        $normalizedOnTime = ($this->on_time_rate / 100) * 5;
        
        return round(($this->quality_rating * $qualityWeight) + ($normalizedOnTime * $timeWeight), 2);
    }
}
