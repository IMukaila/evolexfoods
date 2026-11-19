/**
 * Types for the Two-Tier Procurement Approval Matrix (manual §44–47).
 *
 *   Purchase Request → Approval → Purchase Order → Goods Received → Closed
 *
 * Tier 1 (Supervisor) decides purchases at or below GH₵1,000; anything above
 * is routed directly to Tier 2 (Executive). Routing is stamped on the request
 * by the API (`approval_tier`, `approval_stage`) — these types mirror it.
 */

export type ApprovalTier = 1 | 2;
export type ApprovalStage = 'pending_tier_1' | 'pending_tier_2' | 'approved' | 'rejected' | 'ordered' | 'received' | 'closed';

export interface PurchaseRequestRecord {
  id: number;
  request_number: string;
  requested_by: number;
  requested_at: string;
  item_name: string;
  category: string | null;
  quantity: number | string;
  unit: string | null;
  estimated_cost: number | string | null;
  priority: string | null;
  status: string;
  justification: string | null;
  approval_tier: ApprovalTier | number;
  approval_stage: ApprovalStage | string;
  required_approver_role: string | null;
  tier_label?: string;
  approver_label?: string;
  requires_executive?: boolean;
  requester?: { id: number; name: string; role?: string } | null;
  approvals?: PurchaseApprovalRecord[];
  purchase_orders?: PurchaseOrderRecord[];
}

export interface PurchaseApprovalRecord {
  id: number;
  purchase_request_id: number;
  approver_id: number;
  tier: ApprovalTier | number | null;
  approver_role: string | null;
  decision: string;
  decided_at: string;
  comments: string | null;
  approver?: { id: number; name: string; role?: string } | null;
}

export interface ApprovalTierDef {
  tier: number;
  key: string;
  label: string;
  approver_label: string;
  rule: string;
  min_cost: number;
  max_cost: number | null;
  roles: string[];
  stage: string;
}

export interface ApprovalMatrix {
  currency: string;
  executive_threshold: number;
  workflow: string[];
  tiers: ApprovalTierDef[];
  source: string;
}

export interface PurchaseRequestsPayload {
  count: number;
  pending: number;
  pending_tier_1: number;
  pending_tier_2: number;
  approval_matrix: ApprovalMatrix;
  records: PurchaseRequestRecord[];
  source: string;
}

export interface GoodsReceiptRecord {
  id: number;
  purchase_order_id: number;
  received_at: string;
  quantity_received: number | string;
  condition: string;
  discrepancy?: string | null;
  received_by?: number | null;
  receiver?: { id: number; name: string; role?: string } | null;
}

export interface PurchaseOrderRecord {
  id: number;
  po_number: string;
  purchase_request_id: number | null;
  supplier_id: number | null;
  ordered_at: string;
  total_amount: number | string;
  status: string;
  supplier?: { id: number; name: string } | null;
  receipts?: GoodsReceiptRecord[];
  request?: PurchaseRequestRecord | null;
}

export interface PurchasesPayload {
  count: number;
  purchase_orders: PurchaseOrderRecord[];
  source: string;
}

export interface SupplierRecord {
  id: number;
  name: string;
  category?: string | null;
  phone?: string | null;
  email?: string | null;
  is_active?: boolean;
}
