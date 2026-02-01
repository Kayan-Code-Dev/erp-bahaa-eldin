<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Category extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'description'];

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    public function subcategoryRelations()
    {
        return $this->belongsToMany(Subcategory::class, 'category_subcategory')
                    ->withTimestamps();
    }

    public function supplierOrders()
    {
        return $this->hasMany(SupplierOrder::class);
    }
}
