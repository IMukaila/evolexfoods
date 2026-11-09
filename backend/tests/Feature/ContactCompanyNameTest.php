<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactCompanyNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_accepts_and_displays_a_free_text_company_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'active']));

        $this->postJson('/api/v1/records/contacts', [
            'name' => 'Ama Mensah',
            'company_name' => 'Savannah Foods Ltd',
        ])->assertCreated()
            ->assertJsonPath('company_name', 'Savannah Foods Ltd');

        $this->getJson('/api/v1/records/contacts')
            ->assertOk()
            ->assertJsonPath('data.0.company_name', 'Savannah Foods Ltd');

        $this->assertSame('Savannah Foods Ltd', Contact::firstOrFail()->company_name);
    }

    public function test_existing_company_relationships_remain_supported(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'active']));
        $company = Company::create(['name' => 'Existing Company']);

        $this->postJson('/api/v1/records/contacts', [
            'name' => 'Legacy Linked Contact',
            'company_id' => $company->id,
        ])->assertCreated()
            ->assertJsonPath('company_id', $company->id)
            ->assertJsonPath('company.id', $company->id);
    }
}
