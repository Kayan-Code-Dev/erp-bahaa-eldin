<?php

namespace Tests\Feature\Rental;

use Tests\Feature\BaseTestCase;
use App\Models\Rent;
use App\Models\Client;
use App\Models\Cloth;
use App\Models\Branch;
use Carbon\Carbon;

/**
 * Rental/Appointments CRUD Tests
 *
 * Tests all basic CRUD operations for appointments according to TEST_COVERAGE.md specification
 */
class RentalCrudTest extends BaseTestCase
{
    // ==================== LIST APPOINTMENTS ====================

    /**
     * Test: List Appointments
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: GET /api/v1/appointments
     * - Required Permission: appointments.view
     * - Expected Status: 200
     * - Description: List all appointments with pagination and filtering
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: factory_user, workshop_manager, employee, hr_manager, unauthenticated
     */

    public function test_appointment_list_by_general_manager_succeeds()
    {
        Rent::factory()->count(3)->create();
        $this->authenticateAsSuperAdmin();

        $response = $this->getJson('/api/v1/appointments');

        $response->assertStatus(200);
        $this->assertPaginatedResponse($response);
    }

    public function test_appointment_list_by_reception_employee_succeeds()
    {
        Rent::factory()->count(3)->create();
        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/appointments');

        $response->assertStatus(200);
    }

    public function test_appointment_list_by_factory_user_fails_403()
    {
        Rent::factory()->count(3)->create();
        $this->authenticateAs('factory_user');

        $response = $this->getJson('/api/v1/appointments');

        $this->assertPermissionDenied($response);
    }

