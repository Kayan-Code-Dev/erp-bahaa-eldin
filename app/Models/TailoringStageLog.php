<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TailoringStageLog Model
 * 
 * Immutable log of tailoring stage transitions for audit purposes.
 * Each log entry records a stage change for a tailoring order.
 */
class TailoringStageLog extends Model
{
    use HasFactory;

    /**
     * Disable updated_at since logs are immutable
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'from_stage',
        'to_stage',
        'changed_by',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the order this log belongs to
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who made the change
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ==================== ACCESSORS ====================

    /**
     * Get human-readable stage transition description
     */
    public function getTransitionDescriptionAttribute(): string
    {
        $stages = Order::getTailoringStages();
        $fromLabel = $this->from_stage ? ($stages[$this->from_stage] ?? $this->from_stage) : 'None';
        $toLabel = $stages[$this->to_stage] ?? $this->to_stage;
        
        return "{$fromLabel} → {$toLabel}";
    }

    // ==================== SCOPES ====================

    /**
     * Filter by order
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('changed_by', $userId);
    }

    /**
     * Filter by stage transition
     */
    public function scopeToStage($query, $stage)
    {
        return $query->where('to_stage', $stage);
    }

    /**
     * Filter by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get recent logs
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}





