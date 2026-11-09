<?php

use App\Http\Controllers\Api\V1\AssetMaintenanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CrmController;
use App\Http\Controllers\Api\V1\CommunicationController;
use App\Http\Controllers\Api\V1\CrudController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\FeedInventoryController;
use App\Http\Controllers\Api\V1\FinanceController;
use App\Http\Controllers\Api\V1\GovernanceController;
use App\Http\Controllers\Api\V1\HrTaskController;
use App\Http\Controllers\Api\V1\LivestockController;
use App\Http\Controllers\Api\V1\MarketingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProcurementController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

// Railway health check — no auth required
Route::get('/health', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return response()->json(['status' => 'ok', 'db' => 'connected'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'db' => 'disconnected'], 500);
    }
});

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/2fa/setup', [AuthController::class, 'setup2fa']);
        Route::post('/auth/2fa/send-otp', [AuthController::class, 'sendOtp']);
        Route::post('/auth/2fa/activate', [AuthController::class, 'activate2fa']);
        Route::post('/auth/2fa/deactivate', [AuthController::class, 'deactivate2fa']);

        Route::get('/hub', [DashboardController::class, 'hub']);
        Route::get('/dashboard/ceo', [DashboardController::class, 'ceo']);

        Route::get('/livestock/summary', [LivestockController::class, 'summary']);
        Route::get('/livestock/next-id', [LivestockController::class, 'nextId']);
        Route::get('/livestock/gestation-radar', [LivestockController::class, 'gestationRadar']);
        Route::get('/livestock/pregnancies', [LivestockController::class, 'pregnancies']);
        Route::get('/livestock/births', [LivestockController::class, 'births']);
        Route::get('/livestock/deaths', [LivestockController::class, 'deaths']);
        Route::get('/livestock/mortality-rate', [LivestockController::class, 'mortalityRate']);
        Route::get('/livestock/mortality', [LivestockController::class, 'mortalityRate']);
        Route::get('/livestock/growth', [LivestockController::class, 'growth']);
        Route::get('/livestock/health/sick', [LivestockController::class, 'sick']);
        Route::get('/livestock/health/treatments', [LivestockController::class, 'treatments']);
        Route::get('/locations', [EventController::class, 'locations']);
        Route::post('/livestock/events/birth', [EventController::class, 'birth']);
        Route::post('/livestock/events/death', [EventController::class, 'death']);
        Route::post('/livestock/events/sick', [EventController::class, 'sick']);
        Route::post('/livestock/events/treatment', [EventController::class, 'treatment']);
        Route::post('/procurement/requests', [EventController::class, 'purchaseRequest']);

        Route::get('/livestock', [LivestockController::class, 'index']);
        Route::post('/livestock', fn (\Illuminate\Http\Request $req) => app(CrudController::class)->store($req, 'livestock'));
        Route::get('/livestock/{id}', [LivestockController::class, 'show']);
        Route::put('/livestock/{id}', fn (\Illuminate\Http\Request $req, int $id) => app(CrudController::class)->update($req, 'livestock', $id));
        Route::delete('/livestock/{id}', fn (int $id) => app(CrudController::class)->destroy('livestock', $id));

        Route::get('/feed/summary', [FeedInventoryController::class, 'feedSummary']);
        Route::get('/feed/days-remaining', [FeedInventoryController::class, 'daysRemaining']);
        Route::get('/feed/items', [FeedInventoryController::class, 'index']);
        Route::post('/feed/items', [FeedInventoryController::class, 'store']);
        Route::get('/feed/items/{id}', [FeedInventoryController::class, 'show']);
        Route::put('/feed/items/{id}', [FeedInventoryController::class, 'update']);
        Route::delete('/feed/items/{id}', [FeedInventoryController::class, 'destroy']);
        Route::post('/feed/transactions', [FeedInventoryController::class, 'logTransaction']);
        Route::get('/feed/transactions', [FeedInventoryController::class, 'transactions']);
        Route::get('/inventory', [FeedInventoryController::class, 'inventory']);
        Route::get('/inventory/medicines', [FeedInventoryController::class, 'medicines']);
        Route::get('/inventory/low-stock', [FeedInventoryController::class, 'lowStock']);

        // 1-Click Weekly Owner Report (§113)
        Route::get('/reports/weekly-owner', [ReportController::class, 'weeklyOwner']);

        Route::get('/procurement/requests', [ProcurementController::class, 'requests']);
        Route::get('/procurement/approval-matrix', [ProcurementController::class, 'approvalMatrix']);
        Route::post('/procurement/requests/{id}/approve', [ProcurementController::class, 'approve'])->whereNumber('id');
        Route::post('/procurement/requests/{id}/reject', [ProcurementController::class, 'reject'])->whereNumber('id');
        Route::post('/procurement/requests/{id}/purchase-order', [ProcurementController::class, 'createPurchaseOrder'])->whereNumber('id');
        Route::get('/procurement/requests/{id}/approvals', [ProcurementController::class, 'approvals']);
        Route::get('/procurement/purchases', [ProcurementController::class, 'purchases']);
        Route::get('/suppliers', [ProcurementController::class, 'suppliers']);

        Route::get('/assets', [AssetMaintenanceController::class, 'index']);
        Route::get('/maintenance/due', [AssetMaintenanceController::class, 'due']);
        Route::get('/maintenance/tickets', [AssetMaintenanceController::class, 'tickets']);

        Route::get('/hr/employees', [HrTaskController::class, 'employees']);
        Route::get('/hr/attendance/today', [HrTaskController::class, 'attendanceToday']);
        Route::get('/tasks/incomplete', [HrTaskController::class, 'incompleteTasks']);

        Route::get('/finance/overview', [FinanceController::class, 'overview']);
        Route::get('/finance/spending', [FinanceController::class, 'spending']);

        Route::get('/crm/prospects', [CrmController::class, 'prospects']);
        Route::get('/crm/opportunities', [CrmController::class, 'opportunities']);

        Route::get('/marketing/content', [MarketingController::class, 'content']);
        Route::get('/marketing/performance', [MarketingController::class, 'performance']);

        Route::get('/risks', [GovernanceController::class, 'risks']);
        Route::get('/decisions/pending-owner', [GovernanceController::class, 'pendingOwner']);
        Route::get('/alerts', [GovernanceController::class, 'alerts']);
        Route::get('/audit-logs', [GovernanceController::class, 'auditLogs']);

        // Notification centre
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/stats', [NotificationController::class, 'stats']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications', [NotificationController::class, 'store']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show'])->whereNumber('id');
        Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');
        Route::patch('/notifications/{id}/unread', [NotificationController::class, 'markUnread'])->whereNumber('id');
        Route::delete('/notifications', [NotificationController::class, 'clear']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->whereNumber('id');
        Route::get('/sops', [GovernanceController::class, 'sops']);
        Route::get('/systems', [GovernanceController::class, 'systems']);

        Route::middleware(\App\Http\Middleware\ManageCommunications::class)->group(function () {
            Route::get('/settings', [SettingsController::class, 'index']);
            Route::put('/settings', [SettingsController::class, 'update']);
            Route::post('/settings/test-email', [SettingsController::class, 'testEmail'])->middleware('throttle:5,1');
            Route::post('/settings/clear-cache', [SettingsController::class, 'clearCache']);
            Route::get('/settings/export', [SettingsController::class, 'exportConfig']);
            Route::get('/settings/backups', [SettingsController::class, 'listBackups']);
            Route::post('/settings/backups', [SettingsController::class, 'createBackup']);
            Route::get('/settings/backups/download/{filename}', [SettingsController::class, 'downloadBackup']);
            Route::post('/settings/backups/restore/{filename}', [SettingsController::class, 'restoreBackup']);
            Route::delete('/settings/backups/{filename}', [SettingsController::class, 'deleteBackup']);

            Route::get('/settings/activity-logs', [SettingsController::class, 'activityLogs']);
            Route::post('/settings/activity-logs/clear', [SettingsController::class, 'clearActivityLogs']);

            Route::post('/settings/test-sms', [CommunicationController::class, 'testSms'])->middleware('throttle:5,1');
            Route::get('/communications/templates', [CommunicationController::class, 'templates']);
            Route::post('/communications/templates', [CommunicationController::class, 'saveTemplate']);
            Route::put('/communications/templates/{id}', [CommunicationController::class, 'saveTemplate'])->whereNumber('id');
            Route::get('/communications/messages', [CommunicationController::class, 'messages']);
            Route::post('/communications/messages', [CommunicationController::class, 'send'])->middleware('throttle:10,1');
            Route::post('/communications/messages/{id}/cancel', [CommunicationController::class, 'cancel'])->whereNumber('id');
        });

        // Role & Permission Management
        Route::get('/roles', [RoleController::class, 'index']);
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::post('/roles/{id}/permissions', [RoleController::class, 'syncPermissions']);

        Route::get('/lookups', [CrudController::class, 'lookups']);
        Route::get('/records/{resource}', [CrudController::class, 'index']);
        Route::post('/records/{resource}', [CrudController::class, 'store']);
        Route::get('/records/{resource}/{id}', [CrudController::class, 'show']);
        Route::put('/records/{resource}/{id}', [CrudController::class, 'update']);
        Route::delete('/records/{resource}/{id}', [CrudController::class, 'destroy']);
    });
});
