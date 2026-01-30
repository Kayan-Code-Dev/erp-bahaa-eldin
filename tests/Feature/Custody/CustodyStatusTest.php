<?php

namespace Tests\Feature\Custody;

use Tests\Feature\BaseTestCase;
use App\Models\Custody;
use App\Models\CustodyPhoto;
use Illuminate\Http\UploadedFile;

/**
 * Custody Status Transition Tests
 *
 * Tests custody status transitions according to TEST_COVERAGE.md specification
 * Custody statuses: pending, returned, kept
 */
class CustodyStatusTest extends BaseTestCase
{
    // ==================== RETURN CUSTODY ====================

    /**
     * Test: Return Custody (Pending → Returned)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/custody/{id}/return
     * - Required Permission: custody.return
     * - Expected Status: 200
     * - Description: Return custody item (requires return proof photo for physical items)
     * - Should Pass For: general_manager, reception_employee
     * - Should Fail For: Users without permission (403), custody already returned (422), custody kept (422), missing proof photo (422)
     */

    public function test_custody_return_money_custody_succeeds()
    {
        $custody = Custody::factory()->create([
            'type' => 'money',
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/custody/{$custody->id}/return");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'returned',
            ]);

        $custody->refresh();
        $this->assertEquals('returned', $custody->status);
        $this->assertNotNull($custody->returned_at);
    }

    /**
     * Test: Return Custody (Physical Item with Proof)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/custody/{id}/return
     * - Required Permission: custody.return
     * - Expected Status: 200
     * - Description: Return physical item custody with proof photo
     */

    public function test_custody_return_physical_item_with_proof_succeeds()
    {
        $custody = Custody::factory()->create([
            'type' => 'physical_item',
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $proofPhoto = UploadedFile::fake()->image('return_proof.jpg');
        $response = $this->postJson("/api/v1/custody/{$custody->id}/return", [
            'return_proof_photo' => $proofPhoto,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'returned',
            ]);

        $custody->refresh();
        $this->assertEquals('returned', $custody->status);
        $this->assertNotNull($custody->returned_at);

        // Verify return proof photo was created
        $returnProof = CustodyPhoto::where('custody_id', $custody->id)
            ->where('photo_type', 'return_proof')
            ->first();
        $this->assertNotNull($returnProof);
    }

    // ==================== RETURN CUSTODY ALREADY RETURNED ====================

    /**
     * Test: Return Custody Already Returned (Should Fail)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/custody/{id}/return
     * - Expected Status: 422
     * - Description: Cannot return custody that is already returned
     */

    public function test_custody_return_already_returned_fails_422()
    {
        $custody = Custody::factory()->create([
            'status' => 'returned',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/custody/{$custody->id}/return");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $custody->refresh();
        $this->assertEquals('returned', $custody->status);
    }

    // ==================== RETURN CUSTODY THAT IS KEPT ====================

    /**
     * Test: Return Custody That Is Kept (Should Fail)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/custody/{id}/return
     * - Expected Status: 422
     * - Description: Cannot return custody that is kept (forfeited)
     */

    public function test_custody_return_kept_custody_fails_422()
    {
        $custody = Custody::factory()->create([
            'status' => 'kept',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/custody/{$custody->id}/return");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $custody->refresh();
        $this->assertEquals('kept', $custody->status);
    }

    // ==================== RETURN PHYSICAL ITEM WITHOUT PROOF ====================

    /**
     * Test: Return Physical Item Custody without Proof (Should Fail)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/custody/{id}/return
     * - Expected Status: 422
     * - Description: Physical item custody requires return proof photo
     */

    public function test_custody_return_physical_item_without_proof_fails_422()
    {
        $custody = Custody::factory()->create([
            'type' => 'physical_item',
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/custody/{$custody->id}/return");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $custody->refresh();
        $this->assertEquals('pending', $custody->status);
    }

    // ==================== MARK CUSTODY AS KEPT ====================

    /**
     * Test: Mark Custody as Kept (Forfeited)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: POST /api/v1/custody/{id}/keep (or similar endpoint)
     * - Required Permission: custody.return
     * - Expected Status: 200
     * - Description: Mark custody as kept/forfeited (customer keeps the item)
     * - Should Pass For: general_manager, reception_employee
     */

    public function test_custody_mark_as_kept_succeeds()
    {
        $custody = Custody::factory()->create([
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/custody/{$custody->id}/keep");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'kept',
            ]);

        $custody->refresh();
        $this->assertEquals('kept', $custody->status);
    }

    // ==================== ORDER INTEGRATION ====================

    /**
     * Test: Order Cannot Be Finished with Pending Custody
     * - Type: Integration Test
     * - Module: Custody, Orders
     * - Description: Order with pending custody cannot be finished
     */

    public function test_order_cannot_finish_with_pending_custody()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'delivered', 'paid' => 100, 'remaining' => 0]);

        // Create pending custody
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $order->refresh();
        $this->assertEquals('delivered', $order->status);
    }

    /**
     * Test: Order Can Be Finished with Returned Custody
     * - Type: Integration Test
     * - Module: Custody, Orders
     * - Description: Order with returned custody can be finished
     */

    public function test_order_can_finish_with_returned_custody()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'delivered', 'paid' => 100, 'remaining' => 0]);

        // Create returned custody
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'returned',
        ]);

        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(200)
            ->assertJson(['status' => 'finished']);

        $order->refresh();
        $this->assertEquals('finished', $order->status);
    }

