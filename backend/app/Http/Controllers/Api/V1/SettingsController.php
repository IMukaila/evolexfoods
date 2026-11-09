<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Retrieve all grouped settings along with live system diagnostics.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureDefaultSettings();
        $grouped = Setting::getAllGrouped();

        // System diagnostics and metadata
        $systemInfo = [
            'app_name' => config('app.name', 'Evolex Hub'),
            'app_env' => app()->environment(),
            'app_url' => config('app.url'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'database_connection' => config('database.default'),
            'server_time' => now()->toIso8601String(),
            'server_timezone' => config('app.timezone', 'UTC'),
            'server_status' => 'operational',
            'active_users_count' => User::where('status', 'active')->count(),
            'total_livestock_count' => Animal::where('status', 'alive')->count(),
            'storage_status' => 'healthy',
        ];

        return response()->json([
            'settings' => $grouped,
            'system_info' => $systemInfo,
        ]);
    }

    /**
     * Bulk or grouped update of settings.
     */
    public function update(Request $request): JsonResponse
    {
        $this->ensureDefaultSettings();
        $payload = \App\Services\CommunicationSettings::validate($request->all());
        $updatedCount = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($payload, &$updatedCount) {
            foreach ($payload as $group => $data) {
                foreach ($data as $key => $value) {
                    Setting::set($key, $value, $group);
                    $updatedCount++;
                }
            }
        });

        // Record audit trail
        try {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'settings.updated',
                'auditable_type' => Setting::class,
                'meta' => [
                    'updated_count' => $updatedCount,
                    'keys' => array_keys($payload),
                ],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log audit for settings update: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully updated {$updatedCount} setting(s).",
            'settings' => Setting::getAllGrouped(),
        ]);
    }

    /**
     * Send a diagnostic test email using current or configured SMTP settings.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $data = $request->validate(['recipient' => 'required|email']);
        $recipient = $data['recipient'];
        $start = microtime(true);
        try {
            $status = app(\App\Services\OutboundMailService::class)->send(
                $recipient, 'Evolex Hub email configuration test',
                'This is a real test of your saved Evolex Hub email configuration. No action is required.'
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email acceptance not confirmed. Check saved SMTP host, port, encryption, credentials and sender. A timeout may still have been accepted; check the mailbox before retrying.',
            ], 502);
        }
        AuditLog::create([
            'user_id' => $request->user()?->id, 'action' => 'email.test_'.$status,
            'meta' => ['status' => $status], 'ip_address' => $request->ip(),
        ]);
        return response()->json([
            'success' => true,
            'message' => $status === 'logged' ? 'Test email written to the application log only. No email was sent.' : 'SMTP server accepted the test email. Inbox delivery is not guaranteed.',
            'details' => [
                'recipient' => $recipient,
                'smtp_server' => $status === 'logged' ? 'Log only' : Setting::get('mail_host').':'.Setting::get('mail_port'),
                'encryption' => strtoupper(Setting::get('mail_encryption', 'tls')),
                'from_sender' => Setting::get('mail_from_name').' <'.Setting::get('mail_from_address').'>',
                'handshake' => $status,
                'latency_ms' => round((microtime(true) - $start) * 1000, 1),
                'sent_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Clear application cache, route cache, and configuration cache.
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'system.cache_cleared',
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'System cache and configuration caches cleared successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cache clear error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export complete system configuration snapshot.
     */
    public function exportConfig(): JsonResponse
    {
        $settings = Setting::getAllGrouped();
        return response()->json([
            'export_date' => now()->toIso8601String(),
            'platform' => 'Evolex Digital Operations Platform',
            'version' => '1.0.0',
            'settings' => $settings,
        ]);
    }

    /**
     * List all database backup snapshots.
     */
    public function listBackups(): JsonResponse
    {
        $dir = $this->getBackupsDirectory();
        $dbPath = $this->getDatabasePath();
        $dbSize = File::exists($dbPath) ? File::size($dbPath) : 0;

        $files = File::files($dir);
        $backups = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();
            if (!in_array($file->getExtension(), ['sqlite', 'sql', 'bak', 'gz'], true)) {
                continue;
            }

            $size = $file->getSize();
            $mtime = $file->getMTime();
            $createdAt = \Carbon\Carbon::createFromTimestamp($mtime);

            $type = 'manual';
            if (str_contains($filename, 'auto') || str_contains($filename, 'schedule')) {
                $type = 'automated';
            } elseif (str_contains($filename, 'prerestore')) {
                $type = 'pre-restore';
            }

            $backups[] = [
                'filename' => $filename,
                'size_bytes' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_at' => $createdAt->toIso8601String(),
                'created_at_human' => $createdAt->diffForHumans(),
                'type' => $type,
            ];
        }

        usort($backups, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return response()->json([
            'database_path' => $dbPath,
            'database_size_bytes' => $dbSize,
            'database_size_formatted' => $this->formatBytes($dbSize),
            'auto_backup_schedule' => Setting::get('auto_backup_schedule', 'daily'),
            'backups_count' => count($backups),
            'backups' => $backups,
        ]);
    }

    /**
     * Create a new database backup snapshot.
     */
    public function createBackup(Request $request): JsonResponse
    {
        $dbPath = $this->getDatabasePath();
        if (!File::exists($dbPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Source database file not found at ' . $dbPath,
            ], 404);
        }

        $dir = $this->getBackupsDirectory();
        $note = $request->input('label') ? '_' . \Illuminate\Support\Str::slug($request->input('label')) : '';
        $filename = 'backup_evolex_' . now()->format('Y-m-d_His') . $note . '.sqlite';
        $destination = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!@copy($dbPath, $destination)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create database copy. Please check directory permissions.',
            ], 500);
        }

        $size = File::size($destination);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'database.backup_created',
            'meta' => [
                'filename' => $filename,
                'size' => $this->formatBytes($size),
                'source' => $dbPath,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Database snapshot '{$filename}' created successfully.",
            'backup' => [
                'filename' => $filename,
                'size_bytes' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_at' => now()->toIso8601String(),
                'created_at_human' => 'just now',
                'type' => 'manual',
            ],
        ]);
    }

    /**
     * Download a specific backup snapshot.
     */
    public function downloadBackup(string $filename)
    {
        $clean = basename($filename);
        $path = $this->getBackupsDirectory() . DIRECTORY_SEPARATOR . $clean;

        if (!File::exists($path)) {
            abort(404, 'Backup file not found.');
        }

        return response()->download($path, $clean, [
            'Content-Type' => 'application/x-sqlite3',
        ]);
    }

    /**
     * Restore database from a backup snapshot.
     */
    public function restoreBackup(Request $request, string $filename): JsonResponse
    {
        $clean = basename($filename);
        $backupPath = $this->getBackupsDirectory() . DIRECTORY_SEPARATOR . $clean;

        if (!File::exists($backupPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Selected backup file does not exist.',
            ], 404);
        }

        $dbPath = $this->getDatabasePath();

        // Create safety pre-restore backup first
        $dir = $this->getBackupsDirectory();
        $safetyFilename = 'backup_evolex_prerestore_' . now()->format('Y-m-d_His') . '.sqlite';
        if (File::exists($dbPath)) {
            @copy($dbPath, $dir . DIRECTORY_SEPARATOR . $safetyFilename);
        }

        if (!@copy($backupPath, $dbPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore database from backup file.',
            ], 500);
        }

        try {
            Artisan::call('cache:clear');
        } catch (\Throwable $e) {}

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'database.restored',
            'meta' => [
                'restored_from' => $clean,
                'safety_snapshot' => $safetyFilename,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Database restored from '{$clean}'. A pre-restore safety snapshot ({$safetyFilename}) was also created.",
        ]);
    }

    /**
     * Delete a backup snapshot.
     */
    public function deleteBackup(Request $request, string $filename): JsonResponse
    {
        $clean = basename($filename);
        $path = $this->getBackupsDirectory() . DIRECTORY_SEPARATOR . $clean;

        if (File::exists($path)) {
            File::delete($path);
        }

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'database.backup_deleted',
            'meta' => ['filename' => $clean],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Backup file '{$clean}' deleted.",
        ]);
    }

    /**
     * Retrieve audit and activity logs with filters.
     */
    public function activityLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name,email,role')->latest();

        if ($action = $request->input('action')) {
            if ($action !== 'all') {
                $query->where('action', $action);
            }
        }

        if ($userId = $request->input('user_id')) {
            if ($userId !== 'all') {
                $query->where('user_id', $userId);
            }
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $limit = min((int) ($request->input('limit', 100)), 500);
        $logs = $query->take($limit)->get();

        $totalCount = AuditLog::count();
        $todayCount = AuditLog::whereDate('created_at', today())->count();
        $securityCount = AuditLog::where(function ($q) {
            $q->where('action', 'like', '%2fa%')
              ->orWhere('action', 'like', '%login%')
              ->orWhere('action', 'like', '%auth%');
        })->count();
        $systemCount = AuditLog::where(function ($q) {
            $q->where('action', 'like', '%settings%')
              ->orWhere('action', 'like', '%cache%')
              ->orWhere('action', 'like', '%database%');
        })->count();

        $actions = AuditLog::distinct()->pluck('action')->filter()->values();
        $users = User::select('id', 'name', 'email', 'role')->get();

        return response()->json([
            'metrics' => [
                'total_events' => $totalCount,
                'today_events' => $todayCount,
                'security_events' => $securityCount,
                'system_events' => $systemCount,
            ],
            'available_actions' => $actions,
            'available_users' => $users,
            'logs' => $logs,
        ]);
    }

    /**
     * Purge or prune activity logs.
     */
    public function clearActivityLogs(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);

        if ($days === 'all' || $days === 0 || $days === '0') {
            $deleted = AuditLog::count();
            AuditLog::truncate();
            $msg = 'All activity logs have been cleared.';
        } else {
            $cutoff = now()->subDays((int) $days);
            $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();
            $msg = "Purged activity logs older than {$days} days ({$deleted} record(s) removed).";
        }

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'audit_logs.pruned',
            'meta' => ['retention_days' => $days],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $msg,
        ]);
    }

    private function getDatabasePath(): string
    {
        $path = config('database.connections.sqlite.database');
        if ($path && File::exists($path)) {
            return $path;
        }
        if (File::exists(database_path('database.sqlite'))) {
            return database_path('database.sqlite');
        }
        if (File::exists('C:/sqlite/database.sqlite')) {
            return 'C:/sqlite/database.sqlite';
        }
        return $path ?: database_path('database.sqlite');
    }

    private function ensureDefaultSettings(): void
    {
        if (! Setting::where('key', 'company_name')->exists()) {
            app(SettingSeeder::class)->run();
        }
    }

    private function getBackupsDirectory(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        return $dir;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
