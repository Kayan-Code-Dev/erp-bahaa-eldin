<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class Branch extends Authenticatable
{
    use HasFactory, SoftDeletes, HasApiTokens, HasRoles;

    protected $guard_name = 'branch-api';
    protected $fillable = [
        'uuid',
        'branch_manager_id',
        'name',
        'email',
        'phone',
        'password',
        'location',
        'latitude',
        'longitude',
        'blocked',
        'status',
        'fcm_token',
        'ip_address',
        'last_login',
        'last_logout',
        'otp_code',
        'code_expires_at',
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


    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * العلاقة مع مدير الفرع
     */
    public function manager()
    {
        return $this->belongsTo(BranchManager::class, 'branch_manager_id', 'id');
    }

    public function department()
    {
        return $this->hasMany(Department::class, 'branch_id', 'id');
    }


    public function job()
    {
        return $this->hasMany(BranchJob::class, 'branch_id', 'id');
    }

    public function employee()
    {
        return $this->hasMany(Employee::class, 'branch_id', 'id');
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'branch_id', 'id');
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class, 'branch_id', 'id');
    }


    public function fromBranchInventoryTransfer()
    {
        return $this->hasMany(InventoryTransfer::class, 'from_branch_id', 'id');
    }


    public function toBranchInventoryTransfer()
    {
        return $this->hasMany(InventoryTransfer::class, 'to_branch_id', 'id');
    }

    public function workShop()
    {
        //
        return $this->hasMany(workShop::class, 'branch_id', 'id');
    }
}
