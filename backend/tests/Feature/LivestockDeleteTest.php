<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\HealthRecord;
use App\Models\User;
use App\Models\Weight;
use App\Services\AnimalIdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LivestockDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_animal_can_be_deleted_via_api(): void
    {
        $admin = $this->admin();

        $animal = Animal::create([
            'animal_id' => 'EV-GT-000001',
            'tag_id' => 'TAG-0001',
            'species' => 'goat',
            'breed' => 'West African Dwarf',
            'sex' => 'female',
            'status' => 'alive',
            'current_status' => 'active',
            'health_status' => 'healthy',
        ]);

        // Add related records
        AnimalEvent::create([
            'animal_id' => $animal->id,
            'event_type' => 'registration',
            'occurred_at' => now(),
            'notes' => 'Registered',
        ]);

        Weight::create([
            'animal_id' => $animal->id,
            'weighed_at' => now(),
            'weight_kg' => 25.5,
        ]);

        HealthRecord::create([
            'animal_id' => $animal->id,
            'recorded_date' => now(),
            'condition' => 'good',
            'status' => 'healthy',
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/livestock/{$animal->id}");

        $response->assertStatus(200)
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('animals', ['id' => $animal->id]);
        $this->assertDatabaseMissing('animal_events', ['animal_id' => $animal->id]);
        $this->assertDatabaseMissing('weights', ['animal_id' => $animal->id]);
        $this->assertDatabaseMissing('health_records', ['animal_id' => $animal->id]);
    }

    public function test_deleting_animal_does_not_rewind_animal_id_sequence(): void
    {
        $admin = $this->admin();
        $generator = app(AnimalIdGenerator::class);

        $id1 = $generator->next('goat'); // EV-GT-000001
        $animal = Animal::create([
            'animal_id' => $id1,
            'species' => 'goat',
            'sex' => 'female',
            'status' => 'alive',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/livestock/{$animal->id}")
            ->assertStatus(200);

        // Sequence must not rewind to 000001; next must be 000002
        $id2 = $generator->next('goat');
        $this->assertSame('EV-GT-000002', $id2);
    }
}
