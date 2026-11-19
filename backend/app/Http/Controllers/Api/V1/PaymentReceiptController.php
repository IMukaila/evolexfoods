<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentReceiptPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentReceiptController extends Controller
{
    public function __construct(private readonly PaymentReceiptPdfService $receiptService)
    {
    }

    /**
     * Download or view the 2-part (Original & Duplicate) A4 PDF receipt for a payment.
     */
    public function downloadPdf(Request $request, int $id): Response
    {
        $payment = Payment::with(['invoice.supplier', 'invoice.payments'])->findOrFail($id);
        $binaryPdf = $this->receiptService->generatePdf($payment);

        $receiptNo = 'receipt-payment-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT).'.pdf';
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($binaryPdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $receiptNo),
            'Content-Length' => strlen($binaryPdf),
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Render the standalone printable HTML view for a payment receipt (Original & Duplicate on A4).
     */
    public function viewHtml(Request $request, int $id): Response
    {
        $payment = Payment::with(['invoice.supplier', 'invoice.payments'])->findOrFail($id);
        $html = $this->receiptService->generateHtml($payment);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
