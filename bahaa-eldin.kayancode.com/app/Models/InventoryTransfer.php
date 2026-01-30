<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InventoryTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'from_branch_id',
        'to_branch_id',
        'subCategories_id',
        'quantity',
        'notes',
        'status',
        'requested_by_id',
        'requested_by_type',
        'approved_by_id',
        'approved_by_type',
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
     * المخزون الذي سيتم نقله
     */
    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'inventory_id', 'id');
    }

    /**
     * الفرع الأصلي
     */
    public function fromBranch()
    {
        return $this->belongsTo(Branch::class, 'from_branch_id', 'id');
    }

    /**
     * الفرع المستلم
     */
    public function toBranch()
    {
        return $this->belongsTo(Branch::class, 'to_branch_id', 'id');
    }

    /**
     * الفئة الفرعية للمخزون (اختياري)
     */
    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'subCategories_id', 'id');
    }

    /**
     * الكيان الذي طلب النقل (موظف أو فرع)
     */
    public function requester()
    {
        return $this->morphTo(null, 'requested_by_type', 'requested_by_id');
    }

    /**
     * الكيان الذي وافق على النقل (موظف أو مدير فرع)
     */
    public function approver()
    {
        return $this->morphTo(null, 'approved_by_type', 'approved_by_id');
    }
}
