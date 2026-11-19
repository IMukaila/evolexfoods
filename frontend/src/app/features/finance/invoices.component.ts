import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../../core/services/api.service';
import { FinanceInvoice, FinanceOverview, SupplierLookup } from './finance.models';
import { FinanceNavComponent } from './finance-nav.component';

type InvoiceFilter = 'all' | 'overdue' | 'unpaid' | 'partial' | 'paid';
type InvoiceModal = 'invoice' | 'payment' | 'preview' | null;

interface InvoiceForm {
  invoice_number: string;
  supplier_id: number | null;
  amount: number | null;
  invoice_date: string;
  due_date: string;
}

interface PaymentForm {
  invoice_id: number | null;
  amount: number | null;
  paid_at: string;
  method: string;
  reference: string;
}

@Component({
  selector: 'app-invoices',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, FinanceNavComponent],
  templateUrl: './invoices.component.html',
  styleUrl: './invoices.component.scss'
})
export class InvoicesComponent implements OnInit {
  readonly filters: { id: InvoiceFilter; label: string }[] = [
    { id: 'all', label: 'All bills' },
    { id: 'overdue', label: 'Overdue' },
    { id: 'unpaid', label: 'Unpaid' },
    { id: 'partial', label: 'Part-paid' },
    { id: 'paid', label: 'Paid' }
  ];

  readonly paymentMethods = [
    { value: 'bank_transfer', label: 'Bank transfer' },
    { value: 'mobile_money', label: 'Mobile money' },
    { value: 'cash', label: 'Cash' },
    { value: 'card', label: 'Card' },
    { value: 'other', label: 'Other' }
  ];

  overview: FinanceOverview | null = null;
  invoices: FinanceInvoice[] = [];
  suppliers: SupplierLookup[] = [];
  loading = true;
  loadingOverview = true;
  error = '';
  overviewError = '';
  message = '';
  searchTerm = '';
  statusFilter: InvoiceFilter = 'all';
  modal: InvoiceModal = null;
  saving = false;
  previewInvoice: FinanceInvoice | null = null;
  downloadingId: number | null = null;
  editingId: number | null = null;
  invoiceForm: InvoiceForm = this.emptyInvoiceForm();
  paymentForm: PaymentForm = this.emptyPaymentForm();
  paymentInvoiceLabel = '';

  constructor(private readonly api: ApiService, private readonly route: ActivatedRoute) {}

  ngOnInit(): void {
    this.route.queryParamMap.subscribe((params) => {
      const status = params.get('status');
      if (status === 'overdue' || status === 'paid' || status === 'unpaid' || status === 'partial') {
        this.statusFilter = status;
      }
    });
    this.loadInvoices();
    this.loadSuppliers();
    this.loadOverview();
  }

  get filteredInvoices(): FinanceInvoice[] {
    const search = this.searchTerm.trim().toLowerCase();
    return this.invoices.filter((invoice) => {
      const status = this.displayStatus(invoice);
      if (this.statusFilter !== 'all' && status !== this.statusFilter) {
        return false;
      }
      if (!search) {
        return true;
      }
      return `${invoice.invoice_number} ${invoice.supplier?.name ?? ''}`.toLowerCase().includes(search);
    });
  }

  get agingRows(): { label: string; amount: number; tone: string }[] {
    const aging = this.overview?.invoice_aging;
    if (!aging) {
      return [];
    }
    return [
      { label: 'Current', amount: Number(aging.current), tone: 'current' },
      { label: '1–30 days overdue', amount: Number(aging.days_1_30), tone: 'watch' },
      { label: '31–60 days overdue', amount: Number(aging.days_31_60), tone: 'late' },
      { label: '61+ days overdue', amount: Number(aging.days_61_plus), tone: 'critical' }
    ];
  }

  get agingMaximum(): number {
    return Math.max(1, ...this.agingRows.map((row) => row.amount));
  }

  get paymentTrendMaximum(): number {
    return Math.max(1, ...(this.overview?.monthly_activity ?? []).map((month) => Number(month.payments)));
  }

  get paidThisPeriod(): number {
    return Number(this.overview?.totals.payments ?? 0);
  }

  trackInvoiceById = (_index: number, invoice: FinanceInvoice): number => invoice.id;

  setFilter(filter: InvoiceFilter): void {
    this.statusFilter = filter;
  }

  refresh(): void {
    this.message = '';
    this.loadInvoices();
    this.loadOverview();
  }

