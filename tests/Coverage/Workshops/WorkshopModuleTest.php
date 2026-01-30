<?php

namespace Tests\Coverage\Workshops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Workshop;
use App\Models\Branch;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Inventory;
use App\Models\Transfer;
use App\Models\TransferItem;
use Laravel\Sanctum\Sanctum;

class WorkshopModuleTest extends TestCase
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
            'workshops.view', 'workshops.create', 'workshops.update', 'workshops.delete',
            'workshops.export', 'workshops.manage-clothes', 'workshops.approve-transfers',
            'workshops.update-status', 'workshops.return-cloth', 'workshops.view-logs',
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

    public function test_list_workshops()
    {
        Workshop::factory()->count(5)->create();
        $user = $this->createUserWithPermission('workshops.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/workshops');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_workshop()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $branch = Branch::factory()->create();
        $user = $this->createUserWithPermission('workshops.create');
        Sanctum::actingAs($user);

        $data = [
            'workshop_code' => 'WS-001',
            'name' => 'Test Workshop',
            'branch_id' => $branch->id,
            'address' => [
                'street' => 'Test Street',
                'building' => '1',
                'city_id' => $city->id,
            ],
        ];
        $response = $this->postJson('/api/v1/workshops', $data);
        $response->assertStatus(201)->assertJson(['name' => 'Test Workshop']);
        $this->assertDatabaseHas('workshops', ['workshop_code' => 'WS-001']);
    }

    public function test_show_workshop()
    {
        $workshop = Workshop::factory()->create();
        $user = $this->createUserWithPermission('workshops.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/workshops/{$workshop->id}");
        $response->assertStatus(200)->assertJson(['id' => $workshop->id]);
    }

    public function test_update_workshop()
    {
        $workshop = Workshop::factory()->create();
        $user = $this->createUserWithPermission('workshops.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/workshops/{$workshop->id}", ['name' => 'Updated Workshop']);
        $response->assertStatus(200)->assertJson(['name' => 'Updated Workshop']);
    }

    public function test_delete_workshop()
    {
        $workshop = Workshop::factory()->create();
        $user = $this->createUserWithPermission('workshops.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/workshops/{$workshop->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('workshops', ['id' => $workshop->id]);
    }

    public function test_get_workshop_clothes()
    {
        $workshop = Workshop::factory()->create();
        $user = $this->createUserWithPermission('workshops.manage-clothes');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/workshops/{$workshop->id}/clothes");
        $response->assertStatus(200);
    }

    public function test_export_workshops()
    {
        Workshop::factory()->count(3)->create();
        $user = $this->createUserWithPermission('workshops.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/workshops/export');
        $response->assertStatus(200);
    }

    public function test_get_pending_transfers()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id, 'branch_id' => $branch->id]);
        $transfer = Transfer::factory()->create([
            'from_entity_type' => Branch::class,
            'from_entity_id' => $branch->id,
            'to_entity_type' => Workshop::class,
            'to_entity_id' => $workshop->id,
            'status' => 'pending',
        ]);
        $user = $this->createUserWithPermission('workshops.approve-transfers');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/workshops/{$workshop->id}/pending-transfers");
        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_approve_transfer_receive_clothes()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id, 'branch_id' => $branch->id]);
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        $branch->inventory->clothes()->attach($cloth->id);

        $transfer = Transfer::factory()->create([
            'from_entity_type' => Branch::class,
            'from_entity_id' => $branch->id,
            'to_entity_type' => Workshop::class,
            'to_entity_id' => $workshop->id,
            'status' => 'pending',
        ]);
        TransferItem::create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth->id,
            'status' => 'pending',
        ]);

        $user = $this->createUserWithPermission('workshops.approve-transfers');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/workshops/{$workshop->id}/approve-transfer/{$transfer->id}");
        $response->assertStatus(200);
    }

    public function test_update_cloth_status_in_workshop()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id]);
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        $workshop->inventory->clothes()->attach($cloth->id);

        $user = $this->createUserWithPermission('workshops.update-status');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/workshops/{$workshop->id}/update-cloth-status", [
            'cloth_id' => $cloth->id,
            'status' => 'processing',
            'notes' => 'Starting processing',
        ]);
        $response->assertStatus(200);
    }

    public function test_update_cloth_status_cloth_not_in_workshop_fails()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id]);
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        // Cloth NOT in workshop inventory

        $user = $this->createUserWithPermission('workshops.update-status');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/workshops/{$workshop->id}/update-cloth-status", [
            'cloth_id' => $cloth->id,
            'status' => 'processing',
        ]);
        $response->assertStatus(422);
    }

    public function test_return_cloth_from_workshop()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $branch = Branch::factory()->create(['address_id' => $address->id]);
        $workshop = Workshop::factory()->create(['address_id' => $address->id, 'branch_id' => $branch->id]);
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        $workshop->inventory->clothes()->attach($cloth->id);

        $user = $this->createUserWithPermission('workshops.return-cloth');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/workshops/{$workshop->id}/return-cloth", [
            'cloth_id' => $cloth->id,
            'notes' => 'Returned after repair',
        ]);
        $response->assertStatus(200);
    }
}

