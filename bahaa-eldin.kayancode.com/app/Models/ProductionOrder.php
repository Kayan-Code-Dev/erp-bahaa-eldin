<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'tailoring_order_id',
        'production_code',
        'start_date',
        'expected_finish_date',
        'actual_finish_date',
        'status',
        'production_line',
        'produced_quantity',
        'notes',
    ];

    public function tailoringOrder()
    {
        return $this->belongsTo(TailoringOrder::class, 'tailoring_order_id', 'id');
    }
}