  openCreate(): void {
    this.editingId = null;
    this.invoiceForm = this.emptyInvoiceForm();
    this.modal = 'invoice';
    this.error = '';
  }

  openEdit(invoice: FinanceInvoice): void {
    this.editingId = invoice.id;
    this.invoiceForm = {
      invoice_number: invoice.invoice_number,
      supplier_id: invoice.supplier_id,
      amount: Number(invoice.amount),
      invoice_date: this.toDateInput(invoice.invoice_date),
      due_date: this.toDateInput(invoice.due_date)
    };
    this.modal = 'invoice';
    this.error = '';
  }

  selectedPaymentBalance(): number {
    const invoice = this.invoices.find((item) => item.id === this.paymentForm.invoice_id);
    return invoice ? this.outstandingBalance(invoice) : 0;
  }

  openPayment(invoice: FinanceInvoice): void {
    this.paymentForm = {
      invoice_id: invoice.id,
      amount: this.outstandingBalance(invoice),
      paid_at: this.todayInput(),
      method: 'bank_transfer',
      reference: ''
    };
    this.paymentInvoiceLabel = `${invoice.invoice_number} · ${this.supplierName(invoice)}`;
    this.modal = 'payment';
    this.error = '';
  }

  closeModal(): void {
    if (!this.saving) {
      this.modal = null;
    }
  }

  openPreview(invoice: FinanceInvoice): void {
    this.previewInvoice = invoice;
    this.modal = 'preview';
    this.error = '';
  }

  printInvoice(invoice?: FinanceInvoice): void {
    const target = invoice || this.previewInvoice;
    if (!target) return;
    this.previewInvoice = target;
    this.modal = 'preview';
    setTimeout(() => {
      window.print();
    }, 150);
  }

  downloadPdf(invoice: FinanceInvoice): void {
    this.downloadingId = invoice.id;
    this.api.getBlob(`/invoices/${invoice.id}/pdf`).subscribe({
      next: (blob) => {
        this.downloadingId = null;
        const blobUrl = window.URL.createObjectURL(blob as Blob);
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = `${invoice.invoice_number}.pdf`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
      },
      error: () => {
        this.downloadingId = null;
        window.open(`/api/v1/invoices/${invoice.id}/pdf`, '_blank');
      }
    });
  }

  saveInvoice(): void {
    if (this.saving || !this.invoiceForm.invoice_number.trim() || !this.invoiceForm.amount || !this.invoiceForm.invoice_date) {
      return;
    }
    const payload: Record<string, unknown> = {
      invoice_number: this.invoiceForm.invoice_number.trim(),
      supplier_id: this.invoiceForm.supplier_id || null,
      amount: this.invoiceForm.amount,
      invoice_date: this.invoiceForm.invoice_date,
      due_date: this.invoiceForm.due_date || null
    };
    if (!this.editingId) {
      payload['status'] = 'unpaid';
    }

    this.saving = true;
    const request = this.editingId
      ? this.api.put(`/records/invoices/${this.editingId}`, payload)
      : this.api.post('/records/invoices', payload);
    request.subscribe({
      next: () => {
        this.saving = false;
        this.modal = null;
        this.message = this.editingId ? 'Invoice details updated.' : 'Invoice added to accounts payable.';
        this.loadInvoices();
        this.loadOverview();
      },
      error: (error) => {
        this.saving = false;
        this.error = this.apiError(error, 'Could not save this invoice.');
      }
    });
  }

  savePayment(): void {
    if (this.saving || !this.paymentForm.invoice_id || !this.paymentForm.amount || !this.paymentForm.paid_at) {
      return;
    }
    this.saving = true;
    this.api.post('/records/payments', {
      invoice_id: this.paymentForm.invoice_id,
      amount: this.paymentForm.amount,
      paid_at: this.paymentForm.paid_at,
      method: this.paymentForm.method,
      reference: this.paymentForm.reference.trim() || null
    }).subscribe({
      next: () => {
        this.saving = false;
        this.modal = null;
        this.message = 'Payment recorded and invoice balance updated.';
        this.loadInvoices();
        this.loadOverview();
      },
      error: (error) => {
        this.saving = false;
        this.error = this.apiError(error, 'Could not record this payment.');
      }
    });
  }

