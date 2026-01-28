<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkshopReceipt extends Model
{
    use HasFactory;

    protected $table = 'workshop_receipts';

    // الأعمدة التي يمكن تعبئتها بشكل جماعي
    protected $fillable = [
        'workshop_inspection_id',
        'received_by',
        'received_at',
        'rental_start_date',
        'rental_end_date',
        'notes',
    ];

    // التحويلات التلقائية للتواريخ
    protected $dates = [
        'received_at',
        'rental_start_date',
        'rental_end_date',
        'created_at',
        'updated_at',
    ];

    /**
     * علاقة الورشة (الفحص) المرتبط بهذه الفاتورة
     */
    public function workshopInspection()
    {
        return $this->belongsTo(WorkshopInspection::class, 'workshop_inspection_id', 'id');
    }
}
