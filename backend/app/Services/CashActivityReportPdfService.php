<?php

namespace App\Services;

class CashActivityReportPdfService
{
    /**
     * Generate a binary PDF 1.4 document for the Cash Activity Register report.
     *
     * @param array $entries Array of [ 'date' => ..., 'type' => ..., 'reference' => ..., 'description' => ..., 'method' => ..., 'amount' => ... ]
     * @param array $meta    Array of [ 'from' => ..., 'to' => ..., 'filterSummary' => ..., 'totals' => [...] ]
     */
    public function generatePdf(array $entries, array $meta = []): string
    {
        $pdf = new SimplePdfDocument();
        $pdf->addPage();

        $fromStr = $meta['from'] ?? now()->subDays(30)->toDateString();
        $toStr = $meta['to'] ?? now()->toDateString();
        $filterSummary = $meta['filterSummary'] ?? 'All Transactions';
        $generatedAt = now()->format('d M Y \a\t H:i');

        $totalAmount = 0.0;
        $totalExpenses = 0.0;
        $totalPayments = 0.0;
        foreach ($entries as $e) {
            $amt = (float) ($e['amount'] ?? 0);
            $totalAmount += $amt;
            if (($e['type'] ?? '') === 'Expense') {
                $totalExpenses += $amt;
            } else {
                $totalPayments += $amt;
            }
        }

        $pageNumber = 1;
        $y = $this->renderHeader($pdf, $fromStr, $toStr, $generatedAt, $filterSummary, $totalAmount, $totalExpenses, $totalPayments, count($entries));

        $y = $this->renderTableHeader($pdf, $y);

        $rowCount = count($entries);
        foreach ($entries as $idx => $entry) {
            // Check if row exceeds page height (leave room for row + footer/signatures)
            if ($y > 740) {
                $this->renderPageFooter($pdf, $pageNumber);
                $pdf->addPage();
                $pageNumber++;
                $y = 40;
                $pdf->text('EVOLEX FOODS LTD - CASH ACTIVITY REGISTER (CONTINUED)', 40, $y, 'F2', 10, '#2f4a32');
                $y += 18;
                $y = $this->renderTableHeader($pdf, $y);
            }

            $dateFormatted = date('d M Y', strtotime($entry['date'] ?? now()->toDateString()));
            $type = $entry['type'] ?? 'Expense';
            $ref = $this->truncate($entry['reference'] ?? '—', 18);
            $desc = $this->truncate($entry['description'] ?? 'Operating expense', 35);
            $method = ucwords(str_replace('_', ' ', $entry['method'] ?? 'Manual'));
            $amtFormatted = 'GHc ' . number_format((float) ($entry['amount'] ?? 0), 2);

            $isEven = ($idx % 2 === 0);
            $rowBg = $isEven ? '#ffffff' : '#fcfaf6';
            $pdf->rect(40, $y - 10, 515.28, 16, $rowBg, '#ece6da', 0.5);

            $pdf->text((string) ($idx + 1), 46, $y + 1, 'F1', 7.5, '#777777');
            $pdf->text($dateFormatted, 66, $y + 1, 'F2', 8, '#1c2e1f');

            // Activity Chip
            $isPayment = ($type === 'Supplier payment');
            $chipBg = $isPayment ? '#fff8e1' : '#e8f5e9';
            $chipText = $isPayment ? '#b78103' : '#1b5e20';
            $chipLabel = $isPayment ? 'PAYMENT' : 'EXPENSE';
            $pdf->rect(130, $y - 7, 52, 11, $chipBg, $chipText, 0.4);
            $pdf->text($chipLabel, 134, $y + 1, 'F2', 6.5, $chipText);

            $pdf->text($ref, 188, $y + 1, 'F2', 8, '#1c2e1f');
            $pdf->text($desc, 275, $y + 1, 'F1', 8, '#333333');
            $pdf->text($this->truncate($method, 14), 430, $y + 1, 'F1', 7.5, '#555555');

            // Right-aligned amount
            $pdf->text($amtFormatted, 500, $y + 1, 'F2', 8, '#1c2e1f');

            $y += 16;
        }

        // Table Total Row
        if ($y > 720) {
            $this->renderPageFooter($pdf, $pageNumber);
            $pdf->addPage();
            $pageNumber++;
            $y = 40;
        }

        $pdf->rect(40, $y - 9, 515.28, 18, '#f4efe4', '#2f4a32', 1);
        $pdf->text('TOTAL DISBURSED (' . $rowCount . ' TRANSACTIONS):', 270, $y + 3, 'F2', 8.5, '#2f4a32');
        $pdf->text('GHc ' . number_format($totalAmount, 2), 495, $y + 3, 'F2', 9, '#2f4a32');
        $y += 28;

        // Signatures Section
        if ($y > 700) {
            $this->renderPageFooter($pdf, $pageNumber);
            $pdf->addPage();
            $pageNumber++;
            $y = 50;
        }

        $pdf->line(40, $y + 20, 240, $y + 20, '#999999', 0.8);
        $pdf->text('Prepared By: Accounts Officer', 40, $y + 32, 'F2', 8, '#1c2e1f');
        $pdf->text('Signature: __________________   Date: _________', 40, $y + 44, 'F1', 7.5, '#666666');

        $pdf->line(315, $y + 20, 515, $y + 20, '#999999', 0.8);
        $pdf->text('Verified & Approved By: Finance Director', 315, $y + 32, 'F2', 8, '#1c2e1f');
        $pdf->text('Signature: __________________   Date: _________', 315, $y + 44, 'F1', 7.5, '#666666');

        $this->renderPageFooter($pdf, $pageNumber);

        return $pdf->render();
    }

