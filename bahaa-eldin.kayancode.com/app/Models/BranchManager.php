<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;

class BranchManager extends Authenticatable
{
    use SoftDeletes, HasRoles, HasApiTokens;

    protected $guard_name = 'branchManager-api';
    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'phone',
        'branch_number',
        'branch_name',
        'password',
        'id_number',
        'image',
        'blocked',
        'last_login',
        'last_logout',
        'location',
        'latitude',
        'longitude',
        'status',
        'city_id',
        'fcm_token',
        'ip_address',
        'otp_code',
        'code_expires_at',
    ];

    protected $hidden = [
        'image',
        'password',
        'otp_code',
        'fcm_token',
        'remember_token',
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'code_expires_at' => 'datetime',
        'last_login' => 'datetime',
        'last_logout' => 'datetime',
    ];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return asset('images/default.png');
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->branch_number)) {
                $model->branch_number = self::generateUniqueBranchNumber();
            }
        });
    }

    public static function generateUniqueBranchNumber()
    {
        do {
            $number = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
            $exists = BranchManager::where('branch_number', $number)->exists();
        } while ($exists);
        return $number;
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }


    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function manger()
    {
        //
        return $this->hasMany(Branch::class, 'branch_manager_id', 'id');
    }
}
