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
  editingId: number | null = null;
  showForm = false;
  saving = false;
  message = '';
  error = '';
  table: any;
  viewReady = false;
  private clickBound = false;

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.showForm) {
      this.cancel();
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

  optionsForFilter(filter: AdvancedFilterDef): { value: any; label: string }[] {
    if (filter.options) {
      return filter.options;
    }
    if (filter.lookup) {
      const source = this.lookups[filter.lookup] || [];
      return source.map((item: any) => ({
        value: item.id,
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
    this.form = { ...(this.def.defaults || {}) };
    this.showForm = true;
    this.error = '';
  }

  openEdit(row: any): void {
    this.editingId = row.id;
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
    if (lookup === 'users' || lookup === 'employees' || lookup === 'suppliers' || lookup === 'companies' || lookup === 'locations' || lookup === 'pipeline_stages') {
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
        render: (_data: any, _type: string, row: any) =>
          `<div class="row-actions">
            <button class="icon-btn js-edit" data-id="${row.id}" type="button"
              title="Edit record" aria-label="Edit record">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L20 8l-4-4L4 16v4Zm10-14 4 4"/></svg>
            </button>
            <button class="icon-btn icon-btn--danger js-delete" data-id="${row.id}" type="button"
              title="Delete record" aria-label="Delete record">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M10 7V4h4v3m-7 0 1 13h8l1-13M10 11v6m4-6v6"/></svg>
            </button>
          </div>`
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
}
