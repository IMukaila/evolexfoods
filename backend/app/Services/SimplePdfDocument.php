<?php

namespace App\Services;

/**
 * Pure PHP PDF 1.4 Document Generator (No external dependencies).
 */
class SimplePdfDocument
{
    private array $pages = [];
    private string $currentStream = '';

    public function addPage(): void
    {
        if ($this->currentStream !== '') {
            $this->pages[] = $this->currentStream;
        }
        $this->currentStream = '';
    }

    public function rect(float $x, float $y, float $w, float $h, string $fillColor = '', string $strokeColor = '', float $lineWidth = 1): void
    {
        $cmd = "q\n";
        if ($fillColor !== '') {
            [$r, $g, $b] = $this->hexToRgb($fillColor);
            $cmd .= sprintf("%.3f %.3f %.3f rg\n", $r, $g, $b);
        }
        if ($strokeColor !== '') {
            [$r, $g, $b] = $this->hexToRgb($strokeColor);
            $cmd .= sprintf("%.3f %.3f %.3f RG\n", $r, $g, $b);
            $cmd .= sprintf("%.2f w\n", $lineWidth);
        }
        $pdfY = 841.89 - $y - $h;
        $cmd .= sprintf("%.2f %.2f %.2f %.2f re\n", $x, $pdfY, $w, $h);
        if ($fillColor !== '' && $strokeColor !== '') {
            $cmd .= "B\n";
        } elseif ($fillColor !== '') {
            $cmd .= "f\n";
        } elseif ($strokeColor !== '') {
            $cmd .= "S\n";
        }
        $cmd .= "Q\n";
        $this->currentStream .= $cmd;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#cccccc', float $lineWidth = 1): void
    {
        [$r, $g, $b] = $this->hexToRgb($color);
        $pdfY1 = 841.89 - $y1;
        $pdfY2 = 841.89 - $y2;
        $cmd = sprintf("q\n%.3f %.3f %.3f RG\n%.2f w\n%.2f %.2f m\n%.2f %.2f l\nS\nQ\n", $r, $g, $b, $lineWidth, $x1, $pdfY1, $x2, $pdfY2);
        $this->currentStream .= $cmd;
    }

    public function dashedLine(float $x1, float $y1, float $x2, float $y2, string $color = '#888888', float $lineWidth = 1, array $dash = [4, 4]): void
    {
        [$r, $g, $b] = $this->hexToRgb($color);
        $pdfY1 = 841.89 - $y1;
        $pdfY2 = 841.89 - $y2;
        $dashStr = sprintf("[%s] 0 d\n", implode(' ', $dash));
        $cmd = sprintf("q\n%.3f %.3f %.3f RG\n%.2f w\n%s%.2f %.2f m\n%.2f %.2f l\nS\nQ\n", $r, $g, $b, $lineWidth, $dashStr, $x1, $pdfY1, $x2, $pdfY2);
        $this->currentStream .= $cmd;
    }

    public function text(string $text, float $x, float $y, string $font = 'F1', float $size = 10, string $color = '#000000'): void
    {
        [$r, $g, $b] = $this->hexToRgb($color);
        $pdfY = 841.89 - $y;
        $safeText = $this->escapeText($text);
        $cmd = sprintf("BT\n/%s %.2f Tf\n%.3f %.3f %.3f rg\n%.2f %.2f Td\n(%s) Tj\nET\n", $font, $size, $r, $g, $b, $x, $pdfY, $safeText);
        $this->currentStream .= $cmd;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    private function escapeText(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $text = str_replace('₵', 'GHc', $text);
        return $text;
    }

    public function render(): string
    {
        if ($this->currentStream !== '') {
            $this->pages[] = $this->currentStream;
        }

        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $pageCount = count($this->pages);
        $pageRefs = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageRefs[] = (3 + $i * 2).' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageRefs).'] /Count '.$pageCount.' >>';

        $fontRegular = 3 + $pageCount * 2;
        $fontBold = $fontRegular + 1;
        $fontOblique = $fontRegular + 2;

        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjNum = 3 + $i * 2;
            $streamObjNum = $pageObjNum + 1;
            $stream = $this->pages[$i];
            $streamLen = strlen($stream);

            $objs[$pageObjNum] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents %d 0 R /Resources << /Font << /F1 %d 0 R /F2 %d 0 R /F3 %d 0 R >> /ProcSet [/PDF /Text] >> >>',
                $streamObjNum,
                $fontRegular,
                $fontBold,
                $fontOblique
            );
            $objs[$streamObjNum] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", $streamLen, $stream);
        }

        $objs[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objs[$fontOblique] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';

        $totalObjs = count($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        for ($i = 1; $i <= $totalObjs; $i++) {
            $offsets[$i] = strlen($out);
            $out .= sprintf("%d 0 obj\n%s\nendobj\n", $i, $objs[$i]);
        }

        $xrefOffset = strlen($out);
        $out .= sprintf("xref\n0 %d\n0000000000 65535 f \n", $totalObjs + 1);
        for ($i = 1; $i <= $totalObjs; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF", $totalObjs + 1, $xrefOffset);

        return $out;
    }
}
