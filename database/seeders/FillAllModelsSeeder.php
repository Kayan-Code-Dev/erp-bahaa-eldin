<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\ClothType;
use App\Models\Role;
use App\Models\User;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory as FactoryModel;
use App\Models\Inventory;
use App\Models\Client;
use App\Models\Cloth;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Transfer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FillAllModelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting to seed all models with 10 records each...');
        $this->command->newLine();

        // 1. Countries (no dependencies)
        $this->command->info('Creating 10 countries...');
        $countries = Country::factory(10)->create();
        $this->command->info('✓ Created ' . $countries->count() . ' countries');
        $this->command->newLine();

        // 2. Cities (depends on Countries)
        $this->command->info('Creating 10 cities...');
        $cities = City::factory(10)->create([
            'country_id' => fn() => $countries->random()->id
        ]);
        $this->command->info('✓ Created ' . $cities->count() . ' cities');
        $this->command->newLine();

        // 3. Addresses (depends on Cities)
        $this->command->info('Creating 10 addresses...');
        $addresses = Address::factory(10)->create([
            'city_id' => fn() => $cities->random()->id
        ]);
        $this->command->info('✓ Created ' . $addresses->count() . ' addresses');
        $this->command->newLine();

        // 4. Categories (no dependencies)
        $this->command->info('Creating 10 categories...');
        $categories = Category::factory(10)->create();
        $this->command->info('✓ Created ' . $categories->count() . ' categories');
        $this->command->newLine();

        // 5. Subcategories (depends on Categories)
        $this->command->info('Creating 10 subcategories...');
        $subcategories = Subcategory::factory(10)->create([
            'category_id' => fn() => $categories->random()->id
        ]);
        $this->command->info('✓ Created ' . $subcategories->count() . ' subcategories');
        $this->command->newLine();

        // 6. Cloth Types (no dependencies, but will attach subcategories)
        $this->command->info('Creating 10 cloth types...');
        $clothTypes = collect();
        $existingClothTypeCodes = ClothType::pluck('code')->toArray();
        $clothTypeCounter = 1;

        for ($i = 0; $i < 10; $i++) {
            // Generate unique cloth type code
            do {
                $clothTypeCode = 'CT-' . str_pad($clothTypeCounter++, 3, '0', STR_PAD_LEFT);
            } while (in_array($clothTypeCode, $existingClothTypeCodes));

            $existingClothTypeCodes[] = $clothTypeCode;

            $clothType = ClothType::factory()->create([
                'code' => $clothTypeCode,
                'name' => 'Cloth Type ' . ($clothTypeCounter - 1),
            ]);

            // Attach subcategories to cloth type
            $clothType->subcategories()->attach($subcategories->random(rand(1, 3))->pluck('id')->toArray());

            $clothTypes->push($clothType);
        }
        $this->command->info('✓ Created ' . $clothTypes->count() . ' cloth types with subcategories');
        $this->command->newLine();

        // 7. Roles (no dependencies)
        $this->command->info('Creating 10 roles...');
        $roles = Role::factory(10)->create();
        $this->command->info('✓ Created ' . $roles->count() . ' roles');
        $this->command->newLine();

        // 8. Users (depends on Roles - many-to-many relationship)
        $this->command->info('Creating 10 users...');
        $users = User::factory(10)->create([
            'password' => Hash::make('password123')
        ]);

        // Attach roles to users (many-to-many)
        foreach ($users as $user) {
            $user->roles()->attach($roles->random(rand(1, 2))->pluck('id')->toArray());
        }

        $this->command->info('✓ Created ' . $users->count() . ' users with roles');
        $this->command->newLine();

        // 9. Branches (depends on Addresses, auto-creates Inventories)
        $this->command->info('Creating 10 branches (with auto-created inventories)...');
        $branches = collect();
        $existingBranchCodes = Branch::pluck('branch_code')->toArray();
        $branchCounter = 1;

        for ($i = 0; $i < 10; $i++) {
            // Generate unique branch code
            do {
                $branchCode = 'BR-' . str_pad($branchCounter++, 3, '0', STR_PAD_LEFT);
            } while (in_array($branchCode, $existingBranchCodes));

            $existingBranchCodes[] = $branchCode;
            $address = $addresses->random();

            $branch = Branch::factory()->create([
                'address_id' => $address->id,
                'branch_code' => $branchCode,
                'name' => 'Branch ' . ($branchCounter - 1)
            ]);

            // Auto-create inventory (as done in BranchController)
            $branch->inventory()->create([
                'name' => $branch->name . ' Inventory'
            ]);

            $branches->push($branch);
        }
        $this->command->info('✓ Created ' . $branches->count() . ' branches with inventories');
        $this->command->newLine();

        // 10. Workshops (depends on Addresses, auto-creates Inventories)
        $this->command->info('Creating 10 workshops (with auto-created inventories)...');
        $workshops = collect();
        $existingWorkshopCodes = Workshop::pluck('workshop_code')->toArray();
        $workshopCounter = 1;

        for ($i = 0; $i < 10; $i++) {
            // Generate unique workshop code
            do {
                $workshopCode = 'WS-' . str_pad($workshopCounter++, 3, '0', STR_PAD_LEFT);
            } while (in_array($workshopCode, $existingWorkshopCodes));

            $existingWorkshopCodes[] = $workshopCode;
            $address = $addresses->random();

            $workshop = Workshop::factory()->create([
                'address_id' => $address->id,
                'workshop_code' => $workshopCode,
                'name' => 'Workshop ' . ($workshopCounter - 1)
            ]);

            // Auto-create inventory (as done in WorkshopController)
            $workshop->inventory()->create([
                'name' => $workshop->name . ' Inventory'
            ]);

            $workshops->push($workshop);
        }
        $this->command->info('✓ Created ' . $workshops->count() . ' workshops with inventories');
        $this->command->newLine();

        // 11. Factories (depends on Addresses, auto-creates Inventories)
        $this->command->info('Creating 10 factories (with auto-created inventories)...');
        $factories = collect();
        $existingFactoryCodes = FactoryModel::pluck('factory_code')->toArray();
        $factoryCounter = 1;

        for ($i = 0; $i < 10; $i++) {
            // Generate unique factory code
            do {
                $factoryCode = 'FA-' . str_pad($factoryCounter++, 3, '0', STR_PAD_LEFT);
            } while (in_array($factoryCode, $existingFactoryCodes));

            $existingFactoryCodes[] = $factoryCode;
            $address = $addresses->random();

            $factory = FactoryModel::factory()->create([
                'address_id' => $address->id,
                'factory_code' => $factoryCode,
                'name' => 'Factory ' . ($factoryCounter - 1)
            ]);

            // Auto-create inventory (as done in FactoryController)
            $factory->inventory()->create([
                'name' => $factory->name . ' Inventory'
            ]);

            $factories->push($factory);
        }
        $this->command->info('✓ Created ' . $factories->count() . ' factories with inventories');
        $this->command->newLine();

        // 12. Clients (depends on Addresses)
        $this->command->info('Creating 10 clients...');
        $clients = Client::factory(10)->create([
            'address_id' => fn() => $addresses->random()->id
        ]);
        $this->command->info('✓ Created ' . $clients->count() . ' clients');
        $this->command->newLine();

        // 13. Clothes (depends on ClothTypes and Entities - Branches/Workshops/Factories)
        $this->command->info('Creating 10 clothes...');
        $clothes = collect();
        $allEntities = $branches->merge($workshops)->merge($factories);
        $existingClothCodes = Cloth::pluck('code')->toArray();
        $counter = 1;

        for ($i = 0; $i < 10; $i++) {
            // Generate unique cloth code
            do {
                $clothCode = 'CL-' . str_pad($counter++, 3, '0', STR_PAD_LEFT);
            } while (in_array($clothCode, $existingClothCodes));

            $existingClothCodes[] = $clothCode;
            $clothType = $clothTypes->random();
            $entity = $allEntities->random();

            $cloth = Cloth::factory()->create([
                'code' => $clothCode,
                'name' => 'Cloth ' . ($counter - 1),
                'cloth_type_id' => $clothType->id,
            ]);

            // Add cloth to entity's inventory (no quantity fields, just presence)
            // Ensure cloth is in exactly one inventory (business rule: one cloth = one inventory)
            if ($entity->inventory) {
                $cloth->inventories()->detach(); // Remove from any existing inventories
                $entity->inventory->clothes()->attach($cloth->id);
            }

            $clothes->push($cloth);
        }
        $this->command->info('✓ Created ' . $clothes->count() . ' clothes');
        $this->command->newLine();

        // 14. Orders (depends on Clients, Inventories, Clothes)
        $this->command->info('Creating 10 orders...');
        $orders = collect();

        for ($i = 0; $i < 10; $i++) {
            $client = $clients->random();
            $inventory = $allEntities->random()->inventory;
            $orderClothes = $clothes->random(rand(1, 3));

            // Calculate total price from items with discounts
            $subtotal = 0;
            $itemPrices = [];

            foreach ($orderClothes as $cloth) {
                $itemPrice = rand(50, 500);
                $itemDiscountType = ['none', 'percentage', 'fixed'][rand(0, 2)];
                $itemDiscountValue = 0;

                if ($itemDiscountType === 'percentage') {
                    $itemDiscountValue = rand(5, 25); // 5-25% discount
                } elseif ($itemDiscountType === 'fixed') {
                    $itemDiscountValue = rand(10, 50); // 10-50 fixed discount
                }

                // Calculate item final price
                $itemFinalPrice = $itemPrice;
                if ($itemDiscountType === 'percentage') {
                    $itemFinalPrice = $itemPrice * (1 - $itemDiscountValue / 100);
                } elseif ($itemDiscountType === 'fixed') {
                    $itemFinalPrice = max(0, $itemPrice - $itemDiscountValue);
                }

                $subtotal += $itemFinalPrice;
                $itemPrices[$cloth->id] = [
                    'price' => $itemPrice,
                    'discount_type' => $itemDiscountType,
                    'discount_value' => $itemDiscountValue,
                ];
            }

            // Apply order-level discount
            $orderDiscountType = ['none', 'percentage', 'fixed'][rand(0, 2)];
            $orderDiscountValue = 0;

            if ($orderDiscountType === 'percentage') {
                $orderDiscountValue = rand(5, 15); // 5-15% discount
            } elseif ($orderDiscountType === 'fixed') {
                $orderDiscountValue = rand(20, 100); // 20-100 fixed discount
            }

            $totalPrice = $subtotal;
            if ($orderDiscountType === 'percentage') {
                $totalPrice = $subtotal * (1 - $orderDiscountValue / 100);
            } elseif ($orderDiscountType === 'fixed') {
                $totalPrice = max(0, $subtotal - $orderDiscountValue);
            }

            $order = Order::factory()->create([
                'client_id' => $client->id,
                'inventory_id' => $inventory->id,
                'total_price' => $totalPrice,
                'status' => 'created', // Will be updated after payments are created
                'paid' => 0,
                'remaining' => $totalPrice,
                'visit_datetime' => now()->subDays(rand(0, 30)),
                'order_notes' => rand(0, 1) ? 'Order notes for order ' . ($i + 1) : null,
                'discount_type' => $orderDiscountType,
                'discount_value' => $orderDiscountValue > 0 ? $orderDiscountValue : null,
            ]);

            // Create payment records with variety
            $user = $users->random();
            $targetPaidAmount = rand(0, (int)$totalPrice);

            if ($targetPaidAmount > 0) {
                // Create initial payment (always paid if order has paid amount)
                $initialAmount = min($targetPaidAmount, $totalPrice * 0.5); // Initial payment is up to 50% of total
                \App\Models\Payment::factory()->initial()->create([
                    'order_id' => $order->id,
                    'amount' => $initialAmount,
                    'payment_date' => now()->subDays(rand(0, 30)),
                    'created_by' => $user->id,
                ]);

                $remainingPaid = $targetPaidAmount - $initialAmount;

                // Create additional normal payments if there's remaining paid amount
                if ($remainingPaid > 0) {
                    $numPayments = rand(1, 3);
                    $paymentAmount = $remainingPaid / $numPayments;

                    for ($j = 0; $j < $numPayments; $j++) {
                        $paymentStatus = $j < $numPayments - 1 ? 'paid' : (rand(0, 1) ? 'paid' : 'pending');
                        $paymentFactory = \App\Models\Payment::factory()->normal();

                        if ($paymentStatus === 'paid') {
                            $paymentFactory = $paymentFactory->paid();
                        } else {
                            $paymentFactory = $paymentFactory->pending();
                        }

                        $paymentFactory->create([
                            'order_id' => $order->id,
                            'amount' => $paymentAmount,
                            'payment_date' => $paymentStatus === 'paid' ? now()->subDays(rand(0, 30)) : null,
                            'notes' => rand(0, 1) ? 'Payment note ' . ($j + 1) : null,
                            'created_by' => $user->id,
                        ]);
                    }
                }
            } else {
                // Even if no paid amount, sometimes create pending payments
                if (rand(0, 100) < 40) {
                    $pendingAmount = rand(10, (int)($totalPrice * 0.3));
                    \App\Models\Payment::factory()->pending()->normal()->create([
                        'order_id' => $order->id,
                        'amount' => $pendingAmount,
                        'notes' => 'Pending payment',
                        'created_by' => $user->id,
                    ]);
                }
            }

            // Randomly add fee payments (30% chance) - fees don't affect paid/remaining
            if (rand(0, 100) < 30) {
                $feeAmount = rand(10, 50);
                $feeFactory = \App\Models\Payment::factory()->fee();
                $feeFactory = rand(0, 1) ? $feeFactory->paid() : $feeFactory->pending();

                $feeFactory->create([
                    'order_id' => $order->id,
                    'amount' => $feeAmount,
                    'payment_date' => rand(0, 1) ? now()->subDays(rand(0, 30)) : null,
                    'created_by' => $user->id,
                ]);
            }

            // Recalculate order paid and remaining based on actual payments (non-fee payments only)
            $order->refresh();
            $totalPaid = Payment::where('order_id', $order->id)
                ->where('status', 'paid')
                ->where('payment_type', '!=', 'fee')
                ->sum('amount');

            $order->paid = $totalPaid;
            $order->remaining = max(0, $totalPrice - $totalPaid);

            // Update order status based on paid amount
            if ($order->paid >= $order->total_price) {
                $order->status = 'paid';
                $order->remaining = 0;
            } elseif ($order->paid > 0) {
                $order->status = 'partially_paid';
            } else {
                $order->status = 'created';
            }

            $order->save();

            // Attach items with pivot data
            foreach ($orderClothes as $cloth) {
                $type = ['buy', 'rent', 'tailoring'][rand(0, 2)];
                $pivotData = [
                    'price' => $itemPrices[$cloth->id]['price'],
                    'type' => $type,
                    'status' => ['created', 'partially_paid', 'paid', 'delivered', 'finished', 'canceled'][rand(0, 5)],
                    'notes' => rand(0, 1) ? 'Item notes for ' . $cloth->code : null,
                    'discount_type' => $itemPrices[$cloth->id]['discount_type'],
                    'discount_value' => $itemPrices[$cloth->id]['discount_value'] > 0 ? $itemPrices[$cloth->id]['discount_value'] : null,
                ];

                // Add rent-specific fields only if type is rent
                if ($type === 'rent') {
                    $pivotData['days_of_rent'] = rand(1, 7);
                    $pivotData['occasion_datetime'] = now()->addDays(rand(1, 30));
                }

                $order->items()->attach($cloth->id, $pivotData);
            }

            $orders->push($order);
        }
        $this->command->info('✓ Created ' . $orders->count() . ' orders');
        $this->command->newLine();

        // 15. Transfers (depends on Branches/Workshops/Factories, Clothes)
        $this->command->info('Creating 10 transfers...');
        $transfers = collect();
        $attempts = 0;
        $maxAttempts = 50; // Prevent infinite loop

        while ($transfers->count() < 10 && $attempts < $maxAttempts) {
            $attempts++;

            // Get two different entities
            $fromEntity = $allEntities->random();
            $toEntity = $allEntities->reject(fn($e) => $e->id === $fromEntity->id)->random();

            $fromEntityType = match(true) {
                $branches->contains($fromEntity) => 'branch',
                $workshops->contains($fromEntity) => 'workshop',
                $factories->contains($fromEntity) => 'factory',
                default => 'branch'
            };

            $toEntityType = match(true) {
                $branches->contains($toEntity) => 'branch',
                $workshops->contains($toEntity) => 'workshop',
                $factories->contains($toEntity) => 'factory',
                default => 'branch'
            };

            // Get a cloth that exists in the from entity's inventory
            $availableClothes = $fromEntity->inventory
                ? $fromEntity->inventory->clothes()->get()
                : collect();

            if ($availableClothes->isEmpty()) {
                // If no clothes in inventory, try a different entity or use a cloth from clothes collection
                // Use any cloth from our created clothes and ensure it's in the source inventory
                $cloth = $clothes->random();

                // Ensure cloth is in the from entity's inventory
                // Ensure cloth is in exactly one inventory (business rule: one cloth = one inventory)
                if ($fromEntity->inventory) {
                    // Always ensure cloth is only in the fromEntity's inventory
                    $cloth->inventories()->detach(); // Remove from any existing inventories
                    $fromEntity->inventory->clothes()->attach($cloth->id); // Add to source inventory
                    $transferCloth = $cloth;
                } else {
                    continue; // Skip if entity has no inventory
                }
            } else {
                $transferCloth = $availableClothes->random();
            }

            $transfer = Transfer::create([
                'from_entity_type' => $fromEntityType,
                'from_entity_id' => $fromEntity->id,
                'to_entity_type' => $toEntityType,
                'to_entity_id' => $toEntity->id,
                'transfer_date' => now()->subDays(rand(0, 30))->format('Y-m-d'),
                'status' => ['pending', 'approved', 'rejected'][rand(0, 2)],
            ]);

            // Attach cloth with status
            $transfer->clothes()->attach($transferCloth->id, [
                'status' => ['pending', 'approved', 'rejected'][rand(0, 2)]
            ]);

            $transfers->push($transfer);
        }
        $this->command->info('✓ Created ' . $transfers->count() . ' transfers');
        $this->command->newLine();

        $this->command->info('========================================');
        $this->command->info('✓ Seeding completed successfully!');
        $this->command->info('========================================');
        $this->command->newLine();
        $this->command->info('Summary:');
        $this->command->info('  - Countries: ' . $countries->count());
        $this->command->info('  - Cities: ' . $cities->count());
        $this->command->info('  - Addresses: ' . $addresses->count());
        $this->command->info('  - Categories: ' . $categories->count());
        $this->command->info('  - Subcategories: ' . $subcategories->count());
        $this->command->info('  - Cloth Types: ' . $clothTypes->count());
        $this->command->info('  - Roles: ' . $roles->count());
        $this->command->info('  - Users: ' . $users->count());
        $this->command->info('  - Branches: ' . $branches->count());
        $this->command->info('  - Workshops: ' . $workshops->count());
        $this->command->info('  - Factories: ' . $factories->count());
        $this->command->info('  - Clients: ' . $clients->count());
        $this->command->info('  - Clothes: ' . $clothes->count());
        $this->command->info('  - Orders: ' . $orders->count());
        $this->command->info('  - Transfers: ' . $transfers->count());
        $this->command->newLine();
    }
}

