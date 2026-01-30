<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Branch;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Client;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Order;
use App\Models\Inventory;
use App\Models\Rent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AppointmentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Branch $branch;
    protected Client $client;
    protected Cloth $cloth;
    protected ClothType $clothType;
    protected Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'email' => User::SUPER_ADMIN_EMAIL,
        ]);

        // Create location hierarchy
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);

        // Create branch
        $this->branch = Branch::factory()->create(['address_id' => $address->id]);

        // Create client
        $this->client = Client::factory()->create();

        // Create cloth type and cloth for rental tests
        $this->clothType = ClothType::factory()->create();
        
        $this->inventory = Inventory::factory()->create([
            'inventoriable_type' => 'branch',
            'inventoriable_id' => $this->branch->id,
        ]);

        $this->cloth = Cloth::factory()->create([
            'cloth_type_id' => $this->clothType->id,
        ]);
    }

    // ==================== APPOINTMENT CRUD TESTS ====================

    /** @test */
    public function can_create_rental_delivery_appointment()
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/appointments', [
            'appointment_type' => 'rental_delivery',
            'title' => 'Wedding Dress Rental Delivery',
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'cloth_id' => $this->cloth->id,
            'delivery_date' => now()->addDays(5)->format('Y-m-d'),
            'appointment_time' => '10:00',
            'return_date' => now()->addDays(10)->format('Y-m-d'),
            'notes' => 'VIP client - handle with care',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'appointment' => ['id', 'appointment_type', 'status', 'delivery_date'],
            ]);

        $this->assertDatabaseHas('rents', [
            'appointment_type' => 'rental_delivery',
            'client_id' => $this->client->id,
            'cloth_id' => $this->cloth->id,
            'status' => 'scheduled',
        ]);
    }

    /** @test */
    public function can_create_measurement_appointment_without_cloth()
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/appointments', [
            'appointment_type' => 'measurement',
            'title' => 'Initial Measurements',
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'delivery_date' => now()->addDays(2)->format('Y-m-d'),
            'appointment_time' => '14:00',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('rents', [
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'cloth_id' => null,
        ]);
    }

    /** @test */
    public function appointment_starts_in_scheduled_status()
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/appointments', [
            'appointment_type' => 'fitting',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $appointment = Rent::find($response->json('appointment.id'));
        $this->assertEquals(Rent::STATUS_SCHEDULED, $appointment->status);
    }

    /** @test */
    public function can_auto_calculate_return_date_from_days()
    {
        $deliveryDate = now()->addDays(5);
        
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/appointments', [
            'appointment_type' => 'rental_delivery',
            'client_id' => $this->client->id,
            'cloth_id' => $this->cloth->id,
            'delivery_date' => $deliveryDate->format('Y-m-d'),
            'days_of_rent' => 7,
        ]);

        $response->assertStatus(201);
        
        $appointment = Rent::find($response->json('appointment.id'));
        $expectedReturnDate = $deliveryDate->copy()->addDays(7)->format('Y-m-d');
        $this->assertEquals($expectedReturnDate, $appointment->return_date->format('Y-m-d'));
    }

    /** @test */
    public function can_list_appointments_with_filters()
    {
        // Create multiple appointments
        Rent::create([
            'appointment_type' => 'rental_delivery',
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'delivery_date' => now()->addDays(5),
            'return_date' => now()->addDays(10),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'delivery_date' => now()->addDays(3),
            'return_date' => now()->addDays(3),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        // Filter by type
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/appointments?appointment_type=measurement');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Filter by client
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/appointments?client_id={$this->client->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function can_get_appointment_details()
    {
        $appointment = Rent::create([
            'appointment_type' => 'rental_delivery',
            'title' => 'Test Appointment',
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'cloth_id' => $this->cloth->id,
            'delivery_date' => now()->addDays(5),
            'appointment_time' => '10:00',
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $appointment->id)
            ->assertJsonPath('appointment_type', 'rental_delivery')
            ->assertJsonPath('title', 'Test Appointment');
    }

    /** @test */
    public function can_update_scheduled_appointment()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $newDate = now()->addDays(7)->format('Y-m-d');

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/v1/appointments/{$appointment->id}", [
                'delivery_date' => $newDate,
                'notes' => 'Updated appointment',
            ]);

        $response->assertStatus(200);

        $appointment->refresh();
        $this->assertEquals($newDate, $appointment->delivery_date->format('Y-m-d'));
        $this->assertEquals('Updated appointment', $appointment->notes);
    }

    /** @test */
    public function cannot_update_completed_appointment()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/v1/appointments/{$appointment->id}", [
                'notes' => 'Try to update',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'This appointment cannot be modified']);
    }

    // ==================== STATUS WORKFLOW TESTS ====================

    /** @test */
    public function can_confirm_scheduled_appointment()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/appointments/{$appointment->id}/confirm");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Appointment confirmed']);

        $appointment->refresh();
        $this->assertEquals(Rent::STATUS_CONFIRMED, $appointment->status);
    }

    /** @test */
    public function can_start_appointment()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'confirmed',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/appointments/{$appointment->id}/start");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Appointment started']);

        $appointment->refresh();
        $this->assertEquals(Rent::STATUS_IN_PROGRESS, $appointment->status);
    }

    /** @test */
    public function can_complete_appointment()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'in_progress',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/appointments/{$appointment->id}/complete", [
                'notes' => 'Client measurements recorded successfully',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Appointment completed']);

        $appointment->refresh();
        $this->assertEquals(Rent::STATUS_COMPLETED, $appointment->status);
        $this->assertNotNull($appointment->completed_at);
        $this->assertEquals($this->superAdmin->id, $appointment->completed_by);
    }

    /** @test */
    public function can_cancel_appointment()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/appointments/{$appointment->id}/cancel", [
                'reason' => 'Client requested cancellation',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Appointment cancelled']);

        $appointment->refresh();
        $this->assertEquals(Rent::STATUS_CANCELLED, $appointment->status);
        $this->assertStringContainsString('Client requested cancellation', $appointment->notes);
    }

    /** @test */
    public function can_mark_appointment_as_no_show()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->subDays(1),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/appointments/{$appointment->id}/no-show");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Appointment marked as no-show']);

        $appointment->refresh();
        $this->assertEquals(Rent::STATUS_NO_SHOW, $appointment->status);
    }

    /** @test */
    public function can_reschedule_appointment()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'appointment_time' => '10:00',
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $newDate = now()->addDays(10)->format('Y-m-d');

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
                'new_date' => $newDate,
                'new_time' => '14:00',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Appointment rescheduled']);

        $appointment->refresh();
        $this->assertEquals($newDate, $appointment->delivery_date->format('Y-m-d'));
        $this->assertEquals('14:00', $appointment->appointment_time);
        $this->assertEquals(Rent::STATUS_RESCHEDULED, $appointment->status);
    }

    // ==================== CONFLICT DETECTION TESTS ====================

    /** @test */
    public function prevents_double_booking_of_cloth()
    {
        // Create first booking
        Rent::create([
            'appointment_type' => 'rental_delivery',
            'client_id' => $this->client->id,
            'cloth_id' => $this->cloth->id,
            'delivery_date' => now()->addDays(5),
            'return_date' => now()->addDays(10),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        // Try to create overlapping booking
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/appointments', [
            'appointment_type' => 'rental_delivery',
            'client_id' => $this->client->id,
            'cloth_id' => $this->cloth->id,
            'delivery_date' => now()->addDays(7)->format('Y-m-d'), // Overlaps
            'return_date' => now()->addDays(12)->format('Y-m-d'),
        ]);

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Scheduling conflict: This cloth is already booked for the requested dates']);
    }

    /** @test */
    public function allows_booking_outside_existing_dates()
    {
        // Create first booking
        Rent::create([
            'appointment_type' => 'rental_delivery',
            'client_id' => $this->client->id,
            'cloth_id' => $this->cloth->id,
            'delivery_date' => now()->addDays(5),
            'return_date' => now()->addDays(10),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        // Create non-overlapping booking
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/appointments', [
            'appointment_type' => 'rental_delivery',
            'client_id' => $this->client->id,
            'cloth_id' => $this->cloth->id,
            'delivery_date' => now()->addDays(15)->format('Y-m-d'), // After first booking
            'return_date' => now()->addDays(20)->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
    }

    /** @test */
    public function can_check_cloth_availability()
    {
        // Create booking
        Rent::create([
            'appointment_type' => 'rental_delivery',
            'cloth_id' => $this->cloth->id,
            'delivery_date' => now()->addDays(5),
            'return_date' => now()->addDays(10),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        // Check overlapping dates
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/clothes/{$this->cloth->id}/availability?" . http_build_query([
                'start_date' => now()->addDays(7)->format('Y-m-d'),
                'end_date' => now()->addDays(12)->format('Y-m-d'),
            ]));

        $response->assertStatus(200)
            ->assertJsonPath('is_available', false)
            ->assertJsonCount(1, 'conflicts');

        // Check non-overlapping dates
        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/clothes/{$this->cloth->id}/availability?" . http_build_query([
                'start_date' => now()->addDays(15)->format('Y-m-d'),
                'end_date' => now()->addDays(20)->format('Y-m-d'),
            ]));

        $response->assertStatus(200)
            ->assertJsonPath('is_available', true);
    }

    // ==================== CALENDAR AND SPECIAL ENDPOINTS TESTS ====================

    /** @test */
    public function can_get_calendar_view()
    {
        Rent::create([
            'appointment_type' => 'rental_delivery',
            'title' => 'Wedding Dress',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/appointments/calendar?' . http_build_query([
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addDays(30)->format('Y-m-d'),
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'events' => [
                    '*' => ['id', 'title', 'start', 'type', 'status']
                ]
            ]);
    }

    /** @test */
    public function can_get_today_appointments()
    {
        // Create today's appointment
        Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'branch_id' => $this->branch->id,
            'delivery_date' => today(),
            'appointment_time' => '10:00',
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        // Create future appointment (should not be included)
        Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/appointments/today');

        $response->assertStatus(200)
            ->assertJsonPath('summary.total', 1);
    }

    /** @test */
    public function can_get_upcoming_appointments()
    {
        // Create upcoming appointments
        Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(3),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        Rent::create([
            'appointment_type' => 'rental_delivery',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/appointments/upcoming?days=7');

        $response->assertStatus(200)
            ->assertJsonPath('total', 2);
    }

    /** @test */
    public function can_get_overdue_appointments()
    {
        // Create overdue appointment
        Rent::create([
            'appointment_type' => 'rental_return',
            'client_id' => $this->client->id,
            'delivery_date' => now()->subDays(5),
            'status' => 'scheduled', // Still active but past due
            'created_by' => $this->superAdmin->id,
        ]);

        // Create future appointment (should not be included)
        Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/appointments/overdue');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    /** @test */
    public function can_get_client_appointments()
    {
        Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        Rent::create([
            'appointment_type' => 'rental_delivery',
            'client_id' => $this->client->id,
            'delivery_date' => now()->subDays(10),
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/clients/{$this->client->id}/appointments");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'upcoming')
            ->assertJsonCount(1, 'past')
            ->assertJsonPath('total', 2);
    }

    // ==================== TYPES AND STATUSES TESTS ====================

    /** @test */
    public function can_get_appointment_types()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/appointments/types');

        $response->assertStatus(200)
            ->assertJsonStructure(['types']);

        $types = $response->json('types');
        $this->assertArrayHasKey('rental_delivery', $types);
        $this->assertArrayHasKey('measurement', $types);
        $this->assertArrayHasKey('fitting', $types);
    }

    /** @test */
    public function can_get_appointment_statuses()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/appointments/statuses');

        $response->assertStatus(200)
            ->assertJsonStructure(['statuses']);

        $statuses = $response->json('statuses');
        $this->assertArrayHasKey('scheduled', $statuses);
        $this->assertArrayHasKey('completed', $statuses);
        $this->assertArrayHasKey('cancelled', $statuses);
    }

    // ==================== DELETE TESTS ====================

    /** @test */
    public function can_delete_scheduled_appointment()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('rents', ['id' => $appointment->id]);
    }

    /** @test */
    public function cannot_delete_confirmed_appointment()
    {
        $appointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'confirmed',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Only scheduled appointments can be deleted. Use cancel instead.']);
    }

    // ==================== MODEL TESTS ====================

    /** @test */
    public function is_overdue_attribute_works()
    {
        $overdueAppointment = Rent::create([
            'appointment_type' => 'rental_return',
            'client_id' => $this->client->id,
            'delivery_date' => now()->subDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $futureAppointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $completedAppointment = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->subDays(10),
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertTrue($overdueAppointment->is_overdue);
        $this->assertFalse($futureAppointment->is_overdue);
        $this->assertFalse($completedAppointment->is_overdue); // Completed appointments are not overdue
    }

    /** @test */
    public function display_title_attribute_works()
    {
        $appointmentWithTitle = Rent::create([
            'appointment_type' => 'rental_delivery',
            'title' => 'Wedding Dress Rental',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $appointmentWithoutTitle = Rent::create([
            'appointment_type' => 'measurement',
            'client_id' => $this->client->id,
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertEquals('Wedding Dress Rental', $appointmentWithTitle->display_title);
        $this->assertStringContainsString('Measurement', $appointmentWithoutTitle->display_title);
        $this->assertStringContainsString($this->client->first_name, $appointmentWithoutTitle->display_title);
    }

    /** @test */
    public function is_rental_and_is_tailoring_attributes_work()
    {
        $rentalAppointment = Rent::create([
            'appointment_type' => 'rental_delivery',
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $tailoringAppointment = Rent::create([
            'appointment_type' => 'tailoring_pickup',
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $measurementAppointment = Rent::create([
            'appointment_type' => 'measurement',
            'delivery_date' => now()->addDays(5),
            'status' => 'scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->assertTrue($rentalAppointment->is_rental);
        $this->assertFalse($rentalAppointment->is_tailoring);

        $this->assertFalse($tailoringAppointment->is_rental);
        $this->assertTrue($tailoringAppointment->is_tailoring);

        $this->assertFalse($measurementAppointment->is_rental);
        $this->assertFalse($measurementAppointment->is_tailoring);
    }
}


