<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Death;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LivestockDeathsSyncTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_updating_animal_to_dead_creates_death_record(): void
    {
        $admin = $this->admin();
        $location = Location::create([
            'code' => 'P01',
            'name' => 'North Paddock',
            'type' => 'paddock',
            'status' => 'active',
        ]);

        $animal = Animal::create([
            'animal_id' => 'EV-GT-000001',
            'species' => 'goat',
            'sex' => 'female',
            'location_id' => $location->id,
            'status' => 'alive',
            'current_status' => 'active',
            'health_status' => 'healthy',
        ]);

        $this->assertDatabaseCount('deaths', 0);

        // Edit animal to dead
        $response = $this->actingAs($admin)->putJson("/api/v1/livestock/{$animal->id}", [
            'species' => 'goat',
            'sex' => 'female',
            'status' => 'dead',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('animals', [
            'id' => $animal->id,
            'status' => 'dead',
            'current_status' => 'inactive',
        ]);
        $this->assertDatabaseCount('deaths', 1);
        $this->assertDatabaseHas('deaths', [
            'animal_id' => $animal->id,
        ]);

        // Deaths endpoint returns count 1
        $deathsResponse = $this->actingAs($admin)->getJson('/api/v1/livestock/deaths');
        $deathsResponse->assertOk()->assertJsonPath('count', 1);

        // Reverting to alive removes the death record
        $this->actingAs($admin)->putJson("/api/v1/livestock/{$animal->id}", [
            'species' => 'goat',
            'sex' => 'female',
            'status' => 'alive',
        ]);

        $this->assertDatabaseCount('deaths', 0);
        $deathsResponse2 = $this->actingAs($admin)->getJson('/api/v1/livestock/deaths');
        $deathsResponse2->assertOk()->assertJsonPath('count', 0);
    }

    public function test_recording_death_event_creates_death_record(): void
    {
        $admin = $this->admin();
        $location = Location::create([
            'code' => 'P02',
            'name' => 'South Paddock',
            'type' => 'paddock',
            'status' => 'active',
        ]);

        $animal = Animal::create([
            'animal_id' => 'EV-CT-000001',
            'species' => 'cattle',
            'sex' => 'male',
            'location_id' => $location->id,
            'status' => 'alive',
            'current_status' => 'active',
            'health_status' => 'healthy',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/livestock/events/death', [
            'animal_id' => $animal->animal_id,
            'cause' => 'Sickness',
            'notes' => 'Vet diagnosis: pneumonia',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('animals', [
            'id' => $animal->id,
            'status' => 'dead',
            'current_status' => 'inactive',
        ]);
        $this->assertDatabaseCount('deaths', 1);

        $deathsResponse = $this->actingAs($admin)->getJson('/api/v1/livestock/deaths');
        $deathsResponse->assertOk()->assertJsonPath('count', 1);
    }
}
