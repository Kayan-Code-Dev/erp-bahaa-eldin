<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\WorkshopLog;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Inventory;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Rent;
use App\Models\Client;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Notification;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class WorkshopFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $workshopManager;
    protected Branch $branch;
    protected Workshop $workshop;
    protected Cloth $cloth;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin role with all permissions
        $superAdminRole = Role::create([
            'name' => 'super_admin',
            'description' => 'Super Administrator',
        ]);

        // Create workshop manager role
        $workshopManagerRole = Role::create([
            'name' => 'workshop_manager',
            'description' => 'Workshop Manager',
        ]);

        // Create permissions
        $permissions = [
            'workshops.view',
            'workshops.create',
            'workshops.update',
            'workshops.delete',
            'workshops.manage-clothes',
            'workshops.approve-transfers',
            'workshops.update-status',
            'workshops.return-cloth',
            'workshops.view-logs',
            'transfers.view',
            'transfers.approve',
            'branches.view',
            'clothes.view',
            'inventories.view',
            'notifications.view',
        ];

        foreach ($permissions as $perm) {
            $parts = explode('.', $perm);
            Permission::create([
                'name' => $perm,
                'display_name' => ucfirst(str_replace(['.', '-'], ' ', $perm)),
                'module' => $parts[0],
                'action' => $parts[1] ?? 'view',
            ]);
        }

        // Assign permissions to workshop manager role
        $workshopManagerRole->syncPermissions($permissions);

        // Create super admin user
        $this->superAdmin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->superAdmin->assignRole($superAdminRole);

        // Create workshop manager user
        $this->workshopManager = User::factory()->create([
            'email' => 'workshop@example.com',
        ]);
        $this->workshopManager->assignRole($workshopManagerRole);

        // Create location data
        $country = Country::create(['name' => 'Egypt', 'code' => 'EG']);
        $city = City::create(['name' => 'Cairo', 'country_id' => $country->id]);
        $address = Address::create([
            'street' => 'Test Street',
            'building' => '123',
            'city_id' => $city->id,
        ]);

        // Create branch with inventory
        $this->branch = Branch::create([
            'branch_code' => 'BR-001',
            'name' => 'Main Branch',
            'address_id' => $address->id,
        ]);

        // Create branch inventory
        $branchInventory = Inventory::create([
            'name' => 'Main Branch Inventory',
            'inventoriable_type' => 'branch',
            'inventoriable_id' => $this->branch->id,
        ]);

        // Create workshop linked to branch
        $workshopAddress = Address::create([
            'street' => 'Workshop Street',
            'building' => '456',
            'city_id' => $city->id,
        ]);

        $this->workshop = Workshop::create([
            'workshop_code' => 'WS-001',
            'name' => 'Main Workshop',
            'address_id' => $workshopAddress->id,
            'branch_id' => $this->branch->id,
        ]);

        // Create workshop inventory
        Inventory::create([
            'name' => 'Main Workshop Inventory',
            'inventoriable_type' => 'workshop',
            'inventoriable_id' => $this->workshop->id,
        ]);

        // Create category and subcategory
        $category = Category::create(['name' => 'Dresses', 'code' => 'DR']);
        $subcategory = Subcategory::create([
            'name' => 'Evening Dresses',
            'code' => 'EVE',
            'category_id' => $category->id,
        ]);

        // Create cloth type
        $clothType = ClothType::create([
            'name' => 'Wedding Dress',
            'code' => 'WD',
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
        ]);

        // Create cloth and add to branch inventory
        $this->cloth = Cloth::create([
            'code' => 'CL-001',
            'name' => 'Beautiful Wedding Dress',
            'cloth_type_id' => $clothType->id,
            'breast_size' => '36',
            'waist_size' => '28',
            'status' => 'ready_for_rent',
        ]);

        // Attach cloth to branch inventory
        $branchInventory->clothes()->attach($this->cloth->id);

        // Create client using factory
        $this->client = Client::factory()->create([
            'address_id' => $address->id,
        ]);
    }

    // ==================== BRANCH-WORKSHOP RELATIONSHIP TESTS ====================

    public function test_branch_has_one_workshop_relationship()
    {
        $this->assertNotNull($this->branch->workshop);
        $this->assertEquals($this->workshop->id, $this->branch->workshop->id);
    }

    public function test_workshop_belongs_to_branch_relationship()
    {
        $this->assertNotNull($this->workshop->branch);
        $this->assertEquals($this->branch->id, $this->workshop->branch->id);
    }

    public function test_branch_id_is_unique_on_workshops()
    {
        // Attempt to create another workshop with the same branch_id should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        $address = Address::first();
        Workshop::create([
            'workshop_code' => 'WS-002',
            'name' => 'Second Workshop',
            'address_id' => $address->id,
            'branch_id' => $this->branch->id, // Same branch
        ]);
    }

    // ==================== WORKSHOP CLOTH LISTING TESTS ====================

    public function test_can_list_clothes_in_workshop()
    {
        // Move cloth to workshop
        $branchInventory = $this->branch->inventory;
        $workshopInventory = $this->workshop->inventory;

        $branchInventory->clothes()->detach($this->cloth->id);
        $workshopInventory->clothes()->attach($this->cloth->id);

        // Create a log for the cloth
        WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'received',
            'cloth_status' => 'received',
            'received_at' => now(),
            'user_id' => $this->workshopManager->id,
        ]);

        $response = $this->actingAs($this->workshopManager)
            ->getJson("/api/v1/workshops/{$this->workshop->id}/clothes");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'workshop_status',
                    ]
                ],
                'total',
            ]);
    }

    public function test_can_filter_clothes_by_status()
    {
        // Move cloth to workshop
        $workshopInventory = $this->workshop->inventory;
        $this->branch->inventory->clothes()->detach($this->cloth->id);
        $workshopInventory->clothes()->attach($this->cloth->id);

        // Create a log with status
        WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'status_changed',
            'cloth_status' => 'processing',
            'user_id' => $this->workshopManager->id,
        ]);

        $response = $this->actingAs($this->workshopManager)
            ->getJson("/api/v1/workshops/{$this->workshop->id}/clothes?status=processing");

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    // ==================== PENDING TRANSFERS TESTS ====================

    public function test_can_list_pending_incoming_transfers()
    {
        // Create a pending transfer to workshop
        $transfer = Transfer::create([
            'from_entity_type' => 'branch',
            'from_entity_id' => $this->branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $this->workshop->id,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => 'pending',
            'notes' => 'Test transfer',
        ]);

        TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $this->cloth->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->workshopManager)
            ->getJson("/api/v1/workshops/{$this->workshop->id}/pending-transfers");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'from_entity_type',
                        'from_entity_id',
                        'from_entity_name',
                        'transfer_date',
                        'status',
                        'items',
                    ]
                ]
            ]);

        $this->assertCount(1, $response->json('data'));
    }

    // ==================== TRANSFER APPROVAL TESTS ====================

    public function test_can_approve_incoming_transfer()
    {
        // Create a pending transfer to workshop
        $transfer = Transfer::create([
            'from_entity_type' => 'branch',
            'from_entity_id' => $this->branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $this->workshop->id,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $this->cloth->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/approve-transfer/{$transfer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Transfer approved successfully',
                'clothes_received' => 1,
            ]);

        // Verify cloth is now in workshop inventory
        $this->assertTrue(
            $this->workshop->inventory->clothes()->where('clothes.id', $this->cloth->id)->exists()
        );

        // Verify cloth is no longer in branch inventory
        $this->assertFalse(
            $this->branch->inventory->clothes()->where('clothes.id', $this->cloth->id)->exists()
        );

        // Verify workshop log was created
        $this->assertDatabaseHas('workshop_logs', [
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'received',
            'cloth_status' => 'received',
        ]);
    }

    public function test_cannot_approve_transfer_not_destined_for_workshop()
    {
        // Create another workshop
        $address = Address::first();
        $otherWorkshop = Workshop::create([
            'workshop_code' => 'WS-OTHER',
            'name' => 'Other Workshop',
            'address_id' => $address->id,
        ]);

        // Create a transfer to the other workshop
        $transfer = Transfer::create([
            'from_entity_type' => 'branch',
            'from_entity_id' => $this->branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $otherWorkshop->id,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $this->cloth->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/approve-transfer/{$transfer->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This transfer is not destined for this workshop');
    }

    // ==================== CLOTH STATUS UPDATE TESTS ====================

    public function test_can_update_cloth_status_in_workshop()
    {
        // Move cloth to workshop
        $this->branch->inventory->clothes()->detach($this->cloth->id);
        $this->workshop->inventory->clothes()->attach($this->cloth->id);

        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/update-cloth-status", [
                'cloth_id' => $this->cloth->id,
                'status' => 'processing',
                'notes' => 'Needs cleaning and pressing',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cloth status updated successfully',
            ])
            ->assertJsonPath('log.cloth_status', 'processing')
            ->assertJsonPath('log.notes', 'Needs cleaning and pressing');

        // Verify log was created
        $this->assertDatabaseHas('workshop_logs', [
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'status_changed',
            'cloth_status' => 'processing',
        ]);
    }

    public function test_cannot_update_status_for_cloth_not_in_workshop()
    {
        // Cloth is in branch, not workshop
        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/update-cloth-status", [
                'cloth_id' => $this->cloth->id,
                'status' => 'processing',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cloth is not in this workshop');
    }

    public function test_ready_for_delivery_sends_notification()
    {
        // Move cloth to workshop
        $this->branch->inventory->clothes()->detach($this->cloth->id);
        $this->workshop->inventory->clothes()->attach($this->cloth->id);

        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/update-cloth-status", [
                'cloth_id' => $this->cloth->id,
                'status' => 'ready_for_delivery',
                'notes' => 'Ready for pickup',
            ]);

        $response->assertStatus(200);

        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'type' => 'workshop_cloth_ready',
            'reference_type' => Cloth::class,
            'reference_id' => $this->cloth->id,
        ]);
    }

    // ==================== RETURN CLOTH TESTS ====================

    public function test_can_create_return_transfer()
    {
        // Move cloth to workshop
        $this->branch->inventory->clothes()->detach($this->cloth->id);
        $this->workshop->inventory->clothes()->attach($this->cloth->id);

        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/return-cloth", [
                'cloth_id' => $this->cloth->id,
                'notes' => 'Cloth cleaned and ready',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Return transfer created successfully',
            ])
            ->assertJsonPath('transfer.from_entity_type', 'workshop')
            ->assertJsonPath('transfer.to_entity_type', 'branch');

        // Verify transfer was created
        $this->assertDatabaseHas('transfers', [
            'from_entity_type' => 'workshop',
            'from_entity_id' => $this->workshop->id,
            'to_entity_type' => 'branch',
            'to_entity_id' => $this->branch->id,
            'status' => 'pending',
        ]);

        // Verify workshop log was created
        $this->assertDatabaseHas('workshop_logs', [
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'returned',
        ]);
    }

    public function test_cannot_return_cloth_not_in_workshop()
    {
        // Cloth is in branch, not workshop
        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/return-cloth", [
                'cloth_id' => $this->cloth->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cloth is not in this workshop');
    }

    public function test_cannot_create_duplicate_return_transfer()
    {
        // Move cloth to workshop
        $this->branch->inventory->clothes()->detach($this->cloth->id);
        $this->workshop->inventory->clothes()->attach($this->cloth->id);

        // Create first return transfer
        $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/return-cloth", [
                'cloth_id' => $this->cloth->id,
            ]);

        // Attempt to create second return transfer
        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/return-cloth", [
                'cloth_id' => $this->cloth->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'A return transfer already exists for this cloth');
    }

    // ==================== WORKSHOP LOGS TESTS ====================

    public function test_can_view_workshop_logs()
    {
        // Create some logs
        WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'received',
            'cloth_status' => 'received',
            'received_at' => now(),
            'user_id' => $this->workshopManager->id,
        ]);

        WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'status_changed',
            'cloth_status' => 'processing',
            'notes' => 'Started processing',
            'user_id' => $this->workshopManager->id,
        ]);

        $response = $this->actingAs($this->workshopManager)
            ->getJson("/api/v1/workshops/{$this->workshop->id}/logs");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'workshop_id',
                        'cloth_id',
                        'action',
                        'cloth_status',
                        'notes',
                    ]
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_logs_by_action()
    {
        WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'received',
            'cloth_status' => 'received',
            'user_id' => $this->workshopManager->id,
        ]);

        WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'status_changed',
            'cloth_status' => 'processing',
            'user_id' => $this->workshopManager->id,
        ]);

        $response = $this->actingAs($this->workshopManager)
            ->getJson("/api/v1/workshops/{$this->workshop->id}/logs?action=received");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('received', $response->json('data.0.action'));
    }

    public function test_can_view_cloth_history()
    {
        // Move cloth to workshop
        $this->branch->inventory->clothes()->detach($this->cloth->id);
        $this->workshop->inventory->clothes()->attach($this->cloth->id);

        WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'received',
            'cloth_status' => 'received',
            'user_id' => $this->workshopManager->id,
        ]);

        $response = $this->actingAs($this->workshopManager)
            ->getJson("/api/v1/workshops/{$this->workshop->id}/cloth-history/{$this->cloth->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'cloth',
                'current_status',
                'is_in_workshop',
                'history',
            ])
            ->assertJsonPath('is_in_workshop', true)
            ->assertJsonPath('current_status', 'received');
    }

    // ==================== AUTOMATED TRANSFER COMMAND TESTS ====================

    public function test_scheduled_command_creates_transfer_2_days_before_delivery()
    {
        // The scheduled command logic is tested implicitly in other tests
        // Here we directly test that a transfer CAN be created when conditions are met
        
        // Verify branch has inventory with cloth
        $this->assertTrue(
            $this->branch->inventory->clothes()->where('clothes.id', $this->cloth->id)->exists(),
            'Cloth should be in branch inventory'
        );
        
        // Verify workshop is linked to branch
        $this->assertNotNull($this->branch->workshop);
        $this->assertEquals($this->workshop->id, $this->branch->workshop->id);

        // Create a rent with delivery in 2 days (format date for SQLite compatibility)
        $deliveryDate = Carbon::today()->addDays(2)->format('Y-m-d');
        $returnDate = Carbon::today()->addDays(5)->format('Y-m-d');
        
        $rent = Rent::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'cloth_id' => $this->cloth->id,
            'appointment_type' => 'rental_delivery',
            'delivery_date' => $deliveryDate,
            'return_date' => $returnDate,
            'status' => 'scheduled',
        ]);

        // Verify the rent can be found with the command's query
        $targetDate = Carbon::today()->addDays(2);
        $foundRents = Rent::where('appointment_type', 'rental_delivery')
            ->whereDate('delivery_date', $targetDate)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress', 'active'])
            ->whereNotNull('cloth_id')
            ->whereNotNull('branch_id')
            ->get();
        
        $this->assertCount(1, $foundRents, 'Should find 1 rent matching criteria');
        $this->assertEquals($rent->id, $foundRents->first()->id);

        // Run the command
        $exitCode = Artisan::call('workshop:create-pre-delivery-transfers');
        $this->assertEquals(0, $exitCode, 'Command should exit successfully');

        // Verify transfer was created
        $this->assertDatabaseHas('transfers', [
            'from_entity_type' => 'branch',
            'from_entity_id' => $this->branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $this->workshop->id,
            'status' => 'pending',
        ]);

        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'type' => 'workshop_transfer_incoming',
        ]);
    }

    public function test_scheduled_command_skips_if_transfer_exists()
    {
        // Create a rent with delivery in 2 days
        $rent = Rent::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'cloth_id' => $this->cloth->id,
            'appointment_type' => 'rental_delivery',
            'delivery_date' => Carbon::today()->addDays(2),
            'return_date' => Carbon::today()->addDays(5),
            'status' => 'scheduled',
        ]);

        // Create an existing transfer
        $transfer = Transfer::create([
            'from_entity_type' => 'branch',
            'from_entity_id' => $this->branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $this->workshop->id,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $this->cloth->id,
            'status' => 'pending',
        ]);

        // Run the command
        Artisan::call('workshop:create-pre-delivery-transfers');

        // Verify only one transfer exists
        $transferCount = Transfer::where('from_entity_type', 'branch')
            ->where('from_entity_id', $this->branch->id)
            ->where('to_entity_type', 'workshop')
            ->where('to_entity_id', $this->workshop->id)
            ->count();

        $this->assertEquals(1, $transferCount);
    }

    public function test_scheduled_command_skips_if_cloth_not_in_branch()
    {
        // Move cloth to workshop (not in branch)
        $this->branch->inventory->clothes()->detach($this->cloth->id);
        $this->workshop->inventory->clothes()->attach($this->cloth->id);

        // Create a rent with delivery in 2 days
        $rent = Rent::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'cloth_id' => $this->cloth->id,
            'appointment_type' => 'rental_delivery',
            'delivery_date' => Carbon::today()->addDays(2),
            'return_date' => Carbon::today()->addDays(5),
            'status' => 'scheduled',
        ]);

        // Run the command
        Artisan::call('workshop:create-pre-delivery-transfers');

        // Verify no new transfer was created
        $this->assertDatabaseMissing('transfers', [
            'from_entity_type' => 'branch',
            'from_entity_id' => $this->branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $this->workshop->id,
        ]);
    }

    public function test_scheduled_command_skips_if_branch_has_no_workshop()
    {
        // Create a branch without workshop
        $address = Address::first();
        $branchWithoutWorkshop = Branch::create([
            'branch_code' => 'BR-NO-WS',
            'name' => 'Branch Without Workshop',
            'address_id' => $address->id,
        ]);

        $branchInventory = Inventory::create([
            'name' => 'Branch Without Workshop Inventory',
            'inventoriable_type' => 'branch',
            'inventoriable_id' => $branchWithoutWorkshop->id,
        ]);

        // Move cloth to this branch
        $this->branch->inventory->clothes()->detach($this->cloth->id);
        $branchInventory->clothes()->attach($this->cloth->id);

        // Create a rent with delivery in 2 days
        $rent = Rent::create([
            'client_id' => $this->client->id,
            'branch_id' => $branchWithoutWorkshop->id,
            'cloth_id' => $this->cloth->id,
            'appointment_type' => 'rental_delivery',
            'delivery_date' => Carbon::today()->addDays(2),
            'return_date' => Carbon::today()->addDays(5),
            'status' => 'scheduled',
        ]);

        // Run the command
        Artisan::call('workshop:create-pre-delivery-transfers');

        // Verify no transfer was created
        $this->assertDatabaseMissing('transfers', [
            'from_entity_type' => 'branch',
            'from_entity_id' => $branchWithoutWorkshop->id,
        ]);
    }

    // ==================== UTILITY ENDPOINT TESTS ====================

    public function test_can_get_workshop_statuses()
    {
        $response = $this->actingAs($this->workshopManager)
            ->getJson('/api/v1/workshops/statuses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'received',
                    'processing',
                    'ready_for_delivery',
                ]
            ]);
    }

    public function test_can_get_workshop_actions()
    {
        $response = $this->actingAs($this->workshopManager)
            ->getJson('/api/v1/workshops/actions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'received',
                    'status_changed',
                    'returned',
                ]
            ]);
    }

    // ==================== COMPLETE WORKFLOW TEST ====================

    public function test_complete_workshop_flow()
    {
        // Step 1: Create a rent with delivery in 2 days (triggers automated transfer)
        $rent = Rent::create([
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'cloth_id' => $this->cloth->id,
            'appointment_type' => 'rental_delivery',
            'delivery_date' => Carbon::today()->addDays(2),
            'return_date' => Carbon::today()->addDays(5),
            'status' => 'scheduled',
        ]);

        // Step 2: Run automated transfer command
        Artisan::call('workshop:create-pre-delivery-transfers');

        $transfer = Transfer::where('from_entity_type', 'branch')
            ->where('to_entity_type', 'workshop')
            ->first();

        $this->assertNotNull($transfer);

        // Step 3: Workshop approves transfer
        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/approve-transfer/{$transfer->id}");

        $response->assertStatus(200);

        // Step 4: Workshop updates status to processing
        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/update-cloth-status", [
                'cloth_id' => $this->cloth->id,
                'status' => 'processing',
                'notes' => 'Cleaning the dress',
            ]);

        $response->assertStatus(200);

        // Step 5: Workshop updates status to ready
        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/update-cloth-status", [
                'cloth_id' => $this->cloth->id,
                'status' => 'ready_for_delivery',
                'notes' => 'Ready for customer',
            ]);

        $response->assertStatus(200);

        // Step 6: Workshop creates return transfer
        $response = $this->actingAs($this->workshopManager)
            ->postJson("/api/v1/workshops/{$this->workshop->id}/return-cloth", [
                'cloth_id' => $this->cloth->id,
                'notes' => 'Returning cleaned dress',
            ]);

        $response->assertStatus(201);

        // Step 7: Verify complete history
        $response = $this->actingAs($this->workshopManager)
            ->getJson("/api/v1/workshops/{$this->workshop->id}/cloth-history/{$this->cloth->id}");

        $response->assertStatus(200);
        $history = $response->json('history');

        // Should have 4 log entries: received, processing, ready_for_delivery, returned
        $this->assertCount(4, $history);
    }

    // ==================== MODEL TESTS ====================

    public function test_workshop_log_model_accessors()
    {
        $log = WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'received',
            'cloth_status' => 'received',
            'user_id' => $this->workshopManager->id,
        ]);

        $this->assertEquals('Cloth Received', $log->action_label);
        $this->assertEquals('Received', $log->status_label);
    }

    public function test_workshop_log_scopes()
    {
        WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'received',
            'cloth_status' => 'received',
            'user_id' => $this->workshopManager->id,
        ]);

        $log2 = WorkshopLog::create([
            'workshop_id' => $this->workshop->id,
            'cloth_id' => $this->cloth->id,
            'action' => 'status_changed',
            'cloth_status' => 'processing',
            'user_id' => $this->workshopManager->id,
        ]);

        // Test forWorkshop scope
        $logs = WorkshopLog::forWorkshop($this->workshop->id)->get();
        $this->assertCount(2, $logs);

        // Test forCloth scope
        $logs = WorkshopLog::forCloth($this->cloth->id)->get();
        $this->assertCount(2, $logs);

        // Test byAction scope
        $logs = WorkshopLog::byAction('status_changed')->get();
        $this->assertCount(1, $logs);

        // Test byStatus scope
        $logs = WorkshopLog::byStatus('processing')->get();
        $this->assertCount(1, $logs);
    }
}


