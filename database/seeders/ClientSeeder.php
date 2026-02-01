<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Address;
use App\Models\Phone;
use App\Models\City;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first city (Cairo) or create one
        $city = City::first();

        if (!$city) {
            $this->command->warn('No cities found. Please run CountrySeeder and CitySeeder first.');
            return;
        }

        // Create address
        $address = Address::create([
            'city_id' => $city->id,
            'street' => '123 شارع النيل، الطابق الثالث، شقة 5',
            'building' => '',
            'notes' => null,
        ]);

        // Create client
        $client = Client::create([
            'name' => 'أحمد محمد علي',
            'date_of_birth' => '1990-05-15',
            'national_id' => '29005151234567',
            'source' => 'website',
            'address_id' => $address->id,
            'breast_size' => '100',
            'waist_size' => '85',
            'sleeve_size' => '65',
            'hip_size' => '95',
            'shoulder_size' => '45',
            'length_size' => '175',
            'measurement_notes' => 'يفضل القياس الواسع قليلاً',
            'last_measurement_date' => now()->toDateString(),
        ]);

        // Create phones
        $client->phones()->createMany([
            ['phone' => '01012345678', 'type' => 'mobile'],
            ['phone' => '01112345678', 'type' => 'whatsapp'],
        ]);

        $this->command->info('Client seeded successfully: ' . $client->name);
    }
}