    public function test_appointment_list_with_filters_succeeds()
    {
        // Create appointments with different dates and statuses
        $pastAppointment = Rent::factory()->create([
            'appointment_date' => Carbon::yesterday(),
            'status' => 'completed'
        ]);
        $todayAppointment = Rent::factory()->create([
            'appointment_date' => Carbon::today(),
            'status' => 'scheduled'
        ]);
        $futureAppointment = Rent::factory()->create([
            'appointment_date' => Carbon::tomorrow(),
            'status' => 'confirmed'
        ]);

        $this->authenticateAs('reception_employee');

        // Test status filter
        $response = $this->getJson('/api/v1/appointments?status=scheduled');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        // Test date range filter
        $response = $this->getJson('/api/v1/appointments?start_date=' . Carbon::today()->format('Y-m-d'));
        $response->assertStatus(200);
        // Should include today and future appointments
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    // ==================== CREATE RENTAL DELIVERY APPOINTMENT ====================

    /**
     * Test: Create Rental Delivery Appointment
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: POST /api/v1/appointments
     * - Required Permission: appointments.create
     * - Expected Status: 201
     * - Description: Create a rental delivery appointment
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: Users without permission (403), invalid data (422), cloth conflict (409)
     */

    public function test_appointment_create_rental_delivery_succeeds()
    {
        $client = $this->createCompleteClient();
        $cloth = Cloth::factory()->create();
        $branch = Branch::factory()->create();

        $this->authenticateAs('reception_employee');

        $data = [
            'client_id' => $client->id,
            'cloth_id' => $cloth->id,
            'appointment_type' => 'rental_delivery',
            'appointment_date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'branch_id' => $branch->id,
            'notes' => 'Test delivery appointment',
            'delivery_date' => Carbon::tomorrow()->addDays(1)->format('Y-m-d'),
            'return_date' => Carbon::tomorrow()->addDays(7)->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/v1/appointments', $data);

        $response->assertStatus(201)
            ->assertJson([
                'appointment_type' => 'rental_delivery',
                'status' => 'scheduled',
                'cloth_id' => $cloth->id,
                'client_id' => $client->id,
            ]);

        $this->assertDatabaseHas('rents', [
            'client_id' => $client->id,
            'cloth_id' => $cloth->id,
            'appointment_type' => 'rental_delivery',
            'status' => 'scheduled',
        ]);
    }

    // ==================== CREATE APPOINTMENT WITH CLOTH CONFLICT ====================

    /**
     * Test: Create Appointment with Cloth Conflict (Should Fail)
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: POST /api/v1/appointments
     * - Expected Status: 409
     * - Description: Cannot create appointment if cloth is already booked for the dates
     */

    public function test_appointment_create_with_cloth_conflict_fails_409()
    {
        $client1 = $this->createCompleteClient();
        $client2 = $this->createCompleteClient();
        $cloth = Cloth::factory()->create();
        $branch = Branch::factory()->create();

        // Create first appointment
        Rent::factory()->create([
            'cloth_id' => $cloth->id,
            'appointment_date' => Carbon::tomorrow(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $this->authenticateAs('reception_employee');

        // Try to create overlapping appointment
        $data = [
            'client_id' => $client2->id,
            'cloth_id' => $cloth->id,
            'appointment_type' => 'rental_delivery',
            'appointment_date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '11:00', // Overlaps with existing
            'end_time' => '13:00',
            'branch_id' => $branch->id,
        ];

        $response = $this->postJson('/api/v1/appointments', $data);

        $response->assertStatus(409)
            ->assertJsonStructure(['message', 'conflicts']);
    }

    // ==================== CHECK CLOTH AVAILABILITY ====================

    /**
     * Test: Check Cloth Availability
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: GET /api/v1/clothes/{cloth_id}/availability
     * - Required Permission: appointments.view
     * - Expected Status: 200
     * - Description: Check cloth availability for date range
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_cloth_availability_check_succeeds()
    {
        $cloth = Cloth::factory()->create();

        // Create existing appointment
        Rent::factory()->create([
            'cloth_id' => $cloth->id,
            'appointment_date' => Carbon::tomorrow(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->getJson("/api/v1/clothes/{$cloth->id}/availability?" . http_build_query([
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '11:00'
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'available',
                'conflicts' => [
                    '*' => [
                        'id', 'appointment_date', 'start_time', 'end_time',
                        'client' => ['first_name', 'last_name']
                    ]
                ]
            ]);

        $responseData = $response->json();
        $this->assertFalse($responseData['available']);
        $this->assertNotEmpty($responseData['conflicts']);
    }

    // ==================== SHOW APPOINTMENT ====================

    /**
     * Test: Show Appointment
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: GET /api/v1/appointments/{id}
     * - Required Permission: appointments.view
     * - Expected Status: 200
     * - Description: Get single appointment details
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: Users without permission (403), non-existent ID (404)
     */

    public function test_appointment_show_by_reception_employee_succeeds()
    {
        $appointment = Rent::factory()->create();
        $this->authenticateAs('reception_employee');

        $response = $this->getJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $appointment->id])
            ->assertJsonStructure([
                'id', 'appointment_type', 'status', 'appointment_date',
                'start_time', 'end_time', 'notes',
                'client' => ['id', 'first_name', 'last_name'],
                'cloth' => ['id', 'code', 'name'],
                'branch' => ['id', 'name'],
                'display_title', 'is_overdue'
            ]);
    }

    // ==================== UPDATE APPOINTMENT ====================

    /**
     * Test: Update Appointment
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: PUT /api/v1/appointments/{id}
     * - Required Permission: appointments.update
     * - Expected Status: 200
     * - Description: Update appointment details
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: Users without permission (403), appointment completed/cancelled (422), cloth conflict (409)
     */

    public function test_appointment_update_with_valid_data_succeeds()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);
        $this->authenticateAs('reception_employee');

        $updateData = [
            'notes' => 'Updated appointment notes',
            'start_time' => '14:00',
            'end_time' => '15:00',
        ];

        $response = $this->putJson("/api/v1/appointments/{$appointment->id}", $updateData);

        $response->assertStatus(200);

        $appointment->refresh();
        $this->assertEquals('Updated appointment notes', $appointment->notes);
        $this->assertEquals('14:00', $appointment->start_time);
        $this->assertEquals('15:00', $appointment->end_time);
    }

    // ==================== DELETE APPOINTMENT ====================

    /**
     * Test: Delete Appointment
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: DELETE /api/v1/appointments/{id}
     * - Required Permission: appointments.delete
     * - Expected Status: 200/204
     * - Description: Delete an appointment
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: Users without permission (403)
     */

    public function test_appointment_delete_succeeds()
    {
        $appointment = Rent::factory()->create();
        $this->authenticateAs('reception_employee');

        $response = $this->deleteJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($appointment);
    }

    // ==================== GET TODAY'S APPOINTMENTS ====================

    /**
     * Test: Get Today's Appointments
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: GET /api/v1/appointments/today
     * - Required Permission: appointments.view
     * - Expected Status: 200
     * - Description: Get all appointments scheduled for today
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_appointments_today_succeeds()
    {
        // Create appointments for different dates
        Rent::factory()->create([
            'appointment_date' => Carbon::yesterday(),
            'status' => 'completed'
        ]);

        $todayAppointment1 = Rent::factory()->create([
            'appointment_date' => Carbon::today(),
            'status' => 'scheduled'
        ]);

        $todayAppointment2 = Rent::factory()->create([
            'appointment_date' => Carbon::today(),
            'status' => 'confirmed'
        ]);

        Rent::factory()->create([
            'appointment_date' => Carbon::tomorrow(),
            'status' => 'scheduled'
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/appointments/today');

        $response->assertStatus(200);
        $responseData = $response->json();

        // Should only include today's appointments
        $this->assertCount(2, $responseData);
        $appointmentIds = array_column($responseData, 'id');
        $this->assertContains($todayAppointment1->id, $appointmentIds);
        $this->assertContains($todayAppointment2->id, $appointmentIds);
    }

    // ==================== GET UPCOMING APPOINTMENTS ====================

    /**
     * Test: Get Upcoming Appointments
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: GET /api/v1/appointments/upcoming
     * - Required Permission: appointments.view
     * - Expected Status: 200
     * - Description: Get upcoming appointments (future dates)
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_appointments_upcoming_succeeds()
    {
        // Create appointments for different dates
        Rent::factory()->create([
            'appointment_date' => Carbon::yesterday(),
            'status' => 'completed'
        ]);

        Rent::factory()->create([
            'appointment_date' => Carbon::today(),
            'status' => 'scheduled'
        ]);

        $futureAppointment1 = Rent::factory()->create([
            'appointment_date' => Carbon::tomorrow(),
            'status' => 'scheduled'
        ]);

        $futureAppointment2 = Rent::factory()->create([
            'appointment_date' => Carbon::tomorrow()->addDays(7),
            'status' => 'confirmed'
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/appointments/upcoming');

        $response->assertStatus(200);
        $responseData = $response->json();

        // Should include future appointments
        $this->assertGreaterThanOrEqual(2, count($responseData));
        $appointmentIds = array_column($responseData, 'id');
        $this->assertContains($futureAppointment1->id, $appointmentIds);
        $this->assertContains($futureAppointment2->id, $appointmentIds);
    }

    // ==================== GET OVERDUE APPOINTMENTS ====================

    /**
     * Test: Get Overdue Appointments
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: GET /api/v1/appointments/overdue
     * - Required Permission: appointments.view
     * - Expected Status: 200
     * - Description: Get overdue appointments (past return dates, not completed)
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_appointments_overdue_succeeds()
    {
        // Create various appointments
        Rent::factory()->create([
            'appointment_date' => Carbon::yesterday(),
            'status' => 'completed' // Completed, so not overdue
        ]);

        Rent::factory()->create([
            'appointment_date' => Carbon::today(),
            'status' => 'scheduled' // Today, not overdue
        ]);

        $overdueAppointment = Rent::factory()->create([
            'appointment_date' => Carbon::yesterday()->subDays(2),
            'status' => 'confirmed' // Past date, not completed = overdue
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->getJson('/api/v1/appointments/overdue');

        $response->assertStatus(200);
        $responseData = $response->json();

        // Should include overdue appointments
        $appointmentIds = array_column($responseData, 'id');
        $this->assertContains($overdueAppointment->id, $appointmentIds);
    }

    // ==================== VALIDATION TESTS ====================

    public function test_appointment_create_without_required_fields_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->postJson('/api/v1/appointments', []);

        $this->assertValidationError($response, [
            'client_id', 'cloth_id', 'appointment_type',
            'appointment_date', 'start_time', 'end_time'
        ]);
    }

    public function test_appointment_create_with_past_appointment_date_fails_422()
    {
        $client = $this->createCompleteClient();
        $cloth = Cloth::factory()->create();
        $branch = Branch::factory()->create();

        $this->authenticateAs('reception_employee');

        $data = [
            'client_id' => $client->id,
            'cloth_id' => $cloth->id,
            'appointment_type' => 'rental_delivery',
            'appointment_date' => Carbon::yesterday()->format('Y-m-d'), // Past date
            'start_time' => '10:00',
            'end_time' => '11:00',
            'branch_id' => $branch->id,
        ];

        $response = $this->postJson('/api/v1/appointments', $data);

        $this->assertValidationError($response, ['appointment_date']);
    }

    public function test_appointment_create_with_invalid_time_range_fails_422()
    {
        $client = $this->createCompleteClient();
        $cloth = Cloth::factory()->create();
        $branch = Branch::factory()->create();

        $this->authenticateAs('reception_employee');

        $data = [
            'client_id' => $client->id,
            'cloth_id' => $cloth->id,
            'appointment_type' => 'rental_delivery',
            'appointment_date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '15:00',
            'end_time' => '14:00', // End before start
            'branch_id' => $branch->id,
        ];

        $response = $this->postJson('/api/v1/appointments', $data);

        $this->assertValidationError($response, ['end_time']);
    }

    public function test_appointment_create_with_invalid_appointment_type_fails_422()
    {
        $client = $this->createCompleteClient();
        $cloth = Cloth::factory()->create();
        $branch = Branch::factory()->create();

        $this->authenticateAs('reception_employee');

        $data = [
            'client_id' => $client->id,
            'cloth_id' => $cloth->id,
            'appointment_type' => 'invalid_type',
            'appointment_date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'branch_id' => $branch->id,
        ];

        $response = $this->postJson('/api/v1/appointments', $data);

        $this->assertValidationError($response, ['appointment_type']);
    }
}
