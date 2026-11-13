import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';

export interface SystemInfo {
  app_name: string;
  app_env: string;
  app_url: string;
  laravel_version: string;
  php_version: string;
  database_connection: string;
  server_time: string;
  server_timezone: string;
  server_status: string;
  active_users_count: number;
  total_livestock_count: number;
  storage_status: string;
}

export interface SettingsResponse {
  settings: Record<string, Record<string, any>>;
  system_info: SystemInfo;
}

export interface TestEmailResult {
  success: boolean;
  message: string;
  details?: {
    recipient: string;
    smtp_server: string;
    encryption: string;
    from_sender: string;
    handshake: string;
    latency_ms: number;
    sent_at: string;
  };
}

export interface DatabaseBackup {
  filename: string;
  size_bytes: number;
  size_formatted: string;
  created_at: string;
  created_at_human: string;
  type: 'manual' | 'automated' | 'pre-restore';
}

export interface BackupListResponse {
  database_path: string;
  database_size_bytes: number;
  database_size_formatted: string;
  auto_backup_schedule: string;
  backups_count: number;
  backups: DatabaseBackup[];
}

export interface ActivityLog {
  id: number;
  user_id: number | null;
  action: string;
  auditable_type?: string | null;
  auditable_id?: number | null;
  meta?: Record<string, any> | null;
  ip_address?: string | null;
  created_at: string;
  user?: {
    id: number;
    name: string;
    email: string;
    role: string;
  } | null;
}

export interface ActivityLogsResponse {
  metrics: {
    total_events: number;
    today_events: number;
    security_events: number;
    system_events: number;
  };
  available_actions: string[];
  available_users: Array<{ id: number; name: string; email: string; role: string }>;
  logs: ActivityLog[];
}

@Injectable({
  providedIn: 'root'
})
export class SettingsService {
  constructor(private api: ApiService, private http: HttpClient) {}

  getSettings(): Observable<SettingsResponse> {
    return this.api.get<SettingsResponse>('/settings');
  }

  updateSettings(settings: Record<string, any>): Observable<{ success: boolean; message: string; settings: any }> {
    return this.api.put<{ success: boolean; message: string; settings: any }>('/settings', settings);
  }

  sendTestEmail(recipient?: string): Observable<TestEmailResult> {
    return this.api.post<TestEmailResult>('/settings/test-email', { recipient });
  }

  clearCache(): Observable<{ success: boolean; message: string }> {
    return this.api.post<{ success: boolean; message: string }>('/settings/clear-cache', {});
  }

  exportConfig(): Observable<any> {
    return this.api.get<any>('/settings/export');
  }

  // Database Backups API
  getBackups(): Observable<BackupListResponse> {
    return this.api.get<BackupListResponse>('/settings/backups');
  }

  createBackup(label?: string): Observable<{ success: boolean; message: string; backup: DatabaseBackup }> {
    return this.api.post<{ success: boolean; message: string; backup: DatabaseBackup }>('/settings/backups', { label });
  }

  downloadBackup(filename: string): Observable<Blob> {
    return this.http.get(`/api/v1/settings/backups/download/${encodeURIComponent(filename)}`, {
      responseType: 'blob'
    });
  }

  restoreBackup(filename: string): Observable<{ success: boolean; message: string }> {
    return this.api.post<{ success: boolean; message: string }>(`/settings/backups/restore/${encodeURIComponent(filename)}`, {});
  }

  deleteBackup(filename: string): Observable<{ success: boolean; message: string }> {
    return this.api.delete<{ success: boolean; message: string }>(`/settings/backups/${encodeURIComponent(filename)}`);
  }

  // Activity Logs API
  getActivityLogs(params?: { action?: string; user_id?: string | number; search?: string; limit?: number }): Observable<ActivityLogsResponse> {
    return this.api.get<ActivityLogsResponse>('/settings/activity-logs', params as any);
  }

  clearActivityLogs(days: number | string): Observable<{ success: boolean; message: string }> {
    return this.api.post<{ success: boolean; message: string }>('/settings/activity-logs/clear', { days });
  }

  // ─── Brand Asset (Logo / Favicon) Uploads ──────────────────────────────
  // FormData is used (not ApiService.post) so the browser sends multipart/form-data.
  uploadLogo(file: File): Observable<{ success: boolean; url: string; message: string }> {
    const formData = new FormData();
    formData.append('logo', file);
    return this.http.post<{ success: boolean; url: string; message: string }>('/api/v1/settings/upload-logo', formData);
  }

  uploadFavicon(file: File): Observable<{ success: boolean; url: string; message: string }> {
    const formData = new FormData();
    formData.append('favicon', file);
    return this.http.post<{ success: boolean; url: string; message: string }>('/api/v1/settings/upload-favicon', formData);
  }

  removeLogo(): Observable<{ success: boolean; message: string }> {
    return this.http.delete<{ success: boolean; message: string }>('/api/v1/settings/logo');
  }

  removeFavicon(): Observable<{ success: boolean; message: string }> {
    return this.http.delete<{ success: boolean; message: string }>('/api/v1/settings/favicon');
  }
}
