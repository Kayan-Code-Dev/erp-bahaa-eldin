<?php

namespace Tests\Coverage\Transfers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Transfer;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Inventory;
use App\Models\TransferItem;
use App\Models\Factory;
use Laravel\Sanctum\Sanctum;

class TransferModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = [
            'transfers.view', 'transfers.create', 'transfers.update', 'transfers.delete',
            'transfers.approve', 'transfers.reject', 'transfers.export',
        ];
        foreach ($permissions as $perm) {
            Permission::findOrCreateByName($perm);
        }
    }

    protected function createUserWithPermission(string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test Role']);
        $role->assignPermission($permission);
        $user->assignRole($role);
        return $user;
    }

    protected function createTestData(): array
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id]);
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        $branch->inventory->clothes()->attach($cloth->id);
        return ['branch' => $branch, 'workshop' => $workshop, 'cloth' => $cloth];
    }

    public function test_list_transfers()
    {
        Transfer::factory()->count(5)->create();
        $user = $this->createUserWithPermission('transfers.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transfers');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_transfer()
    {
        $data = $this->createTestData();
        $user = $this->createUserWithPermission('transfers.create');
        Sanctum::actingAs($user);

        $transferData = [
            'from_entity_type' => 'branch',
            'from_entity_id' => $data['branch']->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $data['workshop']->id,
            'transfer_date' => now()->format('Y-m-d'),
            'cloth_ids' => [$data['cloth']->id],
        ];
        $response = $this->postJson('/api/v1/transfers', $transferData);
        $response->assertStatus(201);
        $this->assertDatabaseHas('transfers', [
            'from_entity_type' => Branch::class,
            'from_entity_id' => $data['branch']->id,
        ]);
    }

    public function test_show_transfer()
    {
        $transfer = Transfer::factory()->create();
        $user = $this->createUserWithPermission('transfers.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/transfers/{$transfer->id}");
        $response->assertStatus(200)->assertJson(['id' => $transfer->id]);
    }

    public function test_approve_transfer()
    {
        $data = $this->createTestData();
        $transfer = Transfer::factory()->create([
            'from_entity_type' => Branch::class,
            'from_entity_id' => $data['branch']->id,
            'to_entity_type' => Workshop::class,
            'to_entity_id' => $data['workshop']->id,
            'status' => 'pending',
        ]);
        \App\Models\TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $data['cloth']->id,
            'status' => 'pending',
        ]);
        $user = $this->createUserWithPermission('transfers.approve');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/approve");
        $response->assertStatus(200);
    }

    public function test_reject_transfer()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);
        $user = $this->createUserWithPermission('transfers.reject');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/reject", ['notes' => 'Rejection reason']);
        $response->assertStatus(200);
    }

    public function test_delete_transfer()
    {
        $transfer = Transfer::factory()->create();
        $user = $this->createUserWithPermission('transfers.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/transfers/{$transfer->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('transfers', ['id' => $transfer->id]);
    }

    public function test_export_transfers()
    {
        Transfer::factory()->count(3)->create();
        $user = $this->createUserWithPermission('transfers.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transfers/export');
        $response->assertStatus(200);
    }

    public function test_create_transfer_with_same_source_and_destination_fails()
    {
        $data = $this->createTestData();
        $user = $this->createUserWithPermission('transfers.create');
        Sanctum::actingAs($user);

        $transferData = [
            'from_entity_type' => 'branch',
            'from_entity_id' => $data['branch']->id,
            'to_entity_type' => 'branch',
            'to_entity_id' => $data['branch']->id,
            'transfer_date' => now()->format('Y-m-d'),
            'cloth_ids' => [$data['cloth']->id],
        ];
        $response = $this->postJson('/api/v1/transfers', $transferData);
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Source and destination entities must be different']);
    }

    public function test_create_transfer_with_cloth_not_in_source_inventory_fails()
    {
        $data = $this->createTestData();
        $clothType2 = ClothType::factory()->create();
        $cloth2 = Cloth::factory()->create(['cloth_type_id' => $clothType2->id]);
        // cloth2 is NOT in branch inventory
        $user = $this->createUserWithPermission('transfers.create');
        Sanctum::actingAs($user);

        $transferData = [
            'from_entity_type' => 'branch',
            'from_entity_id' => $data['branch']->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $data['workshop']->id,
            'transfer_date' => now()->format('Y-m-d'),
            'cloth_ids' => [$cloth2->id],
        ];
        $response = $this->postJson('/api/v1/transfers', $transferData);
        $response->assertStatus(422);
    }

    public function test_update_transfer()
    {
        $data = $this->createTestData();
        $transfer = Transfer::factory()->create([
            'from_entity_type' => Branch::class,
            'from_entity_id' => $data['branch']->id,
            'to_entity_type' => Workshop::class,
            'to_entity_id' => $data['workshop']->id,
            'status' => 'pending',
        ]);
        $user = $this->createUserWithPermission('transfers.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/transfers/{$transfer->id}", [
            'notes' => 'Updated notes',
        ]);
        $response->assertStatus(200);
        $transfer->refresh();
        $this->assertEquals('Updated notes', $transfer->notes);
    }

    public function test_approve_transfer_items_partial()
    {
        $data = $this->createTestData();
        $clothType2 = ClothType::factory()->create();
        $cloth2 = Cloth::factory()->create(['cloth_type_id' => $clothType2->id]);
        $data['branch']->inventory->clothes()->attach($cloth2->id);

        $transfer = Transfer::factory()->create([
            'from_entity_type' => Branch::class,
            'from_entity_id' => $data['branch']->id,
            'to_entity_type' => Workshop::class,
            'to_entity_id' => $data['workshop']->id,
            'status' => 'pending',
        ]);
        $item1 = TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $data['cloth']->id,
            'status' => 'pending',
        ]);
        $item2 = TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth2->id,
            'status' => 'pending',
        ]);

        $user = $this->createUserWithPermission('transfers.approve');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/approve-items", [
            'item_ids' => [$item1->id],
        ]);
        $response->assertStatus(200);
        $item1->refresh();
        $item2->refresh();
        $this->assertEquals('approved', $item1->status);
        $this->assertEquals('pending', $item2->status);
    }

    public function test_reject_transfer_items_partial()
    {
        $data = $this->createTestData();
        $clothType2 = ClothType::factory()->create();
        $cloth2 = Cloth::factory()->create(['cloth_type_id' => $clothType2->id]);
        $data['branch']->inventory->clothes()->attach($cloth2->id);

        $transfer = Transfer::factory()->create([
            'from_entity_type' => Branch::class,
            'from_entity_id' => $data['branch']->id,
            'to_entity_type' => Workshop::class,
            'to_entity_id' => $data['workshop']->id,
            'status' => 'pending',
        ]);
        $item1 = TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $data['cloth']->id,
            'status' => 'pending',
        ]);
        $item2 = TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth2->id,
            'status' => 'pending',
        ]);

        $user = $this->createUserWithPermission('transfers.reject');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/reject-items", [
            'item_ids' => [$item1->id],
        ]);
        $response->assertStatus(200);
        $item1->refresh();
        $item2->refresh();
        $this->assertEquals('rejected', $item1->status);
        $this->assertEquals('pending', $item2->status);
    }

    public function test_update_transfer_already_approved_fails()
    {
        $data = $this->createTestData();
        $transfer = Transfer::factory()->create([
            'from_entity_type' => Branch::class,
            'from_entity_id' => $data['branch']->id,
            'to_entity_type' => Workshop::class,
            'to_entity_id' => $data['workshop']->id,
            'status' => 'approved',
        ]);
        $user = $this->createUserWithPermission('transfers.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/transfers/{$transfer->id}", [
            'notes' => 'Updated notes',
        ]);
        $response->assertStatus(422);
    }
}

