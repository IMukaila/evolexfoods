<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoresCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_crud_and_lookup_flow(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'status' => 'active']));

        // 1. Create a store with explicit code
        $res = $this->postJson('/api/v1/records/stores', [
            'code' => 'STR-CUSTOM-001',
            'name' => 'Custom Bulk Store',
            'type' => 'store',
            'address' => 'Bay 1 Supply Yard',
            'capacity' => 1200,
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('name', 'Custom Bulk Store')
            ->assertJsonPath('code', 'STR-CUSTOM-001')
            ->assertJsonPath('type', 'store');

        $storeId = $res->json('id');

        // 2. Create another store with auto-generated code
        $autoRes = $this->postJson('/api/v1/records/stores', [
            'name' => 'Quarantine Clinic Store',
            'type' => 'store',
            'address' => 'Vet Clinic',
            'capacity' => 300,
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('name', 'Quarantine Clinic Store');

        $this->assertStringStartsWith('STR-', $autoRes->json('code'));

        // 3. Index listing returns stores
        $this->getJson('/api/v1/records/stores')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Custom Bulk Store']);

        // 4. Update a store
        $this->putJson("/api/v1/records/stores/{$storeId}", [
            'code' => 'STR-CUSTOM-001',
            'name' => 'Central Feed Depot',
            'type' => 'store',
            'address' => 'Bay 1 Supply Yard (Expanded)',
            'capacity' => 1500,
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('name', 'Central Feed Depot')
            ->assertJsonPath('capacity', 1500);

        // 5. Lookups contains both stores and locations
        $lookupRes = $this->getJson('/api/v1/lookups')->assertOk();
        $this->assertNotEmpty($lookupRes->json('stores'));
        $this->assertNotEmpty($lookupRes->json('locations'));

        // 6. Inventory can reference this store name
        $invRes = $this->postJson('/api/v1/records/inventory', [
            'name' => 'Alfalfa Hay Pellets',
            'category' => 'Feed',
            'unit' => 'kg',
            'storage_location' => 'Central Feed Depot',
            'quantity_on_hand' => 500,
            'average_cost' => 4.5,
            'reorder_level' => 100,
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('storage_location', 'Central Feed Depot');

        // 7. Delete store
        $this->deleteJson("/api/v1/records/stores/{$storeId}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('locations', ['id' => $storeId]);
    }
}
