<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Workshop extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['workshop_code', 'name', 'address_id', 'branch_id'];

    /**
     * Workshop cloth status constants
     */
    const CLOTH_STATUSES = [
        'received' => 'Received',
        'processing' => 'Processing',
        'ready_for_delivery' => 'Ready for Delivery',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function inventory()
    {
        return $this->morphOne(Inventory::class, 'inventoriable');
    }

    /**
     * Get the branch that this workshop belongs to (1:1 relationship)
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get all workshop logs for this workshop
     */
    public function logs()
    {
        return $this->hasMany(WorkshopLog::class);
    }

    /**
     * Get clothes currently in this workshop's inventory
     */
    public function clothes()
    {
        return Cloth::whereHas('inventories', function($query) {
            $query->where('inventoriable_type', 'workshop')
                  ->where('inventoriable_id', $this->id);
        });
    }

    /**
     * Get clothes currently in workshop with their latest status from workshop logs
     */
    public function clothesWithStatus()
    {
        return $this->clothes()->get()->map(function ($cloth) {
            $latestLog = $this->logs()
                ->where('cloth_id', $cloth->id)
                ->whereIn('action', ['received', 'status_changed'])
                ->orderBy('created_at', 'desc')
                ->first();
            
            $cloth->workshop_status = $latestLog ? $latestLog->cloth_status : 'received';
            $cloth->workshop_notes = $latestLog ? $latestLog->notes : null;
            $cloth->received_at = $latestLog ? $latestLog->received_at : null;
            
            return $cloth;
        });
    }

    /**
     * Get pending incoming transfers to this workshop
     */
    public function pendingIncomingTransfers()
    {
        return Transfer::where('to_entity_type', 'workshop')
            ->where('to_entity_id', $this->id)
            ->whereIn('status', ['pending', 'partially_pending'])
            ->with(['fromEntity', 'items.cloth'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get pending outgoing transfers from this workshop
     */
    public function pendingOutgoingTransfers()
    {
        return Transfer::where('from_entity_type', 'workshop')
            ->where('from_entity_id', $this->id)
            ->whereIn('status', ['pending', 'partially_pending'])
            ->with(['toEntity', 'items.cloth'])
            ->orderBy('created_at', 'desc');
    }
}
