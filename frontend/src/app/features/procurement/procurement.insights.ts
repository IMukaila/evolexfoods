/**
 * Pure helpers behind the Two-Tier Procurement Approval Matrix (§44–47).
 *
 * These mirror the server rules in App\Services\ProcurementApprovalService
 * so the UI can preview routing *before* submission ("GH₵2,100 → Executive
 * approval") and explain, for every queued request, exactly whose decision
 * it is waiting for. The API remains the source of truth.
 */

import { ApprovalStage, ApprovalTier, PurchaseRequestRecord } from './procurement.models';

/** GH₵ — at or below: Supervisor (Tier 1). Above: Executive (Tier 2). */
export const DEFAULT_EXECUTIVE_THRESHOLD = 1000;

/** users.role values allowed to decide each tier — mirrors config/evolex.php. */
export const TIER_ROLES: Record<ApprovalTier, string[]> = {
  1: ['supervisor', 'operations', 'operations_manager', 'farm_manager', 'procurement', 'executive', 'owner', 'admin'],
  2: ['executive', 'owner', 'ceo', 'admin']
};

export function tierForCost(estimatedCost: number | string | null | undefined, threshold = DEFAULT_EXECUTIVE_THRESHOLD): ApprovalTier {
  const cost = Number(estimatedCost ?? 0);
  return Number.isFinite(cost) && cost > threshold ? 2 : 1;
}

export function tierLabel(tier: ApprovalTier | number): string {
  return tier === 2 ? 'Tier 2 · Executive approval' : 'Tier 1 · Supervisor approval';
}

export function approverLabel(tier: ApprovalTier | number): string {
  return tier === 2 ? 'Executive / Owner' : 'Supervisor / Operations';
}

export function routingSummary(estimatedCost: number | string | null | undefined, threshold = DEFAULT_EXECUTIVE_THRESHOLD): string {
  const tier = tierForCost(estimatedCost, threshold);
  return tier === 2
    ? `Over GH₵${formatMoney(threshold)} — routes directly to Executive approval`
    : `At or below GH₵${formatMoney(threshold)} — a Supervisor / Operations approver can decide`;
}

export function stageOf(request: PurchaseRequestRecord): ApprovalStage {
  if (request.approval_stage) {
    return request.approval_stage as ApprovalStage;
  }
  switch (request.status) {
    case 'approved':
      return 'approved';
    case 'rejected':
      return 'rejected';
    case 'ordered':
    case 'converted':
      return 'ordered';
    case 'received':
      return 'received';
    case 'closed':
      return 'closed';
    default:
      return tierOf(request) === 2 ? 'pending_tier_2' : 'pending_tier_1';
  }
}

export function tierOf(request: PurchaseRequestRecord): ApprovalTier {
  return (Number(request.approval_tier) === 2 ? 2 : 1) as ApprovalTier;
}

export function isPending(request: PurchaseRequestRecord): boolean {
  const stage = stageOf(request);
  return stage === 'pending_tier_1' || stage === 'pending_tier_2';
}

/** Can this signed-in role decide this request right now? */
export function canDecide(role: string | null | undefined, request: PurchaseRequestRecord): boolean {
  if (!isPending(request)) {
    return false;
  }
  const normalised = String(role ?? '').toLowerCase();
  if (normalised === 'owner' || normalised === 'admin') {
    return true;
  }
  return TIER_ROLES[tierOf(request)].includes(normalised);
}

/** Plain-language "waiting on …" for a pending request. */
export function waitingOn(request: PurchaseRequestRecord, role: string | null | undefined): string {
  if (!isPending(request)) {
    return stageLabel(stageOf(request));
  }
  return canDecide(role, request) ? 'Waiting on you' : `Waiting on ${approverLabel(tierOf(request))}`;
}

export function stageLabel(stage: ApprovalStage | string): string {
  switch (stage) {
    case 'pending_tier_1':
      return 'Pending Supervisor approval';
    case 'pending_tier_2':
      return 'Pending Executive approval';
    case 'approved':
      return 'Approved — ready for PO';
    case 'rejected':
      return 'Rejected';
    case 'ordered':
      return 'Purchase order raised';
    case 'received':
      return 'Goods received';
    case 'closed':
      return 'Closed';
    default:
      return String(stage).replace(/_/g, ' ');
  }
}

export function formatMoney(value: number | string | null | undefined): string {
  const amount = Number(value ?? 0);
  if (!Number.isFinite(amount)) {
    return '—';
  }
  return amount.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export interface ApprovalQueues {
  /** Pending and decidable by the signed-in role. */
  awaitingYou: PurchaseRequestRecord[];
  /** Pending but routed to a different tier than the signed-in role. */
  awaitingOthers: PurchaseRequestRecord[];
  /** Approved and ready to become a Purchase Order. */
  readyForPo: PurchaseRequestRecord[];
  /** PO raised / awaiting delivery. */
  ordered: PurchaseRequestRecord[];
  /** Rejected, received or fully closed. */
  closed: PurchaseRequestRecord[];
}

export function buildQueues(records: PurchaseRequestRecord[], role: string | null | undefined): ApprovalQueues {
  const queues: ApprovalQueues = { awaitingYou: [], awaitingOthers: [], readyForPo: [], ordered: [], closed: [] };
  for (const request of records) {
    const stage = stageOf(request);
    if (stage === 'approved') {
      queues.readyForPo.push(request);
    } else if (stage === 'ordered') {
      queues.ordered.push(request);
    } else if (stage === 'rejected' || stage === 'received' || stage === 'closed') {
      queues.closed.push(request);
    } else if (canDecide(role, request)) {
      queues.awaitingYou.push(request);
    } else {
      queues.awaitingOthers.push(request);
    }
  }
  return queues;
}
