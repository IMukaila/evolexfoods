import { ChangeDetectionStrategy, Component, DestroyRef, OnInit, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Title } from '@angular/platform-browser';
import { HttpErrorResponse } from '@angular/common/http';
import { ApiService } from '../../core/services/api.service';
import { AuthService } from '../../core/services/auth.service';
import {
  ApprovalMatrix,
  PurchaseOrderRecord,
  PurchaseRequestRecord,
  PurchaseRequestsPayload,
  PurchasesPayload,
  SupplierRecord
} from './procurement.models';
import {
  DEFAULT_EXECUTIVE_THRESHOLD,
  approverLabel,
  buildQueues,
  canDecide,
  formatMoney,
  isPending,
  routingSummary,
  stageLabel,
  tierForCost,
  tierLabel,
  waitingOn
} from './procurement.insights';

type LoadState = 'loading' | 'ready' | 'error';
type QueueTab = 'awaitingYou' | 'awaitingOthers' | 'readyForPo' | 'closed' | 'all';

interface RequestForm {
  item_name: string;
  category: string;
  quantity: number | null;
  unit: string;
  estimated_cost: number | null;
  priority: string;
  justification: string;
}

const EMPTY_FORM: RequestForm = { item_name: '', category: '', quantity: null, unit: '', estimated_cost: null, priority: 'normal', justification: '' };

/**
 * Procurement — Two-Tier Approval Matrix (§44–47).
 *
 * Requests are routed on cost: ≤ GH₵1,000 a Supervisor decides (Tier 1),
 * above that the request goes directly to Executive approval (Tier 2).
 * Only an approved request can become a Purchase Order.
 */
