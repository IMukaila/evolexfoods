<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\SalesCull;
use App\Models\User;
use App\Services\SaleReceiptPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleReceiptPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_download_sale_receipt_pdf(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $animal = Animal::create([
            'animal_id' => 'BOV-2026-088',
            'tag_id' => 'TAG-8821',
            'species' => 'cattle',
            'breed' => 'Sanga Cross',
            'sex' => 'female',
            'status' => 'sold',
        ]);

        $sale = SalesCull::create([
            'animal_id' => $animal->id,
            'type' => 'sale',
            'event_date' => now()->subDays(2),
            'buyer' => 'Kasoa Prime Abattoir & Butchery',
            'amount' => 3850.50,
            'notes' => 'Healthy adult cow sold at standard live-weight rate.',
        ]);

        $response = $this->actingAs($user)->get("/api/v1/sales/{$sale->id}/receipt");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('receipt-sale-', $response->headers->get('Content-Disposition') ?? '');

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringEndsWith('%%EOF', trim($content));

        // Assert both ORIGINAL and DUPLICATE are included in the PDF content
        $this->assertStringContainsString('ORIGINAL', $content);
        $this->assertStringContainsString('DUPLICATE', $content);
        $this->assertStringContainsString('CUT ALONG DOTTED LINE', $content);
        $this->assertStringContainsString('BOV-2026-088', $content);
        $this->assertStringContainsString('Kasoa Prime Abattoir', $content);
    }

    public function test_can_generate_pdf_directly_via_service(): void
    {
        $animal = Animal::create([
            'animal_id' => 'OVI-2026-015',
            'tag_id' => 'TAG-1502',
            'species' => 'sheep',
            'breed' => 'Djallonke',
            'sex' => 'male',
            'status' => 'sold',
        ]);

        $sale = SalesCull::create([
            'animal_id' => $animal->id,
            'type' => 'sale',
            'event_date' => now(),
            'buyer' => 'Accra Farm Direct',
            'amount' => 1200.00,
            'notes' => 'Ram sold for breeding.',
        ]);

        $service = new SaleReceiptPdfService();
        $pdfContent = $service->generatePdf($sale);

        $this->assertStringStartsWith('%PDF-1.4', $pdfContent);
        $this->assertStringContainsString('ORIGINAL', $pdfContent);
        $this->assertStringContainsString('DUPLICATE', $pdfContent);
        $this->assertStringContainsString('OVI-2026-015', $pdfContent);
    }

    public function test_alternate_routes_work(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $animal = Animal::create([
            'animal_id' => 'CAP-2026-031',
            'tag_id' => 'TAG-3101',
            'species' => 'goat',
            'breed' => 'West African Dwarf',
            'sex' => 'female',
            'status' => 'sold',
        ]);

        $sale = SalesCull::create([
            'animal_id' => $animal->id,
            'type' => 'sale',
            'event_date' => now(),
            'buyer' => 'Mr. Mensah',
            'amount' => 850.00,
        ]);

        $res1 = $this->actingAs($user)->get("/api/v1/sales/{$sale->id}/pdf");
        $res1->assertStatus(200);

        $res2 = $this->actingAs($user)->get("/api/v1/records/sales/{$sale->id}/receipt");
        $res2->assertStatus(200);

        $res3 = $this->actingAs($user)->get("/api/v1/records/sales/{$sale->id}/pdf");
        $res3->assertStatus(200);
    }
}
