<?php

namespace Tests\Coverage\HR;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Attendance;
use Laravel\Sanctum\Sanctum;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['hr.attendance.view', 'hr.attendance.check-in', 'hr.attendance.manage'];
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

    public function test_list_attendance()
    {
        Attendance::factory()->count(5)->create();
        $user = $this->createUserWithPermission('hr.attendance.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/attendance');
        $response->assertStatus(200);
    }

    public function test_check_in()
    {
        $user = $this->createUserWithPermission('hr.attendance.check-in');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/attendance/check-in');
        $response->assertStatus(201);
    }

    public function test_check_out()
    {
        $user = $this->createUserWithPermission('hr.attendance.check-in');
        Sanctum::actingAs($user);

        // First check in
        $this->postJson('/api/v1/attendance/check-in');

        // Then check out
        $response = $this->postJson('/api/v1/attendance/check-out');
        $response->assertStatus(200);
    }

    public function test_get_today_attendance()
    {
        $user = $this->createUserWithPermission('hr.attendance.check-in');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/attendance/today');
        $response->assertStatus(200);
    }

    public function test_show_attendance()
    {
        $attendance = Attendance::factory()->create();
        $user = $this->createUserWithPermission('hr.attendance.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/attendance/{$attendance->id}");
        $response->assertStatus(200)->assertJson(['id' => $attendance->id]);
    }
}

