<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Services\CashActivityReportPdfService;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FinanceController extends Controller
{
    /**
     * Finance workspace summary. Expenses and invoice payments are kept as
     * separate measures so a bill is not counted as cash outflow until paid.
     */
    public function overview(Request $request): JsonResponse
    {
        [$from, $to] = DateRange::fromRequest($request, 30);
        $today = now()->startOfDay();

        $expenses = Expense::whereBetween('expense_date', [$from, $to])
            ->get(['id', 'category', 'description', 'amount', 'expense_date', 'source']);
        $payments = Payment::with(['invoice.supplier'])
            ->whereBetween('paid_at', [$from, $to])
            ->get();
        $openInvoices = Invoice::with(['supplier', 'payments'])
            ->whereIn('status', ['unpaid', 'partial'])
            ->get();

        $balanceFor = static fn (Invoice $invoice): float => max(
            0,
            (float) $invoice->amount - (float) $invoice->payments->sum('amount')
        );

        $expenseTotal = (float) $expenses->sum('amount');
        $paymentTotal = (float) $payments->sum('amount');
        $openPayables = (float) $openInvoices->sum($balanceFor);
        $overdueInvoices = $openInvoices->filter(
            fn (Invoice $invoice): bool => $invoice->due_date !== null
                && $invoice->due_date->lt($today)
                && $balanceFor($invoice) > 0
        );
        $dueSoonInvoices = $openInvoices->filter(
            fn (Invoice $invoice): bool => $invoice->due_date !== null
                && $invoice->due_date->gte($today)
                && $invoice->due_date->lte($today->copy()->addDays(7))
                && $balanceFor($invoice) > 0
        );

        $aging = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_plus' => 0.0,
        ];
        foreach ($openInvoices as $invoice) {
            $balance = $balanceFor($invoice);
            if ($balance <= 0) {
                continue;
            }

            if ($invoice->due_date === null || $invoice->due_date->gte($today)) {
                $aging['current'] += $balance;
                continue;
            }

            $daysOverdue = (int) $invoice->due_date->diffInDays($today, true);
            $bucket = match (true) {
                $daysOverdue <= 30 => 'days_1_30',
                $daysOverdue <= 60 => 'days_31_60',
                default => 'days_61_plus',
            };
            $aging[$bucket] += $balance;
        }

        $categoryBreakdown = $expenses
            ->groupBy(fn (Expense $expense): string => $expense->category ?: 'Uncategorised')
            ->map(fn ($rows, string $category): array => [
                'category' => $category,
                'amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->take(6)
            ->values();

        $trendFrom = now()->startOfMonth()->subMonths(5);
        $trendTo = now()->endOfMonth();
        $monthlyExpenses = Expense::whereBetween('expense_date', [$trendFrom, $trendTo])->get(['amount', 'expense_date']);
        $monthlyPayments = Payment::whereBetween('paid_at', [$trendFrom, $trendTo])->get(['amount', 'paid_at']);
        $monthlyActivity = [];

        for ($offset = 5; $offset >= 0; $offset--) {
            $month = now()->startOfMonth()->subMonths($offset);
            $key = $month->format('Y-m');
            $monthlyActivity[] = [
                'month' => $key,
                'label' => $month->format('M'),
                'expenses' => round((float) $monthlyExpenses
                    ->filter(fn (Expense $expense): bool => $expense->expense_date->format('Y-m') === $key)
                    ->sum('amount'), 2),
                'payments' => round((float) $monthlyPayments
                    ->filter(fn (Payment $payment): bool => $payment->paid_at->format('Y-m') === $key)
                    ->sum('amount'), 2),
            ];
        }

        $upcomingInvoices = $openInvoices
            ->filter(fn (Invoice $invoice): bool => $invoice->due_date !== null
                && $invoice->due_date->gte($today)
                && $balanceFor($invoice) > 0)
            ->sortBy('due_date')
            ->take(5)
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'supplier' => $invoice->supplier?->name ?? 'Unassigned supplier',
                'balance' => round($balanceFor($invoice), 2),
                'due_date' => $invoice->due_date?->toDateString(),
                'status' => (
                    $invoice->status === 'partial'
                    || (float) $invoice->payments->sum('amount') > 0
                ) ? 'partial' : 'unpaid',
            ])
            ->values();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'currency' => 'GHS',
            'totals' => [
                'expenses' => round($expenseTotal, 2),
                'payments' => round($paymentTotal, 2),
                'outflow' => round($expenseTotal + $paymentTotal, 2),
                'open_payables' => round($openPayables, 2),
                'overdue_payables' => round((float) $overdueInvoices->sum($balanceFor), 2),
                'overdue_invoice_count' => $overdueInvoices->count(),
                'due_7_days' => round((float) $dueSoonInvoices->sum($balanceFor), 2),
                'due_7_days_count' => $dueSoonInvoices->count(),
                'open_invoice_count' => $openInvoices->filter(fn (Invoice $invoice): bool => $balanceFor($invoice) > 0)->count(),
            ],
            'expense_categories' => $categoryBreakdown,
            'monthly_activity' => $monthlyActivity,
            'invoice_aging' => collect($aging)->map(fn (float $amount): float => round($amount, 2)),
            'upcoming_invoices' => $upcomingInvoices,
            'source' => 'expenses, invoices, payments',
        ]);
    }

    /** Existing spend summary retained for executive and reporting consumers. */
    public function spending(Request $request): JsonResponse
    {
        [$from, $to] = $request->filled('from')
            ? DateRange::fromRequest($request)
            : DateRange::period($request);

        $expenses = Expense::whereBetween('expense_date', [$from, $to])->get();
        $orders = PurchaseOrder::whereBetween('ordered_at', [$from, $to])->get();
        $invoices = Invoice::whereBetween('invoice_date', [$from, $to])->get();

        $expenseTotal = (float) $expenses->sum('amount');
        $poTotal = (float) $orders->sum('total_amount');
        $invoiceTotal = (float) $invoices->sum('amount');

        $byCategory = $expenses->groupBy('category')->map(fn ($rows) => round((float) $rows->sum('amount'), 2));

        return response()->json([
            'question' => 23,
            'period' => $request->query('period', 'month'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'expenses' => round($expenseTotal, 2),
            'purchase_orders' => round($poTotal, 2),
            'invoices' => round($invoiceTotal, 2),
            'total_spending' => round($expenseTotal + $poTotal + $invoiceTotal, 2),
            'by_category' => $byCategory,
            'upcoming_invoices' => Invoice::where('status', 'unpaid')->whereDate('due_date', '>=', now())->orderBy('due_date')->get(),
            'source' => 'expenses, purchase_orders, invoices',
        ]);
    }

    /**
     * Download or view the Cash Activity Register PDF statement.
     */
    public function cashActivityPdf(Request $request, CashActivityReportPdfService $pdfService): Response
    {
        if ($request->isMethod('post') && $request->has('entries')) {
            $entries = $request->input('entries', []);
            $meta = $request->input('meta', []);
            $binaryPdf = $pdfService->generatePdf($entries, $meta);
        } else {
            [$from, $to] = DateRange::fromRequest($request, 30);
            $expensesQuery = Expense::whereBetween('expense_date', [$from, $to]);
            $paymentsQuery = Payment::with(['invoice.supplier'])->whereBetween('paid_at', [$from, $to]);

            if ($request->filled('method') && $request->query('method') !== 'all') {
                $method = strtolower($request->query('method'));
                $expensesQuery->whereRaw('LOWER(source) = ?', [$method]);
                $paymentsQuery->whereRaw('LOWER(method) = ?', [$method]);
            }

            $type = $request->query('type', 'all');
            $entries = [];

            if ($type === 'all' || $type === 'Expense') {
                foreach ($expensesQuery->orderByDesc('expense_date')->get() as $exp) {
                    $entries[] = [
                        'date' => $exp->expense_date?->toDateString() ?? '',
                        'type' => 'Expense',
                        'reference' => $exp->category ?: 'Expense',
                        'description' => $exp->description ?: 'Operating expense',
                        'method' => $exp->source ?: 'Manual entry',
                        'amount' => (float) $exp->amount,
                    ];
                }
            }

            if ($type === 'all' || $type === 'Supplier payment') {
                foreach ($paymentsQuery->orderByDesc('paid_at')->get() as $pmt) {
                    $entries[] = [
                        'date' => $pmt->paid_at?->toDateString() ?? '',
                        'type' => 'Supplier payment',
                        'reference' => $pmt->invoice?->invoice_number ?? ('Invoice #' . $pmt->invoice_id),
                        'description' => $pmt->invoice?->supplier?->name ?? 'Supplier payment',
                        'method' => $pmt->method ?: 'Not specified',
                        'amount' => (float) $pmt->amount,
                    ];
                }
            }

            usort($entries, fn ($a, $b) => strcmp($b['date'], $a['date']));

            $meta = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'filterSummary' => $type === 'all' ? 'All Transactions' : ($type . 's only'),
            ];

            $binaryPdf = $pdfService->generatePdf($entries, $meta);
        }

        $filename = 'cash-activity-register-' . ($meta['from'] ?? 'period') . '-to-' . ($meta['to'] ?? 'period') . '.pdf';
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($binaryPdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $filename),
            'Content-Length' => strlen($binaryPdf),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
