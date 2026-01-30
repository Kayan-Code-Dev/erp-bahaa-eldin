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

class CountryTest extends TestCase
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
            'countries.view',
            'countries.create',
            'countries.update',
            'countries.delete',
            'countries.export',
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

    /**
     * Test: List Countries
     */
    public function test_list_countries()
    {
        Country::factory()->count(5)->create();
        $user = $this->createUserWithPermission('countries.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/countries');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
        $this->assertGreaterThanOrEqual(5, count($response->json('data')));
    }

    /**
     * Test: List Countries - Unauthenticated
     */
    public function test_list_countries_unauthenticated()
    {
        $response = $this->getJson('/api/v1/countries');
        $response->assertStatus(401);
    }

    /**
     * Test: List Countries - Without Permission
     */
    public function test_list_countries_without_permission()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/countries');
        $response->assertStatus(403);
    }

    /**
     * Test: Create Country
     */
    public function test_create_country()
    {
        $user = $this->createUserWithPermission('countries.create');
        Sanctum::actingAs($user);

        $data = ['name' => 'Egypt'];
        $response = $this->postJson('/api/v1/countries', $data);

        $response->assertStatus(201)
            ->assertJson(['name' => 'Egypt']);
        $this->assertDatabaseHas('countries', ['name' => 'Egypt']);
    }

    /**
     * Test: Create Country - Without Permission
     */
    public function test_create_country_without_permission()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/countries', ['name' => 'Egypt']);
        $response->assertStatus(403);
    }

    /**
     * Test: Create Country with Duplicate Name (Should Fail)
     */
    public function test_create_country_with_duplicate_name()
    {
        Country::factory()->create(['name' => 'Egypt']);
        $user = $this->createUserWithPermission('countries.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/countries', ['name' => 'Egypt']);
        $response->assertStatus(422);
    }

    /**
     * Test: Create Country - Missing Required Fields
     */
    public function test_create_country_missing_required_fields()
    {
        $user = $this->createUserWithPermission('countries.create');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/countries', []);
        $response->assertStatus(422);
    }

    /**
     * Test: Show Country
     */
    public function test_show_country()
    {
        $country = Country::factory()->create(['name' => 'Egypt']);
        $user = $this->createUserWithPermission('countries.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/countries/{$country->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $country->id, 'name' => 'Egypt']);
    }

    /**
     * Test: Show Country - Not Found
     */
    public function test_show_country_not_found()
    {
        $user = $this->createUserWithPermission('countries.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/countries/99999');
        $response->assertStatus(404);
    }

    /**
     * Test: Update Country
     */
    public function test_update_country()
    {
        $country = Country::factory()->create(['name' => 'Egypt']);
        $user = $this->createUserWithPermission('countries.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/countries/{$country->id}", ['name' => 'New Egypt']);

        $response->assertStatus(200)
            ->assertJson(['name' => 'New Egypt']);
        $this->assertDatabaseHas('countries', ['id' => $country->id, 'name' => 'New Egypt']);
    }

    /**
     * Test: Update Country - Without Permission
     */
    public function test_update_country_without_permission()
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/countries/{$country->id}", ['name' => 'New Name']);
        $response->assertStatus(403);
    }

    /**
     * Test: Delete Country
     */
    public function test_delete_country()
    {
        $country = Country::factory()->create();
        $user = $this->createUserWithPermission('countries.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/countries/{$country->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('countries', ['id' => $country->id]);
    }

    /**
     * Test: Delete Country - Without Permission
     */
    public function test_delete_country_without_permission()
    {
        $country = Country::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/countries/{$country->id}");
        $response->assertStatus(403);
    }

    /**
     * Test: Delete Country with Cities (Should Fail)
     */
    public function test_delete_country_with_cities()
    {
        $country = Country::factory()->create();
        City::factory()->create(['country_id' => $country->id]);
        $user = $this->createUserWithPermission('countries.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/countries/{$country->id}");

        // Should fail due to foreign key constraint
        $response->assertStatus(422);
    }

    /**
     * Test: Export Countries
     */
    public function test_export_countries()
    {
        Country::factory()->count(3)->create();
        $user = $this->createUserWithPermission('countries.export');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/countries/export');

        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    /**
     * Test: Export Countries - Without Permission
     */
    public function test_export_countries_without_permission()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/countries/export');
        $response->assertStatus(403);
    }

    /**
     * Test: Super Admin Can Access All Endpoints
     */
    public function test_super_admin_can_access_all_country_endpoints()
    {
        $superAdmin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($superAdmin);

        // List
        $response = $this->getJson('/api/v1/countries');
        $response->assertStatus(200);

        // Create
        $response = $this->postJson('/api/v1/countries', ['name' => 'Test Country']);
        $response->assertStatus(201);

        $countryId = $response->json('id');

        // Show
        $response = $this->getJson("/api/v1/countries/{$countryId}");
        $response->assertStatus(200);

        // Update
        $response = $this->putJson("/api/v1/countries/{$countryId}", ['name' => 'Updated']);
        $response->assertStatus(200);

        // Delete
        $response = $this->deleteJson("/api/v1/countries/{$countryId}");
        $response->assertStatus(204);

        // Export
        $response = $this->getJson('/api/v1/countries/export');
        $response->assertStatus(200);
    }
}


