<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RoleEntityType Model
 *
 * Represents entity type restrictions for a role.
 * If a role has entries in this table, it only applies to those entity types.
 * If a role has no entries, it applies to all entity types.
 */
class RoleEntityType extends Model
{
    use HasFactory;

    protected $table = 'role_entity_types';

    protected $fillable = [
        'role_id',
        'entity_type',
    ];

    /**
     * Get the role this restriction belongs to
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Scope by entity type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('entity_type', $type);
    }
}

