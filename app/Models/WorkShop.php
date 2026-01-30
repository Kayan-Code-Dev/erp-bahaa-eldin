<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WorkShop extends Model
{
    use HasFactory;

    protected $table = 'work_shops'; // لأنك سميته work_shops في المايغريشن

    protected $fillable = [
        'uuid',
        'name',
        'location',
        'branch_id',
    ];

    // توليد UUID تلقائي عند الإنشاء
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
}
