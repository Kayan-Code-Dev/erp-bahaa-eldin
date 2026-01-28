<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'neighborhood',
        'province',
        'address',
        'home_phone_1',
        'home_phone_2',
    ];

    /**
     * علاقة الموظف
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }
}
