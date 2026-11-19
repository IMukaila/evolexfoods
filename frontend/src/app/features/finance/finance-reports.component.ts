import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import { FinanceExpense, FinanceOverview, FinancePayment } from './finance.models';
import { FinanceNavComponent } from './finance-nav.component';

type ReportRange = '30d' | '90d' | 'ytd' | 'custom';

type ActivityType = 'Expense' | 'Supplier payment';

interface LedgerEntry {
  id: string;
  date: string;
  type: ActivityType;
  reference: string;
  description: string;
  method: string;
  amount: number;
}

@Component({
  selector: 'app-finance-reports',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, FinanceNavComponent],
  templateUrl: './finance-reports.component.html',
  styleUrl: './finance-reports.component.scss'
})
export class FinanceReportsComponent implements OnInit {
  readonly ranges: { id: ReportRange; label: string }[] = [
    { id: '30d', label: '30 days' },
    { id: '90d', label: '90 days' },
    { id: 'ytd', label: 'Year to date' },
    { id: 'custom', label: 'Custom' }
  ];

  overview: FinanceOverview | null = null;
  expenses: FinanceExpense[] = [];
  payments: FinancePayment[] = [];
  entries: LedgerEntry[] = [];
  filteredEntries: LedgerEntry[] = [];
  searchQuery = '';
  activityFilter: 'all' | 'Expense' | 'Supplier payment' = 'all';
  methodFilter = 'all';
  sortFilter: 'date-desc' | 'date-asc' | 'amount-desc' | 'amount-asc' = 'date-desc';
  minAmountFilter: number | null = null;
  maxAmountFilter: number | null = null;
  loading = true;
  error = '';
  range: ReportRange = '30d';
  customFrom = '';
  customTo = '';
  loadedAt: Date | null = null;

  constructor(private readonly api: ApiService) {}

  ngOnInit(): void {
    this.seedCustomRange();
    this.load();
  }

  selectRange(range: ReportRange): void {
    this.range = range;
    if (range !== 'custom') {
      this.load();
    }
  }

  applyCustomRange(): void {
    if (this.customFrom && this.customTo && this.customFrom <= this.customTo) {
      this.load();
    }
  }

  load(): void {
    const bounds = this.rangeBounds();
    if (!bounds) {
      this.error = 'Choose a valid start and end date.';
      return;
    }

    this.loading = true;
    this.error = '';
    forkJoin({
      overview: this.api.get<FinanceOverview>('/finance/overview', bounds),
      expenses: this.api.get<{ data: FinanceExpense[] }>('/records/expenses'),
      payments: this.api.get<{ data: FinancePayment[] }>('/records/payments')
    }).subscribe({
      next: (response) => {
        this.overview = response.overview;
        this.expenses = response.expenses.data ?? [];
        this.payments = response.payments.data ?? [];
        this.entries = this.buildEntries(bounds.from, bounds.to);
        this.applyLedgerFilters();
        this.loadedAt = new Date();
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.error = 'We could not prepare the finance report. Check the API connection and try again.';
      }
    });
  }

  get monthlyMaximum(): number {
    return Math.max(1, ...(this.overview?.monthly_activity ?? []).map((month) => Number(month.expenses) + Number(month.payments)));
  }

  get categoryMaximum(): number {
    return Math.max(1, ...(this.overview?.expense_categories ?? []).map((category) => Number(category.amount)));
  }

  activityWidth(amount: number): number {
    return Math.min(100, Math.max(0, (amount / this.monthlyMaximum) * 100));
  }

  monthTotal(expenses: number, payments: number): number {
    return Number(expenses) + Number(payments);
  }

  categoryWidth(amount: number): number {
    return Math.min(100, Math.max(0, (amount / this.categoryMaximum) * 100));
  }

  formatCurrency(value: number | string | null | undefined): string {
    const amount = Number(value ?? 0);
    return new Intl.NumberFormat('en-GH', {
      style: 'currency',
      currency: 'GHS',
      maximumFractionDigits: 2
    }).format(Number.isFinite(amount) ? amount : 0);
  }

