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

    // Remove any previous print frame
    const existing = document.getElementById('invoice-print-frame');
    if (existing) {
      existing.remove();
    }

    const iframe = document.createElement('iframe');
    iframe.id = 'invoice-print-frame';
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.visibility = 'hidden';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow?.document;
    if (!doc) {
      window.print();
      return;
    }

    const html = this.generateInvoicePrintHtml(target);
    doc.open();
    doc.write(html);
    doc.close();

    // Give browser time to parse styles and fonts, then trigger print
    setTimeout(() => {
      try {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
      } catch {
        window.print();
      }
    }, 250);
  }

  generateInvoicePrintHtml(invoice: FinanceInvoice): string {
    const balance = this.outstandingBalance(invoice);
    const totalPaid = this.totalPaid(invoice);
    const totalAmount = Number(invoice.amount);
    const statusKey = this.displayStatus(invoice);
    const statusText = this.statusLabel(invoice).toUpperCase();

    const statusColors: Record<string, { bg: string; text: string; border: string }> = {
      paid: { bg: '#e8f5e9', text: '#1b5e20', border: '#a5d6a7' },
      overdue: { bg: '#ffebee', text: '#b71c1c', border: '#ef9a9a' },
      partial: { bg: '#e3f2fd', text: '#0d47a1', border: '#90caf9' },
      unpaid: { bg: '#fff8e1', text: '#e65100', border: '#ffe082' }
    };
    const colors = statusColors[statusKey] || statusColors['unpaid'];

    const supplier = invoice.supplier;
    const suppName = this.supplierName(invoice);
    const contactParts: string[] = [];
    if (supplier?.code) contactParts.push(`Vendor Code: ${supplier.code}`);
    if (supplier?.contact_name) contactParts.push(`Contact: ${supplier.contact_name}`);
    if (supplier?.phone) contactParts.push(`Phone: ${supplier.phone}`);
    if (supplier?.email) contactParts.push(`Email: ${supplier.email}`);
    if (supplier?.location) contactParts.push(`Location: ${supplier.location}`);
    const supplierInfoHtml = contactParts.map(p => `<p class="party-line">${p}</p>`).join('');

    let paymentsHtml = '';
    if (invoice.payments?.length) {
      paymentsHtml = `
        <div class="payments-section">
          <p class="section-title">Payment History</p>
          <table class="subtable">
            <thead>
              <tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th></tr>
            </thead>
            <tbody>
              ${invoice.payments.map(p => `
                <tr>
                  <td>${this.formatDate(p.paid_at)}</td>
                  <td>${(p.method || 'Payment').replace('_', ' ')}</td>
                  <td>${p.reference || '—'}</td>
                  <td class="text-right text-success"><strong>${this.formatCurrency(p.amount)}</strong></td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      `;
    }

    return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice ${invoice.invoice_number}</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 12mm 15mm;
    }
    * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      color: #1c2e1f;
      background: #ffffff;
      margin: 0;
      padding: 0;
      font-size: 12.5px;
      line-height: 1.45;
    }
    .sheet {
      width: 100%;
      max-width: 100%;
      margin: 0 auto;
    }
    .header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2.5px solid #2f4a32;
      padding-bottom: 16px;
      margin-bottom: 20px;
    }
    .brand-block {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .brand-logo {
      width: 44px;
      height: 44px;
      border-radius: 8px;
      background: #2f4a32;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 900;
    }
    .brand-name {
      margin: 0;
      font-size: 22px;
      color: #2f4a32;
      font-weight: 800;
      letter-spacing: -0.02em;
    }
    .brand-sub {
      margin: 2px 0 0;
      color: #556b50;
      font-size: 11.5px;
      font-weight: 600;
    }
    .brand-meta {
      margin: 2px 0 0;
      color: #777777;
      font-size: 10.5px;
    }
    .invoice-tag-block {
      text-align: right;
    }
    .status-badge {
      display: inline-block;
      padding: 5px 14px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      background: ${colors.bg};
      color: ${colors.text};
      border: 1px solid ${colors.border};
    }
    .invoice-number {
      margin: 6px 0 0;
      font-size: 15px;
      font-weight: 800;
      color: #1c2e1f;
    }
    .parties-grid {
      display: grid;
      grid-template-columns: 1.2fr 1fr;
      gap: 16px;
      margin-bottom: 20px;
    }
    .party-card {
      background: #faf9f5;
      border: 1px solid #e3ddcd;
      border-radius: 8px;
      padding: 12px 14px;
    }
    .party-kicker {
      margin: 0 0 5px;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #8a8578;
      font-weight: 800;
    }
    .party-name {
      margin: 0 0 4px;
      font-size: 13.5px;
      font-weight: 700;
      color: #1c2e1f;
    }
    .party-line {
      margin: 2px 0;
      font-size: 11.5px;
      color: #555555;
    }
    .meta-row {
      display: flex;
      justify-content: space-between;
      padding: 3px 0;
      font-size: 11.5px;
      color: #555555;
    }
    .meta-row strong {
      color: #1c2e1f;
    }
    .items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 18px;
      font-size: 12px;
    }
    .items-table th {
      background: #2f4a32;
      color: #ffffff;
      padding: 9px 12px;
      font-size: 10.5px;
      text-transform: uppercase;
      font-weight: 700;
      text-align: left;
    }
    .items-table td {
      padding: 10px 12px;
      border-bottom: 1px solid #e3ddcd;
      color: #1c2e1f;
    }
    .sheet-bottom {
      display: grid;
      grid-template-columns: 1.2fr 1fr;
      gap: 18px;
      align-items: start;
      margin-bottom: 20px;
    }
    .payment-notes {
      font-size: 11.5px;
      color: #555555;
      line-height: 1.5;
    }
    .section-title {
      font-size: 10.5px;
      text-transform: uppercase;
      font-weight: 800;
      letter-spacing: 0.05em;
      color: #8a8578;
      margin: 0 0 4px;
    }
    .banking-info {
      margin-top: 6px;
      padding: 8px 10px;
      background: #faf9f5;
      border: 1px solid #e3ddcd;
      border-radius: 6px;
      font-size: 11px;
    }
    .totals-box {
      background: #fdfaf2;
      border: 1px solid #d9d0bf;
      border-radius: 8px;
      padding: 12px 14px;
      display: grid;
      gap: 6px;
      font-size: 12px;
    }
    .total-row {
      display: flex;
      justify-content: space-between;
    }
    .total-row--grand {
      border-top: 1px solid #d9d0bf;
      padding-top: 8px;
      font-weight: 800;
      font-size: 14px;
    }
    .payments-section {
      margin-top: 16px;
      border-top: 1px solid #e3ddcd;
      padding-top: 14px;
    }
    .subtable {
      width: 100%;
      border-collapse: collapse;
      font-size: 11.5px;
      margin-top: 6px;
    }
    .subtable th {
      background: #f4efe4;
      color: #555555;
      padding: 6px 10px;
      font-size: 10px;
      text-transform: uppercase;
      text-align: left;
    }
    .subtable td {
      padding: 7px 10px;
      border-bottom: 1px solid #f0ead9;
    }
    .text-right { text-align: right !important; }
    .text-success { color: #1b5e20 !important; }
    .text-alert { color: #b71c1c !important; }
    .footer {
      margin-top: 24px;
      border-top: 1px solid #e3ddcd;
      padding-top: 10px;
      font-size: 10px;
      color: #8a8578;
      display: flex;
      justify-content: space-between;
    }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="header">
      <div class="brand-block">
        <div class="brand-logo">E</div>
        <div>
          <h1 class="brand-name">EVOLEX FOODS LTD</h1>
          <p class="brand-sub">Agricultural Production &amp; Supply Chain Management</p>
          <p class="brand-meta">Accra · Central Region, Ghana · finance@evolexfoods.com · +233 24 000 0000</p>
        </div>
      </div>
      <div class="invoice-tag-block">
        <span class="status-badge">${statusText}</span>
        <div class="invoice-number">${invoice.invoice_number}</div>
      </div>
    </div>

    <div class="parties-grid">
      <div class="party-card">
        <p class="party-kicker">Supplier / Billed From</p>
        <p class="party-name">${suppName}</p>
        ${supplierInfoHtml}
      </div>
      <div class="party-card">
        <p class="party-kicker">Invoice Details</p>
        <div class="meta-row"><span>Invoice Date:</span><strong>${this.formatDate(invoice.invoice_date)}</strong></div>
        <div class="meta-row"><span>Payment Due:</span><strong class="${statusKey === 'overdue' ? 'text-alert' : ''}">${this.formatDate(invoice.due_date)}</strong></div>
        <div class="meta-row"><span>Currency:</span><strong>GHS (Ghana Cedi)</strong></div>
      </div>
    </div>

    <table class="items-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Description</th>
          <th class="text-right">Qty</th>
          <th class="text-right">Unit Price</th>
          <th class="text-right">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>1</td>
          <td>Commercial Agricultural Supplies &amp; Deliveries (${invoice.invoice_number})</td>
          <td class="text-right">1</td>
          <td class="text-right">${this.formatCurrency(totalAmount)}</td>
          <td class="text-right"><strong>${this.formatCurrency(totalAmount)}</strong></td>
        </tr>
      </tbody>
    </table>

    <div class="sheet-bottom">
      <div class="payment-notes">
        <p class="section-title">Payment terms &amp; instructions</p>
        <p>Please quote <strong>${invoice.invoice_number}</strong> on all remittance advices and bank transfers.</p>
        <div class="banking-info">
          Bank: <strong>GCB Bank / Stanbic Bank Ghana</strong><br />
          Account Name: <strong>Evolex Foods Ltd</strong><br />
          Mobile Money: <strong>MTN MoMo Merchant</strong>
        </div>
      </div>

      <div class="totals-box">
        <div class="total-row"><span>Subtotal:</span><strong>${this.formatCurrency(totalAmount)}</strong></div>
        <div class="total-row text-success"><span>Payments Cleared:</span><strong>- ${this.formatCurrency(totalPaid)}</strong></div>
        <div class="total-row total-row--grand">
          <span>Balance Due:</span>
          <strong class="${balance > 0 ? 'text-alert' : 'text-success'}">${this.formatCurrency(balance)}</strong>
        </div>
      </div>
    </div>

    ${paymentsHtml}

    <div class="footer">
      <span>Evolex Foods Ltd · Supply Chain, Farm Operations &amp; Accounts Payable</span>
      <span>Official System Record · evolexfoods.com</span>
    </div>
  </div>
</body>
</html>`;
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
