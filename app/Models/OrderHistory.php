<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;

class OrderHistory extends Model
{
    use HasFactory, SerializesDates;

    protected $table = 'order_history';

    protected $fillable = [
        'order_id',
        'field_changed',
        'old_value',
        'new_value',
        'change_type',
        'description',
        'changed_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