  formatDate(value: string): string {
    const date = new Date(`${value.slice(0, 10)}T12:00:00`);
    return new Intl.DateTimeFormat('en-GH', { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
  }

  get hasActiveFilters(): boolean {
    return (
      this.activityFilter !== 'all' ||
      this.methodFilter !== 'all' ||
      this.sortFilter !== 'date-desc' ||
      Boolean(this.searchQuery.trim()) ||
      this.minAmountFilter !== null ||
      this.maxAmountFilter !== null
    );
  }

  get availableMethods(): string[] {
    const set = new Set<string>();
    for (const e of this.entries) {
      if (e.method && e.method.trim()) {
        set.add(e.method.trim());
      }
    }
    return Array.from(set).sort();
  }

  get expenseCount(): number {
    return this.entries.filter((e) => e.type === 'Expense').length;
  }

  get paymentCount(): number {
    return this.entries.filter((e) => e.type === 'Supplier payment').length;
  }

  get filteredTotalAmount(): number {
    return this.filteredEntries.reduce((sum, e) => sum + (Number(e.amount) || 0), 0);
  }

  get filteredExpenseTotal(): number {
    return this.filteredEntries
      .filter((e) => e.type === 'Expense')
      .reduce((sum, e) => sum + (Number(e.amount) || 0), 0);
  }

  get filteredPaymentTotal(): number {
    return this.filteredEntries
      .filter((e) => e.type === 'Supplier payment')
      .reduce((sum, e) => sum + (Number(e.amount) || 0), 0);
  }

  applyLedgerFilters(): void {
    const q = this.searchQuery.trim().toLowerCase();
    const type = this.activityFilter;
    const method = this.methodFilter.toLowerCase();
    const min = this.minAmountFilter !== null && this.minAmountFilter !== undefined && (this.minAmountFilter as any) !== ''
      ? Number(this.minAmountFilter)
      : null;
    const max = this.maxAmountFilter !== null && this.maxAmountFilter !== undefined && (this.maxAmountFilter as any) !== ''
      ? Number(this.maxAmountFilter)
      : null;

    let list = this.entries.filter((entry) => {
      if (type !== 'all' && entry.type !== type) {
        return false;
      }
      if (method !== 'all' && (entry.method || '').toLowerCase() !== method) {
        return false;
      }
      if (min !== null && !isNaN(min) && entry.amount < min) {
        return false;
      }
      if (max !== null && !isNaN(max) && entry.amount > max) {
        return false;
      }
      if (q) {
        const matchesRef = (entry.reference || '').toLowerCase().includes(q);
        const matchesDesc = (entry.description || '').toLowerCase().includes(q);
        const matchesMethod = (entry.method || '').toLowerCase().includes(q);
        const matchesDate = this.formatDate(entry.date).toLowerCase().includes(q) || (entry.date || '').includes(q);
        const matchesAmount = entry.amount.toString().includes(q);
        if (!matchesRef && !matchesDesc && !matchesMethod && !matchesDate && !matchesAmount) {
          return false;
        }
      }
      return true;
    });

    list = list.sort((a, b) => {
      switch (this.sortFilter) {
        case 'date-asc':
          return (a.date || '').localeCompare(b.date || '');
        case 'amount-desc':
          return b.amount - a.amount;
        case 'amount-asc':
          return a.amount - b.amount;
        case 'date-desc':
        default:
          return (b.date || '').localeCompare(a.date || '');
      }
    });

    this.filteredEntries = list;
  }

  setActivityFilter(type: 'all' | 'Expense' | 'Supplier payment'): void {
    this.activityFilter = type;
    this.applyLedgerFilters();
  }

  resetLedgerFilters(): void {
    this.searchQuery = '';
    this.activityFilter = 'all';
    this.methodFilter = 'all';
    this.sortFilter = 'date-desc';
    this.minAmountFilter = null;
    this.maxAmountFilter = null;
    this.applyLedgerFilters();
  }

  formatMethodLabel(method: string): string {
    if (!method) return 'Not specified';
    return method.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }

  printLedger(): void {
    const printableHtml = this.buildLedgerPrintHtml();
    let printFrame = document.getElementById('ledger-print-frame') as HTMLIFrameElement;
    if (!printFrame) {
      printFrame = document.createElement('iframe');
      printFrame.id = 'ledger-print-frame';
      printFrame.style.position = 'fixed';
      printFrame.style.right = '0';
      printFrame.style.bottom = '0';
      printFrame.style.width = '0';
      printFrame.style.height = '0';
      printFrame.style.border = '0';
      printFrame.style.visibility = 'hidden';
      document.body.appendChild(printFrame);
    }
    const frameDoc = printFrame.contentDocument || printFrame.contentWindow?.document;
    if (frameDoc) {
      frameDoc.open();
      frameDoc.write(printableHtml);
      frameDoc.close();
      setTimeout(() => {
        printFrame.contentWindow?.focus();
        printFrame.contentWindow?.print();
      }, 250);
    }
  }

  buildLedgerPrintHtml(): string {
    const periodFrom = this.overview?.from ? this.formatDate(this.overview.from) : 'Start';
    const periodTo = this.overview?.to ? this.formatDate(this.overview.to) : 'End';
    const generatedAt = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
      ' at ' + new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

    let filterSummary = 'All Transactions (' + this.entries.length + ' entries in period)';
    if (this.hasActiveFilters) {
      const parts: string[] = [];
      if (this.activityFilter !== 'all') parts.push('Activity: ' + this.activityFilter);
      if (this.methodFilter !== 'all') parts.push('Method: ' + this.formatMethodLabel(this.methodFilter));
      if (this.searchQuery.trim()) parts.push('Search: "' + this.searchQuery.trim() + '"');
      if (this.minAmountFilter !== null) parts.push('Min: GHS ' + this.minAmountFilter);
      if (this.maxAmountFilter !== null) parts.push('Max: GHS ' + this.maxAmountFilter);
      filterSummary = 'Filtered view: ' + parts.join(' | ') + ' (' + this.filteredEntries.length + ' of ' + this.entries.length + ' entries)';
    }

    const rowsHtml = this.filteredEntries.map((e, idx) => `
      <tr>
        <td style="text-align: center; color: #777;">${idx + 1}</td>
        <td><strong>${this.formatDate(e.date)}</strong></td>
        <td>
          <span class="chip ${e.type === 'Supplier payment' ? 'chip-payment' : 'chip-expense'}">${e.type}</span>
        </td>
        <td><strong>${e.reference}</strong></td>
        <td>${e.description}</td>
        <td>${this.formatMethodLabel(e.method)}</td>
        <td style="text-align: right; font-weight: 700;">${this.formatCurrency(e.amount)}</td>
      </tr>
    `).join('');

    return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cash Activity Register &bull; Evolex Foods Ltd</title>
  <style>
    @page { size: A4 portrait; margin: 12mm 15mm; }
    * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      margin: 0; padding: 0; color: #1c2e1f; background: #fff; font-size: 11px;
    }
    .header {
      border-bottom: 2px solid #2f4a32; padding-bottom: 12px; margin-bottom: 14px;
      display: flex; justify-content: space-between; align-items: flex-start;
    }
    .brand h1 { margin: 0; font-size: 20px; color: #2f4a32; letter-spacing: -0.02em; font-weight: 800; }
    .brand p { margin: 2px 0 0; color: #6b7a63; font-size: 9.5px; }
    .header-meta { text-align: right; font-size: 9px; color: #555; }
    .header-meta strong { font-size: 11px; color: #1c2e1f; display: block; margin-bottom: 2px; }
    .report-title-area { margin-bottom: 14px; display: flex; justify-content: space-between; align-items: flex-end; }
    .report-title-area h2 { margin: 0; font-size: 16px; color: #1c2e1f; letter-spacing: -0.01em; }
    .report-title-area p { margin: 2px 0 0; color: #666; font-size: 10px; }
    .filter-tag {
      background: #f4efe4; border: 1px solid #d9d0bf; border-radius: 4px;
      padding: 4px 8px; font-size: 9px; color: #2f4a32; font-weight: 600;
    }
    .kpi-strip {
      display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;
      margin-bottom: 14px;
    }
    .kpi-card {
      background: #fbfaf7; border: 1px solid #e3ddcd; border-radius: 6px; padding: 8px 12px;
    }
    .kpi-card.kpi-card--primary { background: #2f4a32; color: #fff; border-color: #2f4a32; }
    .kpi-card.kpi-card--primary .kpi-label { color: #c9d8c2; }
    .kpi-card.kpi-card--primary .kpi-val { color: #fff; }
    .kpi-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em; color: #8a8578; font-weight: 700; margin-bottom: 2px; }
    .kpi-val { font-size: 14px; font-weight: 800; color: #1c2e1f; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 10px; }
    thead { display: table-header-group; }
    th {
      background: #2f4a32; color: #fff; padding: 7px 8px; text-align: left;
      font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em;
    }
    td { padding: 7px 8px; border-bottom: 1px solid #e3ddcd; }
    tr:nth-child(even) td { background: #fdfaf5; }
    .chip {
      display: inline-block; padding: 2px 6px; border-radius: 999px;
      font-size: 8px; font-weight: 700; text-transform: uppercase;
    }
    .chip-expense { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
    .chip-payment { background: #fff8e1; color: #b78103; border: 1px solid #ffe082; }
    tfoot td {
      background: #f4efe4 !important; font-weight: 800; font-size: 11px;
      border-top: 2px solid #2f4a32; border-bottom: 2px solid #2f4a32; padding: 9px 8px;
    }
    .signatures {
      display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 28px; page-break-inside: avoid;
    }
    .sig-box { border-top: 1px solid #999; padding-top: 5px; font-size: 9px; color: #555; }
    .sig-box strong { display: block; font-size: 10px; color: #1c2e1f; margin-bottom: 2px; }
    .footer {
      border-top: 1px solid #e3ddcd; margin-top: 20px; padding-top: 8px;
      font-size: 8px; color: #888; display: flex; justify-content: space-between;
    }
  </style>
</head>
<body>
  <div class="header">
    <div class="brand">
      <h1>EVOLEX FOODS LTD</h1>
      <p>Agricultural Production &amp; Supply Chain Management &bull; Accra &ndash; Central Region, Ghana</p>
      <p>finance@evolexfoods.com &bull; +233 24 000 0000</p>
    </div>
    <div class="header-meta">
      <strong>OFFICIAL FINANCIAL STATEMENT</strong>
      <div>Period: \${periodFrom} &ndash; \${periodTo}</div>
      <div>Generated: \${generatedAt}</div>
    </div>
  </div>

  <div class="report-title-area">
    <div>
      <h2>Cash Activity Register</h2>
      <p>Detailed transaction register of operating expenses and invoice payments.</p>
    </div>
    <div class="filter-tag">\${filterSummary}</div>
  </div>

  <div class="kpi-strip">
    <div class="kpi-card kpi-card--primary">
      <div class="kpi-label">Total Outflow / Disbursed</div>
      <div class="kpi-val">\${this.formatCurrency(this.filteredTotalAmount)}</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Direct Operating Expenses</div>
      <div class="kpi-val">\${this.formatCurrency(this.filteredExpenseTotal)}</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Supplier Invoice Payments</div>
      <div class="kpi-val">\${this.formatCurrency(this.filteredPaymentTotal)}</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Transactions Count</div>
      <div class="kpi-val">\${this.filteredEntries.length} entries</div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width: 25px; text-align: center;">#</th>
        <th style="width: 85px;">Date</th>
        <th style="width: 100px;">Activity</th>
        <th style="width: 105px;">Reference</th>
        <th>Description / Vendor</th>
        <th style="width: 100px;">Method / Source</th>
        <th style="width: 110px; text-align: right;">Amount (GHS)</th>
      </tr>
    </thead>
    <tbody>
      \${rowsHtml || '<tr><td colspan="7" style="text-align: center; padding: 20px;">No cash activity entries found matching the filter criteria.</td></tr>'}
    </tbody>
    <tfoot>
      <tr>
        <td colspan="6" style="text-align: right; text-transform: uppercase;">Total Disbursed:</td>
        <td style="text-align: right; color: #1c2e1f;">\${this.formatCurrency(this.filteredTotalAmount)}</td>
      </tr>
    </tfoot>
  </table>

  <div class="signatures">
    <div class="sig-box">
      <strong>Prepared By (Accounts Officer)</strong>
      Signature: __________________________ &nbsp;&nbsp;&nbsp;&nbsp; Date: ______________
    </div>
    <div class="sig-box">
      <strong>Verified &amp; Approved By (Finance Lead / Director)</strong>
      Signature: __________________________ &nbsp;&nbsp;&nbsp;&nbsp; Date: ______________
    </div>
  </div>

  <div class="footer">
    <span>Official computer-generated financial record &bull; Evolex Foods Financial Management System</span>
    <span>Page 1 &bull; Verified Record</span>
  </div>
</body>
</html>`;
  }

  exportCsv(): void {
    const list = this.hasActiveFilters ? this.filteredEntries : this.entries;
    const rows = [
      ['Date', 'Type', 'Reference', 'Description', 'Payment method', 'Amount (GHS)'],
      ...list.map((entry) => [entry.date, entry.type, entry.reference, entry.description, entry.method, entry.amount.toFixed(2)])
    ];
    const csv = rows.map((row) => row.map((value) => this.csvCell(String(value))).join(',')).join('\r\n');
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `evolex-finance-activity-${this.overview?.from ?? 'period'}-to-${this.overview?.to ?? 'period'}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  }

  print(): void {
    window.print();
  }

  private buildEntries(from: string, to: string): LedgerEntry[] {
    const expenses: LedgerEntry[] = this.expenses
      .filter((expense) => {
        const d = (expense.expense_date || '').slice(0, 10);
        return Boolean(d && d >= from && d <= to);
      })
      .map((expense) => ({
        id: `expense-${expense.id}`,
        date: expense.expense_date,
        type: 'Expense',
        reference: expense.category || 'Expense',
        description: expense.description || 'Operating expense',
        method: expense.source || 'Manual entry',
        amount: Number(expense.amount)
      }));

    const payments: LedgerEntry[] = this.payments
      .filter((payment) => {
        const d = (payment.paid_at || '').slice(0, 10);
        return Boolean(d && d >= from && d <= to);
      })
      .map((payment) => ({
        id: `payment-${payment.id}`,
        date: payment.paid_at,
        type: 'Supplier payment',
        reference: payment.invoice?.invoice_number || `Invoice #${payment.invoice_id}`,
        description: payment.invoice?.supplier?.name || 'Supplier payment',
        method: payment.method || 'Not specified',
        amount: Number(payment.amount)
      }));

    return [...expenses, ...payments].sort((a, b) => (b.date || '').localeCompare(a.date || ''));
  }

  private rangeBounds(): { from: string; to: string } | null {
    const today = new Date();
    const to = this.toDateInput(today);
    if (this.range === 'custom') {
      return this.customFrom && this.customTo && this.customFrom <= this.customTo
        ? { from: this.customFrom, to: this.customTo }
        : null;
    }
    if (this.range === 'ytd') {
      return { from: `${today.getFullYear()}-01-01`, to };
    }
    const from = new Date(today);
    from.setDate(from.getDate() - (this.range === '90d' ? 89 : 29));
    return { from: this.toDateInput(from), to };
  }

  private seedCustomRange(): void {
    const to = new Date();
    const from = new Date(to);
    from.setDate(from.getDate() - 29);
    this.customFrom = this.toDateInput(from);
    this.customTo = this.toDateInput(to);
  }

  private toDateInput(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  private csvCell(value: string): string {
    return `"${value.replace(/"/g, '""')}"`;
  }
}
