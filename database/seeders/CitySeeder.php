<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\City;
use App\Models\Country;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $citiesByCountry = [
            'Egypt' => [
                'Cairo',
                'Alexandria',
                'Giza',
                'Shubra El Kheima',
                'Port Said',
                'Suez',
                'Luxor',
                'Aswan',
                'Mansoura',
                'Tanta',
                'Ismailia',
                'Faiyum',
                'Zagazig',
                'Damietta',
                'Hurghada',
            ],
            'Saudi Arabia' => [
                'Riyadh',
                'Jeddah',
                'Mecca',
                'Medina',
                'Dammam',
                'Taif',
                'Tabuk',
            ],
            'United Arab Emirates' => [
                'Dubai',
                'Abu Dhabi',
                'Sharjah',
                'Ajman',
                'Ras Al Khaimah',
            ],
            'Kuwait' => [
                'Kuwait City',
                'Hawalli',
                'Salmiya',
            ],
            'Qatar' => [
                'Doha',
                'Al Wakrah',
                'Al Khor',
            ],
        ];

        $totalCities = 0;

        foreach ($citiesByCountry as $countryName => $cities) {
            $country = Country::where('name', $countryName)->first();

            if (!$country) {
                $this->command->warn("Country '{$countryName}' not found. Skipping cities.");
                continue;
            }

            foreach ($cities as $cityName) {
                City::firstOrCreate([
                    'country_id' => $country->id,
                    'name' => $cityName,
                ]);
                $totalCities++;
            }
        }

        $this->command->info("Created {$totalCities} cities.");
    }
}

