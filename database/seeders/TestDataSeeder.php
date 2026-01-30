<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Branch;
use App\Models\City;
use App\Models\Client;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Country;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    /**
     * Seed test data needed for running the buy order tests.
     */
    public function run(): void
    {
        $this->command->info('Seeding test data...');

        // Create user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'),
            ]
        );
        $this->command->info('  ✓ User created/found');

        // Create country
        $country = Country::firstOrCreate(
            ['name' => 'Egypt'],
            []
        );

        // Create city
        $city = City::firstOrCreate(
            ['name' => 'Cairo'],
            ['country_id' => $country->id]
        );

        // Create address
        $address = Address::firstOrCreate(
            ['street' => 'Test Street', 'building' => '123'],
            ['city_id' => $city->id]
        );
        $this->command->info('  ✓ Address created/found');

        // Create client
        $client = Client::firstOrCreate(
            ['national_id' => 'TEST123456789'],
            [
                'first_name' => 'Test',
                'middle_name' => 'Client',
                'last_name' => 'User',
                'date_of_birth' => '1990-01-01',
                'address_id' => $address->id,
            ]
        );
        $this->command->info('  ✓ Client created/found');

        // Create branch
        $branch = Branch::firstOrCreate(
            ['branch_code' => 'MAIN'],
            [
                'name' => 'Main Branch',
                'address_id' => $address->id,
            ]
        );
        $this->command->info('  ✓ Branch created/found');

        // Create inventory for branch
        $inventory = Inventory::firstOrCreate(
            ['inventoriable_type' => Branch::class, 'inventoriable_id' => $branch->id],
            ['name' => 'Main Branch Inventory']
        );
        $this->command->info('  ✓ Inventory created/found');

        // Create cloth type
        $clothType = ClothType::firstOrCreate(
            ['code' => 'WD'],
            ['name' => 'Wedding Dress', 'description' => 'Elegant wedding dresses']
        );
        $this->command->info('  ✓ Cloth type created/found');

        // Create some cloths
        for ($i = 1; $i <= 5; $i++) {
            $cloth = Cloth::firstOrCreate(
                ['code' => "WD-00{$i}"],
                [
                    'name' => "Wedding Dress #{$i}",
                    'cloth_type_id' => $clothType->id,
                    'status' => 'ready_for_rent',
                ]
            );

            // Attach to inventory if not already
            if (!$inventory->clothes()->where('cloth_id', $cloth->id)->exists()) {
                $inventory->clothes()->attach($cloth->id);
            }
        }
        $this->command->info('  ✓ Cloths created/found');

        $this->command->info('Test data seeding complete!');
    }
}

