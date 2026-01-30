<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;

class ClothHistory extends Model
{
    use HasFactory, SerializesDates;

    protected $table = 'cloth_history';

    protected $fillable = [
        'cloth_id',
        'action',
        'entity_type',
        'entity_id',
        'transfer_id',
        'order_id',
        'status',
        'notes',
        'user_id',
    ];

    public function cloth()
    {
        return $this->belongsTo(Cloth::class);
    }

    /**
     * Get the entity (Branch, Workshop, or Factory) that this history record is associated with.
     *
     * Note: This relationship uses enum string values ('branch', 'workshop', 'factory') stored in entity_type.
     * The morphMap is configured globally in Transfer model's boot() method, so this relationship will work.
     * However, currently the controllers manually retrieve entities using the getEntity() helper method.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function entity()
    {
        return $this->morphTo();
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

