<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalesCull;
use App\Services\SaleReceiptPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SaleReceiptController extends Controller
{
    public function __construct(private readonly SaleReceiptPdfService $receiptService)
    {
    }

    /**
     * Download or view the 2-part (Original & Duplicate) A4 PDF sales receipt for a livestock sale.
     */
    public function downloadPdf(Request $request, int $id): Response
    {
        $sale = SalesCull::with('animal')->findOrFail($id);
        $binaryPdf = $this->receiptService->generatePdf($sale);

        $receiptNo = 'receipt-sale-' . str_pad((string) $sale->id, 5, '0', STR_PAD_LEFT) . '.pdf';
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($binaryPdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $receiptNo),
            'Content-Length' => strlen($binaryPdf),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
