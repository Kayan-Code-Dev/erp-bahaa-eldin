<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Address extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['street', 'building', 'notes', 'city_id'];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
