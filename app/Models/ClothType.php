<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class ClothType extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];

    public function clothes()
    {
        return $this->hasMany(Cloth::class);
    }

    public function subcategories()
    {
        return $this->belongsToMany(Subcategory::class, 'cloth_type_subcategory')
                    ->withTimestamps();
    }
}

