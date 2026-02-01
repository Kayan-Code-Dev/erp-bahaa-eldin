<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\LogsActivity;

class Client extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'date_of_birth',
        'national_id',
        'address_id',
        'source',
        // Body measurements
        'breast_size',
        'waist_size',
        'sleeve_size',
        'hip_size',
        'shoulder_size',
        'length_size',
        'measurement_notes',
        'last_measurement_date',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'last_measurement_date' => 'date',
    ];

    public function phones()
    {
        return $this->hasMany(Phone::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Check if client has any measurements recorded
     */
    public function hasMeasurements(): bool
    {
        return $this->breast_size || $this->waist_size || $this->sleeve_size ||
               $this->hip_size || $this->shoulder_size || $this->length_size;
    }

    /**
     * Get all measurements as an array
     */
    public function getMeasurements(): array
    {
        return [
            'breast_size' => $this->breast_size,
            'waist_size' => $this->waist_size,
            'sleeve_size' => $this->sleeve_size,
            'hip_size' => $this->hip_size,
            'shoulder_size' => $this->shoulder_size,
            'length_size' => $this->length_size,
            'measurement_notes' => $this->measurement_notes,
            'last_measurement_date' => $this->last_measurement_date?->format('Y-m-d'),
        ];
    }

    /**
     * Update measurements and set the last measurement date
     */
    public function updateMeasurements(array $measurements): bool
    {
        $measurementFields = ['breast_size', 'waist_size', 'sleeve_size', 'hip_size', 'shoulder_size', 'length_size', 'measurement_notes'];
        
        $hasChanges = false;
        foreach ($measurementFields as $field) {
            if (array_key_exists($field, $measurements)) {
                $this->$field = $measurements[$field];
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->last_measurement_date = now();
        }

        return $this->save();
    }
}
