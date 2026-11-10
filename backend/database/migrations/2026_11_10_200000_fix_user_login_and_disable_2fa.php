<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure two_factor_type can be nullable with 'totp' default
        try {
            DB::statement("ALTER TABLE users MODIFY COLUMN two_factor_type VARCHAR(255) NULL DEFAULT 'totp'");
        } catch (\Throwable $e) {
            // fallback if statement not supported
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('two_factor_type')->nullable()->default('totp')->change();
                });
            } catch (\Throwable $e2) {
                // Ignore if already compatible
            }
        }

        // 2. Disable 2FA for all users by default so nobody is locked out
        DB::table('users')->update([
            'two_factor_enabled'      => 0,
            'two_factor_confirmed_at' => null,
            'two_factor_otp'           => null,
            'two_factor_otp_expires_at' => null,
        ]);

        // 3. Reset owner@evolex.com password to standard 'password'
        User::where('email', 'owner@evolex.com')->update([
            'password' => Hash::make('password'),
            'status'   => 'active',
        ]);

        // 4. Ensure evolexfoods@gmail.com exists as an owner account
        $ownerRoleId = DB::table('roles')->where('slug', 'owner')->orWhere('id', 1)->value('id');
        User::firstOrCreate(
            ['email' => 'evolexfoods@gmail.com'],
            [
                'name'               => 'Inusah Mukaila',
                'password'           => Hash::make('password'),
                'role'               => 'owner',
                'role_id'            => $ownerRoleId,
                'department'         => 'Ownership',
                'status'             => 'active',
                'phone'              => '+233205428488',
                'two_factor_enabled' => false,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed
    }
};
