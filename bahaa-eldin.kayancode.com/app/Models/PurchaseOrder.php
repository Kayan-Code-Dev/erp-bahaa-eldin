<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'sub_category_id',
        'quantity',
        'delivery_date',
        'customizations',
        'notes',
        'source',

    ];

    /**
     * تحويل عمود customizations تلقائيًا لمصفوفة PHP
     */
    protected $casts = [
        'customizations' => 'array',
    ];

    /**
     * العلاقة مع الطلب الأساسي
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * العلاقة مع الفئة الفرعية
     */
    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'sub_category_id', 'id');
    }
}
