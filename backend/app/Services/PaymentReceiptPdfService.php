<?php

namespace App\Services;

use App\Models\Payment;

class PaymentReceiptPdfService
{
    /**
     * Generate a binary PDF 1.4 document containing both ORIGINAL (top half)
     * and DUPLICATE (bottom half) on a single A4 sheet.
     */
    public function generatePdf(Payment $payment): string
    {
        $payment->loadMissing(['invoice.supplier', 'invoice.payments']);

        $pdf = new SimplePdfDocument();
        $pdf->addPage();

        $invoice = $payment->invoice;
        $supplier = $invoice?->supplier;

        $invoiceAmount = (float) ($invoice?->amount ?? $payment->amount);
        $totalPaidOnInvoice = (float) ($invoice?->payments?->sum('amount') ?? $payment->amount);
        $balance = max(0.0, $invoiceAmount - $totalPaidOnInvoice);
        if ($invoice?->status === 'paid' && (! $invoice->payments || $invoice->payments->isEmpty())) {
            $balance = 0.0;
        }

        $receiptNo = 'RCP-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);
        $dateStr = $payment->paid_at ? $payment->paid_at->format('d M Y') : now()->format('d M Y');
        $methodLabel = ucwords(str_replace('_', ' ', $payment->method ?? 'Payment'));
        $refStr = $payment->reference ?: 'REF-'.strtoupper(substr(md5((string) $payment->id), 0, 8));
        $amountFormatted = 'GHc '.number_format((float) $payment->amount, 2);
        $amountWords = $this->amountToWords((float) $payment->amount);

        $supplierName = $supplier?->name ?? 'General Payee / Vendor';
        if ($supplier?->code) {
            $supplierName .= ' ('.$supplier->code.')';
        }
        $invoiceNumber = $invoice?->invoice_number ?? 'Direct Payment / N/A';

        // 1. TOP HALF: ORIGINAL (Payer Copy)
        $this->renderReceiptHalf(
            pdf: $pdf,
            yBase: 20,
            copyType: 'ORIGINAL',
            copySubtitle: 'PAYER / VENDOR COPY',
            copyBadgeBg: '#e8f5e9',
            copyBadgeText: '#1b5e20',
            receiptNo: $receiptNo,
            dateStr: $dateStr,
            methodLabel: $methodLabel,
            refStr: $refStr,
            supplierName: $supplierName,
            supplier: $supplier,
            invoiceNumber: $invoiceNumber,
            invoiceAmount: $invoiceAmount,
            balance: $balance,
            paymentAmount: (float) $payment->amount,
            amountFormatted: $amountFormatted,
            amountWords: $amountWords
        );

        // 2. CENTER PERFORATION / DIVIDER LINE (at Y = 412 pt)
        $pdf->dashedLine(25, 412, 570, 412, '#888888', 1, [4, 3]);
        $pdf->text('- - - 8< - - - CUT ALONG DOTTED LINE - - - - - - - - - - - - - - - - - - - - - - - 8< - - -', 125, 410, 'F1', 7.5, '#777777');
        $pdf->text('( Top Half: ORIGINAL / Payer Copy  |  Bottom Half: DUPLICATE / Accounts Office Copy )', 140, 421, 'F3', 7, '#999999');

        // 3. BOTTOM HALF: DUPLICATE (Accounts Office Copy)
        $this->renderReceiptHalf(
            pdf: $pdf,
            yBase: 432,
            copyType: 'DUPLICATE',
            copySubtitle: 'ACCOUNTS OFFICE COPY',
            copyBadgeBg: '#e3f2fd',
            copyBadgeText: '#0d47a1',
            receiptNo: $receiptNo,
            dateStr: $dateStr,
            methodLabel: $methodLabel,
            refStr: $refStr,
            supplierName: $supplierName,
            supplier: $supplier,
            invoiceNumber: $invoiceNumber,
            invoiceAmount: $invoiceAmount,
            balance: $balance,
            paymentAmount: (float) $payment->amount,
            amountFormatted: $amountFormatted,
            amountWords: $amountWords
        );

        return $pdf->render();
    }

