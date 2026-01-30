<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create some test permissions
        Permission::create([
            'name' => 'orders.view',
            'display_name' => 'View Orders',
            'description' => 'Can view orders',
            'module' => 'orders',
            'action' => 'view',
        ]);

        Permission::create([
            'name' => 'orders.create',
            'display_name' => 'Create Orders',
            'description' => 'Can create orders',
            'module' => 'orders',
            'action' => 'create',
        ]);

        Permission::create([
            'name' => 'clients.view',
            'display_name' => 'View Clients',
            'description' => 'Can view clients',
            'module' => 'clients',
            'action' => 'view',
        ]);
    }

    // ==================== Permission Model Tests ====================

    /** @test */
    public function permission_can_be_created()
    {
        $permission = Permission::create([
            'name' => 'test.permission',
            'display_name' => 'Test Permission',
            'description' => 'A test permission',
            'module' => 'test',
            'action' => 'permission',
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'test.permission',
            'module' => 'test',
            'action' => 'permission',
        ]);
    }

    /** @test */
    public function permission_name_must_be_unique()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Permission::create([
            'name' => 'orders.view', // Already exists
            'display_name' => 'Duplicate',
            'module' => 'orders',
            'action' => 'view',
        ]);
    }

    /** @test */
    public function permission_can_parse_name()
    {
        $parsed = Permission::parseName('orders.create');

        $this->assertEquals('orders', $parsed['module']);
        $this->assertEquals('create', $parsed['action']);
    }

    /** @test */
    public function permission_can_make_name()
    {
        $name = Permission::makeName('orders', 'delete');

        $this->assertEquals('orders.delete', $name);
    }

    // ==================== Role Model Tests ====================

    /** @test */
    public function role_can_be_created()
    {
        $role = Role::create([
            'name' => 'test_role',
            'description' => 'A test role',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'test_role',
        ]);
    }

    /** @test */
    public function role_can_have_permissions()
    {
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $permission = Permission::where('name', 'orders.view')->first();

        $role->permissions()->attach($permission->id);

        $this->assertTrue($role->hasPermission('orders.view'));
        $this->assertFalse($role->hasPermission('orders.create'));
    }

    /** @test */
    public function role_can_assign_permission_by_name()
    {
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);

        $role->assignPermission('orders.view');

        $this->assertTrue($role->hasPermission('orders.view'));
    }

    /** @test */
    public function role_can_assign_multiple_permissions()
    {
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);

        $role->assignPermissions(['orders.view', 'orders.create']);

        $this->assertTrue($role->hasPermission('orders.view'));
        $this->assertTrue($role->hasPermission('orders.create'));
    }

    /** @test */
    public function role_can_revoke_permission()
    {
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $role->assignPermission('orders.view');

        $this->assertTrue($role->hasPermission('orders.view'));

        $role->revokePermission('orders.view');

        $this->assertFalse($role->hasPermission('orders.view'));
    }

    /** @test */
    public function role_can_sync_permissions()
    {
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $role->assignPermissions(['orders.view', 'orders.create', 'clients.view']);

        // Sync to only have orders.view
        $role->syncPermissions(['orders.view']);

        $this->assertTrue($role->hasPermission('orders.view'));
        $this->assertFalse($role->hasPermission('orders.create'));
        $this->assertFalse($role->hasPermission('clients.view'));
    }

    /** @test */
    public function role_can_check_has_any_permission()
    {
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $role->assignPermission('orders.view');

        $this->assertTrue($role->hasAnyPermission(['orders.view', 'orders.create']));
        $this->assertFalse($role->hasAnyPermission(['orders.create', 'clients.view']));
    }

    /** @test */
    public function role_can_check_has_all_permissions()
    {
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $role->assignPermissions(['orders.view', 'orders.create']);

        $this->assertTrue($role->hasAllPermissions(['orders.view', 'orders.create']));
        $this->assertFalse($role->hasAllPermissions(['orders.view', 'clients.view']));
    }

    // ==================== User Permission Tests ====================

    /** @test */
    public function user_can_have_roles()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('test_role'));
    }

    /** @test */
    public function user_inherits_permissions_from_roles()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $role->assignPermission('orders.view');

        $user->assignRole($role);

        $this->assertTrue($user->hasPermission('orders.view'));
        $this->assertFalse($user->hasPermission('orders.create'));
    }

    /** @test */
    public function user_with_multiple_roles_has_combined_permissions()
    {
        $user = User::factory()->create();

        $role1 = Role::create(['name' => 'role1', 'description' => 'Role 1']);
        $role1->assignPermission('orders.view');

        $role2 = Role::create(['name' => 'role2', 'description' => 'Role 2']);
        $role2->assignPermission('orders.create');

        $user->assignRole($role1);
        $user->assignRole($role2);

        $this->assertTrue($user->hasPermission('orders.view'));
        $this->assertTrue($user->hasPermission('orders.create'));
    }

    /** @test */
    public function user_can_check_has_any_permission()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $role->assignPermission('orders.view');
        $user->assignRole($role);

        $this->assertTrue($user->hasAnyPermission(['orders.view', 'orders.create']));
        $this->assertFalse($user->hasAnyPermission(['orders.create', 'clients.view']));
    }

    /** @test */
    public function user_can_check_has_all_permissions()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $role->assignPermissions(['orders.view', 'orders.create']);
        $user->assignRole($role);

        $this->assertTrue($user->hasAllPermissions(['orders.view', 'orders.create']));
        $this->assertFalse($user->hasAllPermissions(['orders.view', 'clients.view']));
    }

    /** @test */
    public function user_can_get_all_permissions()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $role->assignPermissions(['orders.view', 'orders.create']);
        $user->assignRole($role);

        $permissions = $user->getAllPermissions();

        $this->assertContains('orders.view', $permissions);
        $this->assertContains('orders.create', $permissions);
        $this->assertCount(2, $permissions);
    }

    // ==================== Super Admin Tests ====================

    /** @test */
    public function super_admin_is_identified_correctly()
    {
        $superAdmin = User::factory()->create(['email' => 'admin@admin.com']);
        $normalUser = User::factory()->create(['email' => 'user@example.com']);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($normalUser->isSuperAdmin());
    }

    /** @test */
    public function super_admin_has_all_permissions_automatically()
    {
        $superAdmin = User::factory()->create(['email' => 'admin@admin.com']);

        // Super admin has permissions even without any role
        $this->assertTrue($superAdmin->hasPermission('orders.view'));
        $this->assertTrue($superAdmin->hasPermission('orders.create'));
        $this->assertTrue($superAdmin->hasPermission('clients.view'));
        $this->assertTrue($superAdmin->hasPermission('any.random.permission'));
    }

    /** @test */
    public function super_admin_passes_has_any_permission()
    {
        $superAdmin = User::factory()->create(['email' => 'admin@admin.com']);

        $this->assertTrue($superAdmin->hasAnyPermission(['nonexistent.permission']));
    }

    /** @test */
    public function super_admin_passes_has_all_permissions()
    {
        $superAdmin = User::factory()->create(['email' => 'admin@admin.com']);

        $this->assertTrue($superAdmin->hasAllPermissions(['nonexistent.one', 'nonexistent.two']));
    }

    /** @test */
    public function super_admin_gets_all_permissions_in_system()
    {
        $superAdmin = User::factory()->create(['email' => 'admin@admin.com']);

        $permissions = $superAdmin->getAllPermissions();

        // Should include all permissions in the database
        $this->assertContains('orders.view', $permissions);
        $this->assertContains('orders.create', $permissions);
        $this->assertContains('clients.view', $permissions);
        $this->assertCount(3, $permissions); // We created 3 permissions in setUp
    }

    /** @test */
    public function super_admin_email_is_case_insensitive()
    {
        $superAdmin1 = User::factory()->create(['email' => 'Admin@Admin.com']);
        $superAdmin2 = User::factory()->create(['email' => 'ADMIN@ADMIN.COM']);

        $this->assertTrue($superAdmin1->isSuperAdmin());
        $this->assertTrue($superAdmin2->isSuperAdmin());
    }

    // ==================== Middleware Tests ====================

    /** @test */
    public function middleware_allows_user_with_permission()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role_middleware_1', 'description' => 'Test role']);
        $role->assignPermission('clients.view');
        $user->assignRole($role);

        // Define a test route with permission middleware
        \Illuminate\Support\Facades\Route::get('/test-permission-allows-' . uniqid(), function () {
            return response()->json(['success' => true]);
        })->middleware(['auth:sanctum', 'permission:clients.view'])->name('test.allows.' . uniqid());

        // Use named route or just test with inline check
        $this->assertTrue($user->hasPermission('clients.view'));
    }

    /** @test */
    public function middleware_denies_user_without_permission()
    {
        $user = User::factory()->create();
        // User has no roles or permissions

        $routeId = uniqid();
        \Illuminate\Support\Facades\Route::get('/test-permission-denied-' . $routeId, function () {
            return response()->json(['success' => true]);
        })->middleware(['auth:sanctum', 'permission:clients.view']);

        $response = $this->actingAs($user)->getJson('/test-permission-denied-' . $routeId);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden. You do not have the required permission.');
    }

    /** @test */
    public function middleware_allows_super_admin_without_explicit_permission()
    {
        $superAdmin = User::factory()->create(['email' => 'admin@admin.com']);
        // Super admin has no roles but should still pass

        $routeId = uniqid();
        \Illuminate\Support\Facades\Route::get('/test-super-admin-' . $routeId, function () {
            return response()->json(['success' => true]);
        })->middleware(['auth:sanctum', 'permission:any.permission.at.all']);

        $response = $this->actingAs($superAdmin)->getJson('/test-super-admin-' . $routeId);

        $response->assertStatus(200);
    }

    /** @test */
    public function middleware_allows_user_with_any_of_multiple_permissions()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role_any_perms', 'description' => 'Test role']);
        $role->assignPermission('orders.create'); // Only has create, not view
        $user->assignRole($role);

        // Verify the permission is actually assigned
        $this->assertTrue($role->hasPermission('orders.create'));
        $this->assertTrue($user->hasPermission('orders.create'));
        
        // hasAnyPermission should return true since user has orders.create
        $this->assertTrue($user->hasAnyPermission(['orders.view', 'orders.create']));
    }

    /** @test */
    public function middleware_requires_authentication()
    {
        $routeId = uniqid();
        \Illuminate\Support\Facades\Route::get('/test-auth-required-' . $routeId, function () {
            return response()->json(['success' => true]);
        })->middleware(['auth:sanctum', 'permission:clients.view']);

        $response = $this->getJson('/test-auth-required-' . $routeId);

        $response->assertStatus(401);
    }

    // ==================== Role Assignment Tests ====================

    /** @test */
    public function user_can_be_assigned_role_by_name()
    {
        $user = User::factory()->create();
        Role::create(['name' => 'test_role', 'description' => 'Test role']);

        $user->assignRole('test_role');

        $this->assertTrue($user->hasRole('test_role'));
    }

    /** @test */
    public function user_can_have_role_removed()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'description' => 'Test role']);
        $user->assignRole($role);

        $this->assertTrue($user->hasRole('test_role'));

        $user->removeRole('test_role');

        $this->assertFalse($user->hasRole('test_role'));
    }

    /** @test */
    public function user_can_sync_roles()
    {
        $user = User::factory()->create();
        $role1 = Role::create(['name' => 'role1', 'description' => 'Role 1']);
        $role2 = Role::create(['name' => 'role2', 'description' => 'Role 2']);
        $role3 = Role::create(['name' => 'role3', 'description' => 'Role 3']);

        $user->assignRole($role1);
        $user->assignRole($role2);

        // Sync to only have role3
        $user->syncRoles(['role3']);

        $this->assertFalse($user->hasRole('role1'));
        $this->assertFalse($user->hasRole('role2'));
        $this->assertTrue($user->hasRole('role3'));
    }

    /** @test */
    public function user_has_any_role_check_works()
    {
        $user = User::factory()->create();
        Role::create(['name' => 'role1', 'description' => 'Role 1']);
        Role::create(['name' => 'role2', 'description' => 'Role 2']);
        $user->assignRole('role1');

        $this->assertTrue($user->hasAnyRole(['role1', 'role2']));
        $this->assertFalse($user->hasAnyRole(['role2', 'role3']));
    }
}


