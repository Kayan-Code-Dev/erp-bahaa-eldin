<?php

namespace Tests\Feature\Transfer;

use Tests\Feature\BaseTestCase;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Cloth;

/**
 * Transfer Status Transition Tests
 *
 * Tests transfer approval workflow and status transitions according to TEST_COVERAGE.md specification
 * Transfer statuses: pending, partially_pending, partially_approved, approved, rejected
 * Item statuses: pending, approved, rejected
 */
class TransferStatusTest extends BaseTestCase
{
    // ==================== APPROVE TRANSFER (ALL ITEMS) ====================

    /**
     * Test: Approve Transfer (All Items)
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: POST /api/v1/transfers/{id}/approve
     * - Required Permission: transfers.approve
     * - Expected Status: 200
     * - Description: Approve all pending items in transfer (moves cloths to destination inventory)
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     * - Should Fail For: Users without permission (403), no pending items (422), cloth not in source inventory (422)
     */

    public function test_transfer_approve_all_items_succeeds()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();
        $cloth = Cloth::factory()->create();

        // Add cloth to branch inventory
        $branch->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        // Create transfer
        $transfer = Transfer::factory()->create([
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'status' => 'pending',
        ]);

        TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'approved',
            ]);

        // Verify transfer status
        $transfer->refresh();
        $this->assertEquals('approved', $transfer->status);

        // Verify all items approved
        $items = TransferItem::where('transfer_id', $transfer->id)->get();
        foreach ($items as $item) {
            $this->assertEquals('approved', $item->status);
        }

        // Verify cloth moved to destination inventory
        $this->assertFalse($branch->inventory->clothes()->where('cloth_id', $cloth->id)->exists());
        $this->assertTrue($workshop->inventory->clothes()->where('cloth_id', $cloth->id)->exists());
    }

    // ==================== APPROVE TRANSFER ITEMS (PARTIAL APPROVAL) ====================

    /**
     * Test: Approve Transfer Items (Partial Approval)
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: POST /api/v1/transfers/{id}/approve-items
     * - Required Permission: transfers.approve
     * - Expected Status: 200
     * - Description: Approve specific items in transfer
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     */

    public function test_transfer_approve_partial_items_succeeds()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();
        $cloth1 = Cloth::factory()->create();
        $cloth2 = Cloth::factory()->create();

        // Add cloths to branch inventory
        $branch->inventory->clothes()->attach($cloth1->id, ['quantity' => 1]);
        $branch->inventory->clothes()->attach($cloth2->id, ['quantity' => 1]);

        // Create transfer with two items
        $transfer = Transfer::factory()->create([
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'status' => 'pending',
        ]);

        $item1 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth1->id,
            'status' => 'pending',
        ]);

        $item2 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth2->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        // Approve only first item
        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/approve-items", [
            'item_ids' => [$item1->id],
        ]);

        $response->assertStatus(200);

        // Verify partial approval status
        $transfer->refresh();
        $this->assertEquals('partially_approved', $transfer->status);

        // Verify item statuses
        $item1->refresh();
        $item2->refresh();
        $this->assertEquals('approved', $item1->status);
        $this->assertEquals('pending', $item2->status);

        // Verify only approved cloth moved
        $this->assertFalse($branch->inventory->clothes()->where('cloth_id', $cloth1->id)->exists());
        $this->assertTrue($workshop->inventory->clothes()->where('cloth_id', $cloth1->id)->exists());
        $this->assertTrue($branch->inventory->clothes()->where('cloth_id', $cloth2->id)->exists());
        $this->assertFalse($workshop->inventory->clothes()->where('cloth_id', $cloth2->id)->exists());
    }

    // ==================== REJECT TRANSFER (ALL ITEMS) ====================

    /**
     * Test: Reject Transfer (All Items)
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: POST /api/v1/transfers/{id}/reject
     * - Required Permission: transfers.reject
     * - Expected Status: 200
     * - Description: Reject all pending items in transfer (cloths remain in source inventory)
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     */

    public function test_transfer_reject_all_items_succeeds()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();
        $cloth = Cloth::factory()->create();

        // Add cloth to branch inventory
        $branch->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        // Create transfer
        $transfer = Transfer::factory()->create([
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'status' => 'pending',
        ]);

        TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/reject");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'rejected',
            ]);

        // Verify transfer status
        $transfer->refresh();
        $this->assertEquals('rejected', $transfer->status);

        // Verify all items rejected
        $items = TransferItem::where('transfer_id', $transfer->id)->get();
        foreach ($items as $item) {
            $this->assertEquals('rejected', $item->status);
        }

        // Verify cloth remains in source inventory
        $this->assertTrue($branch->inventory->clothes()->where('cloth_id', $cloth->id)->exists());
        $this->assertFalse($workshop->inventory->clothes()->where('cloth_id', $cloth->id)->exists());
    }

    // ==================== REJECT TRANSFER ITEMS (PARTIAL REJECTION) ====================

    /**
     * Test: Reject Transfer Items (Partial Rejection)
     * - Type: Feature Test
     * - Module: Transfers
     * - Endpoint: POST /api/v1/transfers/{id}/reject-items
     * - Required Permission: transfers.reject
     * - Expected Status: 200
     * - Description: Reject specific items in transfer
     * - Should Pass For: general_manager, factory_manager, workshop_manager
     */

    public function test_transfer_reject_partial_items_succeeds()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();
        $cloth1 = Cloth::factory()->create();
        $cloth2 = Cloth::factory()->create();

        // Add cloths to branch inventory
        $branch->inventory->clothes()->attach($cloth1->id, ['quantity' => 1]);
        $branch->inventory->clothes()->attach($cloth2->id, ['quantity' => 1]);

        // Create transfer with two items
        $transfer = Transfer::factory()->create([
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'status' => 'pending',
        ]);

        $item1 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth1->id,
            'status' => 'pending',
        ]);

        $item2 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth2->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        // Reject only first item
        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/reject-items", [
            'item_ids' => [$item1->id],
        ]);

        $response->assertStatus(200);

        // Verify partial rejection status
        $transfer->refresh();
        $this->assertEquals('partially_approved', $transfer->status); // Mixed approved/rejected

        // Verify item statuses
        $item1->refresh();
        $item2->refresh();
        $this->assertEquals('rejected', $item1->status);
        $this->assertEquals('pending', $item2->status);

        // Verify cloths remain in source inventory
        $this->assertTrue($branch->inventory->clothes()->where('cloth_id', $cloth1->id)->exists());
        $this->assertTrue($branch->inventory->clothes()->where('cloth_id', $cloth2->id)->exists());
        $this->assertFalse($workshop->inventory->clothes()->where('cloth_id', $cloth1->id)->exists());
        $this->assertFalse($workshop->inventory->clothes()->where('cloth_id', $cloth2->id)->exists());
    }

    // ==================== TRANSFER STATUS CALCULATIONS ====================

    /**
     * Test: Transfer Status Updates to Approved (All Items Approved)
     * - Type: Feature Test
     * - Module: Transfers
     * - Description: Transfer status should be 'approved' when all items are approved
     */

    public function test_transfer_status_becomes_approved_when_all_items_approved()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);

        $item1 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $item2 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        // Approve both items
        $this->postJson("/api/v1/transfers/{$transfer->id}/approve-items", [
            'item_ids' => [$item1->id, $item2->id],
        ]);

        $transfer->refresh();
        $this->assertEquals('approved', $transfer->status);
    }

    /**
     * Test: Transfer Status Updates to Partially Approved (Mixed Items)
     * - Type: Feature Test
     * - Module: Transfers
     * - Description: Transfer status should be 'partially_approved' when some items are approved and some are rejected/pending
     */

    public function test_transfer_status_becomes_partially_approved_with_mixed_items()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);

        $item1 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $item2 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        // Approve one, reject one
        $this->postJson("/api/v1/transfers/{$transfer->id}/approve-items", [
            'item_ids' => [$item1->id],
        ]);

        $this->postJson("/api/v1/transfers/{$transfer->id}/reject-items", [
            'item_ids' => [$item2->id],
        ]);

        $transfer->refresh();
        $this->assertEquals('partially_approved', $transfer->status);
    }

    /**
     * Test: Transfer Status Updates to Rejected (All Items Rejected)
     * - Type: Feature Test
     * - Module: Transfers
     * - Description: Transfer status should be 'rejected' when all items are rejected
     */

    public function test_transfer_status_becomes_rejected_when_all_items_rejected()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);

        $item1 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $item2 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        // Reject both items
        $this->postJson("/api/v1/transfers/{$transfer->id}/reject-items", [
            'item_ids' => [$item1->id, $item2->id],
        ]);

        $transfer->refresh();
        $this->assertEquals('rejected', $transfer->status);
    }

    // ==================== WORKSHOP INTEGRATION ====================

    /**
     * Test: Workshop Approve Transfer (Receive Clothes)
     * - Type: Integration Test
     * - Module: Transfers, Workshops
     * - Description: Workshop can approve incoming transfers and receive clothes
     */

    public function test_workshop_approve_transfer_receives_clothes()
    {
        $branch = Branch::factory()->create();
        $workshop = \App\Models\Workshop::factory()->create();
        $cloth = Cloth::factory()->create();

        // Add cloth to branch inventory
        $branch->inventory->clothes()->attach($cloth->id, ['quantity' => 1]);

        // Create transfer from branch to workshop
        $transfer = Transfer::factory()->create([
            'from_entity_type' => 'branch',
            'from_entity_id' => $branch->id,
            'to_entity_type' => 'workshop',
            'to_entity_id' => $workshop->id,
            'status' => 'pending',
        ]);

        TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'cloth_id' => $cloth->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('workshop_manager');

        // Workshop approves transfer
        $response = $this->postJson("/api/v1/workshops/{$workshop->id}/approve-transfer/{$transfer->id}");

        $response->assertStatus(200);

        // Verify transfer approved
        $transfer->refresh();
        $this->assertEquals('approved', $transfer->status);

        // Verify cloth moved to workshop inventory
        $this->assertFalse($branch->inventory->clothes()->where('cloth_id', $cloth->id)->exists());
        $this->assertTrue($workshop->inventory->clothes()->where('cloth_id', $cloth->id)->exists());
    }

    // ==================== PERMISSION TESTS ====================

    public function test_transfer_approve_by_factory_manager_succeeds()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);
        TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/approve");

        $response->assertStatus(200);
    }

    public function test_transfer_approve_by_reception_employee_fails_403()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);
        TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('reception_employee');

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/approve");

        $this->assertPermissionDenied($response);
    }

    public function test_transfer_reject_by_workshop_manager_succeeds()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);
        TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('workshop_manager');

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/reject");

        $response->assertStatus(200);
    }

    // ==================== EDGE CASES ====================

    public function test_transfer_approve_with_no_pending_items_fails_422()
    {
        $transfer = Transfer::factory()->create(['status' => 'approved']);
        // No pending items

        $this->authenticateAs('factory_manager');

        $response = $this->postJson("/api/v1/transfers/{$transfer->id}/approve");

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_transfer_approve_nonexistent_transfer_fails_404()
    {
        $this->authenticateAs('factory_manager');

        $response = $this->postJson('/api/v1/transfers/99999/approve');

        $this->assertNotFound($response);
    }

    public function test_transfer_partial_operations_create_correct_status_transitions()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);

        $item1 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $item2 = TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        // Approve first item
        $this->postJson("/api/v1/transfers/{$transfer->id}/approve-items", [
            'item_ids' => [$item1->id],
        ]);

        $transfer->refresh();
        $this->assertEquals('partially_approved', $transfer->status);

        // Reject second item
        $this->postJson("/api/v1/transfers/{$transfer->id}/reject-items", [
            'item_ids' => [$item2->id],
        ]);

        $transfer->refresh();
        $this->assertEquals('partially_approved', $transfer->status); // Still partially approved

        // Check item statuses
        $item1->refresh();
        $item2->refresh();
        $this->assertEquals('approved', $item1->status);
        $this->assertEquals('rejected', $item2->status);
    }

    // ==================== TRANSFER ACTION LOGGING ====================

    public function test_transfer_operations_create_action_logs()
    {
        $transfer = Transfer::factory()->create(['status' => 'pending']);
        TransferItem::factory()->create([
            'transfer_id' => $transfer->id,
            'status' => 'pending',
        ]);

        $this->authenticateAs('factory_manager');

        $this->postJson("/api/v1/transfers/{$transfer->id}/approve");

        // Verify action log was created (assuming TransferAction model exists)
        // This test depends on your specific logging implementation
        $this->assertDatabaseHas('transfer_actions', [
            'transfer_id' => $transfer->id,
            'action' => 'approved',
        ]);
    }
}
