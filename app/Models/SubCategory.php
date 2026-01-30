<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Subcategory extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['category_id', 'name', 'description'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function clothes()
    {
        return $this->belongsToMany(Cloth::class, 'cloth_subcategory')
                    ->withTimestamps();
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_subcategory')
                    ->withTimestamps();
    }
}