@Component({
  selector: 'app-procurement',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './procurement.component.html',
  styleUrl: './procurement.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ProcurementComponent implements OnInit {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly titleService = inject(Title);

  readonly state = signal<LoadState>('loading');
  readonly errorMessage = signal('');
  readonly notice = signal('');
  readonly records = signal<PurchaseRequestRecord[]>([]);
  readonly orders = signal<PurchaseOrderRecord[]>([]);
  readonly suppliers = signal<SupplierRecord[]>([]);
  readonly matrix = signal<ApprovalMatrix | null>(null);
  readonly tab = signal<QueueTab>('awaitingYou');
  readonly selectedId = signal<number | null>(null);
  readonly decisionComment = signal('');
  readonly poSupplierId = signal<number | null>(null);
  readonly busy = signal(false);
  readonly showForm = signal(false);
  readonly form = signal<RequestForm>({ ...EMPTY_FORM });

  readonly role = computed(() => this.auth.user?.role ?? '');
  readonly threshold = computed(() => this.matrix()?.executive_threshold ?? DEFAULT_EXECUTIVE_THRESHOLD);
  readonly queues = computed(() => buildQueues(this.records(), this.role()));
  readonly visibleRecords = computed(() => {
    const tab = this.tab();
    const queues = this.queues();
    return tab === 'all' ? this.records() : queues[tab];
  });
  readonly selected = computed(() => this.records().find((r) => r.id === this.selectedId()) ?? null);
  readonly formTier = computed(() => tierForCost(this.form().estimated_cost, this.threshold()));
  readonly formRouting = computed(() => routingSummary(this.form().estimated_cost, this.threshold()));

  ngOnInit(): void {
    this.titleService.setTitle('Procurement · Evolex Hub');
    this.load();
  }

  load(): void {
    this.state.set('loading');
    this.api
      .get<PurchaseRequestsPayload>('/procurement/requests')
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (payload) => {
          this.records.set(payload.records ?? []);
          if (payload.approval_matrix) {
            this.matrix.set(payload.approval_matrix);
          }
          this.state.set('ready');
          if (this.selectedId() === null && payload.records?.length) {
            this.selectedId.set(payload.records[0].id);
          }
        },
        error: (error: HttpErrorResponse) => {
          this.state.set('error');
          this.errorMessage.set(error.status === 401 ? 'Your session has expired. Sign in again.' : 'We could not load procurement. Check the API connection and try again.');
        }
      });
    this.api.get<PurchasesPayload>('/procurement/purchases').pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (payload) => this.orders.set(payload.purchase_orders ?? []),
      error: () => undefined
    });
    this.api.get<{ records: SupplierRecord[] }>('/suppliers').pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (payload) => this.suppliers.set((payload as unknown as { records?: SupplierRecord[] }).records ?? []),
      error: () => undefined
    });
    this.api.get<ApprovalMatrix>('/procurement/approval-matrix').pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (matrix) => this.matrix.set(matrix),
      error: () => undefined
    });
  }

  setTab(tab: QueueTab): void {
    this.tab.set(tab);
  }

  select(request: PurchaseRequestRecord): void {
    this.selectedId.set(request.id);
    this.decisionComment.set('');
    this.notice.set('');
  }

  toggleForm(): void {
    this.showForm.set(!this.showForm());
    if (this.showForm()) {
      this.form.set({ ...EMPTY_FORM });
    }
  }

  updateForm(patch: Partial<RequestForm>): void {
    this.form.set({ ...this.form(), ...patch });
  }

  submitRequest(): void {
    const form = this.form();
    if (!form.item_name.trim() || !form.quantity) {
      this.notice.set('Give the item a name and quantity so approvers know what is being bought.');
      return;
    }
    this.busy.set(true);
    this.api
      .post<PurchaseRequestRecord>('/procurement/requests', form)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (created) => {
          this.busy.set(false);
          this.showForm.set(false);
          this.notice.set(
            `${created.request_number} submitted — ${tierLabel(tierForCost(created.estimated_cost, this.threshold()))}. ${routingSummary(created.estimated_cost, this.threshold())}.`
          );
          this.load();
          this.selectedId.set(created.id);
        },
        error: (error: HttpErrorResponse) => {
          this.busy.set(false);
          this.notice.set(firstError(error) ?? 'The request could not be submitted. Try again.');
        }
      });
  }

  decide(decision: 'approve' | 'reject'): void {
    const request = this.selected();
    if (!request || this.busy()) {
      return;
    }
    this.busy.set(true);
    this.api
      .post<{ request: PurchaseRequestRecord }>(`/procurement/requests/${request.id}/${decision}`, { comments: this.decisionComment() || null })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.busy.set(false);
          this.notice.set(`${request.request_number} ${decision === 'approve' ? 'approved' : 'rejected'} at ${tierLabel(request.approval_tier)}.`);
          this.decisionComment.set('');
          this.load();
        },
        error: (error: HttpErrorResponse) => {
          this.busy.set(false);
          this.notice.set(firstError(error) ?? `That decision could not be recorded.`);
        }
      });
  }

  createPurchaseOrder(): void {
    const request = this.selected();
    if (!request || this.busy()) {
      return;
    }
    this.busy.set(true);
    this.api
      .post<{ purchase_order: PurchaseOrderRecord }>(`/procurement/requests/${request.id}/purchase-order`, {
        supplier_id: this.poSupplierId(),
        total_amount: request.estimated_cost
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (payload) => {
          this.busy.set(false);
          this.notice.set(`Purchase order ${payload.purchase_order.po_number} raised against ${request.request_number}. Goods can now be received against it.`);
          this.load();
        },
        error: (error: HttpErrorResponse) => {
          this.busy.set(false);
          this.notice.set(firstError(error) ?? 'The purchase order could not be raised.');
        }
      });
  }

  canDecideSelected(): boolean {
    const request = this.selected();
    return !!request && canDecide(this.role(), request);
  }

  helpers = { formatMoney, stageLabel, tierLabel, approverLabel, waitingOn, isPending };
}

function firstError(error: HttpErrorResponse): string | null {
  const body = error.error as { message?: string; errors?: Record<string, string[]> } | null;
  if (body?.errors) {
    const first = Object.values(body.errors)[0];
    if (first?.length) {
      return first[0];
    }
  }
  return body?.message ?? null;
}