    /**
     * Render one half of the receipt at the specified vertical Y coordinate.
     */
    private function renderReceiptHalf(
        SimplePdfDocument $pdf,
        float $yBase,
        string $copyType,
        string $copySubtitle,
        string $copyBadgeBg,
        string $copyBadgeText,
        string $receiptNo,
        string $dateStr,
        string $methodLabel,
        string $refStr,
        string $supplierName,
        mixed $supplier,
        string $invoiceNumber,
        float $invoiceAmount,
        float $balance,
        float $paymentAmount,
        string $amountFormatted,
        string $amountWords
    ): void {
        // Outer Card Box
        $pdf->rect(25, $yBase, 545, 378, '#ffffff', '#2f4a32', 1);

        // Header Banner (Evolex Brand Forest Green)
        $pdf->rect(25, $yBase, 545, 46, '#2f4a32');
        $pdf->text('EVOLEX FOODS LTD', 40, $yBase + 18, 'F2', 13.5, '#ffffff');
        $pdf->text('Agricultural Production & Supply Chain Management', 40, $yBase + 30, 'F1', 7.5, '#c9d8c2');
        $pdf->text('Accra - Central Region, Ghana | finance@evolexfoods.com | +233 24 000 0000', 40, $yBase + 40, 'F1', 7, '#a5bda0');

        // Copy Badge (Top Right of Banner)
        $pdf->rect(385, $yBase + 8, 172, 30, $copyBadgeBg, '#ffffff', 0.8);
        $pdf->text('PAYMENT RECEIPT: '.$copyType, 395, $yBase + 21, 'F2', 9.5, $copyBadgeText);
        $pdf->text($copySubtitle, 395, $yBase + 32, 'F3', 7.5, $copyBadgeText);

        // Metadata Strip (Receipt #, Date, Method, Reference)
        $metaY = $yBase + 52;
        $pdf->rect(35, $metaY, 525, 22, '#fbfaf7', '#e3ddcd', 0.8);
        $pdf->text('Receipt No: '.$receiptNo, 45, $metaY + 14, 'F2', 9, '#1c2e1f');
        $pdf->text('Date: '.$dateStr, 170, $metaY + 14, 'F1', 8.5, '#444444');
        $pdf->text('Method: '.$methodLabel, 275, $metaY + 14, 'F1', 8.5, '#444444');
        $pdf->text('Ref: '.$refStr, 395, $metaY + 14, 'F2', 8.5, '#2f4a32');

        // Two-column section: Vendor / Payee (Left) & Invoice Reference (Right)
        $boxY = $yBase + 80;
        // Left Box: Paid to / Supplier
        $pdf->rect(35, $boxY, 255, 66, '#fcfbf8', '#e3ddcd', 0.8);
        $pdf->text('PAID TO / VENDOR', 45, $boxY + 15, 'F2', 7.5, '#8a8578');
        $pdf->text($supplierName, 45, $boxY + 30, 'F2', 9.5, '#1c2e1f');

        $contact = $supplier?->contact_name ? 'Contact: '.$supplier->contact_name : '';
        if ($supplier?->phone) {
            $contact .= ($contact ? ' | ' : '').$supplier->phone;
        }
        $pdf->text($contact ?: 'No specific contact registered', 45, $boxY + 44, 'F1', 7.5, '#555555');
        $pdf->text('Location: '.($supplier?->location ?? 'Accra / Central Region, Ghana'), 45, $boxY + 56, 'F1', 7, '#777777');

        // Right Box: Invoice Reference & Balance
        $pdf->rect(298, $boxY, 262, 66, '#fcfbf8', '#e3ddcd', 0.8);
        $pdf->text('INVOICE / BILL DETAILS', 308, $boxY + 15, 'F2', 7.5, '#8a8578');
        $pdf->text('Invoice No: '.$invoiceNumber, 308, $boxY + 30, 'F2', 9.5, '#1c2e1f');
        $pdf->text('Bill Total: GHc '.number_format($invoiceAmount, 2), 308, $boxY + 44, 'F1', 8, '#555555');
        
        $balanceColor = $balance <= 0 ? '#1b5e20' : '#b71c1c';
        $balanceText = $balance <= 0 ? 'STATUS: FULLY PAID (Balance: GHc 0.00)' : 'Remaining Balance: GHc '.number_format($balance, 2);
        $pdf->text($balanceText, 308, $boxY + 56, 'F2', 8, $balanceColor);

        // Highlight Amount Box (Green Accent)
        $amtY = $yBase + 152;
        $pdf->rect(35, $amtY, 525, 46, '#f0f7f1', '#2f4a32', 1);
        $pdf->text('AMOUNT RECEIVED / SETTLED (GHS)', 48, $amtY + 16, 'F2', 8, '#2f4a32');
        $pdf->text($amountFormatted, 48, $amtY + 36, 'F2', 15.5, '#1b5e20');
        
        // Amount in Words
        $pdf->text('Amount in words:', 235, $amtY + 16, 'F1', 7.5, '#6b7a63');
        $pdf->text($amountWords, 235, $amtY + 32, 'F3', 8.5, '#1c2e1f');

        // Settlement Line Item Table
        $tblY = $yBase + 204;
        $pdf->rect(35, $tblY, 525, 18, '#2f4a32');
        $pdf->text('#', 45, $tblY + 12, 'F2', 8, '#ffffff');
        $pdf->text('PAYMENT DESCRIPTION & PURPOSE', 65, $tblY + 12, 'F2', 8, '#ffffff');
        $pdf->text('METHOD / REF', 355, $tblY + 12, 'F2', 8, '#ffffff');
        $pdf->text('AMOUNT (GHS)', 475, $tblY + 12, 'F2', 8, '#ffffff');

        $rowY = $tblY + 18;
        $pdf->rect(35, $rowY, 525, 26, '#ffffff', '#e3ddcd', 0.8);
        $pdf->text('1', 45, $rowY + 16, 'F1', 8.5, '#444444');
        $desc = 'Settlement towards '.$invoiceNumber.' - '.$supplierName;
        if (strlen($desc) > 52) {
            $desc = substr($desc, 0, 49).'...';
        }
        $pdf->text($desc, 65, $rowY + 16, 'F2', 8.5, '#1c2e1f');
        $pdf->text($methodLabel.' / '.$refStr, 355, $rowY + 16, 'F1', 8, '#555555');
        $pdf->text(number_format($paymentAmount, 2), 485, $rowY + 16, 'F2', 9, '#1c2e1f');

        // Dual Signatures Section (Issued by Accounts & Acknowledged by Payer)
        $sigY = $yBase + 260;
        $pdf->rect(35, $sigY, 255, 76, '#faf9f5', '#e3ddcd', 0.6);
        $pdf->text('ISSUED BY (ACCOUNTS DEPARTMENT)', 45, $sigY + 14, 'F2', 7.5, '#8a8578');
        $pdf->line(45, $sigY + 50, 275, $sigY + 50, '#999999', 0.8);
        $pdf->text('Authorized Signature & Date Stamp', 45, $sigY + 62, 'F1', 7.5, '#666666');
        $pdf->text('Evolex Foods Ltd · Finance Office', 45, $sigY + 71, 'F3', 7, '#888888');

        $pdf->rect(305, $sigY, 255, 76, '#faf9f5', '#e3ddcd', 0.6);
        $pdf->text('RECEIVED / ACKNOWLEDGED BY', 315, $sigY + 14, 'F2', 7.5, '#8a8578');
        $pdf->line(315, $sigY + 50, 545, $sigY + 50, '#999999', 0.8);
        $pdf->text('Payer / Vendor Signature & Full Name', 315, $sigY + 62, 'F1', 7.5, '#666666');
        $pdf->text('Date: ________________________', 315, $sigY + 71, 'F3', 7, '#888888');

        // Micro Footer
        $pdf->line(35, $yBase + 360, 560, $yBase + 360, '#e3ddcd', 0.6);
        $pdf->text('Official computer-generated payment voucher · Valid without physical seal if digitally authenticated.', 40, $yBase + 371, 'F1', 6.8, '#8a8578');
        $pdf->text('Generated '.now()->format('d M Y, H:i'), 470, $yBase + 371, 'F3', 6.8, '#8a8578');
    }

