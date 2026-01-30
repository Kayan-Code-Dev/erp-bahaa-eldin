<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'phone_primary',
        'phone_secondary',
        'address',
        'visit_date',
        'event_date',
        'source',
        'notes',
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
     * العلاقات
     */

    // // كل عميل عنده أكثر من طلب
    public function orders()
    {
        return $this->hasMany(Order::class, 'client_id', 'id');
    }

    // /**
    //  * سكوب بسيط للبحث عن عميل بالاسم أو الهاتف
    //  */
    // public function scopeSearch($query, $term)
    // {
    //     return $query->where(function ($q) use ($term) {
    //         $q->where('name', 'like', "%{$term}%")
    //             ->orWhere('phone_primary', 'like', "%{$term}%")
    //             ->orWhere('phone_secondary', 'like', "%{$term}%");
    //     });
    // }
}
