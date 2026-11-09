<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    /**
     * Seed the default demo accounts used by the app during local development.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Inusah Mukaila', 'email' => 'owner@evolex.com', 'role' => 'owner', 'department' => 'Ownership'],
            ['name' => 'Daniel Mensah', 'email' => 'executive@evolex.com', 'role' => 'executive', 'department' => 'Executive'],
            ['name' => 'Nia Boateng', 'email' => 'ops@evolex.com', 'role' => 'operations_manager', 'department' => 'Business Operations'],
            ['name' => 'Kwame Asante', 'email' => 'farm@evolex.com', 'role' => 'farm_manager', 'department' => 'Farm'],
            ['name' => 'Amina Diallo', 'email' => 'finance@evolex.com', 'role' => 'finance', 'department' => 'Finance'],
            ['name' => 'Sofia Ribeiro', 'email' => 'marketing@evolex.com', 'role' => 'marketing', 'department' => 'Marketing'],
            ['name' => 'James Carter', 'email' => 'sales@evolex.com', 'role' => 'sales', 'department' => 'Sales'],
            ['name' => 'Ibrahim Touré', 'email' => 'store@evolex.com', 'role' => 'storekeeper', 'department' => 'Stores'],
            ['name' => 'Dr. Helen Nkrumah', 'email' => 'vet@evolex.com', 'role' => 'vet', 'department' => 'Animal Health'],
            ['name' => 'Kojo Ampofo', 'email' => 'procurement@evolex.com', 'role' => 'procurement', 'department' => 'Procurement'],
            ['name' => 'Yaw Mensah', 'email' => 'worker@evolex.com', 'role' => 'farm_employee', 'department' => 'Farm'],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'role' => $userData['role'],
                    'department' => $userData['department'],
                    'status' => 'active',
                    'phone' => '+23320' . random_int(1000000, 9999999),
                ]
            );
        }
        User::where('email', 'owner@evolex.com')->update(['name' => 'Inusah Mukaila']);

    }
}
