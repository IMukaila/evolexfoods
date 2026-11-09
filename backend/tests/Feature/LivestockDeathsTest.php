<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Death;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LivestockDeathsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_livestock_deaths_count_uses_all_recorded_deaths(): void
    {
        $user = User::factory()->create();
        $location = Location::create([
            'code' => 'P01',
            'name' => 'North Paddock',
            'type' => 'paddock',
            'status' => 'active',
        ]);

        $animalOne = Animal::create([
            'animal_id' => 'EV-001',
            'species' => 'goat',
            'sex' => 'female',
            'location_id' => $location->id,
            'status' => 'dead',
            'current_status' => 'inactive',
            'health_status' => 'dead',
        ]);

        $animalTwo = Animal::create([
            'animal_id' => 'EV-002',
            'species' => 'sheep',
            'sex' => 'male',
            'location_id' => $location->id,
            'status' => 'dead',
            'current_status' => 'inactive',
            'health_status' => 'dead',
        ]);

        Death::create([
            'animal_id' => $animalOne->id,
            'death_date' => now()->subDays(120)->toDateString(),
            'cause' => 'old age',
            'location_id' => $location->id,
        ]);

        Death::create([
            'animal_id' => $animalTwo->id,
            'death_date' => now()->subDays(10)->toDateString(),
            'cause' => 'injury',
            'location_id' => $location->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/livestock/deaths');

        $response->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'records');
    }

    public function test_livestock_deaths_respects_explicit_date_filters(): void
    {
        $user = User::factory()->create();
        $location = Location::create([
            'code' => 'P02',
            'name' => 'South Paddock',
            'type' => 'paddock',
            'status' => 'active',
        ]);

        $recentAnimal = Animal::create([
            'animal_id' => 'EV-101',
            'species' => 'cattle',
            'sex' => 'female',
            'location_id' => $location->id,
            'status' => 'dead',
            'current_status' => 'inactive',
            'health_status' => 'dead',
        ]);

        $olderAnimal = Animal::create([
            'animal_id' => 'EV-102',
            'species' => 'cattle',
            'sex' => 'male',
            'location_id' => $location->id,
            'status' => 'dead',
            'current_status' => 'inactive',
            'health_status' => 'dead',
        ]);

        Death::create([
            'animal_id' => $recentAnimal->id,
            'death_date' => now()->subDays(5)->toDateString(),
            'cause' => 'sickness',
            'location_id' => $location->id,
        ]);

        Death::create([
            'animal_id' => $olderAnimal->id,
            'death_date' => now()->subDays(90)->toDateString(),
            'cause' => 'disease',
            'location_id' => $location->id,
        ]);

        $from = now()->subDays(30)->toDateString();
        $to = now()->toDateString();

        $response = $this->actingAs($user)->getJson('/api/v1/livestock/deaths?from=' . $from . '&to=' . $to);

        $response->assertOk()->assertJsonPath('count', 1);
    }
}
