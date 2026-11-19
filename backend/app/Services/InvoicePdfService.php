<?php

namespace App\Services;

use App\Models\Invoice;

class InvoicePdfService
{
    /**
     * Generate a binary PDF 1.4 document for the given invoice.
     */
    public function generatePdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['supplier', 'payments']);

        $pdf = new SimplePdfDocument();
        $pdf->addPage();

        // 1. Top Header Banner (Brand Color)
        $pdf->rect(0, 0, 595.28, 85, '#2f4a32');
        $pdf->text('EVOLEX FOODS LTD', 40, 40, 'F2', 20, '#ffffff');
        $pdf->text('Agricultural Production & Supply Chain Management', 40, 58, 'F1', 10, '#c9d8c2');
        $pdf->text('Accra - Central Region, Ghana | finance@evolexfoods.com | +233 24 000 0000', 40, 72, 'F1', 8.5, '#a5bda0');

        // 2. Invoice Title & Metadata
        $pdf->text('INVOICE / BILL', 40, 118, 'F2', 17, '#1c2e1f');
        $pdf->text('Invoice No: '.$invoice->invoice_number, 40, 136, 'F2', 11, '#2f4a32');
        
        $dateStr = $invoice->invoice_date ? $invoice->invoice_date->format('d M Y') : '—';
        $dueStr = $invoice->due_date ? $invoice->due_date->format('d M Y') : 'No due date';
        $pdf->text('Invoice Date: '.$dateStr, 40, 151, 'F1', 9.5, '#6b7a63');
        $pdf->text('Payment Due: '.$dueStr, 40, 165, 'F1', 9.5, '#6b7a63');

        // Calculate payment figures
        $totalAmount = (float) $invoice->amount;
        $totalPaid = (float) $invoice->payments->sum('amount');
        $balance = max(0.0, $totalAmount - $totalPaid);
        if ($invoice->status === 'paid' && $invoice->payments->isEmpty()) {
            $balance = 0.0;
            $totalPaid = $totalAmount;
        }

        // Determine Status Label and Colors
        $statusKey = strtolower($invoice->status ?? 'unpaid');
        if ($balance <= 0) {
            $statusLabel = 'PAID';
            $bgCol = '#e8f5e9';
            $txtCol = '#1b5e20';
            $borderCol = '#2e7d32';
        } elseif ($invoice->due_date && $invoice->due_date->isPast() && $balance > 0) {
            $statusLabel = 'OVERDUE';
            $bgCol = '#ffebee';
            $txtCol = '#b71c1c';
            $borderCol = '#c62828';
        } elseif ($totalPaid > 0) {
            $statusLabel = 'PART-PAID';
            $bgCol = '#e3f2fd';
            $txtCol = '#0d47a1';
            $borderCol = '#1565c0';
        } else {
            $statusLabel = 'UNPAID';
            $bgCol = '#fff8e1';
            $txtCol = '#e65100';
            $borderCol = '#f57c00';
        }

        // Status Badge (Top Right)
        $pdf->rect(420, 108, 135, 34, $bgCol, $borderCol, 1.2);
        $pdf->text('STATUS: '.$statusLabel, 435, 129, 'F2', 10.5, $txtCol);

        // 3. Supplier / Billed From Section
        $supplier = $invoice->supplier;
        $pdf->rect(40, 185, 515, 68, '#fbfaf7', '#e3ddcd', 1);
        $pdf->text('SUPPLIER / BILLED FROM', 52, 202, 'F2', 8.5, '#8a8578');
        
        $suppName = $supplier ? $supplier->name : 'Unassigned Supplier';
        if ($supplier && $supplier->code) {
            $suppName .= ' ('.$supplier->code.')';
        }
        $pdf->text($suppName, 52, 218, 'F2', 11, '#1c2e1f');

        $contactParts = [];
        if ($supplier?->contact_name) $contactParts[] = 'Contact: '.$supplier->contact_name;
        if ($supplier?->phone) $contactParts[] = 'Phone: '.$supplier->phone;
        if ($supplier?->email) $contactParts[] = 'Email: '.$supplier->email;
        $contactLine = !empty($contactParts) ? implode('  |  ', $contactParts) : 'No supplier contact specified';
        $pdf->text($contactLine, 52, 233, 'F1', 9, '#555555');

        $locationLine = 'Location: '.($supplier?->location ?? 'Headquarters / Ghana');
        $pdf->text($locationLine, 52, 245, 'F1', 8.5, '#777777');

        // 4. Line Items Table Header
        $tableY = 270;
        $pdf->rect(40, $tableY, 515, 24, '#2f4a32');
        $pdf->text('#', 50, $tableY + 16, 'F2', 9.5, '#ffffff');
        $pdf->text('DESCRIPTION / SERVICES', 75, $tableY + 16, 'F2', 9.5, '#ffffff');
        $pdf->text('QTY', 345, $tableY + 16, 'F2', 9.5, '#ffffff');
        $pdf->text('RATE (GHS)', 405, $tableY + 16, 'F2', 9.5, '#ffffff');
        $pdf->text('AMOUNT (GHS)', 470, $tableY + 16, 'F2', 9.5, '#ffffff');

        // 5. Line Item Row
        $rowY = $tableY + 24;
        $pdf->rect(40, $rowY, 515, 30, '#ffffff', '#e3ddcd', 0.5);
        $pdf->text('1', 50, $rowY + 19, 'F1', 9.5, '#333333');
        $itemDesc = 'Supplies / Commercial Agricultural Invoicing ('.$invoice->invoice_number.')';
        $pdf->text($itemDesc, 75, $rowY + 19, 'F1', 9.5, '#1c2e1f');
        $pdf->text('1', 350, $rowY + 19, 'F1', 9.5, '#333333');
        $pdf->text(number_format($totalAmount, 2), 405, $rowY + 19, 'F1', 9.5, '#333333');
        $pdf->text(number_format($totalAmount, 2), 475, $rowY + 19, 'F2', 9.5, '#1c2e1f');

        // 6. Summary / Totals Box (Right Side)
        $summaryY = $rowY + 45;
        $pdf->rect(320, $summaryY, 235, 84, '#fdfaf2', '#d9d0bf', 1);
        
        $pdf->text('Subtotal:', 335, $summaryY + 20, 'F1', 9.5, '#3d4a36');
        $pdf->text('GHS '.number_format($totalAmount, 2), 460, $summaryY + 20, 'F2', 9.5, '#1c2e1f');

        $pdf->text('Payments Cleared:', 335, $summaryY + 40, 'F1', 9.5, '#2e7d32');
        $pdf->text('- GHS '.number_format($totalPaid, 2), 450, $summaryY + 40, 'F2', 9.5, '#2e7d32');

        $pdf->line(330, $summaryY + 54, 545, $summaryY + 54, '#d9d0bf', 0.8);

        $pdf->text('Balance Due:', 335, $summaryY + 72, 'F2', 11, '#1c2e1f');
        $balCol = $balance > 0 ? '#b71c1c' : '#2e7d32';
        $pdf->text('GHS '.number_format($balance, 2), 450, $summaryY + 72, 'F2', 12, $balCol);

        // 7. Payment History (if any)
        $paymentSectionY = $summaryY + 105;
        if ($invoice->payments->isNotEmpty()) {
            $pdf->text('PAYMENT HISTORY', 40, $paymentSectionY, 'F2', 10, '#1c2e1f');
            $pdf->line(40, $paymentSectionY + 4, 280, $paymentSectionY + 4, '#2f4a32', 1);

            $py = $paymentSectionY + 18;
            foreach ($invoice->payments->take(4) as $payment) {
                $pDate = $payment->paid_at ? (is_string($payment->paid_at) ? substr($payment->paid_at, 0, 10) : $payment->paid_at->format('d M Y')) : '—';
                $pMethod = ucwords(str_replace('_', ' ', $payment->method ?? 'Payment'));
                $pRef = $payment->reference ? ' (Ref: '.$payment->reference.')' : '';
                $pAmt = 'GHS '.number_format((float) $payment->amount, 2);

                $pdf->text("• {$pDate} - {$pMethod}{$pRef}: {$pAmt}", 45, $py, 'F1', 8.5, '#3d4a36');
                $py += 15;
            }
        } else {
            $pdf->text('PAYMENT TERMS & INSTRUCTIONS', 40, $paymentSectionY, 'F2', 10, '#1c2e1f');
            $pdf->line(40, $paymentSectionY + 4, 280, $paymentSectionY + 4, '#2f4a32', 1);
            $pdf->text('Bank Transfer: GCB Bank / Stanbic Bank Ghana', 45, $paymentSectionY + 20, 'F1', 9, '#555555');
            $pdf->text('Account Name: Evolex Foods Limited', 45, $paymentSectionY + 34, 'F1', 9, '#555555');
            $pdf->text('Mobile Money: MTN MoMo Merchant / Paybill (Ref: '.$invoice->invoice_number.')', 45, $paymentSectionY + 48, 'F1', 9, '#555555');
        }

        // 8. Footer Section
        $pdf->line(40, 785, 555, 785, '#d9d0bf', 0.8);
        $pdf->text('Evolex Foods Ltd · Supply Chain, Farm Operations & Accounts Payable', 40, 802, 'F1', 8, '#8a8578');
        $pdf->text('Generated on '.now()->format('d M Y, H:i').' · Verified Official System Record', 330, 802, 'F3', 8, '#8a8578');

        return $pdf->render();
    }

    /**
     * Generate standalone printable HTML for this invoice.
     */
    public function generateHtml(Invoice $invoice): string
    {
        $invoice->loadMissing(['supplier', 'payments']);

        $totalAmount = (float) $invoice->amount;
        $totalPaid = (float) $invoice->payments->sum('amount');
        $balance = max(0.0, $totalAmount - $totalPaid);
        if ($invoice->status === 'paid' && $invoice->payments->isEmpty()) {
            $balance = 0.0;
            $totalPaid = $totalAmount;
        }

        $status = strtolower($invoice->status ?? 'unpaid');
        $statusClass = $balance <= 0 ? 'paid' : ($invoice->due_date && $invoice->due_date->isPast() ? 'overdue' : ($totalPaid > 0 ? 'partial' : 'unpaid'));
        $statusLabel = strtoupper($statusClass);

        $supplier = $invoice->supplier;
        $dateStr = $invoice->invoice_date ? $invoice->invoice_date->format('d M Y') : '—';
        $dueStr = $invoice->due_date ? $invoice->due_date->format('d M Y') : 'No due date';

        $paymentsHtml = '';
        if ($invoice->payments->isNotEmpty()) {
            $paymentsHtml = '<h3>Payment Records</h3><table class="items-table"><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th></tr></thead><tbody>';
            foreach ($invoice->payments as $p) {
                $pDate = $p->paid_at ? (is_string($p->paid_at) ? substr($p->paid_at, 0, 10) : $p->paid_at->format('d M Y')) : '—';
                $pMethod = ucwords(str_replace('_', ' ', $p->method ?? 'Payment'));
                $paymentsHtml .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td class="text-right">GH₵ %s</td></tr>',
                    htmlspecialchars($pDate),
                    htmlspecialchars($pMethod),
                    htmlspecialchars($p->reference ?? '—'),
                    number_format((float) $p->amount, 2)
                );
            }
            $paymentsHtml .= '</tbody></table>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice {$invoice->invoice_number} · Evolex Foods</title>
  <style>
    @page { size: A4; margin: 15mm; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1c2e1f; margin: 0; padding: 20px; background: #fff; }
    .invoice-card { max-width: 800px; margin: 0 auto; border: 1px solid #e3ddcd; border-radius: 12px; padding: 32px; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #2f4a32; padding-bottom: 16px; margin-bottom: 24px; }
    .brand h1 { margin: 0; font-size: 24px; color: #2f4a32; letter-spacing: -0.02em; }
    .brand p { margin: 4px 0 0; color: #6b7a63; font-size: 13px; }
    .status-badge { display: inline-block; padding: 6px 14px; border-radius: 999px; font-weight: 700; font-size: 12px; text-transform: uppercase; }
    .status-paid { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
    .status-overdue { background: #ffebee; color: #b71c1c; border: 1px solid #ef9a9a; }
    .status-partial { background: #e3f2fd; color: #0d47a1; border: 1px solid #90caf9; }
    .status-unpaid { background: #fff8e1; color: #e65100; border: 1px solid #ffe082; }
    .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
    .box { background: #fbfaf7; border: 1px solid #e3ddcd; border-radius: 8px; padding: 16px; }
    .box h2 { margin: 0 0 8px; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #8a8578; }
    .box strong { font-size: 15px; color: #1c2e1f; display: block; margin-bottom: 4px; }
    .box p { margin: 2px 0; font-size: 12.5px; color: #555; }
    .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
    .items-table th { background: #2f4a32; color: #fff; text-align: left; padding: 10px 12px; font-size: 11px; text-transform: uppercase; }
    .items-table td { padding: 12px; border-bottom: 1px solid #e3ddcd; }
    .text-right { text-align: right; }
    .totals-area { display: flex; justify-content: flex-end; margin-top: 16px; }
    .totals-box { width: 280px; background: #fdfaf2; border: 1px solid #d9d0bf; border-radius: 8px; padding: 16px; }
    .totals-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
    .totals-row.grand-total { border-top: 1px solid #d9d0bf; padding-top: 8px; margin-top: 8px; font-weight: 700; font-size: 15px; }
    .text-danger { color: #b71c1c; }
    .text-success { color: #1b5e20; }
    .print-bar { max-width: 800px; margin: 0 auto 16px; display: flex; justify-content: flex-end; gap: 8px; }
    .btn { background: #2f4a32; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn:hover { background: #1c2e1f; }
    .footer { margin-top: 32px; border-top: 1px solid #e3ddcd; padding-top: 16px; font-size: 11.5px; color: #8a8578; display: flex; justify-content: space-between; }
    @media print {
      .print-bar { display: none !important; }
      body { padding: 0; }
      .invoice-card { border: none; box-shadow: none; padding: 0; }
    }
  </style>
</head>
<body>
  <div class="print-bar">
    <button class="btn" onclick="window.print()">🖨 Print Invoice</button>
  </div>
  <div class="invoice-card">
    <div class="header">
      <div class="brand">
        <h1>EVOLEX FOODS LTD</h1>
        <p>Supply Chain &amp; Agricultural Operations · Ghana</p>
        <p>Email: finance@evolexfoods.com | Phone: +233 24 000 0000</p>
      </div>
      <div>
        <span class="status-badge status-{$statusClass}">{$statusLabel}</span>
      </div>
    </div>

    <div class="meta-grid">
      <div class="box">
        <h2>Supplier / Billed From</h2>
        <strong>{$supplier?->name}</strong>
        <p>Code: {$supplier?->code}</p>
        <p>Contact: {$supplier?->contact_name}</p>
        <p>Phone: {$supplier?->phone}</p>
        <p>Location: {$supplier?->location}</p>
      </div>
      <div class="box">
        <h2>Invoice Details</h2>
        <strong>Invoice: {$invoice->invoice_number}</strong>
        <p>Issue Date: {$dateStr}</p>
        <p>Payment Due: {$dueStr}</p>
      </div>
    </div>

    <table class="items-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Description</th>
          <th class="text-right">Qty</th>
          <th class="text-right">Rate (GHS)</th>
          <th class="text-right">Amount (GHS)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>1</td>
          <td>Commercial Agricultural Supplies &amp; Deliveries ({$invoice->invoice_number})</td>
          <td class="text-right">1</td>
          <td class="text-right">GH₵ {$totalAmount}</td>
          <td class="text-right"><strong>GH₵ {$totalAmount}</strong></td>
        </tr>
      </tbody>
    </table>

    <div class="totals-area">
      <div class="totals-box">
        <div class="totals-row"><span>Total Amount:</span><strong>GH₵ {$totalAmount}</strong></div>
        <div class="totals-row text-success"><span>Payments Cleared:</span><strong>- GH₵ {$totalPaid}</strong></div>
        <div class="totals-row grand-total">
          <span>Balance Due:</span>
          <span class="text-{$statusClass}">GH₵ {$balance}</span>
        </div>
      </div>
    </div>

    {$paymentsHtml}

    <div class="footer">
      <span>Evolex Foods Ltd · Official Accounts Payable Record</span>
      <span>Generated by Evolex Hub</span>
    </div>
  </div>
</body>
</html>
HTML;
    }
}

