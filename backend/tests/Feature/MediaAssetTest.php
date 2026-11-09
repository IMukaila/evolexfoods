<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_assets_can_be_created_and_used_in_content_calendar_lookup(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'active']));

        $this->postJson('/api/v1/records/media_assets', [
            'title' => 'Goat herd photo',
            'type' => 'photo',
            'subject' => 'Livestock',
            'url' => 'https://example.test/goats.jpg',
            'captured_at' => '2026-11-06',
            'rights' => 'internal',
        ])->assertCreated()
            ->assertJsonPath('title', 'Goat herd photo');

        $asset = MediaAsset::firstOrFail();

        $this->getJson('/api/v1/lookups')->assertOk()
            ->assertJsonPath('media_assets.0.id', $asset->id)
            ->assertJsonPath('media_assets.0.title', 'Goat herd photo');
    }

    public function test_content_calendar_can_reference_a_media_asset(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'active']));
        $asset = MediaAsset::create(['title' => 'Farm photo', 'type' => 'photo']);

        $this->postJson('/api/v1/records/marketing', [
            'title' => 'Farm story',
            'status' => 'draft',
            'media_asset_id' => $asset->id,
        ])->assertCreated()
            ->assertJsonPath('media.id', $asset->id)
            ->assertJsonPath('media.title', 'Farm photo');
    }
}
