<?php

namespace App\Services;

use App\Models\SalesCull;

class SaleReceiptPdfService
{
    /**
     * Generate a binary PDF 1.4 document containing both ORIGINAL (top half)
     * and DUPLICATE (bottom half) on a single A4 sheet for a livestock sale.
     */
    public function generatePdf(SalesCull $sale): string
    {
        $sale->loadMissing(['animal']);

        $pdf = new SimplePdfDocument();
        $pdf->addPage();

        $receiptNo = 'SLS-' . str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT);
        $dateStr = $sale->event_date ? $sale->event_date->format('d M Y') : now()->format('d M Y');
        $typeLabel = $sale->type === 'cull' ? 'Livestock Cull / Disposal' : 'Commercial Livestock Sale';
        $buyerName = $sale->buyer ?: 'General Buyer / Walk-in Customer';
        $animal = $sale->animal;
        $animalId = $animal ? $animal->animal_id : ('Animal #' . $sale->animal_id);
        $tagId = $animal?->tag_id ? ('#' . $animal->tag_id) : 'N/A';
        $species = $animal?->species ? ucfirst($animal->species) : 'Livestock';
        $breed = $animal?->breed ?: 'Standard';
        $sex = $animal?->sex ? ucfirst($animal->sex) : 'N/A';

        $amount = (float) ($sale->amount ?? 0);
        $amountFormatted = 'GHc ' . number_format($amount, 2);
        $amountWords = $this->amountToWords($amount);

        // 1. TOP HALF: ORIGINAL (Buyer Copy)
        $this->renderReceiptHalf(
            pdf: $pdf,
            yBase: 20,
            copyType: 'ORIGINAL',
            copySubtitle: 'BUYER / CUSTOMER COPY',
            copyBadgeBg: '#e8f5e9',
            copyBadgeText: '#1b5e20',
            receiptNo: $receiptNo,
            dateStr: $dateStr,
            typeLabel: $typeLabel,
            buyerName: $buyerName,
            animalId: $animalId,
            tagId: $tagId,
            species: $species,
            breed: $breed,
            sex: $sex,
            notes: $sale->notes ?: 'Livestock verified and handed over.',
            amount: $amount,
            amountFormatted: $amountFormatted,
            amountWords: $amountWords
        );

        // 2. CENTER PERFORATION / CUT LINE (at Y = 412 pt)
        $pdf->dashedLine(25, 412, 570, 412, '#888888', 1, [4, 3]);
        $pdf->text('- - - 8< - - - CUT ALONG DOTTED LINE - - - - - - - - - - - - - - - - - - - - - - - 8< - - -', 125, 410, 'F1', 7.5, '#777777');
        $pdf->text('( Top Half: ORIGINAL / Buyer Copy  |  Bottom Half: DUPLICATE / Farm Accounts Office Copy )', 125, 421, 'F3', 7, '#999999');

        // 3. BOTTOM HALF: DUPLICATE (Accounts Office Copy)
        $this->renderReceiptHalf(
            pdf: $pdf,
            yBase: 432,
            copyType: 'DUPLICATE',
            copySubtitle: 'ACCOUNTS & FARM OFFICE COPY',
            copyBadgeBg: '#e3f2fd',
            copyBadgeText: '#0d47a1',
            receiptNo: $receiptNo,
            dateStr: $dateStr,
            typeLabel: $typeLabel,
            buyerName: $buyerName,
            animalId: $animalId,
            tagId: $tagId,
            species: $species,
            breed: $breed,
            sex: $sex,
            notes: $sale->notes ?: 'Livestock verified and handed over.',
            amount: $amount,
            amountFormatted: $amountFormatted,
            amountWords: $amountWords
        );