    /**
     * Test: Order Can Be Finished with Kept Custody
     * - Type: Integration Test
     * - Module: Custody, Orders
     * - Description: Order with kept (forfeited) custody can be finished
     */

    public function test_order_can_finish_with_kept_custody()
    {
        $order = $this->createCompleteOrder();
        $order->update(['status' => 'delivered', 'paid' => 100, 'remaining' => 0]);

        // Create kept custody
        Custody::factory()->create([
            'order_id' => $order->id,
            'status' => 'kept',
        ]);

        $this->authenticateAs('sales_employee');

        $response = $this->postJson("/api/v1/orders/{$order->id}/finish");

        $response->assertStatus(200)
            ->assertJson(['status' => 'finished']);

        $order->refresh();
        $this->assertEquals('finished', $order->status);
    }

    // ==================== PERMISSION TESTS ====================

    public function test_custody_return_by_reception_employee_succeeds()
    {
        $custody = Custody::factory()->create([
            'type' => 'money',
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/custody/{$custody->id}/return");

        $response->assertStatus(200);
    }

    public function test_custody_return_by_accountant_fails_403()
    {
        $custody = Custody::factory()->create([
            'type' => 'money',
            'status' => 'pending',
        ]);

        $this->authenticateAs('accountant');

        $response = $this->postJson("/api/v1/custody/{$custody->id}/return");

        $this->assertPermissionDenied($response);
    }

    public function test_custody_mark_kept_by_general_manager_succeeds()
    {
        $custody = Custody::factory()->create([
            'status' => 'pending',
        ]);

        $this->authenticateAsSuperAdmin();

        $response = $this->postJson("/api/v1/custody/{$custody->id}/keep");

        $response->assertStatus(200);
    }

    // ==================== PHOTO MANAGEMENT ====================

    /**
     * Test: View Custody Photo (Signed URL)
     * - Type: Feature Test
     * - Module: Custody
     * - Endpoint: GET /api/v1/custody-photos/{path}
     * - Required Permission: None (signed URL validates access)
     * - Expected Status: 200
     * - Description: View custody photo using signed URL
     * - Should Pass For: All users (with valid signed URL)
     */

    public function test_custody_photo_view_with_valid_signed_url_succeeds()
    {
        $custody = Custody::factory()->create();
        $photo = CustodyPhoto::factory()->create([
            'custody_id' => $custody->id,
        ]);

        // Generate signed URL (assuming your controller does this)
        $signedUrl = route('custody.photos.show', ['path' => $photo->photo_path]);

        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg'); // Or appropriate image type
    }

    // ==================== EDGE CASES ====================

    public function test_custody_return_nonexistent_custody_fails_404()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->postJson('/api/v1/custody/99999/return');

        $this->assertNotFound($response);
    }

    public function test_custody_keep_nonexistent_custody_fails_404()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->postJson('/api/v1/custody/99999/keep');

        $this->assertNotFound($response);
    }

    public function test_custody_return_by_unauthenticated_fails_401()
    {
        $custody = Custody::factory()->create([
            'type' => 'money',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/custody/{$custody->id}/return");

        $this->assertUnauthorized($response);
    }

    public function test_custody_keep_by_unauthenticated_fails_401()
    {
        $custody = Custody::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/custody/{$custody->id}/keep");

        $this->assertUnauthorized($response);
    }

    // ==================== STATUS VALIDATION ====================

    public function test_custody_status_transitions_are_validated()
    {
        // Test that invalid status transitions are prevented
        // This depends on your specific business rules implementation
        $this->markTestSkipped('Status transition validation depends on specific business rules implementation');
    }

    public function test_custody_status_changes_create_audit_trail()
    {
        $custody = Custody::factory()->create([
            'type' => 'money',
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $this->postJson("/api/v1/custody/{$custody->id}/return");

        // Verify that status change is logged (assuming you have audit logging)
        // This depends on your logging implementation
        $this->markTestSkipped('Audit trail verification depends on logging implementation');
    }
}
