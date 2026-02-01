<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\Address;
use App\Models\City;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'branch_code' => 'BR-CAIRO-001',
                'name' => 'Cairo Main Branch',
                'city' => 'Cairo',
                'street' => '123 El-Tahrir Street',
            ],
            [
                'branch_code' => 'BR-ALEX-001',
                'name' => 'Alexandria Branch',
                'city' => 'Alexandria',
                'street' => '45 Corniche Road',
            ],
            [
                'branch_code' => 'BR-GIZA-001',
                'name' => 'Giza Branch',
                'city' => 'Giza',
                'street' => '78 Pyramids Street',
            ],
            [
                'branch_code' => 'BR-MANS-001',
                'name' => 'Mansoura Branch',
                'city' => 'Mansoura',
                'street' => '12 El-Gomhoreya Street',
            ],
            [
                'branch_code' => 'BR-TANT-001',
                'name' => 'Tanta Branch',
                'city' => 'Tanta',
                'street' => '56 El-Bahr Street',
            ],
        ];

        $createdCount = 0;

        foreach ($branches as $branchData) {
            // Check if branch already exists
            if (Branch::where('branch_code', $branchData['branch_code'])->exists()) {
                continue;
            }

            // Find city
            $city = City::where('name', $branchData['city'])->first();

            if (!$city) {
                $this->command->warn("City '{$branchData['city']}' not found. Skipping branch '{$branchData['name']}'.");
                continue;
            }

            // Create address for branch
            $address = Address::create([
                'city_id' => $city->id,
                'street' => $branchData['street'],
                'building' => 'Building ' . rand(1, 50),
                'notes' => 'Branch location',
            ]);

            // Create branch
            Branch::create([
                'branch_code' => $branchData['branch_code'],
                'name' => $branchData['name'],
                'address_id' => $address->id,
            ]);

            $createdCount++;
        }

        $this->command->info("Created {$createdCount} branches with addresses.");
    }
}

