<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Client;
use App\Models\Order;
use App\Models\Notification;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Rent;
use App\Models\Receivable;
use App\Services\NotificationService;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $adminUser;
    protected Branch $branch;
    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create base entities
        $country = Country::create(['name' => 'Egypt']);
        $city = City::create(['name' => 'Cairo', 'country_id' => $country->id]);
        $address = Address::create([
            'street' => 'Test Street',
            'building' => 'Building 1',
            'city_id' => $city->id,
        ]);

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'branch_code' => 'TB001',
            'address_id' => $address->id,
        ]);

        $this->branch->inventory()->create(['name' => 'Branch Inventory']);

        // Create users
        $this->adminUser = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        $this->user = User::factory()->create(['email' => 'testuser@example.com']);

        $this->notificationService = new NotificationService();
    }

    // ==================== NOTIFICATION CRUD ====================

    /** @test */
    public function user_can_list_their_notifications()
    {
        // Create notifications for user
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test Notification 1',
            'message' => 'Test message 1',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test Notification 2',
            'message' => 'Test message 2',
            'sent_at' => now(),
        ]);

        // Create notification for another user (should not appear)
        Notification::create([
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin Notification',
            'message' => 'Admin message',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'unread_count',
            ]);

        $this->assertEquals(2, count($response->json('data')));
    }

    /** @test */
    public function can_filter_notifications_by_type()
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'System Notification',
            'message' => 'System message',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_PAYMENT_DUE,
            'title' => 'Payment Due',
            'message' => 'Payment message',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications?type=system');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $notification) {
            $this->assertEquals('system', $notification['type']);
        }
    }

    /** @test */
    public function can_filter_notifications_unread_only()
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Unread Notification',
            'message' => 'Unread message',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Read Notification',
            'message' => 'Read message',
            'read_at' => now(),
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications?unread_only=true');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    /** @test */
    public function can_get_unread_count()
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Notification 1',
            'message' => 'Message 1',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Notification 2',
            'message' => 'Message 2',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Read Notification',
            'message' => 'Read message',
            'read_at' => now(),
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson(['unread_count' => 2]);
    }

    /** @test */
    public function can_get_notification_types()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications/types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'types',
                'priorities',
            ]);
    }

    /** @test */
    public function can_view_notification_details()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test Notification',
            'message' => 'Test message',
            'priority' => Notification::PRIORITY_HIGH,
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $notification->id,
                'title' => 'Test Notification',
                'type' => 'system',
                'priority' => 'high',
            ]);
    }

    /** @test */
    public function cannot_view_another_users_notification()
    {
        $notification = Notification::create([
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin Notification',
            'message' => 'Admin message',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(403);
    }

    // ==================== MARK AS READ/UNREAD ====================

    /** @test */
    public function can_mark_notification_as_read()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test Notification',
            'message' => 'Test message',
            'sent_at' => now(),
        ]);

        $this->assertNull($notification->read_at);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Notification marked as read']);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /** @test */
    public function can_mark_notification_as_unread()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test Notification',
            'message' => 'Test message',
            'read_at' => now(),
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$notification->id}/unread");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Notification marked as unread']);

        $notification->refresh();
        $this->assertNull($notification->read_at);
    }

    /** @test */
    public function can_mark_all_notifications_as_read()
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Notification 1',
            'message' => 'Message 1',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Notification 2',
            'message' => 'Message 2',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'updated_count']);

        $unreadCount = Notification::forUser($this->user->id)->unread()->count();
        $this->assertEquals(0, $unreadCount);
    }

    // ==================== DISMISS ====================

    /** @test */
    public function can_dismiss_notification()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test Notification',
            'message' => 'Test message',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/notifications/{$notification->id}/dismiss");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Notification dismissed']);

        $notification->refresh();
        $this->assertNotNull($notification->dismissed_at);
    }

    /** @test */
    public function can_dismiss_all_notifications()
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Notification 1',
            'message' => 'Message 1',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Notification 2',
            'message' => 'Message 2',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/notifications/dismiss-all');

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'dismissed_count']);

        $undismissedCount = Notification::forUser($this->user->id)->undismissed()->count();
        $this->assertEquals(0, $undismissedCount);
    }

    /** @test */
    public function dismissed_notifications_not_shown_in_list()
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Active Notification',
            'message' => 'Active message',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Dismissed Notification',
            'message' => 'Dismissed message',
            'dismissed_at' => now(),
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    // ==================== DELETE ====================

    /** @test */
    public function can_delete_notification()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test Notification',
            'message' => 'Test message',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    /** @test */
    public function cannot_delete_another_users_notification()
    {
        $notification = Notification::create([
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin Notification',
            'message' => 'Admin message',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(403);
    }

    // ==================== ADMIN FUNCTIONS ====================

    /** @test */
    public function admin_can_broadcast_notification()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', [
                'title' => 'System Announcement',
                'message' => 'Important system message for all users',
                'priority' => 'high',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'recipients_count']);

        // Check both users received notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'System Announcement',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'title' => 'System Announcement',
        ]);
    }

    /** @test */
    public function admin_can_broadcast_to_specific_users()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', [
                'title' => 'Targeted Notification',
                'message' => 'Message for specific user',
                'user_ids' => [$this->user->id],
            ]);

        $response->assertStatus(201)
            ->assertJson(['recipients_count' => 1]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Targeted Notification',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->adminUser->id,
            'title' => 'Targeted Notification',
        ]);
    }

    /** @test */
    public function admin_can_view_all_notifications()
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'User Notification',
            'message' => 'User message',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Admin Notification',
            'message' => 'Admin message',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/notifications/all');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total'));
    }

    // ==================== NOTIFICATION SERVICE ====================

    /** @test */
    public function notification_service_creates_notification()
    {
        $notification = $this->notificationService->create(
            $this->user,
            Notification::TYPE_SYSTEM,
            'Service Test',
            'Created by service',
            ['priority' => Notification::PRIORITY_HIGH]
        );

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->user->id,
            'title' => 'Service Test',
            'priority' => 'high',
        ]);
    }

    /** @test */
    public function notification_service_gets_unread_count()
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test 1',
            'message' => 'Message 1',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test 2',
            'message' => 'Message 2',
            'read_at' => now(),
            'sent_at' => now(),
        ]);

        $count = $this->notificationService->getUnreadCount($this->user);
        $this->assertEquals(1, $count);
    }

    /** @test */
    public function notification_service_marks_all_as_read()
    {
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test 1',
            'message' => 'Message 1',
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test 2',
            'message' => 'Message 2',
            'sent_at' => now(),
        ]);

        $updatedCount = $this->notificationService->markAllAsRead($this->user);
        $this->assertEquals(2, $updatedCount);

        $unreadCount = $this->notificationService->getUnreadCount($this->user);
        $this->assertEquals(0, $unreadCount);
    }

    // ==================== MODEL TESTS ====================

    /** @test */
    public function notification_is_read_accessor_works()
    {
        $unreadNotification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Unread',
            'message' => 'Message',
            'sent_at' => now(),
        ]);

        $readNotification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Read',
            'message' => 'Message',
            'read_at' => now(),
            'sent_at' => now(),
        ]);

        $this->assertFalse($unreadNotification->is_read);
        $this->assertTrue($readNotification->is_read);
    }

    /** @test */
    public function notification_type_label_accessor_works()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_APPOINTMENT_REMINDER,
            'title' => 'Test',
            'message' => 'Message',
            'sent_at' => now(),
        ]);

        $this->assertEquals('Appointment Reminder', $notification->type_label);
    }

    /** @test */
    public function notification_mark_as_read_method_works()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test',
            'message' => 'Message',
            'sent_at' => now(),
        ]);

        $this->assertNull($notification->read_at);

        $notification->markAsRead();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /** @test */
    public function notification_dismiss_method_works()
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test',
            'message' => 'Message',
            'sent_at' => now(),
        ]);

        $this->assertNull($notification->dismissed_at);

        $notification->dismiss();

        $this->assertNotNull($notification->fresh()->dismissed_at);
    }

    /** @test */
    public function notification_scopes_work()
    {
        // Create various notifications
        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Unread High Priority',
            'message' => 'Message',
            'priority' => Notification::PRIORITY_HIGH,
            'sent_at' => now(),
        ]);

        Notification::create([
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_PAYMENT_DUE,
            'title' => 'Read Normal Priority',
            'message' => 'Message',
            'priority' => Notification::PRIORITY_NORMAL,
            'read_at' => now(),
            'sent_at' => now(),
        ]);

        // Test scopes
        $unread = Notification::forUser($this->user->id)->unread()->count();
        $this->assertEquals(1, $unread);

        $read = Notification::forUser($this->user->id)->read()->count();
        $this->assertEquals(1, $read);

        $highPriority = Notification::forUser($this->user->id)->highPriority()->count();
        $this->assertEquals(1, $highPriority);

        $systemType = Notification::forUser($this->user->id)->ofType(Notification::TYPE_SYSTEM)->count();
        $this->assertEquals(1, $systemType);
    }

    // ==================== VALIDATION TESTS ====================

    /** @test */
    public function broadcast_validates_required_fields()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'message']);
    }

    /** @test */
    public function broadcast_validates_priority()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/notifications/broadcast', [
                'title' => 'Test',
                'message' => 'Message',
                'priority' => 'invalid_priority',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }
}






