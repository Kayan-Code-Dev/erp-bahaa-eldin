<?php

namespace Tests\Coverage\Rental;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Rent;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Inventory;
use Laravel\Sanctum\Sanctum;

class RentalAppointmentsTest extends TestCase
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
            'appointments.view', 'appointments.create', 'appointments.update', 'appointments.delete',
            'appointments.manage',
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
        $client = Client::factory()->create(['address_id' => $address->id]);
        $clothType = ClothType::factory()->create();
        $cloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id]);
        return ['branch' => $branch, 'client' => $client, 'cloth' => $cloth];
    }

    public function test_list_appointments()
    {
        Rent::factory()->count(5)->create();
        $user = $this->createUserWithPermission('appointments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/appointments');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_create_rental_delivery_appointment()
    {
        $data = $this->createTestData();
        $user = $this->createUserWithPermission('appointments.create');
        Sanctum::actingAs($user);

        $appointmentData = [
            'appointment_type' => 'rental_delivery',
            'client_id' => $data['client']->id,
            'branch_id' => $data['branch']->id,
            'cloth_id' => $data['cloth']->id,
            'delivery_date' => now()->addDays(5)->format('Y-m-d'),
            'return_date' => now()->addDays(10)->format('Y-m-d'),
        ];
        $response = $this->postJson('/api/v1/appointments', $appointmentData);
        $response->assertStatus(201);
        $this->assertDatabaseHas('rents', ['appointment_type' => 'rental_delivery', 'client_id' => $data['client']->id]);
    }

    public function test_show_appointment()
    {
        $appointment = Rent::factory()->create();
        $user = $this->createUserWithPermission('appointments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/appointments/{$appointment->id}");
        $response->assertStatus(200)->assertJson(['id' => $appointment->id]);
    }

    public function test_update_appointment()
    {
        $appointment = Rent::factory()->create();
        $user = $this->createUserWithPermission('appointments.update');
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/appointments/{$appointment->id}", ['title' => 'Updated Title']);
        $response->assertStatus(200);
    }

    public function test_delete_appointment()
    {
        $appointment = Rent::factory()->create();
        $user = $this->createUserWithPermission('appointments.delete');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/appointments/{$appointment->id}");
        $response->assertStatus(204);
        $this->assertSoftDeleted('rents', ['id' => $appointment->id]);
    }

    public function test_get_today_appointments()
    {
        Rent::factory()->create(['delivery_date' => now()]);
        $user = $this->createUserWithPermission('appointments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/appointments/today');
        $response->assertStatus(200);
    }

    public function test_get_upcoming_appointments()
    {
        Rent::factory()->create(['delivery_date' => now()->addDays(5)]);
        $user = $this->createUserWithPermission('appointments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/appointments/upcoming');
        $response->assertStatus(200);
    }

    public function test_create_appointment_with_cloth_conflict_fails()
    {
        $data = $this->createTestData();
        $user = $this->createUserWithPermission('appointments.create');
        Sanctum::actingAs($user);

        // Create first appointment
        $firstAppointment = Rent::factory()->create([
            'appointment_type' => 'rental_delivery',
            'client_id' => $data['client']->id,
            'branch_id' => $data['branch']->id,
            'cloth_id' => $data['cloth']->id,
            'delivery_date' => now()->addDays(5),
            'return_date' => now()->addDays(10),
            'status' => 'scheduled',
        ]);

        // Try to create conflicting appointment
        $conflictingData = [
            'appointment_type' => 'rental_delivery',
            'client_id' => $data['client']->id,
            'branch_id' => $data['branch']->id,
            'cloth_id' => $data['cloth']->id,
            'delivery_date' => now()->addDays(7)->format('Y-m-d'),
            'return_date' => now()->addDays(12)->format('Y-m-d'),
        ];
        $response = $this->postJson('/api/v1/appointments', $conflictingData);
        $response->assertStatus(409);
        $response->assertJsonFragment(['message' => 'Scheduling conflict']);
    }

    public function test_check_cloth_availability()
    {
        $data = $this->createTestData();
        $user = $this->createUserWithPermission('appointments.view');
        Sanctum::actingAs($user);

        // Create appointment
        Rent::factory()->create([
            'cloth_id' => $data['cloth']->id,
            'delivery_date' => now()->addDays(5),
            'return_date' => now()->addDays(10),
            'status' => 'scheduled',
        ]);

        $response = $this->getJson("/api/v1/clothes/{$data['cloth']->id}/availability", [
            'start_date' => now()->addDays(3)->format('Y-m-d'),
            'end_date' => now()->addDays(15)->format('Y-m-d'),
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['is_available', 'conflicts', 'unavailable_dates']);
    }

    public function test_confirm_appointment()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);
        $user = $this->createUserWithPermission('appointments.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/confirm");
        $response->assertStatus(200);
        $appointment->refresh();
        $this->assertEquals('confirmed', $appointment->status);
    }

    public function test_start_appointment()
    {
        $appointment = Rent::factory()->create(['status' => 'confirmed']);
        $user = $this->createUserWithPermission('appointments.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/start");
        $response->assertStatus(200);
        $appointment->refresh();
        $this->assertEquals('in_progress', $appointment->status);
    }

    public function test_complete_appointment()
    {
        $appointment = Rent::factory()->create(['status' => 'in_progress']);
        $user = $this->createUserWithPermission('appointments.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/complete", ['notes' => 'Completed successfully']);
        $response->assertStatus(200);
        $appointment->refresh();
        $this->assertEquals('completed', $appointment->status);
        $this->assertNotNull($appointment->completed_at);
        $this->assertEquals($user->id, $appointment->completed_by);
    }

    public function test_cancel_appointment()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);
        $user = $this->createUserWithPermission('appointments.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/cancel", ['reason' => 'Client request']);
        $response->assertStatus(200);
        $appointment->refresh();
        $this->assertEquals('cancelled', $appointment->status);
    }

    public function test_mark_no_show()
    {
        $appointment = Rent::factory()->create(['status' => 'confirmed']);
        $user = $this->createUserWithPermission('appointments.manage');
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/no-show");
        $response->assertStatus(200);
        $appointment->refresh();
        $this->assertEquals('no_show', $appointment->status);
    }

    public function test_reschedule_appointment()
    {
        $appointment = Rent::factory()->create([
            'status' => 'scheduled',
            'delivery_date' => now()->addDays(5),
        ]);
        $user = $this->createUserWithPermission('appointments.manage');
        Sanctum::actingAs($user);

        $newDate = now()->addDays(10)->format('Y-m-d');
        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
            'new_date' => $newDate,
            'new_time' => '14:00',
        ]);
        $response->assertStatus(200);
        $appointment->refresh();
        $this->assertEquals($newDate, $appointment->delivery_date->format('Y-m-d'));
    }

    public function test_get_overdue_appointments()
    {
        Rent::factory()->create([
            'delivery_date' => now()->subDays(5),
            'return_date' => now()->subDays(2),
            'status' => 'scheduled',
        ]);
        $user = $this->createUserWithPermission('appointments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/appointments/overdue');
        $response->assertStatus(200);
        $response->assertJsonStructure(['total', 'appointments']);
    }

    public function test_get_calendar_view()
    {
        Rent::factory()->create(['delivery_date' => now()->addDays(5)]);
        $user = $this->createUserWithPermission('appointments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/appointments/calendar', [
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(30)->format('Y-m-d'),
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['events']);
    }

    public function test_get_client_appointments()
    {
        $data = $this->createTestData();
        Rent::factory()->create(['client_id' => $data['client']->id]);
        $user = $this->createUserWithPermission('appointments.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/clients/{$data['client']->id}/appointments");
        $response->assertStatus(200);
        $response->assertJsonStructure(['upcoming', 'past']);
    }

    public function test_create_appointment_with_past_delivery_date_fails()
    {
        $data = $this->createTestData();
        $user = $this->createUserWithPermission('appointments.create');
        Sanctum::actingAs($user);

        $appointmentData = [
            'appointment_type' => 'rental_delivery',
            'client_id' => $data['client']->id,
            'branch_id' => $data['branch']->id,
            'delivery_date' => now()->subDays(1)->format('Y-m-d'),
        ];
        $response = $this->postJson('/api/v1/appointments', $appointmentData);
        $response->assertStatus(422);
    }

    public function test_create_appointment_with_return_date_before_delivery_date_fails()
    {
        $data = $this->createTestData();
        $user = $this->createUserWithPermission('appointments.create');
        Sanctum::actingAs($user);

        $appointmentData = [
            'appointment_type' => 'rental_delivery',
            'client_id' => $data['client']->id,
            'branch_id' => $data['branch']->id,
            'delivery_date' => now()->addDays(10)->format('Y-m-d'),
            'return_date' => now()->addDays(5)->format('Y-m-d'),
        ];
        $response = $this->postJson('/api/v1/appointments', $appointmentData);
        $response->assertStatus(422);
    }

    public function test_create_appointment_with_invalid_appointment_type_fails()
    {
        $data = $this->createTestData();
        $user = $this->createUserWithPermission('appointments.create');
        Sanctum::actingAs($user);

        $appointmentData = [
            'appointment_type' => 'invalid_type',
            'client_id' => $data['client']->id,
            'branch_id' => $data['branch']->id,
            'delivery_date' => now()->addDays(5)->format('Y-m-d'),
        ];
        $response = $this->postJson('/api/v1/appointments', $appointmentData);
        $response->assertStatus(422);
    }
}

