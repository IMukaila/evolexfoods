export interface FieldDef {
  key: string;
  label: string;
  type: 'text' | 'email' | 'number' | 'date' | 'select' | 'textarea';
  required?: boolean;
  options?: { value: string; label: string }[];
  lookup?: 'locations' | 'suppliers' | 'users' | 'employees' | 'animals' | 'assets' | 'companies' | 'contacts' | 'pipeline_stages' | 'media_assets' | 'invoices';
  lookupValue?: string;
  lookupLabel?: string;
  hidden?: boolean;
  placeholder?: string;
}

export interface QuickFilterDef {
  id: string;
  label: string;
  badgeClass?: 'ok' | 'warning' | 'danger' | 'info';
  filterFn: (row: any) => boolean;
}

export interface AdvancedFilterOption {
  value: string;
  label: string;
}

export interface AdvancedFilterDef {
  key: string;
  label: string;
  type: 'select' | 'text' | 'date' | 'number';
  placeholder?: string;
  options?: AdvancedFilterOption[];
  lookup?: 'locations' | 'suppliers' | 'users' | 'employees' | 'animals' | 'assets' | 'companies' | 'contacts' | 'pipeline_stages' | 'media_assets';
  filterFn?: (row: any, value: any) => boolean;
}

export interface KpiMetric {
  label: string;
  value: string | number;
  meta?: string;
  unit?: string;
  alert?: boolean;
  ok?: boolean;
  info?: boolean;
}

export interface KpiDef {
  label: string;
  compute: (rows: any[], allRows: any[]) => KpiMetric;
}

export interface ResourceDef {
  title: string;
  recordLabel?: string;
  subtitle: string;
  columns: { key: string; label: string; render?: (row: any) => string }[];
  fields: FieldDef[];
  defaults?: Record<string, any>;
  quickFilters?: QuickFilterDef[];
  advancedFilters?: AdvancedFilterDef[];
  kpiDefs?: KpiDef[];
}

