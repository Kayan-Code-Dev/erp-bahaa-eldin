<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\LogsActivity;

class FactoryUser extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id',
        'factory_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the factory
     */
    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    // ==================== SCOPES ====================

    /**
     * Filter active factory users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ==================== METHODS ====================

    /**
     * Activate the factory user
     */
    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    /**
     * Deactivate the factory user
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }
}
