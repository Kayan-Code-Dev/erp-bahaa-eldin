<?php

namespace Tests\Coverage\Authentication;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Client;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Login with Valid Credentials
     * 
     * Test Steps:
     * 1. Create a user with known credentials
     * 2. Send POST request to /api/v1/login with valid email and password
     * 3. Verify response contains user object and token
     */
    public function test_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals($user->id, $response->json('user.id'));
    }

    /**
     * Test: Login with Invalid Credentials
     * 
     * Test Steps:
     * 1. Create a user with known credentials
     * 2. Send POST request with wrong password
     * 3. Verify error response
     */
    public function test_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    /**
     * Test: Login with Non-existent User
     * 
     * Test Steps:
     * 1. Send POST request with email that doesn't exist
     * 2. Verify error response
     */
    public function test_login_with_nonexistent_user()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    /**
     * Test: Logout with Valid Token
     * 
     * Test Steps:
     * 1. Login to get a token
     * 2. Send POST request to /api/v1/logout with token in Authorization header
     * 3. Verify logout success
     * 4. Verify token is revoked (try to use it again - should fail)
     */
    public function test_logout_with_valid_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Login to get token
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $token = $loginResponse->json('token');

        // Logout
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out']);

        // Verify token is revoked - try to use it again
        $protectedResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/clients');

        $protectedResponse->assertStatus(401);
    }

    /**
     * Test: Logout without Authentication
     * 
     * Test Steps:
     * 1. Send POST request without token
     * 2. Verify error response
     */
    public function test_logout_without_authentication()
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    /**
     * Test: User Has Permission Through Role
     * 
     * Test Steps:
     * 1. Create a role with specific permission
     * 2. Assign role to user
     * 3. Verify user has the permission
     */
    public function test_user_has_permission_through_role()
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'test_role',
            'description' => 'Test Role',
        ]);

        $permission = Permission::findOrCreateByName('clients.view');
        $role->assignPermission($permission->name);

        $user->assignRole($role);

        $this->assertTrue($user->hasPermission('clients.view'));
        $this->assertTrue($user->hasRole('test_role'));
    }

    /**
     * Test: User Has Multiple Roles
     * 
     * Test Steps:
     * 1. Create user with multiple roles
     * 2. Verify user has permissions from all roles
     */
    public function test_user_has_multiple_roles()
    {
        $user = User::factory()->create();

        $role1 = Role::create(['name' => 'role1', 'description' => 'Role 1']);
        $role2 = Role::create(['name' => 'role2', 'description' => 'Role 2']);

        $permission1 = Permission::findOrCreateByName('clients.view');
        $permission2 = Permission::findOrCreateByName('orders.view');

        $role1->assignPermission($permission1->name);
        $role2->assignPermission($permission2->name);

        $user->assignRole($role1);
        $user->assignRole($role2);

        $this->assertTrue($user->hasPermission('clients.view'));
        $this->assertTrue($user->hasPermission('orders.view'));
        $this->assertTrue($user->hasRole('role1'));
        $this->assertTrue($user->hasRole('role2'));
    }

    /**
     * Test: Super Admin Has All Permissions
     * 
     * Test Steps:
     * 1. Create/use super admin user (admin@admin.com)
     * 2. Verify user has all permissions regardless of roles
     * 3. Verify user can access any endpoint
     */
    public function test_super_admin_has_all_permissions()
    {
        $superAdmin = User::factory()->create([
            'email' => User::SUPER_ADMIN_EMAIL,
        ]);

        Sanctum::actingAs($superAdmin);

        // Verify super admin can access protected endpoint without explicit permission
        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200);
    }

    /**
     * Test: Access Allowed with Permission
     * 
     * Test Steps:
     * 1. Create user with required permission
     * 2. Make request to protected endpoint
     * 3. Verify access is granted
     */
    public function test_access_allowed_with_permission()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test Role']);

        $permission = Permission::findOrCreateByName('clients.view');
        $role->assignPermission($permission->name);

        $user->assignRole($role);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200);
    }

    /**
     * Test: Access Denied without Permission
     * 
     * Test Steps:
     * 1. Create user without required permission
     * 2. Make request to protected endpoint
     * 3. Verify access is denied
     */
    public function test_access_denied_without_permission()
    {
        $user = User::factory()->create();
        // Don't assign any roles/permissions
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden. You do not have the required permission.']);
    }

    /**
     * Test: Access Denied when Unauthenticated
     * 
     * Test Steps:
     * 1. Make request without authentication token
     * 2. Verify access is denied
     */
    public function test_access_denied_when_unauthenticated()
    {
        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(401);
    }
}





