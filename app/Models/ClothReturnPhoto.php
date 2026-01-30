<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClothReturnPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'cloth_id',
        'photo_path',
        'photo_type',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function cloth()
    {
        return $this->belongsTo(Cloth::class);
    }
}
