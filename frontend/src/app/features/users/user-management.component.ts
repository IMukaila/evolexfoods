import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../core/services/api.service';
import { AuthService } from '../../core/services/auth.service';

interface ManagedUser {
  id: number;
  name: string;
  email: string;
  role: string;
  role_id: number | null;
  role_name: string | null;
  phone: string | null;
  department: string | null;
  status: string;
  created_at: string;
  can_view_password: boolean;
}

interface Role {
  id: number;
  name: string;
  is_active: boolean;
}

type ModalMode = 'create' | 'edit' | 'change-password' | 'reset-confirm';

@Component({
  selector: 'app-user-management',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './user-management.component.html',
  styleUrl: './user-management.component.scss'
})
export class UserManagementComponent implements OnInit {
  users: ManagedUser[] = [];
  roles: Role[] = [];

  // pagination / search
  search = '';
  statusFilter = '';
  currentPage = 1;
  perPage = 20;
  totalItems = 0;
  totalPages = 0;

  // modal state
  modalMode: ModalMode | null = null;
  saving = false;
  formError = '';

  // create / edit form
  form = this.emptyForm();
  editingId: number | null = null;

  // newly created password (shown once)
  createdPassword: string | null = null;
  createdUserName = '';
  passwordCopied = false;

  // reset password
  resetTargetId: number | null = null;
  resetTargetName = '';
  resettingPassword = false;
  resetPassword: string | null = null;

  // change-password (self)
  cpCurrentPassword = '';
  cpNewPassword = '';
  cpConfirm = '';
  cpError = '';
  cpSuccess = '';
  cpSaving = false;

  // current logged-in user info
  get me() { return this.auth.user; }
  get isPrivileged(): boolean {
    const r = (this.me?.role || '').toLowerCase();
    return ['owner', 'administrator', 'admin'].includes(r);
  }

  constructor(private api: ApiService, public auth: AuthService) {}

  ngOnInit(): void {
    this.loadUsers();
    this.loadRoles();
  }

  // ── Load ─────────────────────────────────────────────────────────────────

  loadUsers(): void {
    const params: any = { page: this.currentPage, per_page: this.perPage };
    if (this.search) params.search = this.search;
    if (this.statusFilter) params.status = this.statusFilter;

    this.api.get<any>('/users', params).subscribe({
      next: (res) => {
        this.users = res.data;
        this.totalItems = res.meta.total;
        this.totalPages = res.meta.last_page;
        this.currentPage = res.meta.current_page;
      },
      error: (err) => console.error('Failed to load users', err)
    });
  }

  loadRoles(): void {
    this.api.get<any>('/roles', { per_page: 100 }).subscribe({
      next: (res) => { this.roles = res.data.filter((r: Role) => r.is_active); },
      error: () => {}
    });
  }

  onSearch(): void { this.currentPage = 1; this.loadUsers(); }
  changePage(p: number): void { if (p >= 1 && p <= this.totalPages) { this.currentPage = p; this.loadUsers(); } }

  // ── Create modal ──────────────────────────────────────────────────────────

  openCreate(): void {
    this.form = this.emptyForm();
    this.editingId = null;
    this.formError = '';
    this.createdPassword = null;
    this.modalMode = 'create';
  }

  // ── Edit modal ────────────────────────────────────────────────────────────

  openEdit(u: ManagedUser): void {
    this.form = {
      name: u.name,
      email: u.email,
      role: u.role,
      role_id: u.role_id,
      phone: u.phone ?? '',
      department: u.department ?? '',
      status: u.status,
      password: ''
    };
    this.editingId = u.id;
    this.formError = '';
    this.modalMode = 'edit';
  }

  // ── Save ──────────────────────────────────────────────────────────────────

