<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Rent;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Notification;
use App\Models\User;
use App\Models\Cloth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreatePreDeliveryTransfers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workshop:create-pre-delivery-transfers 
                            {--days=2 : Number of days before delivery to create transfer}
                            {--dry-run : Run without creating transfers}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create automated transfers from branches to workshops for rental deliveries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysBeforeDelivery = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $targetDate = Carbon::today()->addDays($daysBeforeDelivery);
        
        $this->info("Looking for rental deliveries on: {$targetDate->format('Y-m-d')}");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No transfers will be created');
        }
        
        // Find all rental delivery appointments with delivery_date = today + X days
        $rents = Rent::where('appointment_type', Rent::TYPE_RENTAL_DELIVERY)
            ->whereDate('delivery_date', $targetDate)
            ->active()
            ->whereNotNull('cloth_id')
            ->whereNotNull('branch_id')
            ->with(['cloth', 'branch.workshop', 'branch.inventory', 'client'])
            ->get();
        
        $this->info("Found {$rents->count()} rental deliveries");
        
        $transfersCreated = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($rents as $rent) {
            try {
                $result = $this->processRent($rent, $dryRun);
                
                if ($result === true) {
                    $transfersCreated++;
                } elseif ($result === false) {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors[] = "Rent #{$rent->id}: {$e->getMessage()}";
                $this->error("Error processing rent #{$rent->id}: {$e->getMessage()}");
                Log::error("CreatePreDeliveryTransfers: Error processing rent #{$rent->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
        
        $this->newLine();
        $this->info("Summary:");
        $this->info("- Transfers created: {$transfersCreated}");
        $this->info("- Skipped: {$skipped}");
        $this->info("- Errors: " . count($errors));
        
        if (!empty($errors)) {
            $this->newLine();
            $this->error("Errors encountered:");
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }
        
        return 0;
    }
    
    /**
     * Process a single rent and create transfer if needed
     */
    protected function processRent(Rent $rent, bool $dryRun): ?bool
    {
        $cloth = $rent->cloth;
        $branch = $rent->branch;
        
        if (!$cloth) {
            $this->warn("  Rent #{$rent->id}: No cloth assigned, skipping");
            return false;
        }
        
        if (!$branch) {
            $this->warn("  Rent #{$rent->id}: No branch assigned, skipping");
            return false;
        }
        
        $workshop = $branch->workshop;
        
        if (!$workshop) {
            $this->warn("  Rent #{$rent->id}: Branch '{$branch->name}' has no workshop, skipping");
            return false;
        }
        
        // Check if cloth is currently in the branch inventory
        $branchInventory = $branch->inventory;
        if (!$branchInventory) {
            $this->warn("  Rent #{$rent->id}: Branch '{$branch->name}' has no inventory, skipping");
            return false;
        }
        
        $clothInBranch = $branchInventory->clothes()->where('clothes.id', $cloth->id)->exists();
        
        if (!$clothInBranch) {
            $this->warn("  Rent #{$rent->id}: Cloth '{$cloth->code}' not in branch inventory, skipping");
            return false;
        }
        
        // Check if a transfer already exists for this cloth to this workshop
        $existingTransfer = Transfer::where('from_entity_type', 'branch')
            ->where('from_entity_id', $branch->id)
            ->where('to_entity_type', 'workshop')
            ->where('to_entity_id', $workshop->id)
            ->whereIn('status', ['pending', 'partially_pending', 'approved'])
            ->whereHas('items', function ($query) use ($cloth) {
                $query->where('cloth_id', $cloth->id);
            })
            ->first();
        
        if ($existingTransfer) {
            $this->warn("  Rent #{$rent->id}: Transfer already exists (#{$existingTransfer->id}), skipping");
            return false;
        }
        
        $this->info("  Rent #{$rent->id}: Creating transfer for cloth '{$cloth->code}' to workshop '{$workshop->name}'");
        
        if ($dryRun) {
            return true;
        }
        
        // Create the transfer
        DB::transaction(function () use ($rent, $cloth, $branch, $workshop) {
            $transfer = Transfer::create([
                'from_entity_type' => 'branch',
                'from_entity_id' => $branch->id,
                'to_entity_type' => 'workshop',
                'to_entity_id' => $workshop->id,
                'transfer_date' => now()->format('Y-m-d'),
                'notes' => "Automated pre-delivery transfer for rental #{$rent->id} (delivery: {$rent->delivery_date->format('Y-m-d')})",
                'status' => 'pending',
            ]);
            
            // Create transfer item
            TransferItem::create([
                'transfer_id' => $transfer->id,
                'cloth_id' => $cloth->id,
                'status' => 'pending',
            ]);
            
            // Note: TransferAction is NOT created for automated/system-generated transfers
            // The transfer's notes field indicates it was auto-created
            
            // Send notification to workshop managers
            $this->notifyWorkshopManagers($workshop, $transfer, $rent, $cloth);
        });
        
        return true;
    }
    
    /**
     * Send notification to users who can manage this workshop
     */
    protected function notifyWorkshopManagers($workshop, $transfer, $rent, $cloth): void
    {
        // Find users with workshop management permission (using roles relationship)
        $workshopManagers = User::whereHas('roles.permissions', function ($query) {
            $query->where('name', 'workshops.approve-transfers')
                  ->orWhere('name', 'workshops.manage-clothes');
        })->orWhereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->get();
        
        foreach ($workshopManagers as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'workshop_transfer_incoming',
                'title' => 'New Workshop Transfer',
                'message' => "Cloth '{$cloth->code}' needs to be prepared for rental delivery on {$rent->delivery_date->format('Y-m-d')}. Transfer #{$transfer->id} is pending approval.",
                'reference_type' => Transfer::class,
                'reference_id' => $transfer->id,
                'priority' => 'high',
                'scheduled_at' => null,
                'sent_at' => now(),
            ]);
        }
    }
}