  removeInvoice(invoice: FinanceInvoice): void {
    if (!confirm(`Delete invoice ${invoice.invoice_number}? This cannot be undone.`)) {
      return;
    }
    this.api.delete(`/records/invoices/${invoice.id}`).subscribe({
      next: () => {
        this.message = 'Invoice deleted.';
        this.loadInvoices();
        this.loadOverview();
      },
      error: (error) => this.error = this.apiError(error, 'Could not delete this invoice.')
    });
  }

  totalPaid(invoice: FinanceInvoice): number {
    if (invoice.status === 'paid' && !(invoice.payments?.length)) {
      return Number(invoice.amount);
    }
    return (invoice.payments ?? []).reduce((sum, payment) => sum + Number(payment.amount), 0);
  }

  outstandingBalance(invoice: FinanceInvoice): number {
    if (invoice.status === 'paid' && !(invoice.payments?.length)) {
      return 0;
    }
    const paid = (invoice.payments ?? []).reduce((sum, payment) => sum + Number(payment.amount), 0);
    return Math.max(0, Number(invoice.amount) - paid);
  }

  displayStatus(invoice: FinanceInvoice): InvoiceFilter {
    const balance = this.outstandingBalance(invoice);
    if (balance <= 0) {
      return 'paid';
    }
    if (invoice.due_date && invoice.due_date.slice(0, 10) < this.todayInput()) {
      return 'overdue';
    }
    const paid = (invoice.payments ?? []).reduce((sum, payment) => sum + Number(payment.amount), 0);
    return paid > 0 || invoice.status === 'partial' ? 'partial' : 'unpaid';
  }

  statusLabel(invoice: FinanceInvoice): string {
    const status = this.displayStatus(invoice);
    return status === 'partial' ? 'Part-paid' : status === 'overdue' ? 'Overdue' : status.charAt(0).toUpperCase() + status.slice(1);
  }

  supplierName(invoice: FinanceInvoice): string {
    return invoice.supplier?.name || 'Unassigned supplier';
  }

  formatCurrency(value: number | string | null | undefined): string {
    const amount = Number(value ?? 0);
    return new Intl.NumberFormat('en-GH', {
      style: 'currency',
      currency: 'GHS',
      maximumFractionDigits: 2
    }).format(Number.isFinite(amount) ? amount : 0);
  }

  formatDate(value: string | null | undefined): string {
    if (!value) {
      return 'No due date';
    }
    const date = new Date(`${value.slice(0, 10)}T12:00:00`);
    return new Intl.DateTimeFormat('en-GH', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
  }

  agingWidth(amount: number): number {
    return amount > 0 ? Math.max(2, (amount / this.agingMaximum) * 100) : 0;
  }

  paymentBarHeight(amount: number): number {
    return amount > 0 ? Math.max(3, (amount / this.paymentTrendMaximum) * 105) : 0;
  }

  private loadInvoices(): void {
    this.loading = true;
    this.api.get<{ data: FinanceInvoice[] }>('/records/invoices').subscribe({
      next: (response) => {
        this.invoices = response.data ?? [];
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.error = 'We could not load the invoice register. Try again in a moment.';
      }
    });
  }

  private loadSuppliers(): void {
    this.api.get<{ suppliers: SupplierLookup[] }>('/lookups').subscribe({
      next: (response) => this.suppliers = response.suppliers ?? [],
      error: () => this.suppliers = []
    });
  }

  private loadOverview(): void {
    this.loadingOverview = true;
    this.api.get<FinanceOverview>('/finance/overview').subscribe({
      next: (response) => {
        this.overview = response;
        this.loadingOverview = false;
        this.overviewError = '';
      },
      error: () => {
        this.loadingOverview = false;
        this.overviewError = 'Aging analytics are temporarily unavailable.';
      }
    });
  }

  private apiError(error: any, fallback: string): string {
    const validation = error?.error?.errors;
    return validation ? Object.values(validation).flat().join(' ') : (error?.error?.message || fallback);
  }

  private emptyInvoiceForm(): InvoiceForm {
    return { invoice_number: '', supplier_id: null, amount: null, invoice_date: this.todayInput(), due_date: '' };
  }

  private emptyPaymentForm(): PaymentForm {
    return { invoice_id: null, amount: null, paid_at: this.todayInput(), method: 'bank_transfer', reference: '' };
  }

  private todayInput(): string {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  private toDateInput(value: string | null): string {
    return value ? value.slice(0, 10) : '';
  }
}
