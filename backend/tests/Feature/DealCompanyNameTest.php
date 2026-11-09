<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealCompanyNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_deal_accepts_a_free_text_company_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'active']));

        $this->postJson('/api/v1/records/deals', [
            'name' => 'Farm supply opportunity',
            'company_name' => 'Savannah Foods Ltd',
        ])->assertCreated()
            ->assertJsonPath('company_name', 'Savannah Foods Ltd');

        $this->assertSame('Savannah Foods Ltd', Deal::firstOrFail()->company_name);
    }

    public function test_deal_accepts_a_free_text_primary_contact_name(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'active']));

        $this->postJson('/api/v1/records/deals', [
            'name' => 'Farm supply opportunity',
            'contact_name' => 'Ama Mensah',
        ])->assertCreated()
            ->assertJsonPath('contact_name', 'Ama Mensah');

        $this->assertSame('Ama Mensah', Deal::firstOrFail()->contact_name);
    }
}
