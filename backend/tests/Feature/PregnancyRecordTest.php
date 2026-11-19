<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Breeding;
use App\Models\Location;
use App\Models\Pregnancy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PregnancyRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Location $location;
    private Animal $dam;
    private Animal $sire;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->location = Location::create([
            'name' => 'Maternity Barn',
            'code' => 'BRN-1',
            'type' => 'barn',
            'status' => 'active',
        ]);

        $this->dam = Animal::create([
            'animal_id' => 'EV-GT-000501',
            'species' => 'goat',
            'breed' => 'West African Dwarf',
            'sex' => 'female',
            'status' => 'alive',
            'location_id' => $this->location->id,
        ]);

        $this->sire = Animal::create([
            'animal_id' => 'EV-GT-000502',
            'species' => 'goat',
            'breed' => 'West African Dwarf',
            'sex' => 'male',
            'status' => 'alive',
            'location_id' => $this->location->id,
        ]);
    }

    public function test_creating_pregnancy_syncs_animal_pregnancy_status(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/records/pregnancies', [
            'animal_id' => $this->dam->id,
            'confirmed_date' => '2026-07-15',
            'expected_birth_date' => '2026-11-20',
            'status' => 'active',
            'notes' => 'Confirmed in July',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('pregnancies', [
            'animal_id' => $this->dam->id,
            'status' => 'active',
        ]);
        $pregnancy = Pregnancy::where('animal_id', $this->dam->id)->first();
        $this->assertNotNull($pregnancy);
        $this->assertSame('2026-07-15', $pregnancy->confirmed_date->toDateString());
        $this->assertSame('2026-11-20', $pregnancy->expected_birth_date->toDateString());

        $this->dam->refresh();
        $this->assertSame('pregnant', $this->dam->pregnancy_status);
        $this->assertSame('2026-11-20', $this->dam->expected_birth_date->toDateString());
    }

    public function test_deleting_pregnancy_reverts_animal_pregnancy_status(): void
    {
        $pregnancy = Pregnancy::create([
            'animal_id' => $this->dam->id,
            'confirmed_date' => '2026-07-15',
            'expected_birth_date' => '2026-11-20',
            'status' => 'active',
        ]);

        $this->dam->update([
            'pregnancy_status' => 'pregnant',
            'expected_birth_date' => '2026-11-20',
        ]);

        $response = $this->actingAs($this->admin)->deleteJson('/api/v1/records/pregnancies/' . $pregnancy->id);
        $response->assertOk();

        $this->dam->refresh();
        $this->assertSame('open', $this->dam->pregnancy_status);
        $this->assertNull($this->dam->expected_birth_date);
    }

    public function test_livestock_pregnancies_endpoint_returns_expected_buckets(): void
    {
        Pregnancy::create([
            'animal_id' => $this->dam->id,
            'confirmed_date' => '2026-07-15',
            'expected_birth_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/livestock/pregnancies?status=all');
        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('expected_births.next_7', 1);
    }
}
