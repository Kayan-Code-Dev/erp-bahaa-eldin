<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Cashbox;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CashboxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates cashboxes for existing branches.
     * Only creates cashboxes if system is empty (no existing cashboxes).
     */
    public function run(): void
    {
        // Check if cashboxes already exist
        if (Cashbox::count() > 0) {
            $this->command->info('Cashboxes already exist. Skipping cashbox seeder.');
            return;
        }

        $this->command->info('Creating cashboxes for branches...');
        $this->command->newLine();

        // Get all branches that don't have cashboxes
        $branches = Branch::whereDoesntHave('cashbox')->get();

        // If no branches exist, create some first
        if ($branches->isEmpty()) {
            $this->command->info('No branches found. Creating 10 branches first...');
            
            // Check if we have addresses
            $addresses = DB::table('addresses')->get();
            
            if ($addresses->isEmpty()) {
                $this->command->warn('No addresses found. Creating addresses first...');
                
                // Check for cities
                $cities = DB::table('cities')->get();
                if ($cities->isEmpty()) {
                    $this->command->warn('No cities found. Creating cities and countries first...');
                    
                    // Check for countries
                    $countries = DB::table('countries')->get();
                    if ($countries->isEmpty()) {
                        $countryId = DB::table('countries')->insertGetId([
                            'name' => 'Egypt',
                            'code' => 'EG',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $countryId = $countries->first()->id;
                    }
                    
                    $cityId = DB::table('cities')->insertGetId([
                        'name' => 'Cairo',
                        'country_id' => $countryId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $cityId = $cities->first()->id;
                }
                
                // Create addresses
                for ($i = 1; $i <= 10; $i++) {
                    DB::table('addresses')->insert([
                        'street' => 'Street ' . $i,
                        'building' => ($i * 10) . 'A',
                        'city_id' => $cityId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                
                $addresses = DB::table('addresses')->get();
            }

            // Create branches (will auto-create cashboxes via boot method)
            for ($i = 1; $i <= 10; $i++) {
                Branch::create([
                    'branch_code' => 'BR-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'name' => 'Branch ' . $i,
                    'address_id' => $addresses->random()->id,
                ]);
            }

            // Get all branches now (they should have cashboxes from boot method)
            $branches = Branch::with('cashbox')->get();
            
            // Define realistic cashbox data
            $cashboxDataTemplates = [
                [
                    'name' => 'Main Cash Register',
                    'initial_balance' => 5000.00,
                    'current_balance' => 5000.00,
                    'description' => 'Primary cash register for daily operations',
                    'is_active' => true,
                ],
                [
                    'name' => 'Secondary Cash Drawer',
                    'initial_balance' => 3000.00,
                    'current_balance' => 3000.00,
                    'description' => 'Backup cash drawer for busy periods',
                    'is_active' => true,
                ],
                [
                    'name' => 'Safe Cashbox',
                    'initial_balance' => 10000.00,
                    'current_balance' => 10000.00,
                    'description' => 'Secure safe cashbox for large amounts',
                    'is_active' => true,
                ],
                [
                    'name' => 'Mobile Cashbox',
                    'initial_balance' => 1500.00,
                    'current_balance' => 1500.00,
                    'description' => 'Portable cashbox for events and deliveries',
                    'is_active' => true,
                ],
                [
                    'name' => 'Emergency Cashbox',
                    'initial_balance' => 2000.00,
                    'current_balance' => 2000.00,
                    'description' => 'Emergency reserve cashbox',
                    'is_active' => true,
                ],
                [
                    'name' => 'Weekend Cashbox',
                    'initial_balance' => 4000.00,
                    'current_balance' => 4000.00,
                    'description' => 'Weekend operations cashbox',
                    'is_active' => true,
                ],
                [
                    'name' => 'Night Shift Cashbox',
                    'initial_balance' => 2500.00,
                    'current_balance' => 2500.00,
                    'description' => 'Night shift cash register',
                    'is_active' => true,
                ],
                [
                    'name' => 'VIP Cashbox',
                    'initial_balance' => 8000.00,
                    'current_balance' => 8000.00,
                    'description' => 'VIP customer transactions cashbox',
                    'is_active' => true,
                ],
                [
                    'name' => 'Retail Cashbox',
                    'initial_balance' => 3500.00,
                    'current_balance' => 3500.00,
                    'description' => 'Retail sales cash register',
                    'is_active' => true,
                ],
                [
                    'name' => 'Admin Cashbox',
                    'initial_balance' => 6000.00,
                    'current_balance' => 6000.00,
                    'description' => 'Administrative expenses cashbox',
                    'is_active' => false, // Inactive as example
                ],
            ];
            
            // Update cashboxes with realistic data
            foreach ($branches->take(10) as $index => $branch) {
                if ($branch->cashbox && isset($cashboxDataTemplates[$index])) {
                    $data = $cashboxDataTemplates[$index];
                    $branch->cashbox->update([
                        'name' => $data['name'],
                        'initial_balance' => $data['initial_balance'],
                        'current_balance' => $data['current_balance'],
                        'description' => $data['description'],
                        'is_active' => $data['is_active'],
                    ]);
                    $this->command->info("  ✓ Updated cashbox: {$data['name']} for {$branch->name} (Balance: " . number_format($data['current_balance'], 2) . ")");
                }
            }
            
            $this->command->info('✓ Created 10 branches with cashboxes.');
            $this->command->newLine();
            return; // Exit since cashboxes were auto-created
        }

        // Limit to 10 cashboxes
        $branches = $branches->take(10);

        if ($branches->isEmpty()) {
            $this->command->warn('No branches available for cashbox creation.');
            return;
        }

        $cashboxData = [
            [
                'name' => 'Main Cash Register',
                'initial_balance' => 5000.00,
                'current_balance' => 5000.00,
                'description' => 'Primary cash register for daily operations',
                'is_active' => true,
            ],
            [
                'name' => 'Secondary Cash Drawer',
                'initial_balance' => 3000.00,
                'current_balance' => 3000.00,
                'description' => 'Backup cash drawer for busy periods',
                'is_active' => true,
            ],
            [
                'name' => 'Safe Cashbox',
                'initial_balance' => 10000.00,
                'current_balance' => 10000.00,
                'description' => 'Secure safe cashbox for large amounts',
                'is_active' => true,
            ],
            [
                'name' => 'Mobile Cashbox',
                'initial_balance' => 1500.00,
                'current_balance' => 1500.00,
                'description' => 'Portable cashbox for events and deliveries',
                'is_active' => true,
            ],
            [
                'name' => 'Emergency Cashbox',
                'initial_balance' => 2000.00,
                'current_balance' => 2000.00,
                'description' => 'Emergency reserve cashbox',
                'is_active' => true,
            ],
            [
                'name' => 'Weekend Cashbox',
                'initial_balance' => 4000.00,
                'current_balance' => 4000.00,
                'description' => 'Weekend operations cashbox',
                'is_active' => true,
            ],
            [
                'name' => 'Night Shift Cashbox',
                'initial_balance' => 2500.00,
                'current_balance' => 2500.00,
                'description' => 'Night shift cash register',
                'is_active' => true,
            ],
            [
                'name' => 'VIP Cashbox',
                'initial_balance' => 8000.00,
                'current_balance' => 8000.00,
                'description' => 'VIP customer transactions cashbox',
                'is_active' => true,
            ],
            [
                'name' => 'Retail Cashbox',
                'initial_balance' => 3500.00,
                'current_balance' => 3500.00,
                'description' => 'Retail sales cash register',
                'is_active' => true,
            ],
            [
                'name' => 'Admin Cashbox',
                'initial_balance' => 6000.00,
                'current_balance' => 6000.00,
                'description' => 'Administrative expenses cashbox',
                'is_active' => false, // Inactive as example
            ],
        ];

        $created = 0;
        foreach ($branches->take(10) as $index => $branch) {
            // Use predefined data or generate
            $data = $cashboxData[$index] ?? [
                'name' => $branch->name . ' Cashbox',
                'initial_balance' => rand(1000, 10000) + (rand(0, 99) / 100),
                'current_balance' => rand(1000, 10000) + (rand(0, 99) / 100),
                'description' => "Cashbox for {$branch->name} branch",
                'is_active' => true,
            ];

            Cashbox::create([
                'name' => $data['name'],
                'branch_id' => $branch->id,
                'initial_balance' => $data['initial_balance'],
                'current_balance' => $data['current_balance'],
                'description' => $data['description'],
                'is_active' => $data['is_active'],
            ]);

            $created++;
            $this->command->info("  ✓ Created cashbox: {$data['name']} for {$branch->name} (Balance: " . number_format($data['current_balance'], 2) . ")");
        }

        $this->command->newLine();
        $this->command->info("✓ Successfully created {$created} cashboxes.");
    }
}