        return $pdf->render();
    }

    private function renderReceiptHalf(
        SimplePdfDocument $pdf,
        float $yBase,
        string $copyType,
        string $copySubtitle,
        string $copyBadgeBg,
        string $copyBadgeText,
        string $receiptNo,
        string $dateStr,
        string $typeLabel,
        string $buyerName,
        string $animalId,
        string $tagId,
        string $species,
        string $breed,
        string $sex,
        string $notes,
        float $amount,
        string $amountFormatted,
        string $amountWords
    ): void {
        // Outer Card Box
        $pdf->rect(25, $yBase, 545, 378, '#ffffff', '#2f4a32', 1);

        // Header Banner (Evolex Brand Forest Green)
        $pdf->rect(25, $yBase, 545, 46, '#2f4a32');
        $pdf->text('EVOLEX FOODS LTD', 40, $yBase + 18, 'F2', 13.5, '#ffffff');
        $pdf->text('Agricultural Production & Supply Chain Management | Livestock Division', 40, $yBase + 30, 'F1', 7.5, '#c9d8c2');
        $pdf->text('Accra - Central Region, Ghana | finance@evolexfoods.com | +233 24 000 0000', 40, $yBase + 40, 'F1', 7, '#a5bda0');

        // Copy Badge (Top Right of Banner)
        $pdf->rect(370, $yBase + 8, 187, 30, $copyBadgeBg, '#ffffff', 0.8);
        $pdf->text('SALES RECEIPT: ' . $copyType, 380, $yBase + 21, 'F2', 9.5, $copyBadgeText);
        $pdf->text($copySubtitle, 380, $yBase + 32, 'F3', 7.5, $copyBadgeText);

        // Metadata Strip (Receipt #, Date, Event Type, Animal ID)
        $metaY = $yBase + 52;
        $pdf->rect(35, $metaY, 525, 22, '#fbfaf7', '#e3ddcd', 0.8);
        $pdf->text('Receipt No: ' . $receiptNo, 45, $metaY + 14, 'F2', 9, '#1c2e1f');
        $pdf->text('Date: ' . $dateStr, 175, $metaY + 14, 'F1', 8.5, '#444444');
        $pdf->text('Type: ' . $typeLabel, 275, $metaY + 14, 'F1', 8.5, '#444444');
        $pdf->text('Animal: ' . $animalId, 445, $metaY + 14, 'F2', 8.5, '#2f4a32');

        // Two-column section: Buyer / Customer (Left) & Livestock Asset Specs (Right)
        $boxY = $yBase + 80;
        // Left Box: Buyer / Customer
        $pdf->rect(35, $boxY, 255, 66, '#fcfbf8', '#e3ddcd', 0.8);
        $pdf->text('SOLD TO / BUYER DETAILS', 45, $boxY + 15, 'F2', 7.5, '#8a8578');
        $pdf->text($this->truncate($buyerName, 32), 45, $boxY + 30, 'F2', 10, '#1c2e1f');
        $pdf->text('Commercial Off-take & Handover Confirmed', 45, $boxY + 44, 'F1', 8, '#555555');
        $pdf->text('Location: Accra / Central Region, Ghana', 45, $boxY + 56, 'F1', 8, '#777777');

        // Right Box: Livestock Asset Specifications
        $pdf->rect(305, $boxY, 255, 66, '#fcfbf8', '#e3ddcd', 0.8);
        $pdf->text('LIVESTOCK SPECIFICATIONS', 315, $boxY + 15, 'F2', 7.5, '#8a8578');
        $pdf->text('ID: ' . $animalId . '  |  Tag: ' . $tagId, 315, $boxY + 30, 'F2', 9.5, '#1c2e1f');
        $pdf->text('Species & Breed: ' . $species . ' · ' . $breed, 315, $boxY + 44, 'F1', 8, '#555555');
        $pdf->text('Sex: ' . $sex . '  |  Status: Transferred to Buyer', 315, $boxY + 56, 'F1', 8, '#1b5e20');

        // Amount Summary Highlight Box
        $amtY = $yBase + 152;
        $pdf->rect(35, $amtY, 525, 46, '#f4efe4', '#2f4a32', 1);

        $pdf->text('AMOUNT RECEIVED / REALIZED VALUE:', 45, $amtY + 17, 'F2', 8, '#2f4a32');
        $pdf->text($amountFormatted, 45, $amtY + 36, 'F2', 14, '#2f4a32');

        $pdf->text('Amount in Words:', 260, $amtY + 17, 'F3', 7.5, '#666666');
        $pdf->text($this->truncate($amountWords, 48), 260, $amtY + 34, 'F2', 8.5, '#1c2e1f');

        // Itemized Breakdown Table
        $tblY = $yBase + 206;
        $pdf->rect(35, $tblY, 525, 17, '#2f4a32');
        $pdf->text('#', 43, $tblY + 12, 'F2', 7.5, '#ffffff');
        $pdf->text('ITEM DESCRIPTION & SPECIFICATIONS', 65, $tblY + 12, 'F2', 7.5, '#ffffff');
        $pdf->text('EVENT CATEGORY', 360, $tblY + 12, 'F2', 7.5, '#ffffff');
        $pdf->text('AMOUNT (GHS)', 485, $tblY + 12, 'F2', 7.5, '#ffffff');

        // Row 1
        $r1Y = $tblY + 17;
        $pdf->rect(35, $r1Y, 525, 26, '#ffffff', '#e3ddcd', 0.6);
        $pdf->text('1', 43, $r1Y + 16, 'F1', 8, '#555555');

        $desc = '1x Livestock Off-take: ' . $species . ' (' . $breed . ', ' . $sex . ') · Animal ID: ' . $animalId;
        $pdf->text($this->truncate($desc, 55), 65, $r1Y + 12, 'F2', 8, '#1c2e1f');
        $pdf->text('Notes: ' . $this->truncate($notes, 58), 65, $r1Y + 22, 'F1', 7.5, '#666666');

        $pdf->text($typeLabel, 360, $r1Y + 16, 'F1', 8, '#444444');
        $pdf->text($amountFormatted, 485, $r1Y + 16, 'F2', 8.5, '#1c2e1f');

        // Signatures Grid
        $sigY = $yBase + 262;
        $pdf->rect(35, $sigY, 255, 62, '#faf8f5', '#e3ddcd', 0.6);
        $pdf->text('ISSUED BY: SALES & FARM OFFICER', 45, $sigY + 14, 'F2', 7, '#8a8578');
        $pdf->line(45, $sigY + 36, 260, $sigY + 36, '#bbbbbb', 0.7);
        $pdf->text('Authorized Signature & Stamp', 45, $sigY + 46, 'F1', 7, '#555555');
        $pdf->text('Evolex Foods Ltd · Commercial Livestock Division', 45, $sigY + 55, 'F3', 6.5, '#888888');

        $pdf->rect(305, $sigY, 255, 62, '#faf8f5', '#e3ddcd', 0.6);
        $pdf->text('RECEIVED & ACKNOWLEDGED BY: BUYER', 315, $sigY + 14, 'F2', 7, '#8a8578');
        $pdf->line(315, $sigY + 36, 530, $sigY + 36, '#bbbbbb', 0.7);
        $pdf->text('Buyer Signature & Full Name', 315, $sigY + 46, 'F1', 7, '#555555');
        $pdf->text('Date: ________________________', 315, $sigY + 55, 'F3', 6.5, '#888888');

        // Footer Note
        $ftY = $yBase + 338;
        $pdf->line(35, $ftY, 560, $ftY, '#e3ddcd', 0.5);
        $pdf->text('Official computer-generated receipt · Evolex Foods Ltd · Ownership transfers upon full settlement and livestock handover.', 35, $ftY + 10, 'F1', 6.5, '#8a8578');
        $pdf->text('Valid without alteration · Verified commercial record', 380, $ftY + 10, 'F3', 6.5, '#8a8578');
    }

    private function truncate(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 2) . '..' : $text;
    }

    private function amountToWords(float $amount): string
    {
        if ($amount <= 0) {
            return 'Zero Ghana Cedis Only';
        }

        $units = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $numToWords = function (int $n) use (&$numToWords, $units, $tens): string {
            if ($n < 20) return $units[$n];
            if ($n < 100) return $tens[(int) ($n / 10)] . ($n % 10 ? ' ' . $units[$n % 10] : '');
            if ($n < 1000) return $units[(int) ($n / 100)] . ' Hundred' . ($n % 100 ? ' and ' . $numToWords($n % 100) : '');
            if ($n < 1000000) return $numToWords((int) ($n / 1000)) . ' Thousand' . ($n % 1000 ? ' ' . $numToWords($n % 1000) : '');
            return $numToWords((int) ($n / 1000000)) . ' Million' . ($n % 1000000 ? ' ' . $numToWords($n % 1000000) : '');
        };

        $cedis = (int) floor($amount);
        $pesewas = (int) round(($amount - $cedis) * 100);

        $words = $numToWords($cedis) . ' Ghana Cedi' . ($cedis === 1 ? '' : 's');
        if ($pesewas > 0) {
            $words .= ' and ' . $numToWords($pesewas) . ' Pesewa' . ($pesewas === 1 ? '' : 's');
        }

        return $words . ' Only';
    }
}
