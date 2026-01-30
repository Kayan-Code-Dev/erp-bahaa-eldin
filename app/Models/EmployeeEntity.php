<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\SerializesDates;

/**
 * EmployeeEntity Model
 *
 * Represents a polymorphic assignment of an employee to an entity
 * (branch, workshop, or factory).
 */
class EmployeeEntity extends Model
{
    use HasFactory, SerializesDates;

    protected $table = 'employee_entity';

    protected $fillable = [
        'employee_id',
        'entity_type',
        'entity_id',
        'is_primary',
        'assigned_at',
        'unassigned_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'assigned_at' => 'date',
        'unassigned_at' => 'date',
    ];

    /**
     * Get the employee this assignment belongs to
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the entity (polymorphic)
     */
    public function entity()
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    /**
     * Get the entity model based on type
     */
    public function getEntityModelAttribute()
    {
        switch ($this->entity_type) {
            case Employee::ENTITY_TYPE_BRANCH:
                return Branch::find($this->entity_id);
            case Employee::ENTITY_TYPE_WORKSHOP:
                return Workshop::find($this->entity_id);
            case Employee::ENTITY_TYPE_FACTORY:
                return Factory::find($this->entity_id);
            default:
                return null;
        }
    }

    /**
     * Scope for active assignments
     */
    public function scopeActive($query)
    {
        return $query->whereNull('unassigned_at');
    }

    /**
     * Scope by entity type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('entity_type', $type);
    }

    /**
     * Scope for primary assignments
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}

