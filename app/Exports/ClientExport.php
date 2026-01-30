<?php

namespace App\Exports;

use App\Exports\BaseExport;

class ClientExport extends BaseExport
{
    public function headings(): array
    {
        return [
            'ID',
            'First Name',
            'Middle Name',
            'Last Name',
            'Date of Birth',
            'National ID',
            'Source',
            // Body Measurements
            'Breast Size',
            'Waist Size',
            'Sleeve Size',
            'Hip Size',
            'Shoulder Size',
            'Length Size',
            'Measurement Notes',
            'Last Measurement Date',
            // Address
            'Address Street',
            'Address Building',
            'Address Notes',
            'City Name',
            'Country Name',
            'Phones',
            'Orders Count',
            'Created At',
            'Updated At',
        ];
    }

    public function map($client): array
    {
        $phones = $client->phones->pluck('phone')->join(', ');
        
        return [
            $client->id,
            $client->first_name,
            $client->middle_name,
            $client->last_name,
            $client->date_of_birth?->format('Y-m-d'),
            $client->national_id,
            $client->source,
            // Body Measurements
            $client->breast_size,
            $client->waist_size,
            $client->sleeve_size,
            $client->hip_size,
            $client->shoulder_size,
            $client->length_size,
            $client->measurement_notes,
            $client->last_measurement_date?->format('Y-m-d'),
            // Address
            $client->address?->street,
            $client->address?->building,
            $client->address?->notes,
            $client->address?->city?->name,
            $client->address?->city?->country?->name,
            $phones,
            $client->orders->count(),
            $client->created_at?->format('Y-m-d H:i:s'),
            $client->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}

