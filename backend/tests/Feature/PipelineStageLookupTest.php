<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineStageLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_stage_lookup_is_populated_and_stages_can_be_assigned_to_deals(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'active']));

        $response = $this->getJson('/api/v1/lookups')
            ->assertOk()
            ->assertJsonPath('pipeline_stages.0.name', 'Prospect Identified')
            ->assertJsonPath('pipeline_stages.3.name', 'Won');

        $stages = collect($response->json('pipeline_stages'));
        $this->assertSame(
            ['Prospect Identified', 'Contacted', 'Negotiation', 'Won'],
            $stages->pluck('name')->all()
        );
        $stage = $stages->firstWhere('name', 'Negotiation');

        $this->assertNotNull($stage);
        $this->assertSame(4, PipelineStage::count());

        $this->postJson('/api/v1/records/deals', [
            'name' => 'New buyer opportunity',
            'pipeline_stage_id' => $stage['id'],
        ])->assertCreated()
            ->assertJsonPath('stage.id', $stage['id'])
            ->assertJsonPath('stage.name', 'Negotiation');

        $this->assertSame(4, PipelineStage::count());
        $this->assertSame($stage['id'], Deal::firstOrFail()->pipeline_stage_id);
    }
}
