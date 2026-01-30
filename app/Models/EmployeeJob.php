<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeJob extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'salary',
        'hire_date',
        'commission',
        'contract_end_date',
        'fingerprint_device_number',
        'work_from',
        'work_to',
    ];

    /**
     * علاقة الوظيفة بالموظف
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
