<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    //
    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'active',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    public function subCategories()
    {
        return $this->hasMany(SubCategory::class, 'category_id', 'id');
    }
}
