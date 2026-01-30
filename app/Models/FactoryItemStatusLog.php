<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * FactoryItemStatusLog Model
 * 
 * Immutable log of factory status transitions for tailoring items (cloth_order pivot).
 * Each log entry records a status change for a tailoring item at the factory.
 */
class FactoryItemStatusLog extends Model
{
    use HasFactory;

    /**
     * Disable updated_at since logs are immutable
     */
    const UPDATED_AT = null;

    protected $table = 'factory_item_status_logs';

    protected $fillable = [
        'cloth_order_id',
        'from_status',
        'to_status',
        'changed_by',
        'rejection_reason',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user who made the change
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ==================== SCOPES ====================

    /**
     * Filter by cloth_order_id
     */
    public function scopeForItem($query, $clothOrderId)
    {
        return $query->where('cloth_order_id', $clothOrderId);
    }

    /**
     * Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('to_status', $status);
    }

    /**
     * Order by created_at descending
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get cloth_order pivot record data (helper method)
     */
    public function getClothOrderPivotData()
    {
        return DB::table('cloth_order')
            ->where('id', $this->cloth_order_id)
            ->first();
    }
}
