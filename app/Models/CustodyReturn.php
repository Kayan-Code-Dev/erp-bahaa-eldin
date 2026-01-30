<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;

class CustodyReturn extends Model
{
    use HasFactory, SerializesDates;

    protected $fillable = [
        'custody_id',
        'order_return_id',
        'client_id',
        'returned_at',
        'return_proof_photo',
        'reason_of_kept',
        'customer_name',
        'customer_phone',
        'customer_id_number',
        'customer_signature_date',
        'notes',
        'returned_by',
    ];

    protected $casts = [
        'returned_at' => 'datetime',
        'customer_signature_date' => 'datetime',
    ];

    public function custody()
    {
        return $this->belongsTo(Custody::class);
    }

    public function orderReturn()
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
