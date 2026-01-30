<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchJob extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'active',
        'department_id',
        'branch_id',
    ];

    /**
     * الوظيفة تنتمي لقسم محدد
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * الوظيفة تنتمي لفرع محدد
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    public function employee()
    {
        return $this->hasMany(Employee::class, 'branch_job_id', 'id');
    }
}
