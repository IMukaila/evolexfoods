export interface FinanceTotals {
  expenses: number;
  payments: number;
  outflow: number;
  open_payables: number;
  overdue_payables: number;
  overdue_invoice_count: number;
  due_7_days: number;
  due_7_days_count: number;
  open_invoice_count: number;
}

export interface FinanceCategory {
  category: string;
  amount: number;
}

export interface FinanceMonth {
  month: string;
  label: string;
  expenses: number;
  payments: number;
}

export interface InvoiceAging {
  current: number;
  days_1_30: number;
  days_31_60: number;
  days_61_plus: number;
}

export interface UpcomingInvoice {
  id: number;
  invoice_number: string;
  supplier: string;
  balance: number;
  due_date: string;
  status: string;
}

export interface FinanceOverview {
  generated_at: string;
  from: string;
  to: string;
  currency: string;
  totals: FinanceTotals;
  expense_categories: FinanceCategory[];
  monthly_activity: FinanceMonth[];
  invoice_aging: InvoiceAging;
  upcoming_invoices: UpcomingInvoice[];
  source: string;
}

export interface SupplierLookup {
  id: number;
  name: string;
  code?: string;
}

export interface FinanceInvoice {
  id: number;
  invoice_number: string;
  supplier_id: number | null;
  supplier?: {
    id: number;
    name: string;
    code?: string;
    contact_name?: string | null;
    phone?: string | null;
    email?: string | null;
    location?: string | null;
  } | null;
  amount: number | string;
  invoice_date: string;
  due_date: string | null;
  status: 'unpaid' | 'partial' | 'paid' | string;
  payments?: FinancePayment[];
}

export interface FinancePayment {
  id: number;
  invoice_id: number;
  amount: number | string;
  paid_at: string;
  method?: string | null;
  reference?: string | null;
  invoice?: {
    id: number;
    invoice_number: string;
    supplier?: { name: string } | null;
  } | null;
}

export interface FinanceExpense {
  id: number;
  category: string;
  description?: string | null;
  amount: number | string;
  expense_date: string;
  source?: string | null;
}
