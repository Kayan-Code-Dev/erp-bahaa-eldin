<?php

namespace Tests\Coverage\Factory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Factory;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use Laravel\Sanctum\Sanctum;

class FactoryModuleCoverageTest extends TestCase
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
            'factories.view', 'factories.create', 'factories.update', 'factories.delete',
            'factories.export', 'factories.manage',
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

    public function test_list_factories()
    {
        Factory::factory()->count(5)->create();
        $user = $this->createUserWithPermission('factories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/factories');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_factory()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('factories.create');
        Sanctum::actingAs($user);

        $data = [
            'factory_code' => 'FA-001',
            'name' => 'Test Factory',
            'address' => [
                'street' => 'Test Street',
                'building' => '1',
                'city_id' => $city->id,
            ],
        ];
        $response = $this->postJson('/api/v1/factories', $data);
        $response->assertStatus(201)->assertJson(['name' => 'Test Factory']);
        $this->assertDatabaseHas('factories', ['factory_code' => 'FA-001']);
    }

    public function test_show_factory()
    {
        $factory = Factory::factory()->create();
        $user = $this->createUserWithPermission('factories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/factories/{$factory->id}");
        $response->assertStatus(200)->assertJson(['id' => $factory->id]);
    }

    public function test_update_factory()
    {
        $factory = Factory::factory()->create();
        $user = $this->createUserWithPermission('factories.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/factories/{$factory->id}", ['name' => 'Updated Factory']);
        $response->assertStatus(200)->assertJson(['name' => 'Updated Factory']);
    }

    public function test_delete_factory()
    {
        $factory = Factory::factory()->create();
        $user = $this->createUserWithPermission('factories.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/factories/{$factory->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('factories', ['id' => $factory->id]);
    }

    public function test_export_factories()
    {
        Factory::factory()->count(3)->create();
        $user = $this->createUserWithPermission('factories.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/factories/export');
        $response->assertStatus(200);
    }

    public function test_get_factory_statistics()
    {
        Factory::factory()->count(3)->create();
        $user = $this->createUserWithPermission('factories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/factories/statistics');
        $response->assertStatus(200);
        $response->assertJsonStructure(['total_factories']);
    }

    public function test_get_factory_ranking()
    {
        Factory::factory()->count(3)->create();
        $user = $this->createUserWithPermission('factories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/factories/ranking');
        $response->assertStatus(200);
    }

    public function test_get_factory_workload()
    {
        Factory::factory()->count(3)->create();
        $user = $this->createUserWithPermission('factories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/factories/workload');
        $response->assertStatus(200);
    }

    public function test_get_factory_recommendation()
    {
        Factory::factory()->count(2)->create();
        $user = $this->createUserWithPermission('factories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/factories/recommend');
        $response->assertStatus(200);
    }

    public function test_get_factory_summary()
    {
        $factory = Factory::factory()->create();
        $user = $this->createUserWithPermission('factories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/factories/{$factory->id}/summary");
        $response->assertStatus(200);
    }

    public function test_get_factory_trends()
    {
        $factory = Factory::factory()->create();
        $user = $this->createUserWithPermission('factories.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/factories/{$factory->id}/trends");
        $response->assertStatus(200);
    }

    public function test_recalculate_factory_statistics()
    {
        $factory = Factory::factory()->create();
        $user = $this->createUserWithPermission('factories.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/factories/{$factory->id}/recalculate");
        $response->assertStatus(200);
    }
}

