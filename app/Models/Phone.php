<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Phone extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['client_id', 'phone', 'type'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
