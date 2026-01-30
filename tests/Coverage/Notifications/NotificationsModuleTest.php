<?php

namespace Tests\Coverage\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Notification;
use Laravel\Sanctum\Sanctum;

class NotificationsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    protected function seedPermissions()
    {
        $permissions = ['notifications.view', 'notifications.manage'];
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

    public function test_list_notifications()
    {
        $user = $this->createUserWithPermission('notifications.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications');
        $response->assertStatus(200);
    }

    public function test_mark_notification_as_read()
    {
        $user = $this->createUserWithPermission('notifications.view');
        Sanctum::actingAs($user);
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson("/api/v1/notifications/{$notification->id}/read");
        $response->assertStatus(200);
    }

    public function test_get_unread_count()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/notifications/unread-count');
        $response->assertStatus(200)->assertJsonStructure(['unread_count']);
    }

    public function test_mark_all_notifications_as_read()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/notifications/mark-all-read');
        $response->assertStatus(200);
    }

    public function test_show_notification()
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/notifications/{$notification->id}");
        $response->assertStatus(200)->assertJson(['id' => $notification->id]);
    }
}

