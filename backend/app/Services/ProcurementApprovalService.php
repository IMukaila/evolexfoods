<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestApproval;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Two-Tier Procurement Approval Matrix (§44–47).
 *
 *   Purchase Request → Approval → Purchase Order → (goods received …)
 *
 * Every request is routed on submission by estimated cost:
 *
 *   Tier 1 — Supervisor approval:   estimated cost ≤ GH₵1,000
 *   Tier 2 — Executive approval:    estimated cost > GH₵1,000, routed
 *                                   directly to Executive/Owner approvers.
 *
 * A purchase may not move forward until the authorised approver for its
 * tier has approved it (§46); only then can a Purchase Order be raised
 * against it (§47). The threshold is configurable through the
 * `procurement_executive_threshold_ghs` setting.
 */
class ProcurementApprovalService
{
    public function threshold(): float
    {
        try {
            $value = Setting::get('procurement_executive_threshold_ghs', null);
            if ($value !== null && is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        } catch (\Throwable) {
            // Settings table unavailable (e.g. mid-migration) — use config.
        }

        return (float) config('evolex.procurement.executive_threshold_ghs', 1000);
    }

    /** 1 = Supervisor tier, 2 = Executive tier. Unknown costs start at Tier 1. */
    public function tierFor(mixed $estimatedCost): int
    {
        return ((float) ($estimatedCost ?? 0)) > $this->threshold() ? 2 : 1;
    }

    public function tierConfig(int $tier): array
    {
        return (array) config('evolex.procurement.tiers.'.$tier, []);
    }

    /** Routing fields stamped onto a new purchase request. */
    public function routingFor(mixed $estimatedCost): array
    {
        $tier = $this->tierFor($estimatedCost);

        return [
            'approval_tier' => $tier,
            'approval_stage' => $tier === 2 ? 'pending_tier_2' : 'pending_tier_1',
            'required_approver_role' => $tier === 2 ? 'executive' : 'supervisor',
        ];
    }

    /** Full matrix description for the approval-queue UI. */
    public function matrix(): array
    {
        $threshold = $this->threshold();

        return [
            'currency' => 'GH₵',
            'executive_threshold' => $threshold,
            'workflow' => ['Purchase Request', 'Approval', 'Purchase Order', 'Goods Received', 'Inventory Updated', 'Closed'],
            'tiers' => [
                [
                    'tier' => 1,
                    'key' => 'tier_1',
                    'label' => $this->tierConfig(1)['label'] ?? 'Tier 1 · Supervisor approval',
                    'approver_label' => $this->tierConfig(1)['approver_label'] ?? 'Supervisor / Operations',
                    'rule' => 'Estimated cost ≤ GH₵'.number_format($threshold, 2),
                    'min_cost' => 0,
                    'max_cost' => $threshold,
                    'roles' => $this->rolesForTier(1),
                    'stage' => 'pending_tier_1',
                ],
                [
                    'tier' => 2,
                    'key' => 'tier_2',
                    'label' => $this->tierConfig(2)['label'] ?? 'Tier 2 · Executive approval',
                    'approver_label' => $this->tierConfig(2)['approver_label'] ?? 'Executive / Owner',
                    'rule' => 'Estimated cost > GH₵'.number_format($threshold, 2).' — routed directly to Executive approval',
                    'min_cost' => $threshold,
                    'max_cost' => null,
                    'roles' => $this->rolesForTier(2),
                    'stage' => 'pending_tier_2',
                ],
            ],
            'source' => 'config/evolex.php, settings',
        ];
    }

    /** @return string[] users.role values allowed to decide at a tier */
    public function rolesForTier(int $tier): array
    {
        return array_values((array) ($this->tierConfig($tier)['roles'] ?? []));
    }

    /** Tier a request belongs to — stored routing wins, cost is the fallback. */
    public function tierOf(PurchaseRequest $request): int
    {
        return (int) ($request->approval_tier ?: $this->tierFor($request->estimated_cost));
    }

    public function stageOf(PurchaseRequest $request): string
    {
        if ($request->approval_stage) {
            return (string) $request->approval_stage;
        }

        return match ($request->status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'ordered', 'converted' => 'ordered',
            'received' => 'received',
            'closed' => 'closed',
            default => $this->tierOf($request) === 2 ? 'pending_tier_2' : 'pending_tier_1',
        };
    }

    public function canDecide(User $user, PurchaseRequest $request): bool
    {
        $role = strtolower((string) ($user->role ?? ''));
        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        $tier = $this->tierOf($request);
        if (in_array($role, $this->rolesForTier($tier), true)) {
            return true;
        }

        // Role-model permissions: Tier 1 may also be decided by anyone whose
        // role carries procurement.approve. Tier 2 stays Executive-only.
        if ($tier === 1) {
            try {
                return $user->hasPermission('procurement.approve');
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Record an approval / rejection at the request's routed tier.
     *
     * @throws ValidationException 422 when decided twice or by the wrong tier
     */
    public function decide(PurchaseRequest $request, User $approver, string $decision, ?string $comments = null): PurchaseRequestApproval
    {
        $stage = $this->stageOf($request);
        if (! in_array($stage, ['pending_tier_1', 'pending_tier_2'], true)) {
            throw ValidationException::withMessages([
                'decision' => $request->request_number.' has already been '.$stage.' and cannot be decided again.',
            ]);
        }

        $tier = $this->tierOf($request);
        if (! $this->canDecide($approver, $request)) {
            $label = $this->tierConfig($tier)['approver_label'] ?? 'an authorised approver';
            throw ValidationException::withMessages([
                'decision' => $request->request_number.' is a '.($tier === 2 ? 'Tier 2' : 'Tier 1').' purchase and requires '.$label.' approval. Your role ('.($approver->role ?: 'unknown').') cannot decide it.',
            ]);
        }

        return DB::transaction(function () use ($request, $approver, $decision, $comments, $tier) {
            $approval = PurchaseRequestApproval::create([
                'purchase_request_id' => $request->id,
                'approver_id' => $approver->id,
                'tier' => $tier,
                'approver_role' => $approver->role,
                'decision' => $decision,
                'decided_at' => now(),
                'comments' => $comments,
            ]);

            $request->update([
                'status' => $decision === 'approved' ? 'approved' : 'rejected',
                'approval_stage' => $decision === 'approved' ? 'approved' : 'rejected',
                'approval_tier' => $tier,
            ]);

            AuditLog::create([
                'user_id' => $approver->id,
                'action' => 'purchase.'.$decision,
                'auditable_type' => PurchaseRequest::class,
                'auditable_id' => $request->id,
                'meta' => [
                    'request_number' => $request->request_number,
                    'tier' => $tier,
                    'estimated_cost' => $request->estimated_cost,
                ],
            ]);

            return $approval;
        });
    }

    /**
     * Raise a Purchase Order against a fully approved request (§47).
     * The PO carries the approval reference via the request link.
     */
    public function createPurchaseOrder(PurchaseRequest $request, User $actor, array $data = []): PurchaseOrder
    {
        if ($this->stageOf($request) !== 'approved') {
            throw ValidationException::withMessages([
                'purchase_order' => 'A Purchase Order can only be raised after '.$request->request_number.' has been approved at '.($this->tierOf($request) === 2 ? 'Executive' : 'Supervisor').' tier.',
            ]);
        }

        return DB::transaction(function () use ($request, $actor, $data) {
            $year = now()->format('Y');
            $highest = 0;
            foreach (PurchaseOrder::where('po_number', 'like', 'PO-'.$year.'-%')->pluck('po_number') as $number) {
                $suffix = substr((string) $number, strlen('PO-'.$year.'-'));
                if (is_numeric($suffix)) {
                    $highest = max($highest, (int) $suffix);
                }
            }

            $order = PurchaseOrder::create([
                'po_number' => 'PO-'.$year.'-'.str_pad((string) ($highest + 1), 3, '0', STR_PAD_LEFT),
                'purchase_request_id' => $request->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'ordered_at' => $data['ordered_at'] ?? now()->toDateString(),
                'total_amount' => $data['total_amount'] ?? $request->estimated_cost ?? 0,
                'status' => 'open',
                'odoo_ref' => $data['odoo_ref'] ?? null,
            ]);

            $request->update(['status' => 'ordered', 'approval_stage' => 'ordered']);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'purchase.order_created',
                'auditable_type' => PurchaseOrder::class,
                'auditable_id' => $order->id,
                'meta' => ['po_number' => $order->po_number, 'request_number' => $request->request_number],
            ]);

            return $order;
        });
    }

    /**
     * Record receipt of goods against a Purchase Order and update statuses.
     */
    public function receiveOrder(PurchaseOrder $order, User $actor, array $data = []): GoodsReceipt
    {
        return DB::transaction(function () use ($order, $actor, $data) {
            $qty = $data['quantity_received'] ?? $order->request?->quantity ?? 1;
            $receipt = GoodsReceipt::create([
                'purchase_order_id' => $order->id,
                'received_at' => $data['received_at'] ?? now()->toDateString(),
                'quantity_received' => $qty,
                'condition' => $data['condition'] ?? 'ok',
                'discrepancy' => $data['discrepancy'] ?? null,
                'received_by' => $actor->id,
            ]);

            $isClose = ! empty($data['close']);
            $newStatus = $isClose ? 'closed' : 'received';

            $order->update(['status' => $newStatus]);

            if ($order->request) {
                $order->request->update([
                    'status' => $newStatus,
                    'approval_stage' => $newStatus,
                ]);
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => $isClose ? 'purchase.received_and_closed' : 'purchase.goods_received',
                'auditable_type' => GoodsReceipt::class,
                'auditable_id' => $receipt->id,
                'meta' => [
                    'po_number' => $order->po_number,
                    'quantity_received' => $qty,
                    'condition' => $receipt->condition,
                    'status' => $newStatus,
                ],
            ]);

            return $receipt;
        });
    }

    /**
     * Close a purchase order and its parent request.
     */
    public function closeOrder(PurchaseOrder $order, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actor) {
            $order->update(['status' => 'closed']);

            if ($order->request) {
                $order->request->update([
                    'status' => 'closed',
                    'approval_stage' => 'closed',
                ]);
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'purchase.order_closed',
                'auditable_type' => PurchaseOrder::class,
                'auditable_id' => $order->id,
                'meta' => [
                    'po_number' => $order->po_number,
                    'request_number' => $order->request?->request_number,
                ],
            ]);

            return $order;
        });
    }

    /**
     * Receive goods directly from a Purchase Request.
     */
    public function receiveRequest(PurchaseRequest $request, User $actor, array $data = []): GoodsReceipt
    {
        // If request does not have a purchase order yet, create one
        $order = $request->purchaseOrders()->latest()->first();
        if (! $order) {
            if (! in_array($request->status, ['approved', 'ordered', 'received'], true)) {
                $request->update(['status' => 'approved', 'approval_stage' => 'approved']);
            }
            $order = $this->createPurchaseOrder($request, $actor, [
                'supplier_id' => $data['supplier_id'] ?? null,
                'total_amount' => $request->estimated_cost ?? 0,
            ]);
        }

        return $this->receiveOrder($order, $actor, $data);
    }

    /**
     * Close a purchase request directly.
     */
    public function closeRequest(PurchaseRequest $request, User $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $request->update([
                'status' => 'closed',
                'approval_stage' => 'closed',
            ]);

            foreach ($request->purchaseOrders as $order) {
                if ($order->status !== 'closed') {
                    $order->update(['status' => 'closed']);
                }
            }

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'purchase.request_closed',
                'auditable_type' => PurchaseRequest::class,
                'auditable_id' => $request->id,
                'meta' => ['request_number' => $request->request_number],
            ]);

            return $request;
        });
    }
}
