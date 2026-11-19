<?php

use App\Models\Location;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $stores = [
            [
                'code' => 'STR-001',
                'name' => 'Feed Store',
                'type' => 'store',
                'address' => 'Supply Yard, Bay 1',
                'capacity' => 1000,
                'status' => 'active',
            ],
            [
                'code' => 'STR-002',
                'name' => 'Clinic Store',
                'type' => 'store',
                'address' => 'Veterinary Clinic, Room 2',
                'capacity' => 250,
                'status' => 'active',
            ],
            [
                'code' => 'STR-003',
                'name' => 'Main Warehouse',
                'type' => 'warehouse',
                'address' => 'Central Depot',
                'capacity' => 5000,
                'status' => 'active',
            ],
            [
                'code' => 'STR-004',
                'name' => 'Cold Storage Room',
                'type' => 'cold_storage',
                'address' => 'Dairy & Veterinary Cold Chain Block',
                'capacity' => 300,
                'status' => 'active',
            ],
            [
                'code' => 'STR-005',
                'name' => 'Equipment & Tool Shed',
                'type' => 'store',
                'address' => 'Workshop Zone B',
                'capacity' => 400,
                'status' => 'active',
            ],
        ];

        foreach ($stores as $store) {
            Location::firstOrCreate(
                ['name' => $store['name']],
                $store
            );
        }
    }

    public function down(): void
    {
        Location::whereIn('code', ['STR-001', 'STR-002', 'STR-003', 'STR-004', 'STR-005'])->delete();
    }
};
