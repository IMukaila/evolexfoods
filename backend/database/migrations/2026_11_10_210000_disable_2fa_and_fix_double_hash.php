<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Force disable 2FA for all users
        DB::table('users')->update([
            'two_factor_enabled'        => 0,
            'two_factor_confirmed_at'   => null,
            'two_factor_secret'         => null,
            'two_factor_otp'            => null,
            'two_factor_otp_expires_at' => null,
            'two_factor_trusted_tokens' => null,
        ]);

        // 2. Reset passwords for owner accounts to clean standard hash
        $cleanHash = Hash::make('password');
        DB::table('users')->where('email', 'owner@evolex.com')->update([
            'password'           => $cleanHash,
            'two_factor_enabled' => 0,
            'status'             => 'active',
        ]);

        DB::table('users')->where('email', 'evolexfoods@gmail.com')->update([
            'password'           => $cleanHash,
            'two_factor_enabled' => 0,
            'status'             => 'active',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
