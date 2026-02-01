<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'code'];

    public function orders()
    {
        return $this->hasMany(SupplierOrder::class);
    }
}

