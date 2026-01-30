<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\LogsActivity;

class ReceivablePayment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'receivable_id',
        'payment_id',
        'transaction_id',
        'amount',
        'payment_date',
        'payment_method',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * Payment method constants
     */
    public const METHOD_CASH = 'cash';
    public const METHOD_CARD = 'card';
    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_CHECK = 'check';

    /**
     * Get available payment methods
     */
    public static function getPaymentMethods(): array
    {
        return [
            self::METHOD_CASH => 'Cash',
            self::METHOD_CARD => 'Credit/Debit Card',
            self::METHOD_TRANSFER => 'Bank Transfer',
            self::METHOD_CHECK => 'Check',
        ];
    }

    /**
     * Get the receivable this payment is for
     */
    public function receivable()
    {
        return $this->belongsTo(Receivable::class);
    }

    /**
     * Get the linked payment (if any)
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the linked transaction (if any)
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the user who created this payment
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}



