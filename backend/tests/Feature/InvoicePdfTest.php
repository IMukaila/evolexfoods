<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_download_invoice_pdf(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create([
            'name' => 'Ghana Feeds Ltd',
            'code' => 'SUP-001',
            'contact_name' => 'Kwame Mensah',
            'phone' => '+233 24 123 4567',
            'email' => 'kwame@ghanafeeds.com',
            'location' => 'Tema Industrial Area',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-TEST-001',
            'supplier_id' => $supplier->id,
            'amount' => 4500.00,
            'invoice_date' => now()->subDays(5),
            'due_date' => now()->addDays(10),
            'status' => 'partial',
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 2000.00,
            'paid_at' => now()->subDays(2),
            'method' => 'bank_transfer',
            'reference' => 'TXN-984210',
        ]);

        $response = $this->actingAs($user)->get("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('INV-2026-TEST-001.pdf', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        $this->assertStringEndsWith('%%EOF', trim($response->getContent()));

        // Also test /records/invoices/{id}/pdf alias
        $aliasResponse = $this->actingAs($user)->get("/api/v1/records/invoices/{$invoice->id}/pdf");
        $aliasResponse->assertStatus(200);
        $aliasResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_view_invoice_html(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-HTML-002',
            'amount' => 1200.00,
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'unpaid',
        ]);

        $response = $this->actingAs($user)->get("/api/v1/invoices/{$invoice->id}/html");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $content = $response->getContent();
        $this->assertStringContainsString('EVOLEX FOODS LTD', $content);
        $this->assertStringContainsString('INV-2026-HTML-002', $content);
        $this->assertStringContainsString('window.print()', $content);
    }

    public function test_unauthenticated_request_cannot_download_pdf(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-UNAUTH',
            'amount' => 500.00,
            'invoice_date' => now(),
            'status' => 'unpaid',
        ]);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/pdf");
        $response->assertStatus(401);
    }
}
