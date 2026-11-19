<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementReceiveAndCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_approve_reject_receive_and_close_procurement(): void
    {
        $requester = User::factory()->create(['role' => 'vet', 'status' => 'active']);
        $supervisor = User::factory()->create(['role' => 'farm_manager', 'status' => 'active']);
        $executive = User::factory()->create(['role' => 'owner', 'status' => 'active']);

        // 1. Create a Tier 1 request (≤ GH₵1,000)
        $req1 = $this->actingAs($requester, 'sanctum')->postJson('/api/v1/procurement/requests', [
            'item_name' => 'Goat Mineral Licks',
            'quantity' => 10,
            'unit' => 'block',
            'estimated_cost' => 500,
            'priority' => 'normal',
        ])->assertCreated()
            ->assertJsonPath('approval_stage', 'pending_tier_1');

        $req1Id = $req1->json('id');

        // 2. Reject another request to test reject action
        $reqToReject = $this->actingAs($requester, 'sanctum')->postJson('/api/v1/procurement/requests', [
            'item_name' => 'Unneeded Luxury Feed',
            'quantity' => 1,
            'unit' => 'bag',
            'estimated_cost' => 300,
        ])->assertCreated();

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$reqToReject->json('id')}/reject", [
                'comments' => 'Not approved due to budget constraints',
            ])
            ->assertOk()
            ->assertJsonPath('request.status', 'rejected')
            ->assertJsonPath('request.approval_stage', 'rejected');

        // 3. Supervisor approves Tier 1 request
        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$req1Id}/approve", [
                'comments' => 'Approved by farm manager',
            ])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved')
            ->assertJsonPath('request.approval_stage', 'approved');

        // 4. Raise purchase order against approved request
        $poRes = $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$req1Id}/purchase-order")
            ->assertCreated()
            ->assertJsonPath('request.status', 'ordered')
            ->assertJsonPath('purchase_order.status', 'open');

        $poId = $poRes->json('purchase_order.id');

        // 5. Receive items against the purchase order
        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/procurement/orders/{$poId}/receive", [
                'quantity_received' => 10,
                'condition' => 'ok',
                'discrepancy' => 'Delivered in full to Feed Store',
                'close' => false,
            ])
            ->assertOk()
            ->assertJsonPath('order.status', 'received')
            ->assertJsonPath('receipt.quantity_received', '10.00');

        $this->assertSame('received', PurchaseRequest::find($req1Id)->status);

        // 6. Close the purchase order
        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/procurement/orders/{$poId}/close")
            ->assertOk()
            ->assertJsonPath('order.status', 'closed');

        $this->assertSame('closed', PurchaseRequest::find($req1Id)->status);

        // 7. Test direct request receive & close on another request
        $req2 = $this->actingAs($requester, 'sanctum')->postJson('/api/v1/procurement/requests', [
            'item_name' => 'Vaccine Batch A',
            'quantity' => 50,
            'unit' => 'dose',
            'estimated_cost' => 2500, // Tier 2
        ])->assertCreated();

        $req2Id = $req2->json('id');

        // Executive approves Tier 2
        $this->actingAs($executive, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$req2Id}/approve")
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');

        // Receive directly from request with close=true
        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$req2Id}/receive", [
                'quantity_received' => 50,
                'condition' => 'ok',
                'close' => true,
            ])
            ->assertOk()
            ->assertJsonPath('request.status', 'closed')
            ->assertJsonPath('request.approval_stage', 'closed');

        $this->assertSame('closed', PurchaseRequest::find($req2Id)->status);
    }
}
