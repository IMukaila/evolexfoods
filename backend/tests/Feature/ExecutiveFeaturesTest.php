<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Pregnancy;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\AnimalIdGenerator;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The four executive features from the operations manual:
 * automated Animal IDs + birth lifecycle (§10/§96), the Weekly Owner
 * Report (§113), the Gestation Horizon Radar (§39) and the two-tier
 * procurement approval matrix (§44–47).
 */
class ExecutiveFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.uniqid().'@evolex.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function animal(string $id, string $species, string $sex, array $extra = []): Animal
    {
        return Animal::create([
            'animal_id' => $id,
            'species' => $species,
            'sex' => $sex,
            'health_status' => 'healthy',
            'status' => 'alive',
            'current_status' => 'active',
            ...$extra,
        ]);
    }

    public function test_animal_ids_follow_the_manual_formats_and_are_never_reused(): void
    {
        $dam = $this->animal('EV-GT-000001', 'goat', 'female');

        $generator = app(AnimalIdGenerator::class);
        $this->assertSame('EV-GT-000002', $generator->peek('goat'));
        $this->assertSame('EV-GT-000002', $generator->next('goat'));
        $this->assertSame('EV-SH-000001', $generator->next('sheep'));
        $this->assertSame('EV-CT-000001', $generator->next('cattle'));

        // Even after the highest-numbered animal is deleted, its ID stays retired.
        $kid = $this->animal('EV-GT-000002', 'goat', 'female');
        $kid->delete();
        $this->assertSame('EV-GT-000003', $generator->next('goats'));
        $this->assertSame('EV-GT-000001', $dam->animal_id);
    }

    public function test_birth_registers_a_litter_closes_the_pregnancy_and_reopens_the_dam(): void
    {
        $keeper = $this->user('farm_manager');
        $dam = $this->animal('EV-GT-000010', 'goat', 'female', [
            'pregnancy_status' => 'pregnant',
            'breeding_status' => 'bred',
            'expected_birth_date' => now()->addDays(3)->toDateString(),
        ]);
        $sire = $this->animal('EV-GT-000011', 'goat', 'male');
        Pregnancy::create([
            'animal_id' => $dam->id,
            'confirmed_date' => now()->subDays(120)->toDateString(),
            'expected_birth_date' => now()->addDays(3)->toDateString(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($keeper, 'sanctum')->postJson('/api/v1/livestock/events/birth', [
            'mother_animal_id' => 'EV-GT-000010',
            'father_animal_id' => 'EV-GT-000011',
            'birth_date' => now()->toDateString(),
            'kids' => [
                ['sex' => 'female', 'birth_weight' => 2.4],
                ['sex' => 'male', 'birth_weight' => 2.1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('litter_size', 2)
            ->assertJsonPath('pregnancy_closed', true)
            ->assertJsonPath('animals.0.mother_id', 'EV-GT-000010')
            ->assertJsonPath('animals.0.father_id', 'EV-GT-000011');

        $ids = collect($response->json('animals'))->pluck('animal_id');
        $this->assertSame(['EV-GT-000012', 'EV-GT-000013'], $ids->all());
        $this->assertDatabaseCount('births', 2);
        $this->assertSame('completed', Pregnancy::first()->status);
        $this->assertSame('open', $dam->fresh()->pregnancy_status);
        $this->assertNull($dam->fresh()->expected_birth_date);
    }

    public function test_birth_rejects_an_unknown_dam(): void
    {
        $keeper = $this->user('farm_manager');

        $this->actingAs($keeper, 'sanctum')
            ->postJson('/api/v1/livestock/events/birth', [
                'mother_animal_id' => 'EV-GT-999999',
                'sex' => 'female',
                'species' => 'goat',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mother_animal_id');
    }

    public function test_gestation_radar_buckets_expected_births_by_horizon(): void
    {
        $keeper = $this->user('executive');
        $soon = $this->animal('EV-SH-000020', 'sheep', 'female');
        $later = $this->animal('EV-SH-000021', 'sheep', 'female');

        foreach ([[$soon, 5], [$later, 45]] as [$animal, $days]) {
            Pregnancy::create([
                'animal_id' => $animal->id,
                'confirmed_date' => now()->subDays(100)->toDateString(),
                'expected_birth_date' => now()->addDays($days)->toDateString(),
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($keeper, 'sanctum')->getJson('/api/v1/livestock/gestation-radar');

        $response->assertOk()
            ->assertJsonPath('total_active', 2)
            ->assertJsonPath('gestation_days.sheep', 150)
            ->assertJsonPath('horizons.0.days', 7)
            ->assertJsonPath('horizons.0.count', 1)
            ->assertJsonPath('horizons.1.count', 1)
            ->assertJsonPath('horizons.2.count', 2)
            ->assertJsonPath('entries.0.animal_code', 'EV-SH-000020')
            ->assertJsonPath('entries.0.bucket', '7')
            ->assertJsonPath('entries.1.bucket', '60');
    }

    public function test_procurement_routes_by_cost_and_only_the_routed_tier_can_decide(): void
    {
        $requester = $this->user('farm_manager');
        $executive = $this->user('executive');

        // GH₵2,500 > GH₵1,000 → Tier 2, routed directly to Executive approval.
        $created = $this->actingAs($requester, 'sanctum')->postJson('/api/v1/procurement/requests', [
            'item_name' => 'Solar Water Pump',
            'quantity' => 1,
            'unit' => 'unit',
            'estimated_cost' => 2500,
        ]);
        $created->assertCreated()
            ->assertJsonPath('approval_tier', 2)
            ->assertJsonPath('approval_stage', 'pending_tier_2');

        $id = $created->json('id');

        // A supervisor-tier role cannot decide a Tier 2 purchase.
        $this->actingAs($requester, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$id}/approve")
            ->assertUnprocessable();

        // A Purchase Order before approval is refused.
        $this->actingAs($executive, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$id}/purchase-order", [])
            ->assertUnprocessable();

        // Executive approval unlocks the PO.
        $this->actingAs($executive, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$id}/approve", ['comments' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('request.approval_stage', 'approved');

        $this->actingAs($executive, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$id}/purchase-order", [])
            ->assertCreated()
            ->assertJsonPath('request.approval_stage', 'ordered');

        $this->assertSame('ordered', PurchaseRequest::find($id)->status);
    }

    public function test_standard_farm_locations_are_available_to_the_animal_form(): void
    {
        $this->seed(LocationSeeder::class);

        $this->actingAs($this->user('farm_manager'), 'sanctum')
            ->getJson('/api/v1/locations')
            ->assertOk()
            ->assertJsonCount(5);
    }

    public function test_supervisor_can_decide_a_small_purchase(): void
    {
        $requester = $this->user('vet');
        $supervisor = $this->user('farm_manager');

        $created = $this->actingAs($requester, 'sanctum')->postJson('/api/v1/procurement/requests', [
            'item_name' => 'Ivermectin',
            'quantity' => 20,
            'unit' => 'bottle',
            'estimated_cost' => 440,
        ]);
        $created->assertCreated()
            ->assertJsonPath('approval_tier', 1)
            ->assertJsonPath('approval_stage', 'pending_tier_1');

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/v1/procurement/requests/{$created->json('id')}/approve")
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');
    }

    public function test_weekly_owner_report_compiles_the_eight_sections(): void
    {
        $owner = $this->user('owner');
        $this->animal('EV-GT-000001', 'goat', 'female');

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/reports/weekly-owner');

        $response->assertOk()->assertJsonPath('manual_reference', '§113');
        $this->assertCount(8, $response->json('sections'));
        $this->assertSame(
            ['executive_summary', 'livestock', 'operations_people', 'feed_inventory', 'procurement_spend', 'sales_marketing', 'risks', 'decisions'],
            collect($response->json('sections'))->pluck('key')->all()
        );
        $response->assertJsonPath('sections.1.herd_reconciliation.equation', 'Opening herd + Births + Purchases − Deaths − Sales = Closing herd');
    }
}
