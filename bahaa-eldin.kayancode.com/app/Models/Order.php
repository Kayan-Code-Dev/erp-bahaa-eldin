<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'order_number',
        'client_id',
        'creator_id',
        'creator_type',
        'order_type',
        'status',
        'delivery_date',
        'notes',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * العلاقات
     */

    // كل طلب تابع لعميل
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'id');
    }

    // كل طلب تابع لفرع
    public function creator()
    {
        return $this->morphTo();
    }


    // // علاقة الطلب التفصيل
    public function tailoringOrder()
    {
        return $this->hasOne(TailoringOrder::class, 'order_id', 'id');
    }

    // علاقة الطلب التأجير
    public function rentOrder()
    {
        return $this->hasOne(RentOrder::class, 'order_id', 'id');
    }

    // // علاقة الطلب الشراء
    public function purchaseOrder()
    {
        return $this->hasOne(PurchaseOrder::class, 'order_id', 'id');
    }


    public function workshopInspection()
    {
        return $this->hasOne(WorkshopInspection::class, 'order_id', 'id');
    }
}
