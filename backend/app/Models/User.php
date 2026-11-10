<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'role_id',
        'phone',
        'department',
        'status',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
        'two_factor_type',
        'two_factor_otp',
        'two_factor_otp_expires_at',
        'two_factor_trusted_tokens',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_otp',
        'two_factor_trusted_tokens',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'          => 'datetime',
            'two_factor_confirmed_at'    => 'datetime',
            'two_factor_otp_expires_at'  => 'datetime',
            'two_factor_enabled'         => 'boolean',
            'two_factor_recovery_codes'  => 'array',
            'two_factor_trusted_tokens'  => 'array',
        ];
    }

    /**
     * Generate standard recovery backup codes.
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = 'EV-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        }
        $this->two_factor_recovery_codes = $codes;
        return $codes;
    }

    /**
     * User role relationship.
     */
    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->roleModel?->hasPermission($permissionName) ?? false;
    }

    /**
     * Get all permissions for the user through their role.
     */
    public function permissions()
    {
        return $this->roleModel?->permissions()->get() ?? collect();
    }
}
