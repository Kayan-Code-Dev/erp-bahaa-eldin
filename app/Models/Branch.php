<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Branch extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = ['branch_code', 'name', 'address_id'];

    /**
     * Boot method to auto-create cashbox when branch is created
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-create a cashbox when a branch is created
        static::created(function ($branch) {
            if (!$branch->cashbox) {
                $branch->cashbox()->create([
                    'name' => "{$branch->name} Cashbox",
                    'initial_balance' => 0,
                    'current_balance' => 0,
                    'description' => "Cashbox for branch: {$branch->name}",
                    'is_active' => true,
                ]);
            }
        });
    }

    public function inventory()
    {
        return $this->morphOne(Inventory::class, 'inventoriable');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Get the cashbox for this branch
     */
    public function cashbox()
    {
        return $this->hasOne(Cashbox::class);
    }

    /**
     * Get the workshop for this branch (1:1 relationship)
     */
    public function workshop()
    {
        return $this->hasOne(Workshop::class);
    }

    public function clothes()
    {
        return Cloth::whereHas('inventories', function($query) {
            $query->where('inventoriable_type', 'branch')
                  ->where('inventoriable_id', $this->id);
        });
    }
}
