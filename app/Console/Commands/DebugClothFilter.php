<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cloth;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;

class DebugClothFilter extends Command
{
    protected $signature = 'debug:cloth-filter {entity_type}';
    protected $description = 'Debug cloth filter by entity type';

    public function handle()
    {
        $entityType = $this->argument('entity_type');
        
        $this->info("=== Debugging Cloth Filter for entity_type: {$entityType} ===");
        $this->newLine();

        // Check inventory data
        $this->info("1. Checking inventories table:");
        $inventories = DB::table('inventories')
            ->select('id', 'name', 'inventoriable_type', 'inventoriable_id')
            ->get();
        
        $this->table(['ID', 'Name', 'Type', 'Entity ID'], $inventories->map(function($inv) {
            return [
                $inv->id,
                $inv->name,
                $inv->inventoriable_type,
                $inv->inventoriable_id,
            ];
        })->toArray());

        // Check cloth_inventory relationships
        $this->newLine();
        $this->info("2. Checking cloth_inventory relationships:");
        $clothInventories = DB::table('cloth_inventory')
            ->join('clothes', 'cloth_inventory.cloth_id', '=', 'clothes.id')
            ->join('inventories', 'cloth_inventory.inventory_id', '=', 'inventories.id')
            ->select(
                'clothes.id as cloth_id',
                'clothes.code as cloth_code',
                'inventories.id as inventory_id',
                'inventories.inventoriable_type',
                'inventories.inventoriable_id'
            )
            ->get();

        $this->table(
            ['Cloth ID', 'Cloth Code', 'Inventory ID', 'Type', 'Entity ID'],
            $clothInventories->map(function($ci) {
                return [
                    $ci->cloth_id,
                    $ci->cloth_code,
                    $ci->inventory_id,
                    $ci->inventoriable_type,
                    $ci->inventoriable_id,
                ];
            })->toArray()
        );

        // Test the query directly
        $this->newLine();
        $this->info("3. Testing filter query directly:");
        
        DB::enableQueryLog();
        
        $query = Cloth::query();
        $query->whereHas('inventories', function($q) use ($entityType) {
            $q->where('inventoriable_type', $entityType);
        });
        
        $results = $query->get(['id', 'code', 'name']);
        
        $queries = DB::getQueryLog();
        $lastQuery = end($queries);
        
        $this->info("SQL Query:");
        $this->line($lastQuery['query']);
        $this->info("Bindings: " . json_encode($lastQuery['bindings'] ?? []));
        $this->newLine();
        
        $this->info("Results (" . $results->count() . " clothes found):");
        if ($results->count() > 0) {
            $this->table(
                ['ID', 'Code', 'Name'],
                $results->map(function($cloth) {
                    return [$cloth->id, $cloth->code, $cloth->name];
                })->toArray()
            );
            
            // Check each cloth's actual inventory
            $this->newLine();
            $this->info("4. Checking actual inventories for each returned cloth:");
            foreach ($results as $cloth) {
                $inventories = $cloth->inventories()->with('inventoriable')->get();
                $this->info("Cloth {$cloth->code} (ID: {$cloth->id}) is in:");
                foreach ($inventories as $inv) {
                    $this->line("  - Inventory ID: {$inv->id}, Type: {$inv->inventoriable_type}, Entity ID: {$inv->inventoriable_id}");
                }
            }
        } else {
            $this->warn("No results found!");
        }

        // Check for data inconsistencies
        $this->newLine();
        $this->info("5. Checking for data inconsistencies:");
        
        // Cloths in multiple inventories
        $clothsInMultipleInventories = DB::table('cloth_inventory')
            ->select('cloth_id', DB::raw('COUNT(*) as inventory_count'))
            ->groupBy('cloth_id')
            ->having('inventory_count', '>', 1)
            ->get();
            
        if ($clothsInMultipleInventories->count() > 0) {
            $this->warn("Found clothes in multiple inventories:");
            foreach ($clothsInMultipleInventories as $cloth) {
                $clothModel = Cloth::find($cloth->cloth_id);
                $inventories = $clothModel->inventories()->get();
                $this->line("  Cloth ID {$cloth->cloth_id} ({$clothModel->code}) is in {$cloth->inventory_count} inventories:");
                foreach ($inventories as $inv) {
                    $this->line("    - Inventory ID: {$inv->id}, Type: {$inv->inventoriable_type}");
                }
            }
        } else {
            $this->info("✓ No clothes found in multiple inventories");
        }

        // Check for incorrect inventoriable_type values
        $incorrectTypes = DB::table('inventories')
            ->whereNotIn('inventoriable_type', ['branch', 'workshop', 'factory'])
            ->get();
            
        if ($incorrectTypes->count() > 0) {
            $this->warn("Found inventories with incorrect inventoriable_type:");
            foreach ($incorrectTypes as $inv) {
                $this->line("  Inventory ID: {$inv->id}, Type: {$inv->inventoriable_type}");
            }
        } else {
            $this->info("✓ All inventories have correct inventoriable_type values");
        }

        return 0;
    }
}


