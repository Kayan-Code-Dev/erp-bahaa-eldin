<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory;
use App\Models\Inventory;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $createdCount = 0;

        // Create inventories for all branches (use 'branch' to match morph map)
        $branches = Branch::all();
        foreach ($branches as $branch) {
            if (!$branch->inventory) {
                Inventory::create([
                    'name' => "{$branch->name} Inventory",
                    'inventoriable_type' => 'branch',
                    'inventoriable_id' => $branch->id,
                ]);
                $createdCount++;
            }
        }

        // Create inventories for all workshops (use 'workshop' to match morph map)
        $workshops = Workshop::all();
        foreach ($workshops as $workshop) {
            if (!$workshop->inventory) {
                Inventory::create([
                    'name' => "{$workshop->name} Inventory",
                    'inventoriable_type' => 'workshop',
                    'inventoriable_id' => $workshop->id,
                ]);
                $createdCount++;
            }
        }

        // Create inventories for all factories (use 'factory' to match morph map)
        $factories = Factory::all();
        foreach ($factories as $factory) {
            if (!$factory->inventory) {
                Inventory::create([
                    'name' => "{$factory->name} Inventory",
                    'inventoriable_type' => 'factory',
                    'inventoriable_id' => $factory->id,
                ]);
                $createdCount++;
            }
        }

        $this->command->info("Created {$createdCount} inventories.");
    }
}

