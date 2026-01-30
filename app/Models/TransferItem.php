<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_id',
        'cloth_id',
        'status',
    ];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function cloth()
    {
        return $this->belongsTo(Cloth::class);
    }
}


































