<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class EmployeeLogin extends Authenticatable
{
    use HasFactory, SoftDeletes, HasApiTokens, HasRoles;

    protected $fillable = [
        'employee_id',
        'username',
        'email',
        'mobile',
        'password',
        'blocked',
        'fcm_token',
        'ip_address',
        'last_login',
        'last_logout',
        'otp_code',
        'code_expires_at',
        'latitude',
        'longitude',
    ];
    protected $hidden = [
        'password',
        'otp_code',
        'fcm_token',
    ];
    protected $casts = [
        'blocked' => 'boolean',
        'last_login' => 'datetime',
        'last_logout' => 'datetime',
        'code_expires_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
