<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'Egypt'],
            ['name' => 'Saudi Arabia'],
            ['name' => 'United Arab Emirates'],
            ['name' => 'Kuwait'],
            ['name' => 'Qatar'],
            ['name' => 'Bahrain'],
            ['name' => 'Oman'],
            ['name' => 'Jordan'],
            ['name' => 'Lebanon'],
            ['name' => 'Iraq'],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(['name' => $country['name']]);
        }

        $this->command->info('Created ' . count($countries) . ' countries.');
    }
}

