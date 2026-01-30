<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'subCategories_id',
        'name',
        'code',
        'price',
        'type',
        'notes',
        'quantity',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'decimal:2',
    ];


    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class, 'subCategories_id', 'id');
    }

    public function inventoryTransfer()
    {
        return $this->hasMany(InventoryTransfer::class, 'inventory_id', 'id');
    }
}
