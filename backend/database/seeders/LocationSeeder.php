<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['code' => 'PAD-A', 'name' => 'North Paddock', 'type' => 'paddock', 'capacity' => 80],
            ['code' => 'PAD-B', 'name' => 'South Paddock', 'type' => 'paddock', 'capacity' => 70],
            ['code' => 'PAD-C', 'name' => 'River Field', 'type' => 'paddock', 'capacity' => 60],
            ['code' => 'BRN-1', 'name' => 'Maternity Barn', 'type' => 'barn', 'capacity' => 20],
            ['code' => 'QRN-1', 'name' => 'Quarantine Pen', 'type' => 'pen', 'capacity' => 12],
        ];

        foreach ($locations as $location) {
            Location::firstOrCreate(
                ['code' => $location['code']],
                $location + ['status' => 'active', 'address' => 'Evolex Farm, Greater Accra']
            );
        }
    }
}
