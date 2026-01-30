<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

/**
 * FactoryEvaluation Model
 * 
 * Records performance evaluations for factories after order completion.
 * Used to track quality, timeliness, and overall factory performance.
 */
class FactoryEvaluation extends Model
{
    use HasFactory, SerializesDates, LogsActivity;

    protected $fillable = [
        'factory_id',
        'order_id',
        'quality_rating',
        'completion_days',
        'expected_days',
        'on_time',
        'craftsmanship_rating',
        'communication_rating',
        'packaging_rating',
        'notes',
        'issues_found',
        'positive_feedback',
        'evaluated_by',
        'evaluated_at',
    ];

    protected $casts = [
        'quality_rating' => 'integer',
        'completion_days' => 'integer',
        'expected_days' => 'integer',
        'on_time' => 'boolean',
        'craftsmanship_rating' => 'integer',
        'communication_rating' => 'integer',
        'packaging_rating' => 'integer',
        'evaluated_at' => 'datetime',
    ];

    /**
     * Rating labels
     */
    public const RATING_LABELS = [
        1 => 'Poor',
        2 => 'Below Average',
        3 => 'Average',
        4 => 'Good',
        5 => 'Excellent',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the factory being evaluated
     */
    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    /**
     * Get the order this evaluation is for
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who created the evaluation
     */
    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get quality rating label
     */
    public function getQualityLabelAttribute(): string
    {
        return self::RATING_LABELS[$this->quality_rating] ?? 'Unknown';
    }

    /**
     * Get average of all sub-ratings
     */
    public function getAverageRatingAttribute(): float
    {
        $ratings = array_filter([
            $this->quality_rating,
            $this->craftsmanship_rating,
            $this->communication_rating,
            $this->packaging_rating,
        ]);

        if (empty($ratings)) {
            return 0;
        }

        return round(array_sum($ratings) / count($ratings), 2);
    }

    /**
     * Get delay in days (negative if early, positive if late)
     */
    public function getDelayDaysAttribute(): ?int
    {
        if ($this->completion_days === null || $this->expected_days === null) {
            return null;
        }

        return $this->completion_days - $this->expected_days;
    }

    /**
     * Check if there were issues
     */
    public function getHasIssuesAttribute(): bool
    {
        return !empty($this->issues_found);
    }

    // ==================== SCOPES ====================

    /**
     * Filter by factory
     */
    public function scopeForFactory($query, $factoryId)
    {
        return $query->where('factory_id', $factoryId);
    }

    /**
     * Filter by order
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Filter by minimum quality rating
     */
    public function scopeMinQuality($query, $minRating)
    {
        return $query->where('quality_rating', '>=', $minRating);
    }

    /**
     * Filter by on-time status
     */
    public function scopeOnTime($query, $onTime = true)
    {
        return $query->where('on_time', $onTime);
    }

    /**
     * Filter late deliveries
     */
    public function scopeLate($query)
    {
        return $query->where('on_time', false);
    }

    /**
     * Filter by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('evaluated_at', [$startDate, $endDate]);
    }

    /**
     * Filter evaluations with issues
     */
    public function scopeWithIssues($query)
    {
        return $query->whereNotNull('issues_found')->where('issues_found', '!=', '');
    }

    /**
     * Get recent evaluations
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('evaluated_at', 'desc')->limit($limit);
    }

    // ==================== STATIC METHODS ====================

    /**
     * Get rating labels
     */
    public static function getRatingLabels(): array
    {
        return self::RATING_LABELS;
    }
}


