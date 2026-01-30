<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\SerializesDates;
use App\Models\Traits\LogsActivity;

class Custody extends Model
{
    use HasFactory, SoftDeletes, SerializesDates, LogsActivity;

    protected $fillable = [
        'order_id',
        'type',
        'description',
        'value',
        'status',
        'returned_at',
        'return_proof_photo',
        'notes',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function photos()
    {
        return $this->hasMany(CustodyPhoto::class);
    }

    public function returns()
    {
        return $this->hasMany(CustodyReturn::class);
    }
}
