<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // ── List all users ────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = User::with('roleModel');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name',       'like', "%{$search}%")
                  ->orWhere('email',      'like', "%{$search}%")
                  ->orWhere('role',       'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginated = $query->orderBy('name')->paginate($perPage);

        $isPrivileged = $this->isPrivileged($request->user());

        $users = $paginated->getCollection()->map(fn ($u) => $this->userRow($u, $isPrivileged));

        return response()->json([
            'data' => $users,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // ── Create a user (auto-generate password) ────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'role'       => 'nullable|string|max:100',
            'role_id'    => 'nullable|exists:roles,id',
            'phone'      => 'nullable|string|max:30',
            'department' => 'nullable|string|max:100',
            'status'     => 'nullable|in:active,inactive,suspended',
            'password'   => 'nullable|string|min:8',   // admin can set custom password
        ]);

        // Generate a secure random password if not provided
        $plainPassword = $validated['password'] ?? $this->generatePassword();

        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($plainPassword),
            'role'       => $validated['role'] ?? 'farm_employee',
            'role_id'    => $validated['role_id'] ?? null,
            'phone'      => $validated['phone'] ?? null,
            'department' => $validated['department'] ?? null,
            'status'     => $validated['status'] ?? 'active',
        ]);

        AuditLog::create([
            'user_id'        => $request->user()?->id,
            'action'         => 'user.created',
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
            'ip_address'     => $request->ip(),
            'meta'           => ['created_email' => $user->email],
        ]);

        $isPrivileged = $this->isPrivileged($request->user());

        return response()->json([
            'message'        => 'User created successfully',
            'data'           => $this->userRow($user, $isPrivileged),
            'plain_password' => $isPrivileged ? $plainPassword : null,  // only returned once, to owner/admin
        ], 201);
    }

    // ── Show a single user ────────────────────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $user = User::with('roleModel')->findOrFail($id);
        $isPrivileged = $this->isPrivileged($request->user());
        return response()->json(['data' => $this->userRow($user, $isPrivileged)]);
    }

    // ── Update a user ─────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'               => 'sometimes|string|max:255',
            'email'              => ['sometimes', 'email', Rule::unique('users')->ignore($id)],
            'role'               => 'nullable|string|max:100',
            'role_id'            => 'nullable|exists:roles,id',
            'phone'              => 'nullable|string|max:30',
            'department'         => 'nullable|string|max:100',
            'status'             => 'nullable|in:active,inactive,suspended',
            'two_factor_enabled' => 'nullable|boolean',
            'password'           => 'nullable|string|min:6',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        AuditLog::create([
            'user_id'        => $request->user()?->id,
            'action'         => 'user.updated',
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
            'ip_address'     => $request->ip(),
        ]);

        $isPrivileged = $this->isPrivileged($request->user());

        return response()->json([
            'message' => 'User updated successfully',
            'data'    => $this->userRow($user->fresh('roleModel'), $isPrivileged),
        ]);
    }

    // ── Delete a user ─────────────────────────────────────────────────────────

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        AuditLog::create([
            'user_id'        => $request->user()?->id,
            'action'         => 'user.deleted',
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
            'ip_address'     => $request->ip(),
            'meta'           => ['deleted_email' => $user->email],
        ]);

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // ── Reset / generate a new password (owner/admin only) ───────────────────

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        if (!$this->isPrivileged($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::findOrFail($id);
        $plain = $this->generatePassword();
        $user->update(['password' => Hash::make($plain)]);
        $user->tokens()->delete();   // force re-login

        AuditLog::create([
            'user_id'        => $request->user()?->id,
            'action'         => 'user.password_reset',
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
            'ip_address'     => $request->ip(),
        ]);

        return response()->json([
            'message'        => 'Password reset successfully',
            'plain_password' => $plain,
        ]);
    }

    // ── Self change-password (any authenticated user) ─────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors'  => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        AuditLog::create([
            'user_id'        => $user->id,
            'action'         => 'user.password_changed',
            'auditable_type' => User::class,
            'auditable_id'   => $user->id,
            'ip_address'     => $request->ip(),
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isPrivileged(?User $user): bool
    {
        if (!$user) return false;
        $role = strtolower((string) $user->role);
        return in_array($role, ['owner', 'administrator', 'admin'], true);
    }

    private function userRow(User $u, bool $showSensitive): array
    {
        return [
            'id'          => $u->id,
            'name'        => $u->name,
            'email'       => $u->email,
            'role'        => $u->role,
            'role_id'     => $u->role_id,
            'role_name'   => $u->roleModel?->name,
            'phone'       => $u->phone,
            'department'  => $u->department,
            'status'      => $u->status,
            'created_at'  => $u->created_at?->toIso8601String(),
            'updated_at'  => $u->updated_at?->toIso8601String(),
            'can_view_password' => $showSensitive,  // tells frontend whether to show reveal button
        ];
    }

    private function generatePassword(): string
    {
        // e.g. "Ev@2026-xK9m"  — readable, 12 chars, upper+lower+digit+symbol
        $chars  = 'abcdefghjkmnpqrstuvwxyz';
        $uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $syms   = '@#$!';

        $pwd = $uppers[random_int(0, strlen($uppers) - 1)]
            . $syms[random_int(0, strlen($syms) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)];

        for ($i = 0; $i < 8; $i++) {
            $pool = $chars . $uppers . $digits;
            $pwd .= $pool[random_int(0, strlen($pool) - 1)];
        }

        // shuffle so the fixed prefix characters are mixed in
        return str_shuffle($pwd);
    }
}
