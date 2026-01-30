<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;

class TransferAction extends Model
{
    use HasFactory, SerializesDates;

    protected $fillable = [
        'transfer_id',
        'user_id',
        'action',
        'action_date',
        'notes',
    ];

    protected $casts = [
        'action_date' => 'datetime',
    ];

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}









