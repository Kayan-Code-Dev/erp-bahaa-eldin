<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use Notifiable, SoftDeletes, HasApiTokens, HasRoles;

    protected $guard_name = 'admin-api';
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'id_number',
        'image',
        'blocked',
        'last_login',
        'last_logout',
        'fcm_token',
        'status',
        'city_id',
        'ip_address',
        'otp_code',
        'code_expires_at',
    ];

    protected $hidden = [
        'password',
        'fcm_token',
        'otp_code',
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'last_login' => 'datetime',
        'last_logout' => 'datetime',
        'code_expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    protected $dates = ['deleted_at'];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return asset('images/default.png');
    }

    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }
}