    private function renderHeader(SimplePdfDocument $pdf, string $from, string $to, string $generatedAt, string $filterSummary, float $total, float $expenses, float $payments, int $count): float
    {
        // Top Banner
        $pdf->rect(0, 0, 595.28, 70, '#2f4a32');
        $pdf->text('EVOLEX FOODS LTD', 40, 28, 'F2', 17, '#ffffff');
        $pdf->text('Agricultural Production & Supply Chain Management | Accra - Central Region, Ghana', 40, 44, 'F1', 8.5, '#c9d8c2');
        $pdf->text('finance@evolexfoods.com | +233 24 000 0000', 40, 57, 'F1', 7.5, '#a5bda0');

        $pdf->text('OFFICIAL FINANCIAL RECORD', 380, 28, 'F2', 9.5, '#ffffff');
        $pdf->text('Period: ' . $from . ' to ' . $to, 380, 42, 'F1', 8, '#c9d8c2');
        $pdf->text('Generated: ' . $generatedAt, 380, 55, 'F1', 7.5, '#c9d8c2');

        // Document Title Area
        $y = 92;
        $pdf->text('CASH ACTIVITY REGISTER', 40, $y, 'F2', 14, '#1c2e1f');
        $pdf->text('Itemized transaction detail of operating expenses and invoice payments.', 40, $y + 13, 'F1', 8.5, '#666666');

        // Filter Tag
        $summaryText = $this->truncate('View: ' . $filterSummary, 55);
        $pdf->rect(340, $y - 12, 215.28, 20, '#f4efe4', '#d9d0bf', 0.8);
        $pdf->text($summaryText, 346, $y + 1, 'F2', 7.5, '#2f4a32');

        $y += 28;

        // KPI Strip (4 mini cards)
        $cardW = 122.0;
        $cardH = 34.0;
        $gap = 9.0;
        $x = 40.0;

        // Total
        $pdf->rect($x, $y, $cardW, $cardH, '#2f4a32', '#2f4a32', 1);
        $pdf->text('TOTAL OUTFLOW', $x + 8, $y + 11, 'F2', 6.5, '#c9d8c2');
        $pdf->text('GHc ' . number_format($total, 2), $x + 8, $y + 26, 'F2', 9.5, '#ffffff');

        // Expenses
        $x += $cardW + $gap;
        $pdf->rect($x, $y, $cardW, $cardH, '#fbfaf7', '#e3ddcd', 0.8);
        $pdf->text('OPERATING EXPENSES', $x + 8, $y + 11, 'F2', 6.5, '#8a8578');
        $pdf->text('GHc ' . number_format($expenses, 2), $x + 8, $y + 26, 'F2', 9.5, '#1c2e1f');

        // Supplier Payments
        $x += $cardW + $gap;
        $pdf->rect($x, $y, $cardW, $cardH, '#fbfaf7', '#e3ddcd', 0.8);
        $pdf->text('SUPPLIER PAYMENTS', $x + 8, $y + 11, 'F2', 6.5, '#8a8578');
        $pdf->text('GHc ' . number_format($payments, 2), $x + 8, $y + 26, 'F2', 9.5, '#1c2e1f');

        // Count
        $x += $cardW + $gap;
        $pdf->rect($x, $y, $cardW, $cardH, '#fbfaf7', '#e3ddcd', 0.8);
        $pdf->text('RECORD COUNT', $x + 8, $y + 11, 'F2', 6.5, '#8a8578');
        $pdf->text($count . ' entries', $x + 8, $y + 26, 'F2', 9.5, '#1c2e1f');

        return $y + $cardH + 16;
    }

    private function renderTableHeader(SimplePdfDocument $pdf, float $y): float
    {
        $pdf->rect(40, $y - 10, 515.28, 16, '#2f4a32', '#2f4a32', 1);
        $pdf->text('#', 46, $y + 1, 'F2', 7.5, '#ffffff');
        $pdf->text('DATE', 66, $y + 1, 'F2', 7.5, '#ffffff');
        $pdf->text('ACTIVITY', 132, $y + 1, 'F2', 7.5, '#ffffff');
        $pdf->text('REFERENCE', 188, $y + 1, 'F2', 7.5, '#ffffff');
        $pdf->text('DESCRIPTION / VENDOR', 275, $y + 1, 'F2', 7.5, '#ffffff');
        $pdf->text('METHOD', 430, $y + 1, 'F2', 7.5, '#ffffff');
        $pdf->text('AMOUNT', 505, $y + 1, 'F2', 7.5, '#ffffff');

        return $y + 16;
    }

    private function renderPageFooter(SimplePdfDocument $pdf, int $pageNumber): void
    {
        $pdf->line(40, 805, 555.28, 805, '#d9d0bf', 0.6);
        $pdf->text('Evolex Foods Financial Management System | Official Verified Record', 40, 818, 'F1', 7.5, '#888888');
        $pdf->text('Page ' . $pageNumber, 520, 818, 'F1', 7.5, '#888888');
    }

    private function truncate(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 2) . '..' : $text;
    }
}
