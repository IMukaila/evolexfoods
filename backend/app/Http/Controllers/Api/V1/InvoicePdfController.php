<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __construct(private readonly InvoicePdfService $pdfService)
    {
    }

    /**
     * Download or view the PDF document for an invoice.
     */
    public function downloadPdf(Request $request, int $id): Response
    {
        $invoice = Invoice::with(['supplier', 'payments'])->findOrFail($id);
        $binaryPdf = $this->pdfService->generatePdf($invoice);

        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $invoice->invoice_number).'.pdf';
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($binaryPdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $filename),
            'Content-Length' => strlen($binaryPdf),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Render the standalone printable HTML view for an invoice.
     */
    public function viewHtml(Request $request, int $id): Response
    {
        $invoice = Invoice::with(['supplier', 'payments'])->findOrFail($id);
        $html = $this->pdfService->generateHtml($invoice);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
