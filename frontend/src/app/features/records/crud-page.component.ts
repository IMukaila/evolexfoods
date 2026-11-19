import { AfterViewInit, Component, ElementRef, HostListener, NgZone, OnDestroy, OnInit, ViewChild } from '@angular/core';
import { NgClass, NgFor, NgIf } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import DataTable from 'datatables.net';
import { ApiService } from '../../core/services/api.service';
import {
  AdvancedFilterDef,
  CRUD_RESOURCES,
  FieldDef,
  KpiMetric,
  QuickFilterDef,
  ResourceDef
} from './crud.registry';
import { FinanceNavComponent } from '../finance/finance-nav.component';
import { GovernanceNavComponent } from '../governance/governance-nav.component';

@Component({
  selector: 'app-crud-page',
  standalone: true,
  imports: [NgFor, NgIf, NgClass, FormsModule, RouterLink, FinanceNavComponent, GovernanceNavComponent],
  templateUrl: './crud-page.component.html',
  styleUrl: './crud-page.component.scss'
})
export class CrudPageComponent implements OnInit, AfterViewInit, OnDestroy {
  @ViewChild('table') tableRef?: ElementRef<HTMLTableElement>;

  resource = '';
  def!: ResourceDef;
  rows: any[] = [];
  filteredRows: any[] = [];
  lookups: any = {};
  form: Record<string, any> = {};
  animalSearchQuery = '';
  editingId: number | null = null;
  showForm = false;
  saving = false;
  message = '';
  error = '';
  table: any;
  viewReady = false;
  private clickBound = false;
  previewPayment: any | null = null;
  downloadingReceipt = false;

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.showForm) {
      this.cancel();
    }
    if (this.previewPayment) {
      this.closeReceiptPreview();
    }
  }

  // Filter & Search states
  searchQuery = '';
  activeQuickFilterId = 'all';
  filterValues: Record<string, any> = {};
  showAdvancedFilters = false;
  computedKpis: KpiMetric[] = [];

  constructor(
    private route: ActivatedRoute,
    private api: ApiService,
    private zone: NgZone
  ) {}

  ngOnInit(): void {
    this.route.data.subscribe((data) => {
      this.resource = data['resource'];
      this.def = CRUD_RESOURCES[this.resource];
      this.showForm = false;
      this.message = '';
      this.error = '';
      this.searchQuery = '';
      this.activeQuickFilterId = 'all';
      this.filterValues = {};
      this.showAdvancedFilters = false;
      this.destroyTable();
      this.loadLookups();
      this.load();
    });
  }

  ngAfterViewInit(): void {
    this.viewReady = true;
    this.bindClicks();
    this.renderTable();
  }

  ngOnDestroy(): void {
    this.destroyTable();
  }

  loadLookups(): void {
    this.api.get<any>('/lookups').subscribe((data) => this.lookups = data || {});
  }

  load(): void {
    this.api.get<{ data: any[] }>(`/records/${this.resource}`).subscribe((res) => {
      this.rows = res.data || [];
      this.applyFilters();
    });
  }

  get searchPlaceholder(): string {
    if (this.resource === 'companies') {
      return 'Search by company, buyer type, city, lifecycle, email...';
    }
    if (this.resource === 'contacts') {
      return 'Search by contact, company, role, email, phone...';
    }
    if (this.resource === 'deals') {
      return 'Search by deal, company, contact, stage, next action...';
    }
    if (this.resource === 'sales') {
      return 'Search by animal ID, buyer, event type, date...';
    }
    if (this.resource === 'campaigns') {
      return 'Search by campaign name, channel, status...';
    }
    if (this.resource === 'marketing') {
      return 'Search by content title, channel, format, status...';
    }
    if (this.resource === 'channel_metrics') {
      return 'Search by channel and metric date...';
    }
    if (this.resource === 'inventory') {
      return 'Search by item name, ID, category, supplier, storage location...';
    }
    if (this.resource === 'procurement') {
      return 'Search by PR number, item, category, requester, status...';
    }
    if (this.resource === 'suppliers') {
      return 'Search by supplier name, code, contact, category, email, phone, location...';
    }
    if (this.resource === 'assets') {
      return 'Search by asset ID, equipment name, serial number, location, supplier...';
    }
    if (this.resource === 'maintenance') {
      return 'Search by ticket #, issue title, asset, priority, status, assignee...';
    }
    if (this.resource === 'employees') {
      return 'Search by employee code, name, role, department, phone, email...';
    }
    if (this.resource === 'tasks') {
      return 'Search by task title, description, module, priority, assignee...';
    }
    return `Search ${this.def?.title || 'records'}...`;
  }

  setQuickFilter(id: string): void {
    this.activeQuickFilterId = id;
    this.applyFilters();
  }

  quickFilterCount(qf: QuickFilterDef): number {
    if (!this.rows) return 0;
    if (qf.id === 'all') return this.rows.length;
    return this.rows.filter(r => qf.filterFn(r)).length;
  }

  toggleAdvancedFilters(): void {
    this.showAdvancedFilters = !this.showAdvancedFilters;
  }

  hasActiveAdvancedFilters(): boolean {
    return this.activeAdvancedFilterCount > 0;
  }

  get activeAdvancedFilterCount(): number {
    return (this.def?.advancedFilters || []).filter((filter) => {
      const value = this.filterValues[filter.key];
      return value !== undefined && value !== null && value !== '';
    }).length;
  }

  hasActiveFilters(): boolean {
    const hasSearch = !!(this.searchQuery && this.searchQuery.trim());
    const hasQuick = !!(this.activeQuickFilterId && this.activeQuickFilterId !== 'all');
    return hasSearch || hasQuick || this.hasActiveAdvancedFilters();
  }

  clearFilters(): void {
    this.searchQuery = '';
    this.activeQuickFilterId = 'all';
    this.filterValues = {};
    this.applyFilters();
  }

  resetAdvancedFilters(): void {
    this.filterValues = {};
    this.applyFilters();
  }

  onSearchInput(): void {
    this.applyFilters();
  }

  applyFilters(): void {
    if (!this.def) return;

    let res = [...this.rows];

    // 1. Quick filter
    if (this.activeQuickFilterId && this.def.quickFilters) {
      const qf = this.def.quickFilters.find(f => f.id === this.activeQuickFilterId);
      if (qf && qf.id !== 'all') {
        res = res.filter(row => qf.filterFn(row));
      }
    }

    // 2. Advanced filters
    if (this.def.advancedFilters) {
      for (const filter of this.def.advancedFilters) {
        const val = this.filterValues[filter.key];
        if (val !== undefined && val !== null && val !== '') {
          if (filter.filterFn) {
            res = res.filter(row => filter.filterFn!(row, val));
          } else {
            res = res.filter(row => String(row[filter.key] ?? '').toLowerCase() === String(val).toLowerCase());
          }
        }
      }
    }

    // 3. Search query
    const q = (this.searchQuery || '').trim().toLowerCase();
    if (q) {
      res = res.filter(row => {
        return Object.entries(row).some(([k, v]) => {
          if (v === null || v === undefined) return false;
          if (typeof v === 'object') {
            return Object.values(v).some(sub => sub && String(sub).toLowerCase().includes(q));
          }
          return String(v).toLowerCase().includes(q);
        });
      });
    }

    this.filteredRows = res;
    this.updateKpis();

    if (this.table) {
      this.table.clear();
      this.table.rows.add(this.filteredRows);
      this.table.draw();
    } else if (this.viewReady) {
      this.renderTable();
    }
  }

  updateKpis(): void {
    if (!this.def) return;
    if (this.def.kpiDefs && this.def.kpiDefs.length) {
      this.computedKpis = this.def.kpiDefs.map(kpi => kpi.compute(this.filteredRows, this.rows));
    } else {
      this.computedKpis = [
        {
          label: 'Total Records',
          value: this.rows.length,
          meta: `${this.def.title} in system`,
          ok: true
        },
        {
          label: 'Showing Records',
          value: this.filteredRows.length,
          meta: this.hasActiveFilters() ? 'Filtered subset' : 'All items active'
        }
      ];
    }
  }

  optionsFor(field: FieldDef): { value: any; label: string }[] {
    if (field.options) {
      return field.options;
    }
    if (field.lookup) {
      const source = this.lookups[field.lookup] || [];
      const valueKey = field.lookupValue || 'id';
      return source.map((item: any) => ({
        value: item[valueKey],
        label: this.lookupLabel(field.lookup as string, item)
      }));
    }
    return [];
  }

  filteredOptionsFor(field: FieldDef): { value: any; label: string }[] {
    const options = this.optionsFor(field);
    const query = this.animalSearchQuery.trim().toLowerCase();
    if (this.resource !== 'sales' || field.key !== 'animal_id' || !query) {
      return options;
    }

    return options.filter((option) => {
      const animal = (this.lookups.animals || []).find((item: any) => String(item.id) === String(option.value));
      return [
        option.label,
        animal?.species,
        animal?.breed,
        animal?.sex,
        animal?.status
      ].some((value) => String(value ?? '').toLowerCase().includes(query));
    });
  }

  optionsForFilter(filter: AdvancedFilterDef): { value: any; label: string }[] {
    if (filter.options) {
      return filter.options;
    }
    if (filter.lookup) {
      const source = this.lookups[filter.lookup] || [];
      const valueKey = filter.lookupValue || 'id';
      return source.map((item: any) => ({
        value: item[valueKey],
        label: item.name || item.title || item.animal_id || item.code || String(item.id)
      }));
    }
    return [];
  }

  exportCsv(): void {
    if (!this.filteredRows.length) return;
    const cols = this.def.columns;
    const header = cols.map(c => `"${c.label}"`).join(',');
    const rows = this.filteredRows.map(row =>
      cols.map(c => {
        let val = c.render ? c.render(row) : (row[c.key] ?? '');
        val = String(val).replace(/<[^>]*>/g, '').replace(/"/g, '""').trim();
        return `"${val}"`;
      }).join(',')
    );
    const csvContent = 'data:text/csv;charset=utf-8,' + [header, ...rows].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `${this.resource}-export-${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  openCreate(): void {
    this.editingId = null;
    this.animalSearchQuery = '';
    this.form = { ...(this.def.defaults || {}) };
    this.showForm = true;
    this.error = '';
  }

  openEdit(row: any): void {
    this.editingId = row.id;
    this.animalSearchQuery = '';
    this.form = {};
    this.def.fields.forEach((field) => {
      const value = field.key === 'company_name' && this.resource === 'contacts'
        ? row.company_name || row.company?.name
        : row[field.key];
      this.form[field.key] = field.type === 'date' ? this.toDateInput(value) : (value ?? '');
    });
    this.showForm = true;
    this.error = '';
  }

  cancel(): void {
    this.showForm = false;
    this.editingId = null;
    this.animalSearchQuery = '';
  }

  save(): void {
    this.saving = true;
    this.error = '';
    const payload: Record<string, any> = {};
    this.def.fields.forEach((field) => {
      const value = this.form[field.key];
      payload[field.key] = value === '' ? null : value;
    });

    const req = this.editingId
      ? this.api.put(`/records/${this.resource}/${this.editingId}`, payload)
      : this.api.post(`/records/${this.resource}`, payload);

    req.subscribe({
      next: () => {
        this.saving = false;
        this.message = this.editingId ? 'Record updated.' : 'Record created.';
        this.showForm = false;
        this.load();
        this.loadLookups();
      },
      error: (err) => {
        this.saving = false;
        const errors = err?.error?.errors;
        this.error = errors ? Object.values(errors).flat().join(' ') : (err?.error?.message || 'Could not save this record.');
      }
    });
  }

  remove(row: any): void {
    if (!confirm(`Delete this ${this.def.title.toLowerCase()} record?`)) {
      return;
    }
    this.api.delete(`/records/${this.resource}/${row.id}`).subscribe({
      next: () => {
        this.message = 'Record deleted.';
        this.load();
      },
      error: (err) => this.error = err?.error?.message || 'Could not delete this record.'
    });
  }

  private lookupLabel(lookup: string, item: any): string {
    if (lookup === 'animals') {
      return item.animal_id;
    }
    if (lookup === 'invoices') {
      return item.invoice_number;
    }
    if (lookup === 'assets') {
      return `${item.asset_id} ${item.name}`;
    }
    if (lookup === 'contacts') {
      return item.company?.name ? `${item.name} · ${item.company.name}` : item.name;
    }
    if (lookup === 'media_assets') {
      return item.title;
    }
    if (lookup === 'users' || lookup === 'employees' || lookup === 'suppliers' || lookup === 'companies' || lookup === 'locations' || lookup === 'stores' || lookup === 'pipeline_stages') {
      return item.name;
    }
    return item.name || item.title || String(item.id);
  }

  private toDateInput(value: any): string {
    if (!value) {
      return '';
    }
    return String(value).slice(0, 10);
  }

  private bindClicks(): void {
    if (this.clickBound || !this.tableRef) {
      return;
    }
    this.clickBound = true;
    this.tableRef.nativeElement.addEventListener('click', (event: Event) => {
      const target = (event.target as HTMLElement).closest('button') as HTMLButtonElement | null;
      if (!target) {
        return;
      }
      const id = Number(target.getAttribute('data-id'));
      const row = this.rows.find((item) => item.id === id);
      if (!row) {
        return;
      }
      if (target.classList.contains('js-preview-receipt')) {
        this.zone.run(() => this.openReceiptPreview(row));
      }
      if (target.classList.contains('js-print-receipt')) {
        this.zone.run(() => this.printReceipt(row));
      }
      if (target.classList.contains('js-download-receipt')) {
        this.zone.run(() => this.downloadReceipt(row));
      }
      if (target.classList.contains('js-edit')) {
        this.zone.run(() => this.openEdit(row));
      }
      if (target.classList.contains('js-delete')) {
        this.zone.run(() => this.remove(row));
      }
    });
  }

  private renderTable(): void {
    if (!this.viewReady || !this.tableRef || !this.def) {
      return;
    }

    this.destroyTable();
    const badgeKeys = ['status', 'severity', 'priority', 'sex', 'health_status'];
    const columns = [
      ...this.def.columns.map((col) => ({
        title: col.label,
        data: col.key,
        defaultContent: '',
        render: (_data: any, _type: string, row: any) => {
          if (col.render) {
            return col.render(row);
          }
          const value = row[col.key] ?? '';
          if (badgeKeys.includes(col.key) && value) {
            return `<span class="badge ${String(value).toLowerCase().replace(/[^a-z0-9]/g, '_')}">${value}</span>`;
          }
          return value === null || value === undefined ? '' : String(value);
        }
      })),
      {
        title: 'Actions',
        data: null,
        orderable: false,
        searchable: false,
        defaultContent: '',
        className: 'col-actions',
        render: (_data: any, _type: string, row: any) => {
          if (this.resource === 'payments') {
            return `<div class="row-actions">
              <button class="icon-btn icon-btn--print js-print-receipt" data-id="${row.id}" type="button"
                title="Print Receipt (A4 Dual Copy: Original & Duplicate)" aria-label="Print Receipt">
                <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
              </button>
              <button class="icon-btn icon-btn--pdf js-download-receipt" data-id="${row.id}" type="button"
                title="Download PDF Receipt" aria-label="Download Receipt">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5 5 5 5-5m-5 5V3"/></svg>
              </button>
              <button class="icon-btn icon-btn--receipt js-preview-receipt" data-id="${row.id}" type="button"
                title="Preview Receipt Voucher" aria-label="Preview Receipt">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
              <button class="icon-btn js-edit" data-id="${row.id}" type="button"
                title="Edit record" aria-label="Edit record">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L20 8l-4-4L4 16v4Zm10-14 4 4"/></svg>
              </button>
              <button class="icon-btn icon-btn--danger js-delete" data-id="${row.id}" type="button"
                title="Delete record" aria-label="Delete record">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M10 7V4h4v3m-7 0 1 13h8l1-13M10 11v6m4-6v6"/></svg>
              </button>
            </div>`;
          }
          return `<div class="row-actions">
            <button class="icon-btn js-edit" data-id="${row.id}" type="button"
              title="Edit record" aria-label="Edit record">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L20 8l-4-4L4 16v4Zm10-14 4 4"/></svg>
            </button>
            <button class="icon-btn icon-btn--danger js-delete" data-id="${row.id}" type="button"
              title="Delete record" aria-label="Delete record">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M10 7V4h4v3m-7 0 1 13h8l1-13M10 11v6m4-6v6"/></svg>
            </button>
          </div>`;
        }
      }
    ];

    this.table = new DataTable(this.tableRef.nativeElement, {
      data: this.filteredRows,
      columns,
      pageLength: 10,
      lengthMenu: [10, 25, 50, 100],
      order: [],
      autoWidth: false,
      destroy: true,
      language: {
        search: 'Search records:',
        emptyTable: 'No matching records found'
      }
    });
  }

  private destroyTable(): void {
    if (this.table) {
      this.table.destroy();
      this.table = null;
    }
    if (this.tableRef?.nativeElement) {
      const tbody = this.tableRef.nativeElement.querySelector('tbody');
      if (tbody) {
        tbody.innerHTML = '';
      }
      const thead = this.tableRef.nativeElement.querySelector('thead');
      if (thead) {
        thead.innerHTML = '';
      }
    }
  }

  // --- Payment Receipt Voucher Methods ---

  openReceiptPreview(payment: any): void {
    this.previewPayment = payment;
  }

  closeReceiptPreview(): void {
    this.previewPayment = null;
  }

  padReceiptId(id: any): string {
    return String(id || '').padStart(5, '0');
  }

  formatPaymentCurrency(amount: any): string {
    if (amount == null || amount === '') return '0.00';
    return Number(amount).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  formatPaymentDate(val: any): string {
    if (!val) return '—';
    const d = new Date(val);
    if (isNaN(d.getTime())) return String(val).slice(0, 10);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  formatPaymentMethod(method: string | null | undefined): string {
    if (!method) return 'Payment';
    return method.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }

  calcInvoiceBalance(payment: any): number {
    if (!payment?.invoice) return 0;
    const invAmount = Number(payment.invoice.amount || 0);
    const payAmount = Number(payment.amount || 0);
    return Math.max(0, invAmount - payAmount);
  }

  amountToWords(amount: number): string {
    if (!amount || amount <= 0) return 'Zero Ghana Cedis Only';
    const units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
      'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    const cedis = Math.floor(amount);
    const pesewas = Math.round((amount - cedis) * 100);

    const convertGroup = (n: number): string => {
      let str = '';
      if (n >= 100) {
        str += units[Math.floor(n / 100)] + ' Hundred';
        n %= 100;
        if (n > 0) str += ' and ';
      }
      if (n >= 20) {
        str += tens[Math.floor(n / 10)];
        if (n % 10 > 0) str += '-' + units[n % 10];
      } else if (n > 0) {
        str += units[n];
      }
      return str;
    };

    let words = '';
    let rem = cedis;
    if (rem >= 1000000) {
      words += convertGroup(Math.floor(rem / 1000000)) + ' Million ';
      rem %= 1000000;
    }
    if (rem >= 1000) {
      words += convertGroup(Math.floor(rem / 1000)) + ' Thousand ';
      rem %= 1000;
    }
    if (rem > 0) {
      words += convertGroup(rem) + ' ';
    }
    words = words.trim() || 'Zero';

    let result = words + ' Ghana ' + (words === 'One' ? 'Cedi' : 'Cedis');
    if (pesewas > 0) {
      result += ' and ' + convertGroup(pesewas) + ' ' + (pesewas === 1 ? 'Pesewa' : 'Pesewas');
    } else {
      result += ' Only';
    }
    return result;
  }

  printReceipt(payment: any): void {
    const printableHtml = this.buildReceiptPrintHtml(payment);
    let printFrame = document.getElementById('payment-receipt-print-frame') as HTMLIFrameElement;
    if (!printFrame) {
      printFrame = document.createElement('iframe');
      printFrame.id = 'payment-receipt-print-frame';
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

  downloadReceipt(payment: any): void {
    this.downloadingReceipt = true;
    this.api.getBlob(`/payments/${payment.id}/receipt`).subscribe({
      next: (blob) => {
        this.downloadingReceipt = false;
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `receipt-payment-${this.padReceiptId(payment.id)}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
      },
      error: (err) => {
        this.downloadingReceipt = false;
        console.error('Failed to download payment receipt PDF', err);
        alert('Could not download payment receipt PDF. Please try again.');
      }
    });
  }

  buildReceiptPrintHtml(payment: any): string {
    const receiptNo = 'RCP-' + this.padReceiptId(payment.id);
    const dateStr = this.formatPaymentDate(payment.paid_at);
    const methodLabel = this.formatPaymentMethod(payment.method);
    const refStr = payment.reference || ('REF-' + String(payment.id).padStart(6, '0'));
    const amountVal = Number(payment.amount || 0);
    const amountFormatted = 'GH₵ ' + this.formatPaymentCurrency(amountVal);
    const amountWords = this.amountToWords(amountVal);

    const supplier = payment.invoice?.supplier;
    let supplierName = supplier?.name || 'General Payee / Vendor';
    if (supplier?.code) supplierName += ` (${supplier.code})`;
    const supplierContact = [supplier?.contact_name ? 'Contact: ' + supplier.contact_name : '', supplier?.phone].filter(Boolean).join(' | ') || 'No contact specified';
    const supplierLoc = supplier?.location || 'Accra / Central Region, Ghana';

    const invoiceNumber = payment.invoice?.invoice_number || 'Direct Payment / N/A';
    const invoiceAmount = Number(payment.invoice?.amount || amountVal);
    const balance = this.calcInvoiceBalance(payment);
    const balanceText = balance <= 0 ? 'FULLY PAID (Balance: GH₵ 0.00)' : `Remaining Balance: GH₵ ${this.formatPaymentCurrency(balance)}`;
    const balanceClass = balance <= 0 ? 'text-success' : 'text-danger';

    const renderCard = (copyType: string, copySub: string, badgeClass: string) => `
      <div class="receipt-card">
        <div class="banner">
          <div class="brand">
            <h1>EVOLEX FOODS LTD</h1>
            <p class="tagline">Agricultural Production &amp; Supply Chain Management</p>
            <p class="contacts">Accra - Central Region, Ghana &bull; finance@evolexfoods.com &bull; +233 24 000 0000</p>
          </div>
          <div class="copy-badge ${badgeClass}">
            <div class="badge-title">PAYMENT RECEIPT: ${copyType}</div>
            <div class="badge-sub">${copySub}</div>
          </div>
        </div>

        <div class="meta-strip">
          <div><strong>Receipt No:</strong> <span class="highlight">${receiptNo}</span></div>
          <div><strong>Date:</strong> ${dateStr}</div>
          <div><strong>Method:</strong> ${methodLabel}</div>
          <div><strong>Ref:</strong> <span class="ref-code">${refStr}</span></div>
        </div>

        <div class="details-grid">
          <div class="detail-box">
            <div class="box-title">PAID TO / VENDOR</div>
            <div class="vendor-name">${supplierName}</div>
            <div class="vendor-contact">${supplierContact}</div>
            <div class="vendor-loc">${supplierLoc}</div>
          </div>
          <div class="detail-box">
            <div class="box-title">INVOICE &amp; SETTLEMENT STATUS</div>
            <div class="vendor-name">Invoice #${invoiceNumber}</div>
            <div class="vendor-contact">Bill Total: GH₵ ${this.formatPaymentCurrency(invoiceAmount)}</div>
            <div class="vendor-loc ${balanceClass}"><strong>${balanceText}</strong></div>
          </div>
        </div>

        <div class="amount-card">
          <div class="amount-number">
            <div class="amount-label">AMOUNT RECEIVED / SETTLED</div>
            <div class="amount-val">${amountFormatted}</div>
          </div>
          <div class="amount-words-box">
            <div class="amount-words-label">Amount in words:</div>
            <div class="amount-words-val">${amountWords}</div>
          </div>
        </div>

        <table class="items-table">
          <thead>
            <tr>
              <th style="width: 30px;">#</th>
              <th>Payment Description &amp; Purpose</th>
              <th style="width: 170px;">Method / Reference</th>
              <th style="width: 120px;" class="text-right">Amount (GHS)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>1</td>
              <td><strong>Settlement towards ${invoiceNumber}</strong> &mdash; ${supplierName}</td>
              <td>${methodLabel} / ${refStr}</td>
              <td class="text-right"><strong>${amountFormatted}</strong></td>
            </tr>
          </tbody>
        </table>

        <div class="sig-grid">
          <div class="sig-box">
            <div class="sig-title">ISSUED BY (ACCOUNTS DEPARTMENT)</div>
            <div class="sig-line"></div>
            <div class="sig-caption">Authorized Signature &amp; Date Stamp</div>
            <div class="sig-sub">Evolex Foods Ltd &bull; Finance Office</div>
          </div>
          <div class="sig-box">
            <div class="sig-title">RECEIVED / ACKNOWLEDGED BY</div>
            <div class="sig-line"></div>
            <div class="sig-caption">Payer / Vendor Signature &amp; Full Name</div>
            <div class="sig-sub">Date: ________________________</div>
          </div>
        </div>

        <div class="card-footer">
          <span>Official computer-generated payment voucher &bull; Valid without physical seal if digitally authenticated.</span>
          <span>Official Record &bull; Evolex Foods Financial Management</span>
        </div>
      </div>
    `;

    return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Receipt ${receiptNo} &bull; Evolex Foods</title>
  <style>
    @page { size: A4 portrait; margin: 0; }
    * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      margin: 0; padding: 0; background: #ffffff; color: #1c2e1f;
    }
    .a4-page {
      width: 210mm; height: 297mm; max-height: 297mm;
      padding: 6mm 8mm; display: flex; flex-direction: column;
      justify-content: space-between; overflow: hidden;
      page-break-inside: avoid; page-break-after: avoid;
    }
    .receipt-card {
      border: 1.5px solid #2f4a32; border-radius: 6px;
      padding: 0 0 4px 0; background: #ffffff;
      display: flex; flex-direction: column; height: 138mm; overflow: hidden;
    }
    .banner {
      background: #2f4a32; color: #ffffff; padding: 8px 14px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .brand h1 { margin: 0; font-size: 16px; letter-spacing: -0.01em; color: #ffffff; font-weight: 800; }
    .brand .tagline { margin: 1px 0 0 0; font-size: 8.5px; color: #c9d8c2; }
    .brand .contacts { margin: 1px 0 0 0; font-size: 7.5px; color: #a5bda0; }
    .copy-badge { border-radius: 5px; padding: 5px 12px; text-align: right; border: 1px solid rgba(255,255,255,0.4); }
    .badge-original { background: #e8f5e9; color: #1b5e20; }
    .badge-duplicate { background: #e3f2fd; color: #0d47a1; }
    .badge-title { font-size: 11px; font-weight: 800; letter-spacing: 0.03em; }
    .badge-sub { font-size: 8.5px; font-style: italic; margin-top: 1px; }
    .meta-strip {
      background: #fbfaf7; border-bottom: 1px solid #e3ddcd;
      padding: 5px 14px; display: flex; justify-content: space-between;
      font-size: 10.5px; color: #444444;
    }
    .meta-strip .highlight { color: #1c2e1f; font-weight: 700; }
    .meta-strip .ref-code { color: #2f4a32; font-weight: 700; }
    .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 6px 14px; }
    .detail-box { background: #fcfbf8; border: 1px solid #e3ddcd; border-radius: 5px; padding: 6px 10px; }
    .box-title { font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em; color: #8a8578; font-weight: 700; margin-bottom: 2px; }
    .vendor-name { font-size: 11px; font-weight: 700; color: #1c2e1f; }
    .vendor-contact, .vendor-loc { font-size: 9px; color: #555555; margin-top: 1px; }
    .text-success { color: #1b5e20 !important; }
    .text-danger { color: #b71c1c !important; }
    .amount-card {
      margin: 0 14px 6px 14px; background: #f0f7f1; border: 1.2px solid #2f4a32;
      border-radius: 6px; padding: 6px 12px; display: flex; justify-content: space-between; align-items: center;
    }
    .amount-label { font-size: 8.5px; font-weight: 700; color: #2f4a32; letter-spacing: 0.03em; }
    .amount-val { font-size: 17px; font-weight: 800; color: #1b5e20; margin-top: 1px; }
    .amount-words-box { max-width: 60%; text-align: right; }
    .amount-words-label { font-size: 8px; color: #6b7a63; text-transform: uppercase; }
    .amount-words-val { font-size: 9.5px; font-weight: 600; font-style: italic; color: #1c2e1f; line-height: 1.25; }
    .items-table { width: calc(100% - 28px); margin: 0 14px 6px 14px; border-collapse: collapse; font-size: 9.5px; }
    .items-table th { background: #2f4a32; color: #ffffff; padding: 4px 8px; text-align: left; font-size: 8.5px; text-transform: uppercase; font-weight: 700; }
    .items-table td { padding: 5px 8px; border: 1px solid #e3ddcd; background: #ffffff; }
    .text-right { text-align: right; }
    .sig-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 0 14px; margin-top: auto; }
    .sig-box { background: #faf9f5; border: 1px solid #e3ddcd; border-radius: 5px; padding: 6px 10px; }
    .sig-title { font-size: 8px; text-transform: uppercase; font-weight: 700; color: #8a8578; }
    .sig-line { border-bottom: 1px solid #777777; margin: 18px 0 3px 0; }
    .sig-caption { font-size: 8.5px; color: #555555; }
    .sig-sub { font-size: 7.5px; font-style: italic; color: #888888; }
    .card-footer {
      border-top: 1px solid #e3ddcd; margin-top: 4px; padding: 3px 14px 0 14px;
      font-size: 7px; color: #8a8578; display: flex; justify-content: space-between;
    }
    .cut-divider {
      display: flex; align-items: center; justify-content: center;
      gap: 8px; padding: 4px 0; color: #666666;
    }
    .cut-line { flex: 1; border-bottom: 1.5px dashed #888888; }
    .cut-text {
      font-size: 8.5px; font-weight: 700; letter-spacing: 0.05em;
      text-transform: uppercase; display: flex; align-items: center; gap: 6px; color: #444444;
    }
    .cut-sub { font-size: 7.5px; color: #777777; font-weight: normal; margin-left: 6px; }
  </style>
</head>
<body>
  <div class="a4-page">
    ${renderCard('ORIGINAL', 'PAYER / VENDOR COPY', 'badge-original')}

    <div class="cut-divider">
      <div class="cut-line"></div>
      <div class="cut-text">
        <span>&#9986; CUT ALONG DOTTED LINE</span>
        <span class="cut-sub">(Top: Original Payer Copy &bull; Bottom: Duplicate Accounts Copy)</span>
      </div>
      <div class="cut-line"></div>
    </div>

    ${renderCard('DUPLICATE', 'ACCOUNTS OFFICE COPY', 'badge-duplicate')}
  </div>
</body>
</html>`;
  }
}
