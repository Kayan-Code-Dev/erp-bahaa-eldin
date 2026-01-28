<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'full_name',
        'phone',
        'national_id',
        'branch_id',
        'department_id',
        'city_id',
        'branch_job_id',
        'status',
    ];

    protected static function booted()
    {
        static::deleting(function ($employee) {
            if ($employee->isForceDeleting()) {
                $employee->login()->forceDelete();
                $employee->job()->forceDelete();
                $employee->contact()->forceDelete();
                $employee->educations()->forceDelete();
            } else {
                $employee->login()->delete();
                $employee->job()->delete();
                $employee->contact()->delete();
                $employee->educations()->delete();
            }
        });
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }


    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function branchJob()
    {
        return $this->belongsTo(BranchJob::class, 'branch_job_id', 'id');
    }


    public function login()
    {
        return $this->hasOne(EmployeeLogin::class, 'employee_id', 'id');
    }

    public function job()
    {
        return $this->hasOne(EmployeeJob::class, 'employee_id', 'id');
    }

    public function contact()
    {
        return $this->hasOne(EmployeeContact::class, 'employee_id', 'id');
    }

    public function educations()
    {
        return $this->hasMany(EmployeeEducation::class, 'employee_id', 'id');
    }
}
