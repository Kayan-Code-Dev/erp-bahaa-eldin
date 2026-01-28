<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'active',
    ];


    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }


    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'subCategories_id', 'id');
    }

    public function inventoryTransfer()
    {
        return $this->hasMany(InventoryTransfer::class, 'subCategories_id', 'id');
    }

    public function rentOrders()
    {
        return $this->hasMany(RentOrder::class, 'sub_category_id', 'id');
    }

    public function purchaseOrder()
    {
        return $this->hasMany(PurchaseOrder::class, 'sub_category_id', 'id');
    }
}
