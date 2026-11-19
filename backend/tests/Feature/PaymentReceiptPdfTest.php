<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PaymentReceiptPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReceiptPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_download_payment_receipt_pdf(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create([
            'name' => 'Gold Coast Agro Supplies',
            'code' => 'SUP-007',
            'contact_name' => 'Kojo Arthur',
            'phone' => '+233 20 888 9999',
            'email' => 'kojo@goldcoastagro.com',
            'location' => 'Winneba Road, Central Region',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-PAY-010',
            'supplier_id' => $supplier->id,
            'amount' => 5000.00,
            'invoice_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
            'status' => 'partial',
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 2500.00,
            'paid_at' => now()->subDay(),
            'method' => 'bank_transfer',
            'reference' => 'STANBIC-VOUCHER-1102',
        ]);

        $response = $this->actingAs($user)->get("/api/v1/payments/{$payment->id}/receipt");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('receipt-payment-', $response->headers->get('Content-Disposition') ?? '');
        
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringEndsWith('%%EOF', trim($content));
        
        // Assert both ORIGINAL and DUPLICATE are included in the PDF content
        $this->assertStringContainsString('ORIGINAL', $content);
        $this->assertStringContainsString('DUPLICATE', $content);
        $this->assertStringContainsString('CUT ALONG DOTTED LINE', $content);

        // Also test the /records/payments/{id}/receipt alias
        $aliasResponse = $this->actingAs($user)->get("/api/v1/records/payments/{$payment->id}/receipt");
        $aliasResponse->assertStatus(200);
        $aliasResponse->assertHeader('Content-Type', 'application/pdf');

        // Also test /payments/{id}/pdf
        $pdfResponse = $this->actingAs($user)->get("/api/v1/payments/{$payment->id}/pdf");
        $pdfResponse->assertStatus(200);
        $pdfResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_view_payment_receipt_html_with_dual_halves(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create([
            'name' => 'Cape Coast Poultry Feed Co',
            'code' => 'SUP-009',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-DUAL-003',
            'supplier_id' => $supplier->id,
            'amount' => 1800.00,
            'invoice_date' => now(),
            'status' => 'paid',
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 1800.00,
            'paid_at' => now(),
            'method' => 'mobile_money',
            'reference' => 'MTN-MOMO-882190',
        ]);

        $response = $this->actingAs($user)->get("/api/v1/payments/{$payment->id}/html");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $html = $response->getContent();

        $this->assertStringContainsString('EVOLEX FOODS LTD', $html);
        $this->assertStringContainsString('ORIGINAL', $html);
        $this->assertStringContainsString('PAYER / VENDOR COPY', $html);
        $this->assertStringContainsString('DUPLICATE', $html);
        $this->assertStringContainsString('ACCOUNTS OFFICE COPY', $html);
        $this->assertStringContainsString('CUT ALONG DOTTED LINE', $html);
        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('MTN-MOMO-882190', $html);
        $this->assertStringContainsString('Cape Coast Poultry Feed Co', $html);
    }

    public function test_can_generate_receipt_for_orphan_payment(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $payment = Payment::create([
            'invoice_id' => null,
            'amount' => 350.00,
            'paid_at' => now(),
            'method' => 'cash',
            'reference' => 'CASH-PETTY-01',
        ]);

        $response = $this->actingAs($user)->get("/api/v1/payments/{$payment->id}/receipt");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $htmlResponse = $this->actingAs($user)->get("/api/v1/payments/{$payment->id}/html");
        $htmlResponse->assertStatus(200);
        $this->assertStringContainsString('CASH-PETTY-01', $htmlResponse->getContent());
    }

    public function test_amount_to_words_conversion(): void
    {
        $service = new PaymentReceiptPdfService();

        $this->assertSame('Two Thousand Five Hundred Ghana Cedis Only', $service->amountToWords(2500.00));
        $this->assertSame('One Thousand Two Hundred and Fifty Ghana Cedis and Fifty Pesewas', $service->amountToWords(1250.50));
        $this->assertSame('One Ghana Cedi Only', $service->amountToWords(1.00));
        $this->assertSame('Zero Ghana Cedis Only', $service->amountToWords(0.00));
    }

    public function test_unauthenticated_request_cannot_download_receipt(): void
    {
        $payment = Payment::create([
            'amount' => 100.00,
            'paid_at' => now(),
            'method' => 'cash',
        ]);

        $response = $this->getJson("/api/v1/payments/{$payment->id}/receipt");
        $response->assertStatus(401);
    }
}
