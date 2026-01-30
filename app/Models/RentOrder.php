<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'sub_category_id',
        'model_name',
        'rental_duration',
        'measurements',
        'event_date',
        'delivery_date',
        'source',
        'notes',
    ];

    /**
     * تحويل الحقول تلقائياً للأنواع المناسبة
     */
    protected $casts = [
        'measurements' => 'array',
        'event_date' => 'datetime',
        'delivery_date' => 'date',
    ];

    /**
     * العلاقة مع جدول الطلب الأساسي
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * العلاقة مع الفئة
     */
    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'sub_category_id', 'id');
    }
}
