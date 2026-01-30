<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'active',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    public function job()
    {
        return $this->hasMany(BranchJob::class, 'department_id', 'id');
    }

    public function employee()
    {
        return $this->hasMany(Employee::class, 'department_id', 'id');
    }
}
