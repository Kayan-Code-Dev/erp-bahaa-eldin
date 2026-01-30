<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustodyPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'custody_id',
        'photo_path',
        'photo_type',
    ];

    public function custody()
    {
        return $this->belongsTo(Custody::class);
    }
}
