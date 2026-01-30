<?php

namespace Tests\Coverage\CoreEntities;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Country;
use App\Models\City;
use Laravel\Sanctum\Sanctum;

class CityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['cities.view', 'cities.create', 'cities.update', 'cities.delete', 'cities.export'];
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

    public function test_list_cities()
    {
        $country = Country::factory()->create();
        City::factory()->count(5)->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('cities.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cities');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_list_cities_unauthenticated()
    {
        $response = $this->getJson('/api/v1/cities');
        $response->assertStatus(401);
    }

    public function test_list_cities_without_permission()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/cities');
        $response->assertStatus(403);
    }

    public function test_create_city()
    {
        $country = Country::factory()->create();
        $user = $this->createUserWithPermission('cities.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/cities', ['name' => 'Cairo', 'country_id' => $country->id]);
        $response->assertStatus(201)->assertJson(['name' => 'Cairo']);
        $this->assertDatabaseHas('cities', ['name' => 'Cairo', 'country_id' => $country->id]);
    }

    public function test_create_city_without_permission()
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/cities', ['name' => 'Cairo', 'country_id' => $country->id]);
        $response->assertStatus(403);
    }

    public function test_create_city_missing_required_fields()
    {
        $user = $this->createUserWithPermission('cities.create');
        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/cities', []);
        $response->assertStatus(422);
    }

    public function test_show_city()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('cities.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/cities/{$city->id}");
        $response->assertStatus(200)->assertJson(['id' => $city->id]);
    }

    public function test_show_city_not_found()
    {
        $user = $this->createUserWithPermission('cities.view');
        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/cities/99999');
        $response->assertStatus(404);
    }

    public function test_update_city()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('cities.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/cities/{$city->id}", ['name' => 'New Cairo']);
        $response->assertStatus(200)->assertJson(['name' => 'New Cairo']);
        $this->assertDatabaseHas('cities', ['id' => $city->id, 'name' => 'New Cairo']);
    }

    public function test_delete_city()
    {
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('cities.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/cities/{$city->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('cities', ['id' => $city->id]);
    }

    public function test_export_cities()
    {
        $country = Country::factory()->create();
        City::factory()->count(3)->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('cities.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cities/export');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_all_city_endpoints()
    {
        $country = Country::factory()->create();
        $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/v1/cities');
        $response->assertStatus(200);

        $response = $this->postJson('/api/v1/cities', ['name' => 'Test City', 'country_id' => $country->id]);
        $response->assertStatus(201);
        $cityId = $response->json('id');

        $response = $this->getJson("/api/v1/cities/{$cityId}");
        $response->assertStatus(200);

        $response = $this->putJson("/api/v1/cities/{$cityId}", ['name' => 'Updated']);
        $response->assertStatus(200);

        $response = $this->deleteJson("/api/v1/cities/{$cityId}");
        $response->assertStatus(204);
    }
}


