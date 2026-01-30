<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'code',
        'active',
        'country_id',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function admins()
    {
        return $this->hasMany(Admin::class, 'city_id', 'id');
    }

    public function branchManagers()
    {
        return $this->hasMany(BranchManager::class, 'city_id', 'id');
    }

    public function employee()
    {
        return $this->hasMany(Employee::class, 'city_id', 'id');
    }
}
