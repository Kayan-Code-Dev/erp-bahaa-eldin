<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Cloth extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'clothes';

    protected $fillable = [
        'code',
        'name',
        'description',
        'cloth_type_id',
        'breast_size',
        'waist_size',
        'sleeve_size',
        'notes',
        'status',
    ];

    public function clothType()
    {
        return $this->belongsTo(ClothType::class);
    }

    public function inventories()
    {
        return $this->belongsToMany(Inventory::class, 'cloth_inventory')
                    ->withTimestamps();
    }

    public function history()
    {
        return $this->hasMany(ClothHistory::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'cloth_order')
                    ->withPivot([
                        'price',
                        'type',
                        'quantity',
                        'paid',      // المبلغ المدفوع
                        'remaining', // المبلغ المتبقي
                        'status',
                        'notes',
                        'discount_type',
                        'discount_value',
                        'returnable',
                        'factory_status',
                        'factory_rejection_reason',
                        'factory_accepted_at',
                        'factory_rejected_at',
                        'factory_expected_delivery_date',
                        'factory_delivered_at',
                        'factory_notes',
                        // Measurements (مقاسات)
                        'sleeve_length',
                        'forearm',
                        'shoulder_width',
                        'cuffs',
                        'waist',
                        'chest_length',
                        'total_length',
                        'hinch',
                        'dress_size',
                    ])
                    ->withTimestamps();
    }

    public function transferItems()
    {
        return $this->belongsToMany(Transfer::class, 'transfer_items')
                    ->withTimestamps();
    }

    public function rents()
    {
        return $this->hasMany(Rent::class);
    }

    public function supplierOrders()
    {
        return $this->belongsToMany(SupplierOrder::class, 'supplier_order_clothes')
                    ->withPivot(['price', 'notes'])
                    ->withTimestamps();
    }
}
