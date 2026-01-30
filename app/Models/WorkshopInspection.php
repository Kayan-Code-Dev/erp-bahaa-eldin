<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkshopInspection extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'order_id',
        'inspection_employee_id',
        'delivery_employee_id',
        'status',
        'invoice_number',
    ];
    // توليد UUID تلقائي عند الإنشاء
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // العلاقة مع الطلب
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    // العلاقة مع موظف الفحص
    public function inspectionEmployee()
    {
        return $this->belongsTo(Employee::class, 'inspection_employee_id', 'id');
    }

    // العلاقة مع موظف التسليم
    public function deliveryEmployee()
    {
        return $this->belongsTo(Employee::class, 'delivery_employee_id', 'id');
    }

    public function workshopReceipt()
    {
        return $this->hasMany(WorkshopReceipt::class, 'workshop_inspection_id', 'id');
    }
}
