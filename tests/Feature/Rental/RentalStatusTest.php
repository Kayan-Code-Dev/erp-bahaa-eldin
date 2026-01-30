<?php

namespace Tests\Feature\Rental;

use Tests\Feature\BaseTestCase;
use App\Models\Rent;
use Carbon\Carbon;

/**
 * Rental/Appointments Status Transition Tests
 *
 * Tests appointment status transitions according to TEST_COVERAGE.md specification
 * Statuses: scheduled, confirmed, in_progress, completed, cancelled, no_show, rescheduled
 */
class RentalStatusTest extends BaseTestCase
{
    // ==================== CONFIRM APPOINTMENT ====================

    /**
     * Test: Confirm Appointment (Scheduled → Confirmed)
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: POST /api/v1/appointments/{id}/confirm
     * - Required Permission: appointments.manage
     * - Expected Status: 200
     * - Description: Confirm a scheduled appointment
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: Users without permission (403), appointment already completed/cancelled (422)
     */

    public function test_appointment_confirm_scheduled_appointment_succeeds()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/confirm");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'confirmed',
            ]);

        $appointment->refresh();
        $this->assertEquals('confirmed', $appointment->status);
    }

    // ==================== START APPOINTMENT ====================

    /**
     * Test: Start Appointment (Scheduled/Confirmed → In Progress)
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: POST /api/v1/appointments/{id}/start
     * - Required Permission: appointments.manage
     * - Expected Status: 200
     * - Description: Start an appointment (mark as in progress)
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_appointment_start_confirmed_appointment_succeeds()
    {
        $appointment = Rent::factory()->create(['status' => 'confirmed']);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/start");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'in_progress',
            ]);

        $appointment->refresh();
        $this->assertEquals('in_progress', $appointment->status);
    }

    // ==================== COMPLETE APPOINTMENT ====================

    /**
     * Test: Complete Appointment (In Progress → Completed)
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: POST /api/v1/appointments/{id}/complete
     * - Required Permission: appointments.manage
     * - Expected Status: 200
     * - Description: Mark appointment as completed
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_appointment_complete_in_progress_appointment_succeeds()
    {
        $appointment = Rent::factory()->create(['status' => 'in_progress']);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'completed',
            ]);

        $appointment->refresh();
        $this->assertEquals('completed', $appointment->status);
        $this->assertNotNull($appointment->completed_at);
    }

    // ==================== CANCEL APPOINTMENT ====================

    /**
     * Test: Cancel Appointment
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: POST /api/v1/appointments/{id}/cancel
     * - Required Permission: appointments.manage
     * - Expected Status: 200
     * - Description: Cancel an appointment
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_appointment_cancel_succeeds()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'cancelled',
            ]);

        $appointment->refresh();
        $this->assertEquals('cancelled', $appointment->status);
    }

    // ==================== MARK NO-SHOW ====================

    /**
     * Test: Mark No-Show
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: POST /api/v1/appointments/{id}/no-show
     * - Required Permission: appointments.manage
     * - Expected Status: 200
     * - Description: Mark appointment as no-show
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_appointment_mark_no_show_succeeds()
    {
        $appointment = Rent::factory()->create(['status' => 'confirmed']);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/no-show");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'no_show',
            ]);

        $appointment->refresh();
        $this->assertEquals('no_show', $appointment->status);
    }

    // ==================== RESCHEDULE APPOINTMENT ====================

    /**
     * Test: Reschedule Appointment
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: POST /api/v1/appointments/{id}/reschedule
     * - Required Permission: appointments.manage
     * - Expected Status: 200
     * - Description: Reschedule an appointment to new date/time
     * - Should Pass For: general_manager, reception_employee, sales_employee
     * - Should Fail For: Users without permission (403), cloth conflict (409)
     */

    public function test_appointment_reschedule_succeeds()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);

        $this->authenticateAs('reception_employee');

        $newDate = Carbon::tomorrow()->addDays(2)->format('Y-m-d');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/reschedule", [
            'appointment_date' => $newDate,
            'start_time' => '15:00',
            'end_time' => '16:00',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'rescheduled',
            ]);

        $appointment->refresh();
        $this->assertEquals('rescheduled', $appointment->status);
        $this->assertEquals($newDate, $appointment->appointment_date->format('Y-m-d'));
        $this->assertEquals('15:00', $appointment->start_time);
        $this->assertEquals('16:00', $appointment->end_time);
    }

    // ==================== INVALID STATUS TRANSITIONS ====================

    public function test_appointment_confirm_completed_appointment_fails_422()
    {
        $appointment = Rent::factory()->create(['status' => 'completed']);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/confirm");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $appointment->refresh();
        $this->assertEquals('completed', $appointment->status);
    }

    public function test_appointment_start_cancelled_appointment_fails_422()
    {
        $appointment = Rent::factory()->create(['status' => 'cancelled']);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/start");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $appointment->refresh();
        $this->assertEquals('cancelled', $appointment->status);
    }

    public function test_appointment_complete_scheduled_appointment_fails_422()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/complete");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $appointment->refresh();
        $this->assertEquals('scheduled', $appointment->status);
    }

    // ==================== PERMISSION TESTS ====================

    public function test_appointment_confirm_by_reception_employee_succeeds()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);
        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/confirm");

        $response->assertStatus(200);
    }

    public function test_appointment_confirm_by_accountant_fails_403()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);
        $this->authenticateAs('accountant');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/confirm");

        $this->assertPermissionDenied($response);
    }

    public function test_appointment_complete_by_sales_employee_succeeds()
    {
        $appointment = Rent::factory()->create(['status' => 'in_progress']);
        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/complete");

        $response->assertStatus(200);
    }

    // ==================== GET CLIENT APPOINTMENTS ====================

    /**
     * Test: Get Client Appointments
     * - Type: Feature Test
     * - Module: Appointments
     * - Endpoint: GET /api/v1/clients/{client_id}/appointments
     * - Required Permission: appointments.view
     * - Expected Status: 200
     * - Description: Get all appointments for a specific client
     * - Should Pass For: general_manager, reception_employee, sales_employee
     */

    public function test_client_appointments_list_succeeds()
    {
        $client = $this->createCompleteClient();

        // Create appointments for this client
        $appointment1 = Rent::factory()->create(['client_id' => $client->id]);
        $appointment2 = Rent::factory()->create(['client_id' => $client->id]);

        // Create appointment for different client
        Rent::factory()->create();

        $this->authenticateAs('reception_employee');

        $response = $this->getJson("/api/v1/clients/{$client->id}/appointments");

        $response->assertStatus(200);
        $responseData = $response->json();

        // Should only include client's appointments
        $this->assertCount(2, $responseData);
        $appointmentIds = array_column($responseData, 'id');
        $this->assertContains($appointment1->id, $appointmentIds);
        $this->assertContains($appointment2->id, $appointmentIds);
    }

    // ==================== APPOINTMENT WORKFLOW INTEGRATION ====================

    /**
     * Test: Complete Appointment Workflow
     * - Type: Integration Test
     * - Module: Appointments
     * - Description: Test complete appointment lifecycle
     */
    public function test_appointment_complete_workflow()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);

        $this->authenticateAs('reception_employee');

        // 1. Confirm appointment
        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/confirm");
        $response->assertStatus(200);
        $appointment->refresh();
        $this->assertEquals('confirmed', $appointment->status);

        // 2. Start appointment
        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/start");
        $response->assertStatus(200);
        $appointment->refresh();
        $this->assertEquals('in_progress', $appointment->status);

        // 3. Complete appointment
        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/complete");
        $response->assertStatus(200);
        $appointment->refresh();
        $this->assertEquals('completed', $appointment->status);
        $this->assertNotNull($appointment->completed_at);
    }

    // ==================== CLOTH AVAILABILITY INTEGRATION ====================

    /**
     * Test: Cloth Availability Affects Appointment Creation
     * - Type: Integration Test
     * - Module: Appointments
     * - Description: Verify cloth availability checking works during appointment creation
     */
    public function test_cloth_availability_integration()
    {
        $cloth = \App\Models\Cloth::factory()->create();
        $client1 = $this->createCompleteClient();
        $client2 = $this->createCompleteClient();
        $branch = \App\Models\Branch::factory()->create();

        // Create first appointment
        Rent::factory()->create([
            'cloth_id' => $cloth->id,
            'appointment_date' => Carbon::tomorrow(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $this->authenticateAs('reception_employee');

        // Check availability - should show conflict
        $response = $this->getJson("/api/v1/clothes/{$cloth->id}/availability?" . http_build_query([
            'date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '11:00',
            'end_time' => '13:00'
        ]));

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertFalse($responseData['available']);
        $this->assertNotEmpty($responseData['conflicts']);

        // Try to create overlapping appointment - should fail
        $data = [
            'client_id' => $client2->id,
            'cloth_id' => $cloth->id,
            'appointment_type' => 'rental_delivery',
            'appointment_date' => Carbon::tomorrow()->format('Y-m-d'),
            'start_time' => '11:00',
            'end_time' => '13:00',
            'branch_id' => $branch->id,
        ];

        $response = $this->postJson('/api/v1/appointments', $data);
        $response->assertStatus(409);
    }

    // ==================== EDGE CASES ====================

    public function test_appointment_status_transition_audit_trail()
    {
        $appointment = Rent::factory()->create(['status' => 'scheduled']);

        $this->authenticateAs('reception_employee');

        // Perform multiple status changes
        $this->postJson("/api/v1/appointments/{$appointment->id}/confirm");
        $this->postJson("/api/v1/appointments/{$appointment->id}/start");
        $this->postJson("/api/v1/appointments/{$appointment->id}/complete");

        // Verify final state
        $appointment->refresh();
        $this->assertEquals('completed', $appointment->status);
        $this->assertNotNull($appointment->completed_at);

        // Note: Audit trail verification would depend on your logging implementation
        // This test ensures the workflow completes successfully
    }

    public function test_appointment_bulk_operations()
    {
        // Create multiple appointments
        $appointment1 = Rent::factory()->create(['status' => 'scheduled']);
        $appointment2 = Rent::factory()->create(['status' => 'scheduled']);
        $appointment3 = Rent::factory()->create(['status' => 'confirmed']);

        $this->authenticateAs('reception_employee');

        // Test that operations work on different appointment types
        $response = $this->postJson("/api/v1/appointments/{$appointment1->id}/confirm");
        $response->assertStatus(200);

        $response = $this->postJson("/api/v1/appointments/{$appointment2->id}/cancel");
        $response->assertStatus(200);

        $response = $this->postJson("/api/v1/appointments/{$appointment3->id}/start");
        $response->assertStatus(200);

        // Verify final states
        $appointment1->refresh();
        $appointment2->refresh();
        $appointment3->refresh();

        $this->assertEquals('confirmed', $appointment1->status);
        $this->assertEquals('cancelled', $appointment2->status);
        $this->assertEquals('in_progress', $appointment3->status);
    }
}