    /**
     * Generate standalone printable HTML containing both ORIGINAL and DUPLICATE
     * on one single A4 portrait sheet.
     */
    public function generateHtml(Payment $payment): string
    {
        $payment->loadMissing(['invoice.supplier', 'invoice.payments']);

        $invoice = $payment->invoice;
        $supplier = $invoice?->supplier;

        $invoiceAmount = (float) ($invoice?->amount ?? $payment->amount);
        $totalPaidOnInvoice = (float) ($invoice?->payments?->sum('amount') ?? $payment->amount);
        $balance = max(0.0, $invoiceAmount - $totalPaidOnInvoice);
        if ($invoice?->status === 'paid' && (! $invoice->payments || $invoice->payments->isEmpty())) {
            $balance = 0.0;
        }

        $receiptNo = 'RCP-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT);
        $dateStr = $payment->paid_at ? $payment->paid_at->format('d M Y') : now()->format('d M Y');
        $methodLabel = ucwords(str_replace('_', ' ', $payment->method ?? 'Payment'));
        $refStr = $payment->reference ?: 'REF-'.strtoupper(substr(md5((string) $payment->id), 0, 8));
        $amountFormatted = 'GH₵ '.number_format((float) $payment->amount, 2);
        $amountWords = $this->amountToWords((float) $payment->amount);

        $supplierName = $supplier?->name ?? 'General Payee / Vendor';
        if ($supplier?->code) {
            $supplierName .= ' ('.$supplier->code.')';
        }
        $supplierContact = $supplier?->contact_name ? 'Contact: '.$supplier->contact_name : '';
        if ($supplier?->phone) {
            $supplierContact .= ($supplierContact ? ' | ' : '').$supplier->phone;
        }
        $supplierLocation = $supplier?->location ?? 'Accra / Central Region, Ghana';

        $invoiceNumber = $invoice?->invoice_number ?? 'Direct Payment / N/A';
        $balanceClass = $balance <= 0 ? 'text-success' : 'text-danger';
        $balanceText = $balance <= 0 ? 'FULLY PAID (Balance: GH₵ 0.00)' : 'Remaining Balance: GH₵ '.number_format($balance, 2);

        $renderHalf = function (string $copyType, string $copySubtitle, string $badgeClass) use (
            $receiptNo, $dateStr, $methodLabel, $refStr, $supplierName, $supplierContact,
            $supplierLocation, $invoiceNumber, $invoiceAmount, $balanceClass, $balanceText,
            $amountFormatted, $amountWords, $payment
        ) {
            return <<<HTML
      <div class="receipt-card">
        <div class="banner">
          <div class="brand">
            <h1>EVOLEX FOODS LTD</h1>
            <p class="tagline">Agricultural Production & Supply Chain Management</p>
            <p class="contacts">Accra - Central Region, Ghana &bull; finance@evolexfoods.com &bull; +233 24 000 0000</p>
          </div>
          <div class="copy-badge {$badgeClass}">
            <div class="badge-title">PAYMENT RECEIPT: {$copyType}</div>
            <div class="badge-sub">{$copySubtitle}</div>
          </div>
        </div>

        <div class="meta-strip">
          <div><strong>Receipt No:</strong> <span class="highlight">{$receiptNo}</span></div>
          <div><strong>Date:</strong> {$dateStr}</div>
          <div><strong>Method:</strong> {$methodLabel}</div>
          <div><strong>Ref:</strong> <span class="ref-code">{$refStr}</span></div>
        </div>

        <div class="details-grid">
          <div class="detail-box">
            <div class="box-title">PAID TO / VENDOR</div>
            <div class="vendor-name">{$supplierName}</div>
            <div class="vendor-contact">{$supplierContact}</div>
            <div class="vendor-loc">{$supplierLocation}</div>
          </div>
          <div class="detail-box">
            <div class="box-title">INVOICE &amp; SETTLEMENT STATUS</div>
            <div class="vendor-name">Invoice #{$invoiceNumber}</div>
            <div class="vendor-contact">Bill Total: GH₵ {$this->formatNumber($invoiceAmount)}</div>
            <div class="vendor-loc {$balanceClass}"><strong>{$balanceText}</strong></div>
          </div>
        </div>

        <div class="amount-card">
          <div class="amount-number">
            <div class="amount-label">AMOUNT RECEIVED / SETTLED</div>
            <div class="amount-val">{$amountFormatted}</div>
          </div>
          <div class="amount-words-box">
            <div class="amount-words-label">Amount in words:</div>
            <div class="amount-words-val">{$amountWords}</div>
          </div>
        </div>

        <table class="items-table">
          <thead>
            <tr>
              <th style="width: 30px;">#</th>
              <th>Payment Description &amp; Purpose</th>
              <th style="width: 160px;">Method / Reference</th>
              <th style="width: 120px;" class="text-right">Amount (GHS)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>1</td>
              <td><strong>Settlement towards {$invoiceNumber}</strong> &mdash; {$supplierName}</td>
              <td>{$methodLabel} / {$refStr}</td>
              <td class="text-right"><strong>GH₵ {$this->formatNumber((float) $payment->amount)}</strong></td>
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
HTML;
        };

        $originalHtml = $renderHalf('ORIGINAL', 'PAYER / VENDOR COPY', 'badge-original');
        $duplicateHtml = $renderHalf('DUPLICATE', 'ACCOUNTS OFFICE COPY', 'badge-duplicate');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Receipt {$receiptNo} · Evolex Foods</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 0;
    }
    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      margin: 0;
      padding: 0;
      background: #eef2ed;
      color: #1c2e1f;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .print-actions {
      width: 210mm;
      max-width: 100%;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      padding: 14px 16px;
    }
    .btn {
      background: #2f4a32;
      color: #ffffff;
      border: none;
      padding: 9px 18px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    }
    .btn:hover {
      background: #1c2e1f;
    }
    .a4-page {
      width: 210mm;
      height: 297mm;
      max-height: 297mm;
      background: #ffffff;
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      padding: 7mm 8mm;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
      page-break-inside: avoid;
      page-break-after: avoid;
    }
    .receipt-card {
      border: 1.5px solid #2f4a32;
      border-radius: 6px;
      padding: 0 0 4px 0;
      background: #ffffff;
      display: flex;
      flex-direction: column;
      height: 138mm;
      overflow: hidden;
    }
    .banner {
      background: #2f4a32;
      color: #ffffff;
      padding: 8px 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .brand h1 {
      margin: 0;
      font-size: 16px;
      letter-spacing: -0.01em;
      color: #ffffff;
      font-weight: 800;
    }
    .brand .tagline {
      margin: 1px 0 0 0;
      font-size: 8.5px;
      color: #c9d8c2;
    }
    .brand .contacts {
      margin: 1px 0 0 0;
      font-size: 7.5px;
      color: #a5bda0;
    }
    .copy-badge {
      border-radius: 5px;
      padding: 5px 12px;
      text-align: right;
      border: 1px solid rgba(255,255,255,0.4);
    }
    .badge-original {
      background: #e8f5e9;
      color: #1b5e20;
    }
    .badge-duplicate {
      background: #e3f2fd;
      color: #0d47a1;
    }
    .badge-title {
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.03em;
    }
    .badge-sub {
      font-size: 8.5px;
      font-style: italic;
      margin-top: 1px;
    }
    .meta-strip {
      background: #fbfaf7;
      border-bottom: 1px solid #e3ddcd;
      padding: 5px 14px;
      display: flex;
      justify-content: space-between;
      font-size: 10.5px;
      color: #444444;
    }
    .meta-strip .highlight {
      color: #1c2e1f;
      font-weight: 700;
    }
    .meta-strip .ref-code {
      color: #2f4a32;
      font-weight: 700;
    }
    .details-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      padding: 6px 14px;
    }
    .detail-box {
      background: #fcfbf8;
      border: 1px solid #e3ddcd;
      border-radius: 5px;
      padding: 6px 10px;
    }
    .box-title {
      font-size: 8px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #8a8578;
      font-weight: 700;
      margin-bottom: 2px;
    }
    .vendor-name {
      font-size: 11px;
      font-weight: 700;
      color: #1c2e1f;
    }
    .vendor-contact, .vendor-loc {
      font-size: 9px;
      color: #555555;
      margin-top: 1px;
    }
    .text-success { color: #1b5e20 !important; }
    .text-danger { color: #b71c1c !important; }
    .amount-card {
      margin: 0 14px 6px 14px;
      background: #f0f7f1;
      border: 1.2px solid #2f4a32;
      border-radius: 6px;
      padding: 6px 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .amount-label {
      font-size: 8.5px;
      font-weight: 700;
      color: #2f4a32;
      letter-spacing: 0.03em;
    }
    .amount-val {
      font-size: 17px;
      font-weight: 800;
      color: #1b5e20;
      margin-top: 1px;
    }
    .amount-words-box {
      max-width: 60%;
      text-align: right;
    }
    .amount-words-label {
      font-size: 8px;
      color: #6b7a63;
      text-transform: uppercase;
    }
    .amount-words-val {
      font-size: 9.5px;
      font-weight: 600;
      font-style: italic;
      color: #1c2e1f;
      line-height: 1.25;
    }
    .items-table {
      width: calc(100% - 28px);
      margin: 0 14px 6px 14px;
      border-collapse: collapse;
      font-size: 9.5px;
    }
    .items-table th {
      background: #2f4a32;
      color: #ffffff;
      padding: 4px 8px;
      text-align: left;
      font-size: 8.5px;
      text-transform: uppercase;
      font-weight: 700;
    }
    .items-table td {
      padding: 5px 8px;
      border: 1px solid #e3ddcd;
      background: #ffffff;
    }
    .text-right { text-align: right; }
    .sig-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      padding: 0 14px;
      margin-top: auto;
    }
    .sig-box {
      background: #faf9f5;
      border: 1px solid #e3ddcd;
      border-radius: 5px;
      padding: 6px 10px;
    }
    .sig-title {
      font-size: 8px;
      text-transform: uppercase;
      font-weight: 700;
      color: #8a8578;
    }
    .sig-line {
      border-bottom: 1px solid #777777;
      margin: 18px 0 3px 0;
    }
    .sig-caption {
      font-size: 8.5px;
      color: #555555;
    }
    .sig-sub {
      font-size: 7.5px;
      font-style: italic;
      color: #888888;
    }
    .card-footer {
      border-top: 1px solid #e3ddcd;
      margin-top: 4px;
      padding: 3px 14px 0 14px;
      font-size: 7px;
      color: #8a8578;
      display: flex;
      justify-content: space-between;
    }
    .cut-divider {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 4px 0;
      color: #666666;
    }
    .cut-line {
      flex: 1;
      border-bottom: 1.5px dashed #888888;
    }
    .cut-text {
      font-size: 8.5px;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      display: flex;
      align-items: center;
      gap: 6px;
      color: #444444;
    }
    .cut-sub {
      font-size: 7.5px;
      color: #777777;
      font-weight: normal;
      margin-left: 6px;
    }
    @media print {
      body {
        background: #ffffff !important;
        padding: 0 !important;
      }
      .print-actions {
        display: none !important;
      }
      .a4-page {
        box-shadow: none !important;
        margin: 0 !important;
        padding: 6mm 8mm !important;
        width: 100% !important;
        height: 297mm !important;
        max-height: 297mm !important;
      }
    }
  </style>
</head>
<body>
  <div class="print-actions">
    <button class="btn" onclick="window.print()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
      Print Receipt (A4 Dual Copy)
    </button>
  </div>

  <div class="a4-page">
    {$originalHtml}

    <div class="cut-divider">
      <div class="cut-line"></div>
      <div class="cut-text">
        <span>&#9986; CUT ALONG DOTTED LINE</span>
        <span class="cut-sub">(Top: Original Payer Copy &bull; Bottom: Duplicate Accounts Copy)</span>
      </div>
      <div class="cut-line"></div>
    </div>

    {$duplicateHtml}
  </div>
</body>
</html>
HTML;
    }

    private function formatNumber(float $number): string
    {
        return number_format($number, 2);
    }

    /**
     * Convert currency number into English words (Ghana Cedis and Pesewas).
     */
    public function amountToWords(float $amount): string
    {
        if ($amount <= 0) {
            return 'Zero Ghana Cedis Only';
        }

        $units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $cedis = (int) floor($amount);
        $pesewas = (int) round(($amount - $cedis) * 100);

        $convertGroup = function (int $n) use ($units, $tens, &$convertGroup): string {
            $str = '';
            if ($n >= 100) {
                $str .= $units[(int) ($n / 100)].' Hundred';
                $n %= 100;
                if ($n > 0) {
                    $str .= ' and ';
                }
            }
            if ($n >= 20) {
                $str .= $tens[(int) ($n / 10)];
                if ($n % 10 > 0) {
                    $str .= '-'.$units[$n % 10];
                }
            } elseif ($n > 0) {
                $str .= $units[$n];
            }
            return $str;
        };

        $words = '';
        if ($cedis >= 1000000) {
            $millions = (int) floor($cedis / 1000000);
            $words .= $convertGroup($millions).' Million ';
            $cedis %= 1000000;
        }

        if ($cedis >= 1000) {
            $thousands = (int) floor($cedis / 1000);
            $words .= $convertGroup($thousands).' Thousand ';
            $cedis %= 1000;
        }

        if ($cedis > 0) {
            $words .= $convertGroup($cedis).' ';
        }

        $words = trim($words);
        if ($words === '') {
            $words = 'Zero';
        }

        $result = $words.' Ghana '.($words === 'One' ? 'Cedi' : 'Cedis');

        if ($pesewas > 0) {
            $pesewaWords = $convertGroup($pesewas);
            $result .= ' and '.$pesewaWords.' '.($pesewas === 1 ? 'Pesewa' : 'Pesewas');
        } else {
            $result .= ' Only';
        }

        return $result;
    }
}