export const CRUD_RESOURCES: Record<string, ResourceDef> = {
  livestock: {
    title: 'Livestock Master',
    subtitle: 'Create, edit and archive animals with unique Evolex IDs.',
    columns: [
      { key: 'animal_id', label: 'Animal ID' },
      { key: 'species', label: 'Species' },
      { key: 'breed', label: 'Breed' },
      { key: 'sex', label: 'Sex' },
      { key: 'location', label: 'Location', render: (row) => row.location?.name || '' },
      { key: 'health_status', label: 'Health' },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'animal_id', label: 'Animal ID', type: 'text', placeholder: 'Auto if blank' },
      { key: 'tag_id', label: 'Tag ID', type: 'text' },
      { key: 'species', label: 'Species', type: 'select', required: true, options: [
        { value: 'goat', label: 'Goat' }, { value: 'sheep', label: 'Sheep' }, { value: 'cattle', label: 'Cattle' }
      ]},
      { key: 'breed', label: 'Breed', type: 'text' },
      { key: 'sex', label: 'Sex', type: 'select', required: true, options: [
        { value: 'female', label: 'Female' }, { value: 'male', label: 'Male' }
      ]},
      { key: 'dob', label: 'Date of birth', type: 'date' },
      { key: 'location_id', label: 'Location', type: 'select', lookup: 'locations' },
      { key: 'weight', label: 'Weight (kg)', type: 'number' },
      { key: 'source', label: 'Source', type: 'select', options: [
        { value: 'born_on_farm', label: 'Born on farm' }, { value: 'purchased', label: 'Purchased' }
      ]},
      { key: 'health_status', label: 'Health status', type: 'select', options: [
        { value: 'healthy', label: 'Healthy' }, { value: 'sick', label: 'Sick' }, { value: 'quarantine', label: 'Quarantine' }
      ]},
      { key: 'pregnancy_status', label: 'Pregnancy', type: 'select', options: [
        { value: 'open', label: 'Open' }, { value: 'pregnant', label: 'Pregnant' }
      ]},
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'alive', label: 'Alive' }, { value: 'dead', label: 'Dead' }
      ]},
      { key: 'notes', label: 'Notes', type: 'textarea' }
    ],
    defaults: { species: 'goat', sex: 'female', status: 'alive', health_status: 'healthy' }
  },
  health: {
    title: 'Health Records',
    subtitle: 'Open and closed animal health cases.',
    columns: [
      { key: 'animal', label: 'Animal', render: (row) => row.animal?.animal_id || row.animal_id },
      { key: 'condition', label: 'Condition' },
      { key: 'severity', label: 'Severity' },
      { key: 'recorded_date', label: 'Recorded' },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'animal_id', label: 'Animal', type: 'select', required: true, lookup: 'animals', lookupValue: 'id', lookupLabel: 'animal_id' },
      { key: 'recorded_date', label: 'Recorded date', type: 'date', required: true },
      { key: 'condition', label: 'Condition', type: 'text', required: true },
      { key: 'severity', label: 'Severity', type: 'select', options: [
        { value: 'low', label: 'Low' }, { value: 'moderate', label: 'Moderate' }, { value: 'high', label: 'High' }, { value: 'critical', label: 'Critical' }
      ]},
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'open', label: 'Open' }, { value: 'closed', label: 'Closed' }
      ]},
      { key: 'notes', label: 'Notes', type: 'textarea' }
    ],
    defaults: { status: 'open', severity: 'moderate' }
  },
  treatments: {
    title: 'Treatments',
    subtitle: 'Medication and treatment records.',
    columns: [
      { key: 'animal', label: 'Animal', render: (row) => row.animal?.animal_id || row.animal_id },
      { key: 'treatment_type', label: 'Type' },
      { key: 'medicine', label: 'Medicine' },
      { key: 'dosage', label: 'Dosage' },
      { key: 'start_date', label: 'Started' },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'animal_id', label: 'Animal', type: 'select', required: true, lookup: 'animals', lookupValue: 'id', lookupLabel: 'animal_id' },
      { key: 'start_date', label: 'Start date', type: 'date', required: true },
      { key: 'treatment_type', label: 'Type', type: 'text', required: true },
      { key: 'medicine', label: 'Medicine', type: 'text' },
      { key: 'dosage', label: 'Dosage', type: 'text' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'active', label: 'Active' }, { value: 'completed', label: 'Completed' }
      ]},
      { key: 'notes', label: 'Notes', type: 'textarea' }
    ],
    defaults: { status: 'active' }
  },
  inventory: {
    title: 'Inventory Items',
    subtitle: 'Stock levels, reorder points, storage locations and categories.',
    columns: [
      { key: 'item_id', label: 'Item ID', render: (row) => `<strong>${row.item_id || '-'}</strong>` },
      {
        key: 'name',
        label: 'Name',
        render: (row) => `<strong>${row.name}</strong>${row.storage_location ? `<div class="sub-text">📍 ${row.storage_location}</div>` : ''}`
      },
      {
        key: 'category',
        label: 'Category',
        render: (row) => `<span class="badge ${String(row.category || '').toLowerCase().replace(/[^a-z0-9]/g, '_')}">${row.category || '-'}</span>`
      },
      {
        key: 'stock_health',
        label: 'Stock Health',
        render: (row) => {
          const qty = Number(row.quantity_on_hand || 0);
          const reorder = Number(row.reorder_level || 0);
          if (qty <= 0) return `<span class="badge badge-out-of-stock">Out of Stock</span>`;
          if (qty <= reorder) return `<span class="badge badge-low-stock">Low Stock</span>`;
          return `<span class="badge badge-adequate">Adequate</span>`;
        }
      },
      {
        key: 'quantity_on_hand',
        label: 'On Hand',
        render: (row) => `<strong>${row.quantity_on_hand ?? 0}</strong> <span class="unit-text">${row.unit || ''}</span>`
      },
      {
        key: 'reorder_level',
        label: 'Reorder At',
        render: (row) => `${row.reorder_level ?? 0} <span class="unit-text">${row.unit || ''}</span>`
      },
      {
        key: 'average_cost',
        label: 'Avg Cost',
        render: (row) => row.average_cost !== null && row.average_cost !== undefined ? `$${Number(row.average_cost).toFixed(2)}` : '-'
      },
      { key: 'supplier', label: 'Supplier', render: (row) => row.supplier?.name || '-' },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'item_id', label: 'Item ID', type: 'text', placeholder: 'Auto if blank' },
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'category', label: 'Category', type: 'select', required: true, options: [
        { value: 'Feed', label: 'Feed' }, { value: 'Medicine', label: 'Medicine' }, { value: 'Veterinary Supplies', label: 'Veterinary Supplies' },
        { value: 'Tools', label: 'Tools' }, { value: 'PPE', label: 'PPE' }, { value: 'Fuel', label: 'Fuel' }, { value: 'Spare Parts', label: 'Spare Parts' }
      ]},
      { key: 'unit', label: 'Unit', type: 'text' },
      { key: 'storage_location', label: 'Storage', type: 'text' },
      { key: 'quantity_on_hand', label: 'Quantity on hand', type: 'number' },
      { key: 'average_cost', label: 'Average cost', type: 'number' },
      { key: 'supplier_id', label: 'Supplier', type: 'select', lookup: 'suppliers' },
      { key: 'reorder_level', label: 'Reorder level', type: 'number' },
      { key: 'minimum_stock', label: 'Minimum stock', type: 'number' },
      { key: 'expiry_date', label: 'Expiry date', type: 'date' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }
      ]}
    ],
    defaults: { unit: 'kg', status: 'active', quantity_on_hand: 0 },
    quickFilters: [
      { id: 'all', label: 'All Items', filterFn: () => true },
      {
        id: 'low_stock',
        label: '⚠️ Low Stock Alert',
        badgeClass: 'warning',
        filterFn: (row) => Number(row.quantity_on_hand || 0) <= Number(row.reorder_level || 0) && Number(row.quantity_on_hand || 0) > 0
      },
      {
        id: 'out_of_stock',
        label: '🚨 Out of Stock',
        badgeClass: 'danger',
        filterFn: (row) => Number(row.quantity_on_hand || 0) <= 0
      },
      {
        id: 'feed',
        label: '🌾 Feed',
        badgeClass: 'ok',
        filterFn: (row) => (row.category || '').toLowerCase() === 'feed'
      },
      {
        id: 'medicine',
        label: '💊 Medicine & Vet',
        badgeClass: 'info',
        filterFn: (row) => {
          const c = (row.category || '').toLowerCase();
          return c.includes('med') || c.includes('vet');
        }
      },
      {
        id: 'active',
        label: 'Active Only',
        badgeClass: 'ok',
        filterFn: (row) => (row.status || '').toLowerCase() === 'active'
      }
    ],
    advancedFilters: [
      {
        key: 'category',
        label: 'Category',
        type: 'select',
        placeholder: 'All Categories',
        options: [
          { value: 'Feed', label: 'Feed' },
          { value: 'Medicine', label: 'Medicine' },
          { value: 'Veterinary Supplies', label: 'Veterinary Supplies' },
          { value: 'Tools', label: 'Tools' },
          { value: 'PPE', label: 'PPE' },
          { value: 'Fuel', label: 'Fuel' },
          { value: 'Spare Parts', label: 'Spare Parts' }
        ],
        filterFn: (row, val) => !val || (row.category || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'stock_health',
        label: 'Stock Health',
        type: 'select',
        placeholder: 'All Levels',
        options: [
          { value: 'adequate', label: 'Adequate Stock' },
          { value: 'low_stock', label: 'Low Stock' },
          { value: 'out_of_stock', label: 'Out of Stock' }
        ],
        filterFn: (row, val) => {
          if (!val) return true;
          const qty = Number(row.quantity_on_hand || 0);
          const reorder = Number(row.reorder_level || 0);
          if (val === 'out_of_stock') return qty <= 0;
          if (val === 'low_stock') return qty <= reorder && qty > 0;
          if (val === 'adequate') return qty > reorder;
          return true;
        }
      },
      {
        key: 'storage_location',
        label: 'Storage Location',
        type: 'text',
        placeholder: 'Search storage location...',
        filterFn: (row, val) => !val || (row.storage_location || '').toLowerCase().includes(val.toLowerCase())
      },
      {
        key: 'supplier_id',
        label: 'Supplier',
        type: 'select',
        placeholder: 'All Suppliers',
        lookup: 'suppliers',
        filterFn: (row, val) => !val || String(row.supplier_id) === String(val)
      },
      {
        key: 'status',
        label: 'Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'active', label: 'Active' },
          { value: 'inactive', label: 'Inactive' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      }
    ],
    kpiDefs: [
      {
        label: 'Total Stock Items',
        compute: (rows, all) => ({
          label: 'Total Stock Items',
          value: rows.length,
          meta: `${all.length} items cataloged`,
          ok: true
        })
      },
      {
        label: 'Low Stock Alert',
        compute: (rows) => {
          const count = rows.filter(r => Number(r.quantity_on_hand || 0) <= Number(r.reorder_level || 0) && Number(r.quantity_on_hand || 0) > 0).length;
          return {
            label: 'Low Stock Alert',
            value: count,
            meta: 'Below reorder threshold',
            alert: count > 0
          };
        }
      },
      {
        label: 'Out of Stock',
        compute: (rows) => {
          const count = rows.filter(r => Number(r.quantity_on_hand || 0) <= 0).length;
          return {
            label: 'Out of Stock',
            value: count,
            meta: 'Zero inventory on hand',
            alert: count > 0
          };
        }
      },
      {
        label: 'Total Valuation',
        compute: (rows) => {
          const total = rows.reduce((sum, r) => sum + (Number(r.quantity_on_hand || 0) * Number(r.average_cost || 0)), 0);
          return {
            label: 'Total Valuation',
            value: '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            meta: 'Asset value on hand',
            ok: true
          };
        }
      }
    ]
  },
  suppliers: {
    title: 'Suppliers Directory',
    subtitle: 'Vendor catalog, supplier ratings, contacts and categories.',
    columns: [
      { key: 'code', label: 'Code', render: (row) => `<strong>${row.code || '-'}</strong>` },
      {
        key: 'name',
        label: 'Supplier Name',
        render: (row) => `<strong>${row.name}</strong>${row.contact_name ? `<div class="sub-text">👤 ${row.contact_name}</div>` : ''}`
      },
      {
        key: 'category',
        label: 'Category',
        render: (row) => row.category ? `<span class="badge ${String(row.category).toLowerCase().replace(/[^a-z0-9]/g, '_')}">${row.category}</span>` : '-'
      },
      {
        key: 'rating',
        label: 'Rating',
        render: (row) => row.rating !== null && row.rating !== undefined ? `<span class="badge rating-badge">⭐ ${Number(row.rating).toFixed(1)} / 5</span>` : '-'
      },
      {
        key: 'phone',
        label: 'Phone',
        render: (row) => row.phone ? `<span class="contact-link">📞 ${row.phone}</span>` : '-'
      },
      {
        key: 'email',
        label: 'Email',
        render: (row) => row.email ? `<span class="contact-link">✉️ ${row.email}</span>` : '-'
      },
      {
        key: 'location',
        label: 'Location',
        render: (row) => row.location ? `📍 ${row.location}` : '-'
      },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'code', label: 'Code', type: 'text', placeholder: 'Auto if blank' },
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'category', label: 'Category', type: 'text' },
      { key: 'contact_name', label: 'Contact', type: 'text' },
      { key: 'phone', label: 'Phone', type: 'text' },
      { key: 'email', label: 'Email', type: 'text' },
      { key: 'location', label: 'Location', type: 'text' },
      { key: 'rating', label: 'Rating', type: 'number' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }
      ]},
      { key: 'notes', label: 'Notes', type: 'textarea' }
    ],
    defaults: { status: 'active', rating: 5 },
    quickFilters: [
      { id: 'all', label: 'All Suppliers', filterFn: () => true },
      {
        id: 'active',
        label: '✅ Active Only',
        badgeClass: 'ok',
        filterFn: (row) => (row.status || '').toLowerCase() === 'active'
      },
      {
        id: 'top_rated',
        label: '⭐ Top Rated (4★+)',
        badgeClass: 'warning',
        filterFn: (row) => Number(row.rating || 0) >= 4
      },
      {
        id: 'feed',
        label: '🌾 Feed Suppliers',
        badgeClass: 'info',
        filterFn: (row) => (row.category || '').toLowerCase().includes('feed')
      },
      {
        id: 'vet',
        label: '💊 Vet & Medicine',
        badgeClass: 'info',
        filterFn: (row) => {
          const c = (row.category || '').toLowerCase();
          return c.includes('vet') || c.includes('med');
        }
      },
      {
        id: 'inactive',
        label: '⚠️ Inactive / Suspended',
        badgeClass: 'danger',
        filterFn: (row) => (row.status || '').toLowerCase() === 'inactive'
      }
    ],
    advancedFilters: [
      {
        key: 'category',
        label: 'Category',
        type: 'select',
        placeholder: 'All Categories',
        options: [
          { value: 'Feed', label: 'Feed' },
          { value: 'Medicine', label: 'Medicine' },
          { value: 'Veterinary Supplies', label: 'Veterinary Supplies' },
          { value: 'Machinery', label: 'Machinery' },
          { value: 'Equipment', label: 'Equipment' },
          { value: 'Fertilizers', label: 'Fertilizers' },
          { value: 'Seeds', label: 'Seeds' },
          { value: 'Supplies', label: 'General Supplies' }
        ],
        filterFn: (row, val) => !val || (row.category || '').toLowerCase().includes(val.toLowerCase())
      },
      {
        key: 'min_rating',
        label: 'Min Rating',
        type: 'select',
        placeholder: 'Any Rating',
        options: [
          { value: '4.5', label: '4.5★ & Above' },
          { value: '4.0', label: '4.0★ & Above' },
          { value: '3.0', label: '3.0★ & Above' },
          { value: '2.0', label: '2.0★ & Above' }
        ],
        filterFn: (row, val) => {
          if (!val) return true;
          return Number(row.rating || 0) >= Number(val);
        }
      },
      {
        key: 'location',
        label: 'Location / Region',
        type: 'text',
        placeholder: 'Search location...',
        filterFn: (row, val) => !val || (row.location || '').toLowerCase().includes(val.toLowerCase())
      },
      {
        key: 'status',
        label: 'Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'active', label: 'Active' },
          { value: 'inactive', label: 'Inactive' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      }
    ],
    kpiDefs: [
      {
        label: 'Total Suppliers',
        compute: (rows, all) => ({
          label: 'Total Suppliers',
          value: rows.length,
          meta: `${all.length} registered partners`,
          ok: true
        })
      },
      {
        label: 'Active Partners',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'active').length;
          return {
            label: 'Active Partners',
            value: count,
            meta: 'Operational supplier agreements',
            ok: true
          };
        }
      },
      {
        label: 'Top Rated (4★+)',
        compute: (rows) => {
          const count = rows.filter(r => Number(r.rating || 0) >= 4).length;
          return {
            label: 'Top Rated (4★+)',
            value: count,
            meta: 'Reliable supplier quality',
            ok: true
          };
        }
      },
      {
        label: 'Inactive Accounts',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'inactive').length;
          return {
            label: 'Inactive Accounts',
            value: count,
            meta: 'Suspended or dormant accounts',
            alert: count > 0
          };
        }
      }
    ]
  },
  procurement: {
    title: 'Purchase Requests',
    subtitle: 'Request, approve and track farm purchases.',
    columns: [
      { key: 'request_number', label: 'PR Number', render: (row) => `<strong>${row.request_number || '-'}</strong>` },
      {
        key: 'item_name',
        label: 'Item Requested',
        render: (row) => `<strong>${row.item_name}</strong>${row.justification ? `<div class="sub-text">${row.justification}</div>` : ''}`
      },
      {
        key: 'category',
        label: 'Category',
        render: (row) => row.category ? `<span class="badge ${String(row.category).toLowerCase().replace(/[^a-z0-9]/g, '_')}">${row.category}</span>` : '-'
      },
      {
        key: 'quantity',
        label: 'Qty',
        render: (row) => `<strong>${row.quantity ?? 0}</strong> <span class="unit-text">${row.unit || ''}</span>`
      },
      {
        key: 'estimated_cost',
        label: 'Est. Cost',
        render: (row) => row.estimated_cost !== null && row.estimated_cost !== undefined ? `$${Number(row.estimated_cost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '-'
      },
      {
        key: 'priority',
        label: 'Priority',
        render: (row) => `<span class="badge priority-${(row.priority || 'normal').toLowerCase()}">${row.priority || 'normal'}</span>`
      },
      {
        key: 'status',
        label: 'Status',
        render: (row) => `<span class="badge status-${(row.status || 'pending').toLowerCase()}">${row.status || 'pending'}</span>`
      },
      { key: 'requester', label: 'Requested By', render: (row) => row.requester?.name || '-' },
      {
        key: 'requested_at',
        label: 'Date',
        render: (row) => row.requested_at ? String(row.requested_at).slice(0, 10) : '-'
      }
    ],
    fields: [
      { key: 'item_name', label: 'Item name', type: 'text', required: true },
      { key: 'category', label: 'Category', type: 'text' },
      { key: 'quantity', label: 'Quantity', type: 'number', required: true },
      { key: 'unit', label: 'Unit', type: 'text' },
      { key: 'estimated_cost', label: 'Estimated cost', type: 'number' },
      { key: 'priority', label: 'Priority', type: 'select', options: [
        { value: 'low', label: 'Low' }, { value: 'normal', label: 'Normal' }, { value: 'high', label: 'High' }
      ]},
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'pending', label: 'Pending' }, { value: 'approved', label: 'Approved' }, { value: 'rejected', label: 'Rejected' }
      ]},
      { key: 'justification', label: 'Justification', type: 'textarea' }
    ],
    defaults: { priority: 'normal', status: 'pending', unit: 'kg' },
    quickFilters: [
      { id: 'all', label: 'All Requests', filterFn: () => true },
      {
        id: 'pending',
        label: '⏳ Pending Approval',
        badgeClass: 'warning',
        filterFn: (row) => (row.status || '').toLowerCase() === 'pending'
      },
      {
        id: 'high_priority',
        label: '🚨 High Priority',
        badgeClass: 'danger',
        filterFn: (row) => (row.priority || '').toLowerCase() === 'high'
      },
      {
        id: 'approved',
        label: '✅ Approved',
        badgeClass: 'ok',
        filterFn: (row) => (row.status || '').toLowerCase() === 'approved'
      },
      {
        id: 'rejected',
        label: '❌ Rejected',
        badgeClass: 'danger',
        filterFn: (row) => (row.status || '').toLowerCase() === 'rejected'
      }
    ],
    advancedFilters: [
      {
        key: 'status',
        label: 'Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'pending', label: 'Pending' },
          { value: 'approved', label: 'Approved' },
          { value: 'rejected', label: 'Rejected' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'priority',
        label: 'Priority',
        type: 'select',
        placeholder: 'All Priorities',
        options: [
          { value: 'high', label: 'High' },
          { value: 'normal', label: 'Normal' },
          { value: 'low', label: 'Low' }
        ],
        filterFn: (row, val) => !val || (row.priority || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'category',
        label: 'Category',
        type: 'text',
        placeholder: 'Filter category (e.g. Feed, Equipment)',
        filterFn: (row, val) => !val || (row.category || '').toLowerCase().includes(val.toLowerCase())
      },
      {
        key: 'date_from',
        label: 'Requested After',
        type: 'date',
        filterFn: (row, val) => {
          if (!val) return true;
          if (!row.requested_at) return false;
          return new Date(row.requested_at) >= new Date(val);
        }
      },
      {
        key: 'date_to',
        label: 'Requested Before',
        type: 'date',
        filterFn: (row, val) => {
          if (!val) return true;
          if (!row.requested_at) return false;
          return new Date(row.requested_at) <= new Date(val + 'T23:59:59');
        }
      },
      {
        key: 'min_cost',
        label: 'Min Cost ($)',
        type: 'number',
        placeholder: '0.00',
        filterFn: (row, val) => {
          if (val === null || val === undefined || val === '') return true;
          return Number(row.estimated_cost || 0) >= Number(val);
        }
      },
      {
        key: 'max_cost',
        label: 'Max Cost ($)',
        type: 'number',
        placeholder: 'Any',
        filterFn: (row, val) => {
          if (val === null || val === undefined || val === '') return true;
          return Number(row.estimated_cost || 0) <= Number(val);
        }
      }
    ],
    kpiDefs: [
      {
        label: 'Total Requests',
        compute: (rows, all) => ({
          label: 'Total Requests',
          value: rows.length,
          meta: `${all.length} requests in history`,
          ok: true
        })
      },
      {
        label: 'Pending Approval',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'pending').length;
          return {
            label: 'Pending Approval',
            value: count,
            meta: 'Awaiting manager review',
            alert: count > 0
          };
        }
      },
      {
        label: 'High Priority',
        compute: (rows) => {
          const count = rows.filter(r => (r.priority || '').toLowerCase() === 'high').length;
          return {
            label: 'High Priority',
            value: count,
            meta: 'Urgent requests needed',
            alert: count > 0
          };
        }
      },
      {
        label: 'Estimated Spend',
        compute: (rows) => {
          const total = rows.reduce((sum, r) => sum + Number(r.estimated_cost || 0), 0);
          return {
            label: 'Estimated Spend',
            value: '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            meta: 'Total requested budget',
            ok: true
          };
        }
      }
    ]
  },
  assets: {
    title: 'Asset Register',
    subtitle: 'Machinery, vehicles, equipment assets and warranties.',
    columns: [
      { key: 'asset_id', label: 'Asset ID', render: (row) => `<strong>${row.asset_id || '-'}</strong>` },
      {
        key: 'name',
        label: 'Asset / Equipment',
        render: (row) => `<strong>${row.name}</strong>${row.serial_number ? `<div class="sub-text">SN: ${row.serial_number}</div>` : ''}`
      },
      {
        key: 'category',
        label: 'Category',
        render: (row) => row.category ? `<span class="badge ${String(row.category).toLowerCase().replace(/[^a-z0-9]/g, '_')}">${row.category}</span>` : '-'
      },
      {
        key: 'location',
        label: 'Location',
        render: (row) => row.location?.name ? `📍 ${row.location.name}` : '-'
      },
      {
        key: 'condition',
        label: 'Condition',
        render: (row) => {
          const c = (row.condition || 'good').toLowerCase();
          const label = c.charAt(0).toUpperCase() + c.slice(1);
          return `<span class="badge condition-${c}">${label}</span>`;
        }
      },
      {
        key: 'purchase_price',
        label: 'Purchase Price',
        render: (row) => row.purchase_price !== null && row.purchase_price !== undefined ? `$${Number(row.purchase_price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '-'
      },
      { key: 'supplier', label: 'Supplier', render: (row) => row.supplier?.name || '-' },
      { key: 'status', label: 'Status' },
      {
        key: 'purchase_date',
        label: 'Purchased',
        render: (row) => row.purchase_date ? String(row.purchase_date).slice(0, 10) : '-'
      }
    ],
    fields: [
      { key: 'asset_id', label: 'Asset ID', type: 'text', placeholder: 'Auto if blank' },
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'category', label: 'Category', type: 'text' },
      { key: 'serial_number', label: 'Serial', type: 'text' },
      { key: 'purchase_date', label: 'Purchase date', type: 'date' },
      { key: 'purchase_price', label: 'Purchase price', type: 'number' },
      { key: 'supplier_id', label: 'Supplier', type: 'select', lookup: 'suppliers' },
      { key: 'location_id', label: 'Location', type: 'select', lookup: 'locations' },
      { key: 'condition', label: 'Condition', type: 'select', options: [
        { value: 'good', label: 'Good' }, { value: 'fair', label: 'Fair' }, { value: 'poor', label: 'Poor' }
      ]},
      { key: 'warranty_until', label: 'Warranty until', type: 'date' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'active', label: 'Active' }, { value: 'retired', label: 'Retired' }
      ]}
    ],
    defaults: { status: 'active', condition: 'good' },
    quickFilters: [
      { id: 'all', label: 'All Assets', filterFn: () => true },
      {
        id: 'active',
        label: '✅ Active in Service',
        badgeClass: 'ok',
        filterFn: (row) => (row.status || '').toLowerCase() === 'active'
      },
      {
        id: 'good',
        label: '🟢 Good Condition',
        badgeClass: 'ok',
        filterFn: (row) => (row.condition || '').toLowerCase() === 'good'
      },
      {
        id: 'maintenance',
        label: '⚠️ Needs Maintenance',
        badgeClass: 'warning',
        filterFn: (row) => ['poor', 'fair'].includes((row.condition || '').toLowerCase())
      },
      {
        id: 'machinery',
        label: '🚜 Machinery & Vehicles',
        badgeClass: 'info',
        filterFn: (row) => {
          const c = (row.category || '').toLowerCase();
          return c.includes('mach') || c.includes('vehic') || c.includes('tractor');
        }
      },
      {
        id: 'retired',
        label: '🛑 Retired',
        badgeClass: 'danger',
        filterFn: (row) => (row.status || '').toLowerCase() === 'retired'
      }
    ],
    advancedFilters: [
      {
        key: 'category',
        label: 'Category',
        type: 'select',
        placeholder: 'All Categories',
        options: [
          { value: 'Machinery', label: 'Machinery' },
          { value: 'Vehicles', label: 'Vehicles' },
          { value: 'Irrigation', label: 'Irrigation' },
          { value: 'Livestock Equipment', label: 'Livestock Equipment' },
          { value: 'Processing', label: 'Processing' },
          { value: 'IT & Tech', label: 'IT & Tech' },
          { value: 'Tools', label: 'Tools' },
          { value: 'Storage', label: 'Storage' }
        ],
        filterFn: (row, val) => !val || (row.category || '').toLowerCase().includes(val.toLowerCase())
      },
      {
        key: 'condition',
        label: 'Condition',
        type: 'select',
        placeholder: 'All Conditions',
        options: [
          { value: 'good', label: 'Good' },
          { value: 'fair', label: 'Fair' },
          { value: 'poor', label: 'Poor' }
        ],
        filterFn: (row, val) => !val || (row.condition || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'location_id',
        label: 'Location',
        type: 'select',
        placeholder: 'All Locations',
        lookup: 'locations',
        filterFn: (row, val) => !val || String(row.location_id) === String(val)
      },
      {
        key: 'supplier_id',
        label: 'Supplier',
        type: 'select',
        placeholder: 'All Suppliers',
        lookup: 'suppliers',
        filterFn: (row, val) => !val || String(row.supplier_id) === String(val)
      },
      {
        key: 'status',
        label: 'Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'active', label: 'Active' },
          { value: 'retired', label: 'Retired' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'min_price',
        label: 'Min Value ($)',
        type: 'number',
        placeholder: '0.00',
        filterFn: (row, val) => {
          if (val === null || val === undefined || val === '') return true;
          return Number(row.purchase_price || 0) >= Number(val);
        }
      },
      {
        key: 'max_price',
        label: 'Max Value ($)',
        type: 'number',
        placeholder: 'Any',
        filterFn: (row, val) => {
          if (val === null || val === undefined || val === '') return true;
          return Number(row.purchase_price || 0) <= Number(val);
        }
      }
    ],
    kpiDefs: [
      {
        label: 'Total Assets Owned',
        compute: (rows, all) => ({
          label: 'Total Assets Owned',
          value: rows.length,
          meta: `${all.length} registered equipment units`,
          ok: true
        })
      },
      {
        label: 'Active in Service',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'active').length;
          return {
            label: 'Active in Service',
            value: count,
            meta: 'Operational farm machinery',
            ok: true
          };
        }
      },
      {
        label: 'Maintenance Needs',
        compute: (rows) => {
          const count = rows.filter(r => ['poor', 'fair'].includes((r.condition || '').toLowerCase())).length;
          return {
            label: 'Maintenance Needs',
            value: count,
            meta: 'Fair or poor condition',
            alert: count > 0
          };
        }
      },
      {
        label: 'Total Asset Value',
        compute: (rows) => {
          const total = rows.reduce((sum, r) => sum + Number(r.purchase_price || 0), 0);
          return {
            label: 'Total Asset Value',
            value: '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            meta: 'Capital equipment investment',
            ok: true
          };
        }
      }
    ]
  },
  maintenance: {
    title: 'Maintenance Tickets',
    subtitle: 'Equipment repairs, preventive servicing schedules and diagnostics.',
    columns: [
      { key: 'ticket_number', label: 'Ticket #', render: (row) => `<strong>${row.ticket_number || '-'}</strong>` },
      {
        key: 'title',
        label: 'Issue / Task',
        render: (row) => `<strong>${row.title}</strong>${row.description ? `<div class="sub-text">${row.description}</div>` : ''}`
      },
      {
        key: 'asset',
        label: 'Asset / Equipment',
        render: (row) => row.asset ? `🚜 <strong>${row.asset.asset_id || ''}</strong> ${row.asset.name || ''}` : '-'
      },
      {
        key: 'type',
        label: 'Type',
        render: (row) => {
          const t = (row.type || 'corrective').toLowerCase();
          const label = t.charAt(0).toUpperCase() + t.slice(1);
          return `<span class="badge type-${t}">${label}</span>`;
        }
      },
      {
        key: 'priority',
        label: 'Priority',
        render: (row) => {
          const p = (row.priority || 'normal').toLowerCase();
          const label = p.charAt(0).toUpperCase() + p.slice(1);
          return `<span class="badge priority-${p}">${label}</span>`;
        }
      },
      {
        key: 'status',
        label: 'Status',
        render: (row) => {
          const s = (row.status || 'open').toLowerCase().replace(/\s+/g, '_');
          const label = s.replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase());
          return `<span class="badge status-${s}">${label}</span>`;
        }
      },
      {
        key: 'assigned_to',
        label: 'Assigned To',
        render: (row) => row.assignee?.name ? `👤 ${row.assignee.name}` : (row.assigned_to ? `User #${row.assigned_to}` : '<span class="muted">Unassigned</span>')
      },
      {
        key: 'reported_at',
        label: 'Reported',
        render: (row) => row.reported_at ? String(row.reported_at).slice(0, 10) : '-'
      }
    ],
    fields: [
      { key: 'title', label: 'Title', type: 'text', required: true },
      { key: 'asset_id', label: 'Asset', type: 'select', lookup: 'assets' },
      { key: 'type', label: 'Type', type: 'select', options: [
        { value: 'preventive', label: 'Preventive' }, { value: 'corrective', label: 'Corrective' },
        { value: 'emergency', label: 'Emergency' }, { value: 'inspection', label: 'Inspection' }
      ]},
      { key: 'priority', label: 'Priority', type: 'select', options: [
        { value: 'low', label: 'Low' }, { value: 'normal', label: 'Normal' }, { value: 'high', label: 'High' }
      ]},
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'open', label: 'Open' }, { value: 'assigned', label: 'Assigned' },
        { value: 'in_progress', label: 'In progress' }, { value: 'closed', label: 'Closed' }
      ]},
      { key: 'assigned_to', label: 'Assigned to', type: 'select', lookup: 'users' },
      { key: 'description', label: 'Description', type: 'textarea' }
    ],
    defaults: { type: 'corrective', priority: 'normal', status: 'open' },
    quickFilters: [
      { id: 'all', label: 'All Tickets', filterFn: () => true },
      {
        id: 'open_action',
        label: '🚨 Open / Needs Action',
        badgeClass: 'danger',
        filterFn: (row) => ['open', 'assigned'].includes((row.status || '').toLowerCase())
      },
      {
        id: 'in_progress',
        label: '⚡ In Progress',
        badgeClass: 'warning',
        filterFn: (row) => (row.status || '').toLowerCase() === 'in_progress'
      },
      {
        id: 'emergency',
        label: '🔥 High Priority / Urgent',
        badgeClass: 'danger',
        filterFn: (row) => ['high', 'urgent'].includes((row.priority || '').toLowerCase()) || (row.type || '').toLowerCase() === 'emergency'
      },
      {
        id: 'preventive',
        label: '🛡️ Preventive Servicing',
        badgeClass: 'info',
        filterFn: (row) => (row.type || '').toLowerCase() === 'preventive'
      },
      {
        id: 'closed',
        label: '✅ Resolved & Closed',
        badgeClass: 'ok',
        filterFn: (row) => (row.status || '').toLowerCase() === 'closed'
      }
    ],
    advancedFilters: [
      {
        key: 'status',
        label: 'Ticket Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'open', label: 'Open' },
          { value: 'assigned', label: 'Assigned' },
          { value: 'in_progress', label: 'In Progress' },
          { value: 'closed', label: 'Closed' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'priority',
        label: 'Priority Level',
        type: 'select',
        placeholder: 'All Priorities',
        options: [
          { value: 'high', label: 'High' },
          { value: 'normal', label: 'Normal' },
          { value: 'low', label: 'Low' }
        ],
        filterFn: (row, val) => !val || (row.priority || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'type',
        label: 'Maintenance Type',
        type: 'select',
        placeholder: 'All Types',
        options: [
          { value: 'preventive', label: 'Preventive' },
          { value: 'corrective', label: 'Corrective' },
          { value: 'emergency', label: 'Emergency' },
          { value: 'inspection', label: 'Inspection' }
        ],
        filterFn: (row, val) => !val || (row.type || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'asset_id',
        label: 'Asset',
        type: 'select',
        placeholder: 'All Assets',
        lookup: 'assets',
        filterFn: (row, val) => !val || String(row.asset_id) === String(val)
      },
      {
        key: 'assigned_to',
        label: 'Assigned Staff',
        type: 'select',
        placeholder: 'All Assignees',
        lookup: 'users',
        filterFn: (row, val) => !val || String(row.assigned_to) === String(val)
      },
      {
        key: 'date_from',
        label: 'Reported After',
        type: 'date',
        filterFn: (row, val) => {
          if (!val) return true;
          if (!row.reported_at) return false;
          return new Date(row.reported_at) >= new Date(val);
        }
      },
      {
        key: 'date_to',
        label: 'Reported Before',
        type: 'date',
        filterFn: (row, val) => {
          if (!val) return true;
          if (!row.reported_at) return false;
          return new Date(row.reported_at) <= new Date(val + 'T23:59:59');
        }
      }
    ],
    kpiDefs: [
      {
        label: 'Total Tickets',
        compute: (rows, all) => ({
          label: 'Total Tickets',
          value: rows.length,
          meta: `${all.length} service records logged`,
          ok: true
        })
      },
      {
        label: 'Active / Unresolved',
        compute: (rows) => {
          const count = rows.filter(r => ['open', 'assigned', 'in_progress'].includes((r.status || '').toLowerCase())).length;
          return {
            label: 'Active / Unresolved',
            value: count,
            meta: 'Tickets awaiting completion',
            alert: count > 0
          };
        }
      },
      {
        label: 'High Priority & Emergency',
        compute: (rows) => {
          const count = rows.filter(r => ['high', 'urgent'].includes((r.priority || '').toLowerCase()) || (r.type || '').toLowerCase() === 'emergency').length;
          return {
            label: 'High Priority & Emergency',
            value: count,
            meta: 'Critical farm repairs',
            alert: count > 0
          };
        }
      },
      {
        label: 'Preventive Checks',
        compute: (rows) => {
          const count = rows.filter(r => (r.type || '').toLowerCase() === 'preventive').length;
          return {
            label: 'Preventive Checks',
            value: count,
            meta: 'Scheduled equipment maintenance',
            ok: true
          };
        }
      }
    ]
  },
  employees: {
    title: 'HR & Personnel Directory',
    subtitle: 'Workforce records, department assignments, contacts and employment status.',
    columns: [
      { key: 'employee_code', label: 'Emp ID', render: (row) => `<strong>${row.employee_code || '-'}</strong>` },
      {
        key: 'name',
        label: 'Employee Name',
        render: (row) => `<strong>${row.name}</strong>${row.role ? `<div class="sub-text">💼 ${row.role}</div>` : ''}`
      },
      {
        key: 'department',
        label: 'Department',
        render: (row) => row.department ? `<span class="badge department">${row.department}</span>` : '-'
      },
      {
        key: 'phone',
        label: 'Phone',
        render: (row) => row.phone ? `<span class="contact-link">📞 ${row.phone}</span>` : '-'
      },
      {
        key: 'email',
        label: 'Email',
        render: (row) => row.email ? `<span class="contact-link">✉️ ${row.email}</span>` : '-'
      },
      {
        key: 'hire_date',
        label: 'Hire Date',
        render: (row) => row.hire_date ? String(row.hire_date).slice(0, 10) : '-'
      },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'employee_code', label: 'Code', type: 'text', placeholder: 'Auto if blank' },
      { key: 'name', label: 'Name', type: 'text', required: true },
      { key: 'role', label: 'Role / Title', type: 'text', placeholder: 'e.g. Veterinary Officer, Farm Manager' },
      { key: 'department', label: 'Department', type: 'select', options: [
        { value: 'Livestock & Animal Health', label: 'Livestock & Animal Health' },
        { value: 'Feed & Rations', label: 'Feed & Rations' },
        { value: 'Operations & Maintenance', label: 'Operations & Maintenance' },
        { value: 'Finance & Procurement', label: 'Finance & Procurement' },
        { value: 'Administration', label: 'Administration' },
        { value: 'Security & Field', label: 'Security & Field' }
      ]},
      { key: 'phone', label: 'Phone', type: 'text' },
      { key: 'email', label: 'Email', type: 'text' },
      { key: 'hire_date', label: 'Hire date', type: 'date' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'active', label: 'Active' },
        { value: 'on_leave', label: 'On Leave' },
        { value: 'inactive', label: 'Inactive' }
      ]}
    ],
    defaults: { status: 'active', department: 'Livestock & Animal Health' },
    quickFilters: [
      { id: 'all', label: 'All Employees', filterFn: () => true },
      {
        id: 'active',
        label: '✅ Active Staff',
        badgeClass: 'ok',
        filterFn: (row) => (row.status || '').toLowerCase() === 'active'
      },
      {
        id: 'livestock_dept',
        label: '🐐 Livestock & Health',
        badgeClass: 'info',
        filterFn: (row) => {
          const d = (row.department || '').toLowerCase();
          const r = (row.role || '').toLowerCase();
          return d.includes('live') || d.includes('animal') || d.includes('health') || r.includes('vet') || r.includes('livestock');
        }
      },
      {
        id: 'ops_dept',
        label: '🚜 Operations & Field',
        badgeClass: 'info',
        filterFn: (row) => {
          const d = (row.department || '').toLowerCase();
          return d.includes('op') || d.includes('maint') || d.includes('feed') || d.includes('field');
        }
      },
      {
        id: 'admin_dept',
        label: '💼 Admin & Finance',
        badgeClass: 'info',
        filterFn: (row) => {
          const d = (row.department || '').toLowerCase();
          return d.includes('admin') || d.includes('fin') || d.includes('procure');
        }
      },
      {
        id: 'leave_inactive',
        label: '⚠️ On Leave / Inactive',
        badgeClass: 'warning',
        filterFn: (row) => (row.status || '').toLowerCase() !== 'active'
      }
    ],
    advancedFilters: [
      {
        key: 'department',
        label: 'Department',
        type: 'select',
        placeholder: 'All Departments',
        options: [
          { value: 'Livestock & Animal Health', label: 'Livestock & Animal Health' },
          { value: 'Feed & Rations', label: 'Feed & Rations' },
          { value: 'Operations & Maintenance', label: 'Operations & Maintenance' },
          { value: 'Finance & Procurement', label: 'Finance & Procurement' },
          { value: 'Administration', label: 'Administration' },
          { value: 'Security & Field', label: 'Security & Field' }
        ],
        filterFn: (row, val) => !val || (row.department || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'role',
        label: 'Role / Designation',
        type: 'text',
        placeholder: 'Filter by job title...',
        filterFn: (row, val) => !val || (row.role || '').toLowerCase().includes(val.toLowerCase())
      },
      {
        key: 'status',
        label: 'Employment Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'active', label: 'Active' },
          { value: 'on_leave', label: 'On Leave' },
          { value: 'inactive', label: 'Inactive' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'date_from',
        label: 'Hired After',
        type: 'date',
        filterFn: (row, val) => {
          if (!val) return true;
          if (!row.hire_date) return false;
          return new Date(row.hire_date) >= new Date(val);
        }
      },
      {
        key: 'date_to',
        label: 'Hired Before',
        type: 'date',
        filterFn: (row, val) => {
          if (!val) return true;
          if (!row.hire_date) return false;
          return new Date(row.hire_date) <= new Date(val + 'T23:59:59');
        }
      }
    ],
    kpiDefs: [
      {
        label: 'Total Workforce',
        compute: (rows, all) => ({
          label: 'Total Workforce',
          value: rows.length,
          meta: `${all.length} registered personnel`,
          ok: true
        })
      },
      {
        label: 'Active on Duty',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'active').length;
          return {
            label: 'Active on Duty',
            value: count,
            meta: 'Current active staff',
            ok: true
          };
        }
      },
      {
        label: 'Departments Active',
        compute: (rows) => {
          const depts = new Set(rows.map(r => r.department).filter(Boolean));
          return {
            label: 'Departments Active',
            value: depts.size,
            meta: 'Operational divisions',
            info: true
          };
        }
      },
      {
        label: 'On Leave / Inactive',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() !== 'active').length;
          return {
            label: 'On Leave / Inactive',
            value: count,
            meta: 'Staff absent or off-duty',
            alert: count > 0
          };
        }
      }
    ]
  },
  tasks: {
    title: 'Tasks & Operations',
    subtitle: 'Daily farm assignments, field work orders and workflow tracking.',
    columns: [
      {
        key: 'title',
        label: 'Task & Details',
        render: (row) => `<strong>${row.title}</strong>${row.description ? `<div class="sub-text">${row.description}</div>` : ''}`
      },
      {
        key: 'module',
        label: 'Module / Area',
        render: (row) => row.module ? `<span class="badge module-${String(row.module).toLowerCase()}">${row.module}</span>` : '-'
      },
      {
        key: 'priority',
        label: 'Priority',
        render: (row) => {
          const p = (row.priority || 'normal').toLowerCase();
          const label = p.charAt(0).toUpperCase() + p.slice(1);
          return `<span class="badge priority-${p}">${label}</span>`;
        }
      },
      {
        key: 'status',
        label: 'Status',
        render: (row) => {
          const s = (row.status || 'open').toLowerCase().replace(/\s+/g, '_');
          const label = s.replace(/_/g, ' ').replace(/\b\w/g, (l: string) => l.toUpperCase());
          return `<span class="badge status-${s}">${label}</span>`;
        }
      },
      {
        key: 'assignee',
        label: 'Assigned To',
        render: (row) => row.assignee?.name ? `👤 ${row.assignee.name}` : (row.assigned_to ? `User #${row.assigned_to}` : '<span class="muted">Unassigned</span>')
      },
      {
        key: 'due_date',
        label: 'Due Date',
        render: (row) => {
          if (!row.due_date) return '-';
          const d = String(row.due_date).slice(0, 10);
          const isOverdue = new Date(d) < new Date(new Date().toDateString()) && (row.status || '').toLowerCase() !== 'completed';
          if (isOverdue) {
            return `<span class="badge badge-overdue">⚠️ Overdue (${d})</span>`;
          }
          return d;
        }
      }
    ],
    fields: [
      { key: 'title', label: 'Title', type: 'text', required: true },
      { key: 'module', label: 'Module / Area', type: 'select', options: [
        { value: 'Livestock', label: 'Livestock' },
        { value: 'Health', label: 'Animal Health' },
        { value: 'Feed', label: 'Feed & Nutrition' },
        { value: 'Maintenance', label: 'Maintenance' },
        { value: 'Procurement', label: 'Procurement' },
        { value: 'Finance', label: 'Finance' },
        { value: 'General', label: 'General Farm' }
      ]},
      { key: 'description', label: 'Description', type: 'textarea' },
      { key: 'assigned_to', label: 'Assigned to', type: 'select', lookup: 'users' },
      { key: 'due_date', label: 'Due date', type: 'date' },
      { key: 'priority', label: 'Priority', type: 'select', options: [
        { value: 'low', label: 'Low' },
        { value: 'normal', label: 'Normal' },
        { value: 'high', label: 'High' },
        { value: 'urgent', label: 'Urgent' }
      ]},
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'open', label: 'Open' },
        { value: 'in_progress', label: 'In progress' },
        { value: 'completed', label: 'Completed' },
        { value: 'cancelled', label: 'Cancelled' }
      ]}
    ],
    defaults: { status: 'open', priority: 'normal', module: 'Livestock' },
    quickFilters: [
      { id: 'all', label: 'All Tasks', filterFn: () => true },
      {
        id: 'open_tasks',
        label: '🚨 Open / Pending',
        badgeClass: 'warning',
        filterFn: (row) => (row.status || '').toLowerCase() === 'open'
      },
      {
        id: 'in_progress',
        label: '⚡ In Progress',
        badgeClass: 'info',
        filterFn: (row) => (row.status || '').toLowerCase() === 'in_progress'
      },
      {
        id: 'urgent',
        label: '🔥 High Priority & Urgent',
        badgeClass: 'danger',
        filterFn: (row) => ['high', 'urgent'].includes((row.priority || '').toLowerCase()) && (row.status || '').toLowerCase() !== 'completed'
      },
      {
        id: 'livestock_tasks',
        label: '🐐 Herd & Animal Tasks',
        badgeClass: 'ok',
        filterFn: (row) => ['livestock', 'health', 'feed'].includes((row.module || '').toLowerCase())
      },
      {
        id: 'completed',
        label: '✅ Completed',
        badgeClass: 'ok',
        filterFn: (row) => (row.status || '').toLowerCase() === 'completed'
      }
    ],
    advancedFilters: [
      {
        key: 'status',
        label: 'Task Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'open', label: 'Open' },
          { value: 'in_progress', label: 'In Progress' },
          { value: 'completed', label: 'Completed' },
          { value: 'cancelled', label: 'Cancelled' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'priority',
        label: 'Priority Level',
        type: 'select',
        placeholder: 'All Priorities',
        options: [
          { value: 'urgent', label: 'Urgent' },
          { value: 'high', label: 'High' },
          { value: 'normal', label: 'Normal' },
          { value: 'low', label: 'Low' }
        ],
        filterFn: (row, val) => !val || (row.priority || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'module',
        label: 'Module / Area',
        type: 'select',
        placeholder: 'All Areas',
        options: [
          { value: 'Livestock', label: 'Livestock' },
          { value: 'Health', label: 'Animal Health' },
          { value: 'Feed', label: 'Feed & Nutrition' },
          { value: 'Maintenance', label: 'Maintenance' },
          { value: 'Procurement', label: 'Procurement' },
          { value: 'Finance', label: 'Finance' },
          { value: 'General', label: 'General' }
        ],
        filterFn: (row, val) => !val || (row.module || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'assigned_to',
        label: 'Assigned Staff',
        type: 'select',
        placeholder: 'All Assignees',
        lookup: 'users',
        filterFn: (row, val) => !val || String(row.assigned_to) === String(val)
      },
      {
        key: 'date_to',
        label: 'Due Before',
        type: 'date',
        filterFn: (row, val) => {
          if (!val) return true;
          if (!row.due_date) return false;
          return new Date(row.due_date) <= new Date(val + 'T23:59:59');
        }
      },
      {
        key: 'date_from',
        label: 'Due After',
        type: 'date',
        filterFn: (row, val) => {
          if (!val) return true;
          if (!row.due_date) return false;
          return new Date(row.due_date) >= new Date(val);
        }
      }
    ],
    kpiDefs: [
      {
        label: 'Total Tasks',
        compute: (rows, all) => ({
          label: 'Total Tasks',
          value: rows.length,
          meta: `${all.length} work items logged`,
          ok: true
        })
      },
      {
        label: 'Open & Active',
        compute: (rows) => {
          const count = rows.filter(r => ['open', 'in_progress'].includes((r.status || '').toLowerCase())).length;
          return {
            label: 'Open & Active',
            value: count,
            meta: 'Unfinished work orders',
            alert: count > 0
          };
        }
      },
      {
        label: 'High Priority',
        compute: (rows) => {
          const count = rows.filter(r => ['high', 'urgent'].includes((r.priority || '').toLowerCase()) && (r.status || '').toLowerCase() !== 'completed').length;
          return {
            label: 'High Priority',
            value: count,
            meta: 'Urgent tasks requiring attention',
            alert: count > 0
          };
        }
      },
      {
        label: 'Tasks Completed',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'completed').length;
          return {
            label: 'Tasks Completed',
            value: count,
            meta: 'Successfully resolved work orders',
            ok: true
          };
        }
      }
    ]
  },
  expenses: {
    title: 'Expenses',
    subtitle: 'Operating expenditure and cost register.',
    columns: [
      { key: 'expense_date', label: 'Date' },
      {
        key: 'category',
        label: 'Category',
        render: (row) => {
          const cat = row.category || 'General';
          return `<span class="badge badge-info">${cat}</span>`;
        }
      },
      { key: 'description', label: 'Description' },
      {
        key: 'amount',
        label: 'Amount (GHS)',
        render: (row) => row.amount != null ? 'GHS ' + Number(row.amount).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-'
      },
      { key: 'source', label: 'Source' }
    ],
    fields: [
      { key: 'expense_date', label: 'Date', type: 'date', required: true },
      {
        key: 'category',
        label: 'Category',
        type: 'select',
        required: true,
        options: [
          { value: 'Feed', label: 'Feed & Nutrition' },
          { value: 'Veterinary', label: 'Veterinary & Healthcare' },
          { value: 'Wages', label: 'Wages & Labor' },
          { value: 'Fuel', label: 'Fuel & Energy' },
          { value: 'Maintenance', label: 'Repairs & Maintenance' },
          { value: 'Supplies', label: 'Farm Supplies' },
          { value: 'Logistics', label: 'Logistics & Transport' },
          { value: 'Utilities', label: 'Utilities & Water' },
          { value: 'Administrative', label: 'Administrative & Other' }
        ]
      },
      { key: 'description', label: 'Description', type: 'text' },
      { key: 'amount', label: 'Amount (GHS)', type: 'number', required: true },
      { key: 'source', label: 'Source / Channel', type: 'text', placeholder: 'e.g. Cash, Mobile Money, Bank' }
    ],
    quickFilters: [
      { id: 'all', label: 'All Expenses', filterFn: () => true },
      {
        id: 'feed',
        label: 'Feed & Nutrition',
        badgeClass: 'ok',
        filterFn: (row) => (row.category || '').toLowerCase().includes('feed')
      },
      {
        id: 'vet',
        label: 'Veterinary & Meds',
        badgeClass: 'info',
        filterFn: (row) => {
          const cat = (row.category || '').toLowerCase();
          return cat.includes('vet') || cat.includes('med') || cat.includes('health');
        }
      },
      {
        id: 'wages',
        label: 'Wages & Labor',
        badgeClass: 'warning',
        filterFn: (row) => {
          const cat = (row.category || '').toLowerCase();
          return cat.includes('wage') || cat.includes('labor') || cat.includes('salary');
        }
      },
      {
        id: 'fuel',
        label: 'Fuel & Operations',
        filterFn: (row) => {
          const cat = (row.category || '').toLowerCase();
          return cat.includes('fuel') || cat.includes('maint') || cat.includes('util');
        }
      },
      {
        id: 'high',
        label: 'High Value (≥ GHS 500)',
        badgeClass: 'danger',
        filterFn: (row) => Number(row.amount || 0) >= 500
      }
    ],
    advancedFilters: [
      {
        key: 'category',
        label: 'Expense Category',
        type: 'select',
        placeholder: 'All Categories',
        options: [
          { value: 'Feed', label: 'Feed & Nutrition' },
          { value: 'Veterinary', label: 'Veterinary & Healthcare' },
          { value: 'Wages', label: 'Wages & Labor' },
          { value: 'Fuel', label: 'Fuel & Energy' },
          { value: 'Maintenance', label: 'Repairs & Maintenance' },
          { value: 'Supplies', label: 'Farm Supplies' },
          { value: 'Logistics', label: 'Logistics & Transport' },
          { value: 'Utilities', label: 'Utilities & Water' },
          { value: 'Administrative', label: 'Administrative & Other' }
        ],
        filterFn: (row, val) => !val || (row.category || '').toLowerCase().includes(val.toLowerCase())
      },
      {
        key: 'min_amount',
        label: 'Min Amount (GHS)',
        type: 'number',
        placeholder: 'Min GHS',
        filterFn: (row, val) => val == null || val === '' || Number(row.amount || 0) >= Number(val)
      },
      {
        key: 'max_amount',
        label: 'Max Amount (GHS)',
        type: 'number',
        placeholder: 'Max GHS',
        filterFn: (row, val) => val == null || val === '' || Number(row.amount || 0) <= Number(val)
      },
      {
        key: 'date_from',
        label: 'From Date',
        type: 'date',
        filterFn: (row, val) => !val || !row.expense_date || row.expense_date >= val
      },
      {
        key: 'date_to',
        label: 'To Date',
        type: 'date',
        filterFn: (row, val) => !val || !row.expense_date || row.expense_date <= val
      }
    ],
    kpiDefs: [
      {
        label: 'Total Spend',
        compute: (rows, all) => {
          const total = rows.reduce((sum, r) => sum + Number(r.amount || 0), 0);
          return {
            label: 'Total Spend',
            value: `GHS ${total.toLocaleString('en-GH', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`,
            meta: `${rows.length} expense entries recorded`,
            ok: true
          };
        }
      },
      {
        label: 'Total Expenses',
        compute: (rows, all) => ({
          label: 'Total Expenses',
          value: rows.length,
          meta: `Out of ${all.length} registered costs`,
          info: true
        })
      },
      {
        label: 'Average Expense',
        compute: (rows) => {
          const total = rows.reduce((sum, r) => sum + Number(r.amount || 0), 0);
          const avg = rows.length > 0 ? total / rows.length : 0;
          return {
            label: 'Average Expense',
            value: `GHS ${avg.toLocaleString('en-GH', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`,
            meta: 'Mean spend per transaction'
          };
        }
      },
      {
        label: 'Top Spending Area',
        compute: (rows) => {
          const catTotals: Record<string, number> = {};
          rows.forEach((r) => {
            const cat = r.category || 'Other';
            catTotals[cat] = (catTotals[cat] || 0) + Number(r.amount || 0);
          });
          const top = Object.entries(catTotals).sort((a, b) => b[1] - a[1])[0];
          return {
            label: 'Top Spending Area',
            value: top ? top[0] : 'None',
            meta: top ? `GHS ${top[1].toLocaleString('en-GH', { maximumFractionDigits: 0 })} spent` : 'No expenses',
            alert: Boolean(top && top[1] >= 1000)
          };
        }
      }
    ]
  },
  invoices: {
    title: 'Invoices',
    subtitle: 'Supplier invoices and payment status.',
    columns: [
      { key: 'invoice_number', label: 'Invoice' },
      { key: 'supplier', label: 'Supplier', render: (row) => row.supplier?.name || '' },
      { key: 'amount', label: 'Amount' },
      { key: 'invoice_date', label: 'Date' },
      { key: 'due_date', label: 'Due' },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'invoice_number', label: 'Invoice number', type: 'text', required: true },
      { key: 'supplier_id', label: 'Supplier', type: 'select', lookup: 'suppliers' },
      { key: 'amount', label: 'Amount', type: 'number', required: true },
      { key: 'invoice_date', label: 'Invoice date', type: 'date', required: true },
      { key: 'due_date', label: 'Due date', type: 'date' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'unpaid', label: 'Unpaid' }, { value: 'paid', label: 'Paid' }, { value: 'partial', label: 'Partial' }
      ]}
    ],
    defaults: { status: 'unpaid' }
  },
  payments: {
    title: 'Payments',
    subtitle: 'Supplier payment register linked to invoice balances.',
    columns: [
      { key: 'invoice', label: 'Invoice', render: (row) => row.invoice?.invoice_number || (row.invoice_id ? `Invoice #${row.invoice_id}` : '-') },
      { key: 'supplier', label: 'Supplier', render: (row) => row.invoice?.supplier?.name || '-' },
      {
        key: 'amount',
        label: 'Amount (GHS)',
        render: (row) => row.amount != null ? 'GHS ' + Number(row.amount).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-'
      },
      { key: 'paid_at', label: 'Paid On' },
      {
        key: 'method',
        label: 'Method',
        render: (row) => {
          const m = row.method || 'other';
          const badgeClass = m === 'mobile_money' ? 'badge-ok' : m === 'bank_transfer' ? 'badge-info' : 'badge-warning';
          const label = m.replace('_', ' ').replace(/\b\w/g, (c: string) => c.toUpperCase());
          return `<span class="badge ${badgeClass}">${label}</span>`;
        }
      },
      { key: 'reference', label: 'Reference' }
    ],
    fields: [
      { key: 'invoice_id', label: 'Invoice', type: 'select', required: true, lookup: 'invoices' },
      { key: 'amount', label: 'Amount (GHS)', type: 'number', required: true },
      { key: 'paid_at', label: 'Payment date', type: 'date', required: true },
      { key: 'method', label: 'Method', type: 'select', options: [
        { value: 'bank_transfer', label: 'Bank transfer' }, { value: 'mobile_money', label: 'Mobile money' },
        { value: 'cash', label: 'Cash' }, { value: 'card', label: 'Card' }, { value: 'other', label: 'Other' }
      ]},
      { key: 'reference', label: 'Reference / Transaction ID', type: 'text' }
    ],
    defaults: { method: 'bank_transfer' },
    quickFilters: [
      { id: 'all', label: 'All Payments', filterFn: () => true },
      {
        id: 'bank',
        label: 'Bank Transfer',
        badgeClass: 'info',
        filterFn: (row) => (row.method || '').toLowerCase() === 'bank_transfer'
      },
      {
        id: 'momo',
        label: 'Mobile Money',
        badgeClass: 'ok',
        filterFn: (row) => (row.method || '').toLowerCase() === 'mobile_money'
      },
      {
        id: 'cash',
        label: 'Cash',
        badgeClass: 'warning',
        filterFn: (row) => (row.method || '').toLowerCase() === 'cash'
      },
      {
        id: 'card',
        label: 'Card & Other',
        filterFn: (row) => ['card', 'other'].includes((row.method || '').toLowerCase())
      },
      {
        id: 'high',
        label: 'High Value (≥ GHS 1,000)',
        badgeClass: 'danger',
        filterFn: (row) => Number(row.amount || 0) >= 1000
      }
    ],
    advancedFilters: [
      {
        key: 'method',
        label: 'Payment Method',
        type: 'select',
        placeholder: 'All Payment Methods',
        options: [
          { value: 'bank_transfer', label: 'Bank transfer' },
          { value: 'mobile_money', label: 'Mobile money' },
          { value: 'cash', label: 'Cash' },
          { value: 'card', label: 'Card' },
          { value: 'other', label: 'Other' }
        ],
        filterFn: (row, val) => !val || (row.method || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'min_amount',
        label: 'Min Amount (GHS)',
        type: 'number',
        placeholder: 'Min GHS',
        filterFn: (row, val) => val == null || val === '' || Number(row.amount || 0) >= Number(val)
      },
      {
        key: 'max_amount',
        label: 'Max Amount (GHS)',
        type: 'number',
        placeholder: 'Max GHS',
        filterFn: (row, val) => val == null || val === '' || Number(row.amount || 0) <= Number(val)
      },
      {
        key: 'date_from',
        label: 'From Date',
        type: 'date',
        filterFn: (row, val) => !val || !row.paid_at || row.paid_at >= val
      },
      {
        key: 'date_to',
        label: 'To Date',
        type: 'date',
        filterFn: (row, val) => !val || !row.paid_at || row.paid_at <= val
      }
    ],
    kpiDefs: [
      {
        label: 'Total Disbursed',
        compute: (rows, all) => {
          const total = rows.reduce((sum, r) => sum + Number(r.amount || 0), 0);
          return {
            label: 'Total Disbursed',
            value: `GHS ${total.toLocaleString('en-GH', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`,
            meta: `${rows.length} payment vouchers issued`,
            ok: true
          };
        }
      },
      {
        label: 'Payments Count',
        compute: (rows, all) => ({
          label: 'Payments Count',
          value: rows.length,
          meta: `Out of ${all.length} registered payments`,
          info: true
        })
      },
      {
        label: 'Average Disbursed',
        compute: (rows) => {
          const total = rows.reduce((sum, r) => sum + Number(r.amount || 0), 0);
          const avg = rows.length > 0 ? total / rows.length : 0;
          return {
            label: 'Average Disbursed',
            value: `GHS ${avg.toLocaleString('en-GH', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`,
            meta: 'Mean disbursement per payment'
          };
        }
      },
      {
        label: 'Dominant Channel',
        compute: (rows) => {
          const counts: Record<string, number> = {};
          rows.forEach((r) => {
            const m = r.method || 'Other';
            counts[m] = (counts[m] || 0) + 1;
          });
          const top = Object.entries(counts).sort((a, b) => b[1] - a[1])[0];
          const name = top ? top[0].replace('_', ' ').replace(/\b\w/g, (c: string) => c.toUpperCase()) : 'None';
          return {
            label: 'Dominant Channel',
            value: name,
            meta: top ? `${top[1]} transactions` : 'No records'
          };
        }
      }
    ]
  },
  companies: {
    title: 'CRM Companies',
    recordLabel: 'company',
    subtitle: 'Buyer accounts, lifecycle stage and contact details.',
    columns: [
      { key: 'name', label: 'Company' },
      { key: 'category', label: 'Buyer type' },
      { key: 'lifecycle_stage', label: 'Lifecycle' },
      { key: 'city', label: 'City' },
      { key: 'email', label: 'Email' },
      { key: 'phone', label: 'Phone' },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'name', label: 'Company name', type: 'text', required: true },
      { key: 'category', label: 'Buyer type', type: 'select', options: [
        { value: 'restaurants', label: 'Restaurants' }, { value: 'hotels', label: 'Hotels' },
        { value: 'supermarkets', label: 'Supermarkets' }, { value: 'butchers', label: 'Butchers' },
        { value: 'distributors', label: 'Distributors' }, { value: 'caterers', label: 'Caterers' }
      ]},
      { key: 'lifecycle_stage', label: 'Lifecycle stage', type: 'select', options: [
        { value: 'lead', label: 'Lead' }, { value: 'prospect', label: 'Prospect' },
        { value: 'customer', label: 'Customer' }, { value: 'subscriber', label: 'Subscriber' }
      ]},
      { key: 'city', label: 'City', type: 'text' },
      { key: 'phone', label: 'Phone', type: 'text' },
      { key: 'email', label: 'Email', type: 'email' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }
      ]}
    ],
    defaults: { lifecycle_stage: 'lead', status: 'active' },
    quickFilters: [
      { id: 'all', label: 'All companies', filterFn: () => true },
      { id: 'leads', label: 'Leads', badgeClass: 'info', filterFn: (row) => row.lifecycle_stage === 'lead' },
      { id: 'prospects', label: 'Prospects', badgeClass: 'warning', filterFn: (row) => row.lifecycle_stage === 'prospect' },
      { id: 'customers', label: 'Customers', badgeClass: 'ok', filterFn: (row) => row.lifecycle_stage === 'customer' },
      { id: 'inactive', label: 'Inactive', badgeClass: 'danger', filterFn: (row) => row.status === 'inactive' }
    ],
    advancedFilters: [
      { key: 'category', label: 'Buyer type', type: 'select', options: [
        { value: 'restaurants', label: 'Restaurants' }, { value: 'hotels', label: 'Hotels' },
        { value: 'supermarkets', label: 'Supermarkets' }, { value: 'butchers', label: 'Butchers' },
        { value: 'distributors', label: 'Distributors' }, { value: 'caterers', label: 'Caterers' }
      ]},
      { key: 'lifecycle_stage', label: 'Lifecycle stage', type: 'select', options: [
        { value: 'lead', label: 'Lead' }, { value: 'prospect', label: 'Prospect' },
        { value: 'customer', label: 'Customer' }, { value: 'subscriber', label: 'Subscriber' }
      ]},
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }
      ]},
      { key: 'city', label: 'City', type: 'text', placeholder: 'Filter by city', filterFn: (row, value) => String(row.city || '').toLowerCase().includes(String(value).toLowerCase()) }
    ],
    kpiDefs: [
      {
        label: 'Companies in view',
        compute: (rows) => ({ label: 'Companies in view', value: rows.length, meta: 'Accounts matching current filters', info: true })
      },
      {
        label: 'Active customers',
        compute: (rows, allRows) => {
          const count = allRows.filter((row) => row.lifecycle_stage === 'customer' && row.status !== 'inactive').length;
          return { label: 'Active customers', value: count, meta: 'Customer accounts in the directory', ok: true };
        }
      },
      {
        label: 'Leads & prospects',
        compute: (rows) => {
          const count = rows.filter((row) => ['lead', 'prospect'].includes(row.lifecycle_stage)).length;
          return { label: 'Leads & prospects', value: count, meta: 'Potential buyers in the filtered view', info: true };
        }
      }
    ]
  },
  contacts: {
    title: 'Contacts',
    recordLabel: 'contact',
    subtitle: 'People and buyer-side decision makers linked to company accounts.',
    columns: [
      { key: 'name', label: 'Contact' },
      { key: 'company', label: 'Company', render: (row) => row.company_name || row.company?.name || '-' },
      { key: 'role', label: 'Role' },
      { key: 'email', label: 'Email' },
      { key: 'phone', label: 'Phone' },
      { key: 'lifecycle_stage', label: 'Lifecycle' }
    ],
    fields: [
      { key: 'name', label: 'Contact name', type: 'text', required: true },
      { key: 'company_name', label: 'Company', type: 'text' },
      { key: 'role', label: 'Role / title', type: 'text' },
      { key: 'email', label: 'Email', type: 'email' },
      { key: 'phone', label: 'Phone', type: 'text' },
      { key: 'lifecycle_stage', label: 'Lifecycle stage', type: 'select', options: [
        { value: 'lead', label: 'Lead' }, { value: 'prospect', label: 'Prospect' },
        { value: 'customer', label: 'Customer' }, { value: 'subscriber', label: 'Subscriber' }
      ]}
    ],
    defaults: { lifecycle_stage: 'lead' },
    quickFilters: [
      { id: 'all', label: 'All contacts', filterFn: () => true },
      { id: 'leads', label: 'Leads', badgeClass: 'info', filterFn: (row) => row.lifecycle_stage === 'lead' },
      { id: 'prospects', label: 'Prospects', badgeClass: 'warning', filterFn: (row) => row.lifecycle_stage === 'prospect' },
      { id: 'customers', label: 'Customers', badgeClass: 'ok', filterFn: (row) => row.lifecycle_stage === 'customer' }
    ],
    advancedFilters: [
      { key: 'company_name', label: 'Company', type: 'text', filterFn: (row, value) =>
        String(row.company_name || row.company?.name || '').toLowerCase().includes(String(value).toLowerCase())
      },
      { key: 'lifecycle_stage', label: 'Lifecycle stage', type: 'select', options: [
        { value: 'lead', label: 'Lead' }, { value: 'prospect', label: 'Prospect' },
        { value: 'customer', label: 'Customer' }, { value: 'subscriber', label: 'Subscriber' }
      ]},
      { key: 'role', label: 'Role / title', type: 'text', placeholder: 'Filter by role', filterFn: (row, value) => String(row.role || '').toLowerCase().includes(String(value).toLowerCase()) }
    ],
    kpiDefs: [
      {
        label: 'Contacts in view',
        compute: (rows) => ({ label: 'Contacts in view', value: rows.length, meta: 'People matching current filters', info: true })
      },
      {
        label: 'Linked to a company',
        compute: (rows) => {
          const count = rows.filter((row) => !!row.company_id).length;
          return { label: 'Linked to a company', value: count, meta: 'Contacts with an account relationship', ok: true };
        }
      }
    ]
  },
  deals: {
    title: 'Deals & Pipeline',
    recordLabel: 'deal',
    subtitle: 'Manage buyer opportunities, pipeline stages and next actions.',
    columns: [
      { key: 'name', label: 'Deal' },
      { key: 'company', label: 'Company', render: (row) => row.company_name || row.company?.name || '-' },
      { key: 'contact', label: 'Contact', render: (row) => row.contact_name || row.contact?.name || '-' },
      { key: 'stage', label: 'Pipeline stage', render: (row) => row.stage?.name || '-' },
      { key: 'amount', label: 'Amount', render: (row) => row.amount === null || row.amount === undefined ? '-' : Number(row.amount).toLocaleString() },
      { key: 'close_date', label: 'Expected close' },
      { key: 'next_action', label: 'Next action' },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'name', label: 'Deal name', type: 'text', required: true },
      { key: 'company_name', label: 'Company', type: 'text' },
      { key: 'contact_name', label: 'Primary contact', type: 'text' },
      { key: 'pipeline_stage_id', label: 'Pipeline stage', type: 'select', lookup: 'pipeline_stages' },
      { key: 'amount', label: 'Deal value', type: 'number' },
      { key: 'close_date', label: 'Expected close date', type: 'date' },
      { key: 'next_action', label: 'Next action', type: 'text' },
      { key: 'next_action_date', label: 'Next action date', type: 'date' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'open', label: 'Open' }, { value: 'won', label: 'Won' }, { value: 'lost', label: 'Lost' }
      ]}
    ],
    defaults: { status: 'open' },
    quickFilters: [
      { id: 'all', label: 'All deals', filterFn: () => true },
      { id: 'open', label: 'Open', badgeClass: 'info', filterFn: (row) => row.status === 'open' },
      { id: 'won', label: 'Won', badgeClass: 'ok', filterFn: (row) => row.status === 'won' },
      { id: 'lost', label: 'Lost', badgeClass: 'danger', filterFn: (row) => row.status === 'lost' },
      { id: 'action_due', label: 'Follow-up overdue', badgeClass: 'warning', filterFn: (row) => row.status === 'open' && !!row.next_action_date && String(row.next_action_date).slice(0, 10) < new Date().toISOString().slice(0, 10) }
    ],
    advancedFilters: [
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'open', label: 'Open' }, { value: 'won', label: 'Won' }, { value: 'lost', label: 'Lost' }
      ]},
      { key: 'company_name', label: 'Company', type: 'text', filterFn: (row, value) =>
        String(row.company_name || row.company?.name || '').toLowerCase().includes(String(value).toLowerCase())
      },
      { key: 'contact_name', label: 'Primary contact', type: 'text', filterFn: (row, value) =>
        String(row.contact_name || row.contact?.name || '').toLowerCase().includes(String(value).toLowerCase())
      },
      { key: 'pipeline_stage_id', label: 'Pipeline stage', type: 'select', lookup: 'pipeline_stages' },
      { key: 'close_from', label: 'Close date from', type: 'date', filterFn: (row, value) => !!row.close_date && String(row.close_date).slice(0, 10) >= String(value) },
      { key: 'close_to', label: 'Close date to', type: 'date', filterFn: (row, value) => !!row.close_date && String(row.close_date).slice(0, 10) <= String(value) },
      { key: 'amount_min', label: 'Minimum value', type: 'number', filterFn: (row, value) => Number(row.amount || 0) >= Number(value) },
      { key: 'amount_max', label: 'Maximum value', type: 'number', filterFn: (row, value) => Number(row.amount || 0) <= Number(value) }
    ],
    kpiDefs: [
      {
        label: 'Open pipeline',
        compute: (rows) => {
          const deals = rows.filter((row) => row.status === 'open');
          const value = deals.reduce((sum, row) => sum + Number(row.amount || 0), 0);
          return { label: 'Open pipeline', value: value.toLocaleString(), meta: `${deals.length} open deal${deals.length === 1 ? '' : 's'} in view`, info: true };
        }
      },
      {
        label: 'Won value',
        compute: (rows) => {
          const won = rows.filter((row) => row.status === 'won');
          const value = won.reduce((sum, row) => sum + Number(row.amount || 0), 0);
          return { label: 'Won value', value: value.toLocaleString(), meta: `${won.length} closed-won deal${won.length === 1 ? '' : 's'}`, ok: true };
        }
      },
      {
        label: 'Follow-ups overdue',
        compute: (rows) => {
          const today = new Date().toISOString().slice(0, 10);
          const count = rows.filter((row) => row.status === 'open' && row.next_action_date && String(row.next_action_date).slice(0, 10) < today).length;
          return { label: 'Follow-ups overdue', value: count, meta: 'Open deal actions past due', alert: count > 0 };
        }
      }
    ]
  },
  sales: {
    title: 'Livestock Sales & Culls',
    recordLabel: 'sale record',
    subtitle: 'Track animals sold or culled, buyers and realized value.',
    columns: [
      { key: 'animal', label: 'Animal', render: (row) => row.animal?.animal_id || '-' },
      { key: 'type', label: 'Event type' },
      { key: 'event_date', label: 'Date' },
      { key: 'buyer', label: 'Buyer' },
      { key: 'amount', label: 'Amount', render: (row) => row.amount === null || row.amount === undefined ? '-' : Number(row.amount).toLocaleString() },
      { key: 'notes', label: 'Notes' }
    ],
    fields: [
      { key: 'animal_id', label: 'Animal', type: 'select', required: true, lookup: 'animals', lookupValue: 'id', lookupLabel: 'animal_id' },
      { key: 'type', label: 'Event type', type: 'select', required: true, options: [
        { value: 'sale', label: 'Sale' }, { value: 'cull', label: 'Cull' }
      ]},
      { key: 'event_date', label: 'Date', type: 'date', required: true },
      { key: 'buyer', label: 'Buyer', type: 'text' },
      { key: 'amount', label: 'Amount', type: 'number' },
      { key: 'notes', label: 'Notes', type: 'textarea' }
    ],
    defaults: { type: 'sale' },
    quickFilters: [
      { id: 'all', label: 'All events', filterFn: () => true },
      { id: 'sales', label: 'Sales', badgeClass: 'ok', filterFn: (row) => row.type === 'sale' },
      { id: 'culls', label: 'Culls', badgeClass: 'warning', filterFn: (row) => row.type === 'cull' }
    ],
    advancedFilters: [
      { key: 'type', label: 'Event type', type: 'select', options: [
        { value: 'sale', label: 'Sale' }, { value: 'cull', label: 'Cull' }
      ]},
      { key: 'animal_id', label: 'Animal', type: 'select', lookup: 'animals' },
      { key: 'event_from', label: 'Date from', type: 'date', filterFn: (row, value) => !!row.event_date && String(row.event_date).slice(0, 10) >= String(value) },
      { key: 'event_to', label: 'Date to', type: 'date', filterFn: (row, value) => !!row.event_date && String(row.event_date).slice(0, 10) <= String(value) },
      { key: 'buyer', label: 'Buyer', type: 'text', placeholder: 'Filter by buyer', filterFn: (row, value) => String(row.buyer || '').toLowerCase().includes(String(value).toLowerCase()) }
    ],
    kpiDefs: [
      {
        label: 'Sales value',
        compute: (rows) => {
          const sales = rows.filter((row) => row.type === 'sale');
          const value = sales.reduce((sum, row) => sum + Number(row.amount || 0), 0);
          return { label: 'Sales value', value: value.toLocaleString(), meta: `${sales.length} sale${sales.length === 1 ? '' : 's'} in view`, ok: true };
        }
      },
      {
        label: 'Culls',
        compute: (rows) => ({ label: 'Culls', value: rows.filter((row) => row.type === 'cull').length, meta: 'Cull records in view', alert: rows.some((row) => row.type === 'cull') })
      }
    ]
  },
  marketing: {
    title: 'Content Calendar',
    recordLabel: 'content item',
    subtitle: 'Plan, review and track approved content across channels.',
    columns: [
      { key: 'title', label: 'Content' },
      { key: 'channel', label: 'Channel' },
      { key: 'content_type', label: 'Format' },
      { key: 'scheduled_for', label: 'Scheduled' },
      { key: 'published_at', label: 'Published' },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'title', label: 'Title', type: 'text', required: true },
      { key: 'channel', label: 'Channel', type: 'select', options: [
        { value: 'Instagram', label: 'Instagram' }, { value: 'Facebook', label: 'Facebook' },
        { value: 'LinkedIn', label: 'LinkedIn' }, { value: 'Website', label: 'Website' },
        { value: 'WhatsApp', label: 'WhatsApp' }, { value: 'YouTube', label: 'YouTube' },
        { value: 'TikTok', label: 'TikTok' }, { value: 'Email', label: 'Email' }
      ]},
      { key: 'content_type', label: 'Format', type: 'select', options: [
        { value: 'post', label: 'Post' }, { value: 'carousel', label: 'Carousel' },
        { value: 'article', label: 'Article' }, { value: 'video', label: 'Video' },
        { value: 'story', label: 'Story' }, { value: 'email', label: 'Email' }
      ]},
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'draft', label: 'Draft' }, { value: 'review', label: 'In review' },
        { value: 'scheduled', label: 'Scheduled' }, { value: 'published', label: 'Published' }
      ]},
      { key: 'scheduled_for', label: 'Scheduled for', type: 'date' },
      { key: 'published_at', label: 'Published at', type: 'date' },
      { key: 'media_asset_id', label: 'Media asset', type: 'select', lookup: 'media_assets' },
      { key: 'body', label: 'Caption / copy', type: 'textarea' }
    ],
    defaults: { status: 'draft' },
    quickFilters: [
      { id: 'all', label: 'All content', filterFn: () => true },
      { id: 'draft', label: 'Draft', badgeClass: 'info', filterFn: (row) => row.status === 'draft' },
      { id: 'review', label: 'In review', badgeClass: 'warning', filterFn: (row) => row.status === 'review' },
      { id: 'scheduled', label: 'Scheduled', badgeClass: 'warning', filterFn: (row) => row.status === 'scheduled' },
      { id: 'published', label: 'Published', badgeClass: 'ok', filterFn: (row) => row.status === 'published' }
    ],
    advancedFilters: [
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'draft', label: 'Draft' }, { value: 'review', label: 'In review' },
        { value: 'scheduled', label: 'Scheduled' }, { value: 'published', label: 'Published' }
      ]},
      { key: 'channel', label: 'Channel', type: 'select', options: [
        { value: 'Instagram', label: 'Instagram' }, { value: 'Facebook', label: 'Facebook' },
        { value: 'LinkedIn', label: 'LinkedIn' }, { value: 'Website', label: 'Website' },
        { value: 'WhatsApp', label: 'WhatsApp' }, { value: 'YouTube', label: 'YouTube' },
        { value: 'TikTok', label: 'TikTok' }, { value: 'Email', label: 'Email' }
      ]},
      { key: 'content_type', label: 'Format', type: 'select', options: [
        { value: 'post', label: 'Post' }, { value: 'carousel', label: 'Carousel' },
        { value: 'article', label: 'Article' }, { value: 'video', label: 'Video' },
        { value: 'story', label: 'Story' }, { value: 'email', label: 'Email' }
      ]},
      { key: 'scheduled_from', label: 'Scheduled from', type: 'date', filterFn: (row, value) => !!row.scheduled_for && String(row.scheduled_for).slice(0, 10) >= String(value) },
      { key: 'scheduled_to', label: 'Scheduled to', type: 'date', filterFn: (row, value) => !!row.scheduled_for && String(row.scheduled_for).slice(0, 10) <= String(value) }
    ],
    kpiDefs: [
      {
        label: 'Content items',
        compute: (rows) => ({ label: 'Content items', value: rows.length, meta: 'Items in the filtered calendar', info: true })
      },
      {
        label: 'Scheduled',
        compute: (rows) => ({ label: 'Scheduled', value: rows.filter((row) => row.status === 'scheduled').length, meta: 'Queued to publish', info: true })
      },
      {
        label: 'Published',
        compute: (rows) => ({ label: 'Published', value: rows.filter((row) => row.status === 'published').length, meta: 'Published items in view', ok: true })
      }
    ]
  },
  media_assets: {
    title: 'Media Library',
    recordLabel: 'media asset',
    subtitle: 'Keep reusable photos, videos and other campaign media available for content items.',
    columns: [
      { key: 'title', label: 'Title' },
      { key: 'type', label: 'Type' },
      { key: 'subject', label: 'Subject' },
      { key: 'captured_at', label: 'Captured' },
      { key: 'rights', label: 'Rights' },
      { key: 'url', label: 'Media link' }
    ],
    fields: [
      { key: 'title', label: 'Title', type: 'text', required: true },
      { key: 'type', label: 'Type', type: 'select', options: [
        { value: 'photo', label: 'Photo' }, { value: 'video', label: 'Video' },
        { value: 'audio', label: 'Audio' }, { value: 'design', label: 'Design' },
        { value: 'document', label: 'Document' }, { value: 'other', label: 'Other' }
      ]},
      { key: 'subject', label: 'Subject', type: 'text' },
      { key: 'location', label: 'Location', type: 'text' },
      { key: 'url', label: 'Media URL or file path', type: 'text' },
      { key: 'captured_at', label: 'Captured on', type: 'date' },
      { key: 'rights', label: 'Usage rights', type: 'text' }
    ],
    quickFilters: [
      { id: 'all', label: 'All media', filterFn: () => true }
    ]
  },
  campaigns: {
    title: 'Campaigns',
    recordLabel: 'campaign',
    subtitle: 'Set campaign channels, dates, budgets and delivery status.',
    columns: [
      { key: 'name', label: 'Campaign' },
      { key: 'channel', label: 'Channel' },
      { key: 'start_date', label: 'Starts' },
      { key: 'end_date', label: 'Ends' },
      { key: 'budget', label: 'Budget', render: (row) => row.budget === null || row.budget === undefined ? '-' : Number(row.budget).toLocaleString() },
      { key: 'status', label: 'Status' }
    ],
    fields: [
      { key: 'name', label: 'Campaign name', type: 'text', required: true },
      { key: 'channel', label: 'Channel', type: 'select', options: [
        { value: 'Instagram', label: 'Instagram' }, { value: 'Facebook', label: 'Facebook' },
        { value: 'LinkedIn', label: 'LinkedIn' }, { value: 'Website', label: 'Website' },
        { value: 'WhatsApp', label: 'WhatsApp' }, { value: 'YouTube', label: 'YouTube' },
        { value: 'multi', label: 'Multi-channel' }, { value: 'Email', label: 'Email' }
      ]},
      { key: 'start_date', label: 'Start date', type: 'date' },
      { key: 'end_date', label: 'End date', type: 'date' },
      { key: 'budget', label: 'Budget', type: 'number' },
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'draft', label: 'Draft' }, { value: 'active', label: 'Active' },
        { value: 'paused', label: 'Paused' }, { value: 'completed', label: 'Completed' }
      ]}
    ],
    defaults: { status: 'draft' },
    quickFilters: [
      { id: 'all', label: 'All campaigns', filterFn: () => true },
      { id: 'active', label: 'Active', badgeClass: 'ok', filterFn: (row) => row.status === 'active' },
      { id: 'draft', label: 'Draft', badgeClass: 'info', filterFn: (row) => row.status === 'draft' },
      { id: 'paused', label: 'Paused', badgeClass: 'warning', filterFn: (row) => row.status === 'paused' },
      { id: 'completed', label: 'Completed', filterFn: (row) => row.status === 'completed' }
    ],
    advancedFilters: [
      { key: 'status', label: 'Status', type: 'select', options: [
        { value: 'draft', label: 'Draft' }, { value: 'active', label: 'Active' },
        { value: 'paused', label: 'Paused' }, { value: 'completed', label: 'Completed' }
      ]},
      { key: 'channel', label: 'Channel', type: 'select', options: [
        { value: 'Instagram', label: 'Instagram' }, { value: 'Facebook', label: 'Facebook' },
        { value: 'LinkedIn', label: 'LinkedIn' }, { value: 'Website', label: 'Website' },
        { value: 'WhatsApp', label: 'WhatsApp' }, { value: 'YouTube', label: 'YouTube' },
        { value: 'multi', label: 'Multi-channel' }, { value: 'Email', label: 'Email' }
      ]},
      { key: 'starts_from', label: 'Starts from', type: 'date', filterFn: (row, value) => !!row.start_date && String(row.start_date).slice(0, 10) >= String(value) },
      { key: 'starts_to', label: 'Starts by', type: 'date', filterFn: (row, value) => !!row.start_date && String(row.start_date).slice(0, 10) <= String(value) },
      { key: 'budget_min', label: 'Minimum budget', type: 'number', filterFn: (row, value) => Number(row.budget || 0) >= Number(value) },
      { key: 'budget_max', label: 'Maximum budget', type: 'number', filterFn: (row, value) => Number(row.budget || 0) <= Number(value) }
    ],
    kpiDefs: [
      {
        label: 'Campaigns in view',
        compute: (rows) => ({ label: 'Campaigns in view', value: rows.length, meta: 'Campaigns matching current filters', info: true })
      },
      {
        label: 'Active campaigns',
        compute: (rows) => ({ label: 'Active campaigns', value: rows.filter((row) => row.status === 'active').length, meta: 'Currently active', ok: true })
      },
      {
        label: 'Planned budget',
        compute: (rows) => {
          const total = rows.filter((row) => row.status !== 'completed').reduce((sum, row) => sum + Number(row.budget || 0), 0);
          return { label: 'Planned budget', value: total.toLocaleString(), meta: 'Budget across active and planned campaigns', info: true };
        }
      }
    ]
  },
  channel_metrics: {
    title: 'Channel Performance',
    recordLabel: 'channel metric',
    subtitle: 'Record reach, engagement and leads by digital channel and date.',
    columns: [
      { key: 'channel', label: 'Channel' },
      { key: 'metric_date', label: 'Metric date' },
      { key: 'reach', label: 'Reach' },
      { key: 'views', label: 'Views' },
      { key: 'followers', label: 'Followers' },
      { key: 'website_visits', label: 'Website visits' },
      { key: 'leads', label: 'Leads' },
      { key: 'engagement_rate', label: 'Engagement %' }
    ],
    fields: [
      { key: 'channel', label: 'Channel', type: 'select', required: true, options: [
        { value: 'Instagram', label: 'Instagram' }, { value: 'Facebook', label: 'Facebook' },
        { value: 'LinkedIn', label: 'LinkedIn' }, { value: 'Website', label: 'Website' },
        { value: 'WhatsApp', label: 'WhatsApp' }, { value: 'YouTube', label: 'YouTube' },
        { value: 'TikTok', label: 'TikTok' }, { value: 'Email', label: 'Email' }
      ]},
      { key: 'metric_date', label: 'Metric date', type: 'date', required: true },
      { key: 'reach', label: 'Reach', type: 'number' },
      { key: 'views', label: 'Views', type: 'number' },
      { key: 'followers', label: 'Followers', type: 'number' },
      { key: 'website_visits', label: 'Website visits', type: 'number' },
      { key: 'leads', label: 'Leads', type: 'number' },
      { key: 'engagement_rate', label: 'Engagement rate (%)', type: 'number' }
    ],
    defaults: { reach: 0, views: 0, followers: 0, website_visits: 0, leads: 0, engagement_rate: 0 },
    quickFilters: [
      { id: 'all', label: 'All metrics', filterFn: () => true },
      { id: 'instagram', label: 'Instagram', badgeClass: 'info', filterFn: (row) => row.channel === 'Instagram' },
      { id: 'website', label: 'Website', badgeClass: 'ok', filterFn: (row) => row.channel === 'Website' },
      { id: 'whatsapp', label: 'WhatsApp', badgeClass: 'warning', filterFn: (row) => row.channel === 'WhatsApp' }
    ],
    advancedFilters: [
      { key: 'channel', label: 'Channel', type: 'select', options: [
        { value: 'Instagram', label: 'Instagram' }, { value: 'Facebook', label: 'Facebook' },
        { value: 'LinkedIn', label: 'LinkedIn' }, { value: 'Website', label: 'Website' },
        { value: 'WhatsApp', label: 'WhatsApp' }, { value: 'YouTube', label: 'YouTube' },
        { value: 'TikTok', label: 'TikTok' }, { value: 'Email', label: 'Email' }
      ]},
      { key: 'metric_from', label: 'Date from', type: 'date', filterFn: (row, value) => !!row.metric_date && String(row.metric_date).slice(0, 10) >= String(value) },
      { key: 'metric_to', label: 'Date to', type: 'date', filterFn: (row, value) => !!row.metric_date && String(row.metric_date).slice(0, 10) <= String(value) },
      { key: 'minimum_leads', label: 'Minimum leads', type: 'number', filterFn: (row, value) => Number(row.leads || 0) >= Number(value) }
    ],
    kpiDefs: [
      {
        label: 'Total reach',
        compute: (rows) => ({ label: 'Total reach', value: rows.reduce((sum, row) => sum + Number(row.reach || 0), 0).toLocaleString(), meta: 'Sum across filtered metric rows', info: true })
      },
      {
        label: 'Leads',
        compute: (rows) => ({ label: 'Leads', value: rows.reduce((sum, row) => sum + Number(row.leads || 0), 0).toLocaleString(), meta: 'Leads captured in view', ok: true })
      },
      {
        label: 'Average engagement',
        compute: (rows) => {
          const average = rows.length ? rows.reduce((sum, row) => sum + Number(row.engagement_rate || 0), 0) / rows.length : 0;
          return { label: 'Average engagement', value: average.toFixed(2), unit: '%', meta: 'Across filtered metric rows', info: true };
        }
      }
    ]
  },
  risks: {
    title: 'Risk Register',
    subtitle: 'Operational, biosecurity, financial and asset risk tracking with mitigation protocols.',
    columns: [
      {
        key: 'title',
        label: 'Risk & Mitigation Strategy',
        render: (row) => `<strong>${row.title}</strong>${row.mitigation ? `<div class="sub-text"><span style="color:var(--warn-text);font-weight:600;">Mitigation:</span> ${row.mitigation}</div>` : ''}`
      },
      {
        key: 'category',
        label: 'Category',
        render: (row) => `<span class="badge category-pill">${(row.category || 'General').toUpperCase()}</span>`
      },
      {
        key: 'severity',
        label: 'Severity',
        render: (row) => {
          const s = (row.severity || 'medium').toLowerCase();
          const cls = s === 'critical' ? 'badge-danger' : s === 'high' ? 'badge-warning' : s === 'medium' ? 'badge-info' : 'badge-ok';
          return `<span class="badge ${cls}">${s.toUpperCase()}</span>`;
        }
      },
      {
        key: 'likelihood',
        label: 'Likelihood',
        render: (row) => {
          const l = (row.likelihood || 'possible').toLowerCase();
          const cls = l === 'likely' ? 'badge-danger' : l === 'possible' ? 'badge-warning' : 'badge-ok';
          return `<span class="badge ${cls}">${l.charAt(0).toUpperCase() + l.slice(1)}</span>`;
        }
      },
      {
        key: 'owner',
        label: 'Risk Owner',
        render: (row) => row.owner?.name ? `<span style="font-weight:600;color:var(--text-strong);">${row.owner.name}</span>${row.owner.role ? `<div class="sub-text">${row.owner.role.replace('_', ' ')}</div>` : ''}` : '<span class="sub-text">Unassigned</span>'
      },
      {
        key: 'identified_at',
        label: 'Identified Date',
        render: (row) => row.identified_at ? row.identified_at.slice(0, 10) : '-'
      },
      {
        key: 'status',
        label: 'Status',
        render: (row) => {
          const st = (row.status || 'open').toLowerCase();
          const cls = st === 'open' ? 'badge-danger' : st === 'mitigating' ? 'badge-warning' : 'badge-ok';
          const lbl = st === 'open' ? 'Open Threat' : st === 'mitigating' ? 'Mitigating' : 'Controlled';
          return `<span class="badge ${cls}">${lbl}</span>`;
        }
      }
    ],
    fields: [
      { key: 'title', label: 'Risk Title', type: 'text', required: true, placeholder: 'e.g. Water pump failure during dry spell' },
      {
        key: 'category',
        label: 'Category',
        type: 'select',
        required: true,
        options: [
          { value: 'health', label: 'Animal Health & Biosecurity' },
          { value: 'feed', label: 'Feed & Nutrition Supply' },
          { value: 'assets', label: 'Machinery & Farm Assets' },
          { value: 'infrastructure', label: 'Water & Facilities' },
          { value: 'financial', label: 'Financial & Cash Flow' },
          { value: 'weather', label: 'Weather & Climate' },
          { value: 'compliance', label: 'Regulatory & Compliance' },
          { value: 'operational', label: 'General Operational' }
        ]
      },
      {
        key: 'severity',
        label: 'Severity Level',
        type: 'select',
        required: true,
        options: [
          { value: 'critical', label: 'Critical (Severe business or herd impact)' },
          { value: 'high', label: 'High (Significant interruption or cost)' },
          { value: 'medium', label: 'Medium (Moderate disruption)' },
          { value: 'low', label: 'Low (Minor operational variance)' }
        ]
      },
      {
        key: 'likelihood',
        label: 'Likelihood',
        type: 'select',
        required: true,
        options: [
          { value: 'likely', label: 'Likely (High probability of occurrence)' },
          { value: 'possible', label: 'Possible (May occur occasionally)' },
          { value: 'unlikely', label: 'Unlikely (Low probability)' }
        ]
      },
      { key: 'owner_id', label: 'Risk Owner', type: 'select', lookup: 'users' },
      {
        key: 'status',
        label: 'Control Status',
        type: 'select',
        required: true,
        options: [
          { value: 'open', label: 'Open (Active uncontrolled threat)' },
          { value: 'mitigating', label: 'Mitigating (Mitigation in progress)' },
          { value: 'closed', label: 'Closed (Threat resolved or contained)' }
        ]
      },
      { key: 'mitigation', label: 'Mitigation Plan & Action Protocol', type: 'textarea', placeholder: 'Detail immediate actions, preventative measures, and responsibilities' },
      { key: 'identified_at', label: 'Date Identified', type: 'date' }
    ],
    defaults: { status: 'open', severity: 'medium', likelihood: 'possible', category: 'health' },
    quickFilters: [
      { id: 'all', label: 'All Risks', filterFn: () => true },
      {
        id: 'critical',
        label: 'Critical & High',
        badgeClass: 'danger',
        filterFn: (r) => ['critical', 'high'].includes((r.severity || '').toLowerCase()) && (r.status || '').toLowerCase() !== 'closed'
      },
      {
        id: 'open',
        label: 'Active Threats (Open)',
        badgeClass: 'warning',
        filterFn: (r) => (r.status || '').toLowerCase() === 'open'
      },
      {
        id: 'mitigating',
        label: 'In Mitigation',
        badgeClass: 'info',
        filterFn: (r) => (r.status || '').toLowerCase() === 'mitigating'
      },
      {
        id: 'health',
        label: 'Health & Biosecurity',
        filterFn: (r) => ['health', 'biosecurity', 'disease'].some(k => (r.category || '').toLowerCase().includes(k))
      },
      {
        id: 'assets',
        label: 'Assets & Facilities',
        filterFn: (r) => ['assets', 'machinery', 'infrastructure', 'equipment'].some(k => (r.category || '').toLowerCase().includes(k))
      },
      {
        id: 'closed',
        label: 'Controlled / Resolved',
        badgeClass: 'ok',
        filterFn: (r) => (r.status || '').toLowerCase() === 'closed'
      }
    ],
    advancedFilters: [
      {
        key: 'category',
        label: 'Risk Category',
        type: 'select',
        placeholder: 'All Categories',
        options: [
          { value: 'health', label: 'Animal Health & Biosecurity' },
          { value: 'feed', label: 'Feed & Nutrition Supply' },
          { value: 'assets', label: 'Machinery & Farm Assets' },
          { value: 'infrastructure', label: 'Water & Facilities' },
          { value: 'financial', label: 'Financial & Cash Flow' },
          { value: 'weather', label: 'Weather & Climate' },
          { value: 'compliance', label: 'Regulatory & Compliance' },
          { value: 'operational', label: 'General Operational' }
        ],
        filterFn: (row, val) => !val || (row.category || '').toLowerCase().includes(val.toLowerCase())
      },
      {
        key: 'severity',
        label: 'Severity Level',
        type: 'select',
        placeholder: 'All Severities',
        options: [
          { value: 'critical', label: 'Critical' },
          { value: 'high', label: 'High' },
          { value: 'medium', label: 'Medium' },
          { value: 'low', label: 'Low' }
        ],
        filterFn: (row, val) => !val || (row.severity || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'likelihood',
        label: 'Likelihood',
        type: 'select',
        placeholder: 'All Likelihoods',
        options: [
          { value: 'likely', label: 'Likely' },
          { value: 'possible', label: 'Possible' },
          { value: 'unlikely', label: 'Unlikely' }
        ],
        filterFn: (row, val) => !val || (row.likelihood || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'status',
        label: 'Control Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'open', label: 'Open' },
          { value: 'mitigating', label: 'Mitigating' },
          { value: 'closed', label: 'Closed' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'owner_id',
        label: 'Assigned Owner',
        type: 'select',
        placeholder: 'All Owners',
        lookup: 'users',
        filterFn: (row, val) => !val || String(row.owner_id) === String(val)
      },
      {
        key: 'date_from',
        label: 'Identified After',
        type: 'date',
        filterFn: (row, val) => !val || !row.identified_at || row.identified_at.slice(0, 10) >= val
      },
      {
        key: 'date_to',
        label: 'Identified Before',
        type: 'date',
        filterFn: (row, val) => !val || !row.identified_at || row.identified_at.slice(0, 10) <= val
      }
    ],
    kpiDefs: [
      {
        label: 'Total Risks Logged',
        compute: (rows, all) => ({
          label: 'Total Risks Logged',
          value: rows.length,
          meta: `${all.length} registered governance items`,
          ok: true
        })
      },
      {
        label: 'Active Threats',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() !== 'closed').length;
          return {
            label: 'Active Threats',
            value: count,
            meta: 'Requiring active management',
            alert: count > 0
          };
        }
      },
      {
        label: 'Critical & High Exposure',
        compute: (rows) => {
          const count = rows.filter(r => ['critical', 'high'].includes((r.severity || '').toLowerCase()) && (r.status || '').toLowerCase() !== 'closed').length;
          return {
            label: 'Critical & High Exposure',
            value: count,
            meta: 'Immediate priority mitigations',
            alert: count > 0
          };
        }
      },
      {
        label: 'Controlled & Resolved',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'closed').length;
          return {
            label: 'Controlled & Resolved',
            value: count,
            meta: 'Risks mitigated to safe level',
            ok: true
          };
        }
      }
    ]
  },
  decisions: {
    title: 'Owner & Executive Decisions',
    subtitle: 'Operational signoffs, capital expenditure authorizations and governance approvals.',
    columns: [
      {
        key: 'title',
        label: 'Decision & Context',
        render: (row) => `<strong>${row.title}</strong>${row.description ? `<div class="sub-text">${row.description}</div>` : ''}`
      },
      {
        key: 'requested_by',
        label: 'Requested By',
        render: (row) => row.requester?.name ? `<strong>${row.requester.name}</strong>${row.requester.department ? `<div class="sub-text">${row.requester.department}</div>` : ''}` : '<span class="sub-text">Staff Member</span>'
      },
      {
        key: 'owner',
        label: 'Decision Authority',
        render: (row) => row.owner?.name ? `<span style="font-weight:600;color:var(--text-strong);">${row.owner.name}</span><div class="sub-text">Ownership / Exec</div>` : '<span class="sub-text">Unassigned Owner</span>'
      },
      {
        key: 'due_date',
        label: 'Due Date',
        render: (row) => {
          if (!row.due_date) return '<span class="sub-text">No deadline</span>';
          const due = row.due_date.slice(0, 10);
          const isOverdue = due < new Date().toISOString().slice(0, 10) && (row.status || '').toLowerCase() === 'pending_owner';
          return isOverdue ? `<span class="badge badge-danger">Overdue: ${due}</span>` : `<span>${due}</span>`;
        }
      },
      {
        key: 'status',
        label: 'Status',
        render: (row) => {
          const s = (row.status || 'pending_owner').toLowerCase();
          const cls = s === 'approved' ? 'badge-ok' : s === 'rejected' ? 'badge-danger' : s === 'pending_owner' ? 'badge-warning' : 'badge-info';
          const label = s === 'pending_owner' ? 'Pending Owner' : s === 'approved' ? 'Approved' : s === 'rejected' ? 'Rejected' : s.replace('_', ' ');
          return `<span class="badge ${cls}">${label}</span>`;
        }
      },
      {
        key: 'decision_notes',
        label: 'Decision Notes / Outcome',
        render: (row) => row.decision_notes ? `<span class="sub-text" style="color:var(--text-strong);">${row.decision_notes}</span>` : '<span class="sub-text">Pending outcome</span>'
      }
    ],
    fields: [
      { key: 'title', label: 'Decision Title', type: 'text', required: true, placeholder: 'e.g. Approve emergency feed supplier contract' },
      { key: 'description', label: 'Context & Justification', type: 'textarea', placeholder: 'Explain why ownership or executive signoff is required, budget impact, or alternatives' },
      {
        key: 'status',
        label: 'Decision Status',
        type: 'select',
        required: true,
        options: [
          { value: 'pending_owner', label: 'Pending Owner (Awaiting Executive Signoff)' },
          { value: 'approved', label: 'Approved (Sanctioned to proceed)' },
          { value: 'rejected', label: 'Rejected (Denied or deferred)' },
          { value: 'under_review', label: 'Under Review (More info requested)' }
        ]
      },
      { key: 'requested_by', label: 'Requested By', type: 'select', lookup: 'users' },
      { key: 'owner_id', label: 'Decision Authority (Owner / CEO)', type: 'select', lookup: 'users' },
      { key: 'due_date', label: 'Target Decision Date', type: 'date' },
      { key: 'decision_notes', label: 'Decision Notes & Rationale', type: 'textarea', placeholder: 'Record condition of approval or reasons for rejection' }
    ],
    defaults: { status: 'pending_owner' },
    quickFilters: [
      { id: 'all', label: 'All Decisions', filterFn: () => true },
      {
        id: 'pending',
        label: 'Pending Owner',
        badgeClass: 'warning',
        filterFn: (r) => (r.status || '').toLowerCase() === 'pending_owner'
      },
      {
        id: 'overdue',
        label: 'Overdue Signoffs',
        badgeClass: 'danger',
        filterFn: (r) => {
          const due = (r.due_date || '').slice(0, 10);
          return Boolean(due && due < new Date().toISOString().slice(0, 10) && (r.status || '').toLowerCase() === 'pending_owner');
        }
      },
      {
        id: 'approved',
        label: 'Approved Decisions',
        badgeClass: 'ok',
        filterFn: (r) => (r.status || '').toLowerCase() === 'approved'
      },
      {
        id: 'rejected',
        label: 'Rejected / Denied',
        badgeClass: 'danger',
        filterFn: (r) => (r.status || '').toLowerCase() === 'rejected'
      }
    ],
    advancedFilters: [
      {
        key: 'status',
        label: 'Decision Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'pending_owner', label: 'Pending Owner' },
          { value: 'approved', label: 'Approved' },
          { value: 'rejected', label: 'Rejected' },
          { value: 'under_review', label: 'Under Review' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'owner_id',
        label: 'Decision Authority',
        type: 'select',
        placeholder: 'All Decision Makers',
        lookup: 'users',
        filterFn: (row, val) => !val || String(row.owner_id) === String(val)
      },
      {
        key: 'requested_by',
        label: 'Requested By',
        type: 'select',
        placeholder: 'All Requesters',
        lookup: 'users',
        filterFn: (row, val) => !val || String(row.requested_by) === String(val)
      },
      {
        key: 'due_before',
        label: 'Due Before',
        type: 'date',
        filterFn: (row, val) => !val || !row.due_date || row.due_date.slice(0, 10) <= val
      },
      {
        key: 'due_after',
        label: 'Due After',
        type: 'date',
        filterFn: (row, val) => !val || !row.due_date || row.due_date.slice(0, 10) >= val
      }
    ],
    kpiDefs: [
      {
        label: 'Total Decisions',
        compute: (rows, all) => ({
          label: 'Total Decisions',
          value: rows.length,
          meta: `${all.length} registered governance items`,
          ok: true
        })
      },
      {
        label: 'Pending Owner',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'pending_owner').length;
          return {
            label: 'Pending Owner',
            value: count,
            meta: 'Awaiting ownership signoff',
            alert: count > 0
          };
        }
      },
      {
        label: 'Overdue Signoffs',
        compute: (rows) => {
          const today = new Date().toISOString().slice(0, 10);
          const count = rows.filter(r => {
            const due = (r.due_date || '').slice(0, 10);
            return due && due < today && (r.status || '').toLowerCase() === 'pending_owner';
          }).length;
          return {
            label: 'Overdue Signoffs',
            value: count,
            meta: 'Exceeded target approval deadline',
            alert: count > 0
          };
        }
      },
      {
        label: 'Approved Decisions',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'approved').length;
          return {
            label: 'Approved Decisions',
            value: count,
            meta: 'Sanctioned & executed mandates',
            ok: true
          };
        }
      }
    ]
  },
  sops: {
    title: 'Standard Operating Procedures',
    subtitle: 'Official protocols, operational standards and compliance documentation.',
    columns: [
      {
        key: 'code',
        label: 'SOP Code',
        render: (row) => `<span class="badge sop-code">${row.code}</span>`
      },
      {
        key: 'title',
        label: 'Procedure Title & Summary',
        render: (row) => `<strong>${row.title}</strong>${row.summary ? `<div class="sub-text">${row.summary}</div>` : ''}`
      },
      {
        key: 'category',
        label: 'Department / Scope',
        render: (row) => `<span class="badge category-pill">${row.category || 'General'}</span>`
      },
      {
        key: 'version',
        label: 'Version',
        render: (row) => `<span style="font-weight:600;color:var(--text-strong);">v${row.version || '1.0'}</span>`
      },
      {
        key: 'status',
        label: 'Status',
        render: (row) => {
          const s = (row.status || 'published').toLowerCase();
          const cls = s === 'published' ? 'badge-ok' : s === 'draft' ? 'badge-warning' : s === 'review' ? 'badge-info' : 'badge-secondary';
          return `<span class="badge ${cls}">${s.charAt(0).toUpperCase() + s.slice(1)}</span>`;
        }
      },
      {
        key: 'drive_url',
        label: 'Reference Link',
        render: (row) => row.drive_url ? `<a href="${row.drive_url}" target="_blank" rel="noopener noreferrer" class="link-doc">View Document &nearr;</a>` : '<span class="sub-text">In-Hub SOP</span>'
      }
    ],
    fields: [
      { key: 'code', label: 'SOP Code', type: 'text', required: true, placeholder: 'e.g. SOP-LS-004' },
      { key: 'title', label: 'Procedure Title', type: 'text', required: true, placeholder: 'e.g. Routine vaccination and biosecurity protocol' },
      {
        key: 'category',
        label: 'Operational Domain',
        type: 'select',
        required: true,
        options: [
          { value: 'Livestock', label: 'Livestock Care & Tagging' },
          { value: 'Health', label: 'Animal Health & Disease Isolation' },
          { value: 'Breeding', label: 'Breeding & Pregnancy Management' },
          { value: 'Feed', label: 'Feed & Nutrition Storage & Issuing' },
          { value: 'Procurement', label: 'Procurement & Purchase Approvals' },
          { value: 'Inventory', label: 'Inventory & Goods Receiving' },
          { value: 'Assets', label: 'Asset Maintenance & Equipment' },
          { value: 'HR', label: 'HR, Attendance & Labor Safety' },
          { value: 'Marketing', label: 'Commercial & Marketing Review' },
          { value: 'Finance', label: 'Financial Control & Accounting' },
          { value: 'General', label: 'General Farm Governance' }
        ]
      },
      { key: 'version', label: 'Version Number', type: 'text', required: true, placeholder: 'e.g. 1.0, 1.1' },
      {
        key: 'status',
        label: 'Lifecycle Status',
        type: 'select',
        required: true,
        options: [
          { value: 'published', label: 'Published (Active controlled standard)' },
          { value: 'draft', label: 'Draft (In preparation)' },
          { value: 'review', label: 'In Review (Pending review)' },
          { value: 'archived', label: 'Archived (Superseded or deprecated)' }
        ]
      },
      { key: 'summary', label: 'Procedure Summary & Scope', type: 'textarea', placeholder: 'Brief summary of the SOP objectives, trigger events, and steps' },
      { key: 'drive_url', label: 'Cloud Document URL', type: 'text', placeholder: 'https://drive.google.com/...' }
    ],
    defaults: { version: '1.0', status: 'published', category: 'Livestock' },
    quickFilters: [
      { id: 'all', label: 'All Procedures', filterFn: () => true },
      {
        id: 'published',
        label: 'Published & Active',
        badgeClass: 'ok',
        filterFn: (r) => (r.status || '').toLowerCase() === 'published'
      },
      {
        id: 'drafts',
        label: 'Drafts & Revisions',
        badgeClass: 'warning',
        filterFn: (r) => ['draft', 'review'].includes((r.status || '').toLowerCase())
      },
      {
        id: 'livestock',
        label: 'Herd & Animal Health',
        filterFn: (r) => ['livestock', 'health', 'breeding'].includes((r.category || '').toLowerCase())
      },
      {
        id: 'supply',
        label: 'Supply & Facilities',
        filterFn: (r) => ['feed', 'procurement', 'inventory', 'assets'].includes((r.category || '').toLowerCase())
      },
      {
        id: 'people_ops',
        label: 'People & Operations',
        filterFn: (r) => ['hr', 'marketing', 'finance', 'general'].includes((r.category || '').toLowerCase())
      }
    ],
    advancedFilters: [
      {
        key: 'category',
        label: 'Department / Scope',
        type: 'select',
        placeholder: 'All Domains',
        options: [
          { value: 'Livestock', label: 'Livestock' },
          { value: 'Health', label: 'Animal Health' },
          { value: 'Breeding', label: 'Breeding' },
          { value: 'Feed', label: 'Feed' },
          { value: 'Procurement', label: 'Procurement' },
          { value: 'Inventory', label: 'Inventory' },
          { value: 'Assets', label: 'Assets & Facilities' },
          { value: 'HR', label: 'Human Resources' },
          { value: 'Marketing', label: 'Marketing' },
          { value: 'Finance', label: 'Finance' },
          { value: 'General', label: 'General' }
        ],
        filterFn: (row, val) => !val || (row.category || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'status',
        label: 'Lifecycle Status',
        type: 'select',
        placeholder: 'All Statuses',
        options: [
          { value: 'published', label: 'Published' },
          { value: 'draft', label: 'Draft' },
          { value: 'review', label: 'In Review' },
          { value: 'archived', label: 'Archived' }
        ],
        filterFn: (row, val) => !val || (row.status || '').toLowerCase() === val.toLowerCase()
      },
      {
        key: 'version',
        label: 'Version',
        type: 'text',
        placeholder: 'e.g. 1.0',
        filterFn: (row, val) => !val || (row.version || '').toLowerCase().includes(val.toLowerCase())
      }
    ],
    kpiDefs: [
      {
        label: 'Total SOP Library',
        compute: (rows, all) => ({
          label: 'Total SOP Library',
          value: rows.length,
          meta: `${all.length} registered operating procedures`,
          ok: true
        })
      },
      {
        label: 'Published & Enforced',
        compute: (rows) => {
          const count = rows.filter(r => (r.status || '').toLowerCase() === 'published').length;
          return {
            label: 'Published & Enforced',
            value: count,
            meta: 'Active standard protocols',
            ok: true
          };
        }
      },
      {
        label: 'Operational Domains',
        compute: (rows) => {
          const categories = new Set(rows.map(r => (r.category || '').toLowerCase()).filter(Boolean));
          return {
            label: 'Operational Domains',
            value: categories.size,
            meta: 'Core processes covered',
            info: true
          };
        }
      },
      {
        label: 'Pending Revisions',
        compute: (rows) => {
          const count = rows.filter(r => ['draft', 'review'].includes((r.status || '').toLowerCase())).length;
          return {
            label: 'Pending Revisions',
            value: count,
            meta: 'Drafts or under review',
            alert: count > 0
          };
        }
      }
    ]
  }
};