  save(): void {
    this.saving = true;
    this.formError = '';

    const body: any = {
      name: this.form.name,
      email: this.form.email,
      role: this.form.role,
      role_id: this.form.role_id || null,
      phone: this.form.phone || null,
      department: this.form.department || null,
      status: this.form.status,
    };
    if (this.form.password) body.password = this.form.password;

    if (this.modalMode === 'create') {
      this.api.post<any>('/users', body).subscribe({
        next: (res) => {
          this.saving = false;
          this.createdPassword = res.plain_password ?? null;
          this.createdUserName = res.data.name;
          this.passwordCopied = false;
          this.loadUsers();
          if (this.createdPassword) {
            this.modalMode = 'create'; // keep modal open to show password
          } else {
            this.closeModal();
          }
        },
        error: (err) => {
          this.saving = false;
          this.formError = err?.error?.message || err?.error?.errors
            ? Object.values(err.error.errors || {}).flat().join(' ')
            : 'Failed to create user.';
        }
      });
    } else {
      this.api.put<any>(`/users/${this.editingId}`, body).subscribe({
        next: () => { this.saving = false; this.loadUsers(); this.closeModal(); },
        error: (err) => {
          this.saving = false;
          this.formError = err?.error?.message || 'Failed to update user.';
        }
      });
    }
  }

  // ── Delete ────────────────────────────────────────────────────────────────

  deleteUser(u: ManagedUser): void {
    if (!confirm(`Delete user "${u.name}" (${u.email})? This cannot be undone.`)) return;
    this.api.delete<any>(`/users/${u.id}`).subscribe({
      next: () => this.loadUsers(),
      error: (err) => alert(err?.error?.message || 'Failed to delete user.')
    });
  }

  // ── Reset password (privileged) ───────────────────────────────────────────

  openResetPassword(u: ManagedUser): void {
    this.resetTargetId = u.id;
    this.resetTargetName = u.name;
    this.resetPassword = null;
    this.passwordCopied = false;
    this.modalMode = 'reset-confirm';
  }

  confirmReset(): void {
    if (!this.resetTargetId) return;
    this.resettingPassword = true;
    this.api.post<any>(`/users/${this.resetTargetId}/reset-password`, {}).subscribe({
      next: (res) => {
        this.resettingPassword = false;
        this.resetPassword = res.plain_password;
        this.passwordCopied = false;
      },
      error: (err) => {
        this.resettingPassword = false;
        alert(err?.error?.message || 'Failed to reset password.');
        this.closeModal();
      }
    });
  }

  // ── Change own password ───────────────────────────────────────────────────

  openChangePassword(): void {
    this.cpCurrentPassword = '';
    this.cpNewPassword = '';
    this.cpConfirm = '';
    this.cpError = '';
    this.cpSuccess = '';
    this.cpSaving = false;
    this.modalMode = 'change-password';
  }

  submitChangePassword(): void {
    this.cpError = '';
    if (this.cpNewPassword !== this.cpConfirm) {
      this.cpError = 'New passwords do not match.'; return;
    }
    if (this.cpNewPassword.length < 8) {
      this.cpError = 'Password must be at least 8 characters.'; return;
    }
    this.cpSaving = true;
    this.api.post<any>('/auth/change-password', {
      current_password: this.cpCurrentPassword,
      new_password: this.cpNewPassword,
      new_password_confirmation: this.cpConfirm
    }).subscribe({
      next: () => { this.cpSaving = false; this.cpSuccess = 'Password changed successfully!'; },
      error: (err) => {
        this.cpSaving = false;
        this.cpError = err?.error?.message || 'Failed to change password.';
      }
    });
  }

  // ── Clipboard ─────────────────────────────────────────────────────────────

  copyPassword(pwd: string | null): void {
    if (!pwd) return;
    navigator.clipboard.writeText(pwd);
    this.passwordCopied = true;
    setTimeout(() => (this.passwordCopied = false), 3000);
  }

  // ── Misc ──────────────────────────────────────────────────────────────────

  closeModal(): void {
    this.modalMode = null;
    this.createdPassword = null;
    this.resetPassword = null;
  }

  statusBadge(status: string): string {
    return { active: 'badge-active', inactive: 'badge-inactive', suspended: 'badge-suspended' }[status] ?? 'badge-inactive';
  }

  private emptyForm() {
    return { name: '', email: '', role: 'farm_employee', role_id: null as number | null,
             phone: '', department: '', status: 'active', password: '' };
  }

  get pages(): number[] {
    return Array.from({ length: this.totalPages }, (_, i) => i + 1);
  }
}
