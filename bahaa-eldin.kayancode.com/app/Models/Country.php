<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    protected $fillable = [
        'name',
        'code',
        'currency_name',
        'currency_symbol',
        'image',
        'description',
        'active',
    ];


    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function cities()
    {
        return $this->hasMany(City::class, 'country_id', 'id');
    }
}
