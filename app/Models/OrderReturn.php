<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

class OrderReturn extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    protected $fillable = [
        'order_id',
        'step',
        'returned_cloth_id',
        'return_entity_type',
        'return_entity_id',
        'cloth_status_on_return',
        'fees_amount',
        'fees_paid',
        'fees_payment_date',
        'return_date',
        'completed_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'fees_payment_date' => 'datetime',
        'return_date' => 'datetime',
        'completed_at' => 'datetime',
        'fees_paid' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function cloth()
    {
        return $this->belongsTo(Cloth::class, 'returned_cloth_id');
    }

    public function custodyReturns()
    {
        return $this->hasMany(CustodyReturn::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
