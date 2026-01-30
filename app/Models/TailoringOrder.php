<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TailoringOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'visit_date',
        'event_date',
        'model_name',
        'fabric_preference',
        'measurements',
        'delivery_date',
        'quantity',
        'notes',
        'source',

    ];

    /**
     * تحويل الحقول تلقائياً للأنواع المناسبة
     */
    protected $casts = [
        'measurements' => 'array',
        'visit_date' => 'datetime',
        'event_date' => 'datetime',
        'delivery_date' => 'date',
    ];

    /**
     * العلاقة مع الطلب الرئيسي
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function productionOrder()
    {
        return $this->hasOne(ProductionOrder::class, 'tailoring_order_id', 'id');
    }
}
