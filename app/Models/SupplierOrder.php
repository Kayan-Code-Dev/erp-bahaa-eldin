<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class SupplierOrder extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'supplier_id',
        'category_id',
        'subcategory_id',
        'branch_id',
        'order_number',
        'order_date',
        'status',
        'total_amount',
        'payment_amount',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
        'total_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
    ];

    protected $appends = ['remaining_payment'];

    /**
     * Get remaining payment (total_amount - payment_amount)
     */
    public function getRemainingPaymentAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->payment_amount;
    }

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_SHIPPED => 'Shipped',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Clothes created via this supplier order
     */
    public function clothes()
    {
        return $this->belongsToMany(Cloth::class, 'supplier_order_clothes')
                    ->withPivot(['price', 'notes'])
                    ->withTimestamps();
    }
}

