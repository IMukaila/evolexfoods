import { Component, HostListener, OnDestroy, OnInit } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { CommonModule, NgClass, NgFor, NgIf, NgSwitch, NgSwitchCase } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService, TwoFactorSetupResponse, TwoFaMethod } from '../core/services/auth.service';
import { NotificationService } from '../core/services/notification.service';
import { SettingsService } from '../core/services/settings.service';
import { AppNotification } from '../core/models/notification.model';

@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, NgFor, NgIf, NgSwitch, NgSwitchCase, NgClass, FormsModule],
  templateUrl: './shell.component.html',
  styleUrl: './shell.component.scss'
})
export class ShellComponent implements OnInit, OnDestroy {
  sidebarCollapsed = false;
  darkMode = false;
  showNotifications = false;
  showUserMenu = false;
  searchQuery = '';
  logoUrl = 'assets/logo-full-dark.svg';
  activePopupGroup: any = null;
  popupTop = 0;
  private popupCloseTimer: any = null;
  showLogoutConfirm = false;

  // Notifications (live from the Notification Centre API)
  notifications: AppNotification[] = [];
  notificationCount = 0;
  private notificationRefreshTimer: ReturnType<typeof setInterval> | null = null;

  // ─── 2FA Mandatory Modal ───────────────────────────────────────────────────
  showTwoFactorModal = false;
  twoFactorSetup: TwoFactorSetupResponse | null = null;
  twoFactorActivationCode = '';
  twoFactorActivating = false;
  twoFactorError = '';
  twoFactorSuccess = false;
  copiedKey = false;
  copiedCodes = false;

  // Multi-method activation
  shellActivationStep: 'select-method' | 'send-otp' | 'verify' = 'select-method';
  shellSelectedMethod: TwoFaMethod = 'totp';
  shellOtpSent = false;
  shellOtpSending = false;
  shellOtpSendHint = '';

  navGroups = [
    {
      label: 'Dashboard',
      icon: 'hub',
      expanded: true,
      items: [
        { path: '/hub', label: 'Hub', icon: 'hub', permission: 'dashboard.view' },
        { path: '/notifications', label: 'Notifications', icon: 'notifications', permission: 'notifications.view' },
        { path: '/ceo', label: 'CEO Command', icon: 'ceo', permission: 'ceo.view' },
        { path: '/reports', label: 'Reports', icon: 'report', permission: 'reports.view' }
      ]
    },
    {
      label: 'Operations',
      icon: 'livestock',
      expanded: false,
      items: [
        { path: '/livestock', label: 'Livestock', icon: 'livestock', permission: 'livestock.view' },
        { path: '/health', label: 'Health', icon: 'health', permission: 'health.view' },
        { path: '/treatments', label: 'Treatments', icon: 'treatments', permission: 'treatments.view' },
        { path: '/breeding', label: 'Breeding', icon: 'breeding', permission: 'breeding.view' },
        { path: '/kidding-radar', label: 'Kidding Radar', icon: 'breeding', permission: 'breeding.view' },
        { path: '/feed', label: 'Feed', icon: 'feed', permission: 'feed.view' }
      ]
    },
    {
      label: 'Supply Chain',
      icon: 'inventory',
      expanded: false,
      items: [
        { path: '/inventory', label: 'Inventory', icon: 'inventory', permission: 'inventory.view' },
        { path: '/procurement', label: 'Procurement', icon: 'procurement', permission: 'procurement.view' },
        { path: '/suppliers', label: 'Suppliers', icon: 'suppliers', permission: 'suppliers.view' },
        { path: '/assets', label: 'Assets', icon: 'assets', permission: 'assets.view' },
        { path: '/maintenance', label: 'Maintenance', icon: 'maintenance', permission: 'maintenance.view' }
      ]
    },
    {
      label: 'Human Resources',
      icon: 'people',
      expanded: false,
      items: [
        { path: '/hr', label: 'People', icon: 'people', permission: 'hr.view' },
        { path: '/users', label: 'User Accounts', icon: 'settings', permission: 'settings.manage_roles' },
        { path: '/tasks', label: 'Tasks', icon: 'tasks', permission: 'tasks.view' }
      ]
    },
    {
      label: 'Finance & Accounting',
      icon: 'expenses',
      expanded: true,
      items: [
        { path: '/finance', label: 'Overview', icon: 'ceo', permission: 'finance.view' },
        { path: '/expenses', label: 'Expenses', icon: 'expenses', permission: 'expenses.view' },
        { path: '/invoices', label: 'Invoices & Payables', icon: 'invoices', permission: 'invoices.view' },
        { path: '/payments', label: 'Payments', icon: 'invoices', permission: 'payments.view' },
        { path: '/finance/reports', label: 'Reports', icon: 'ceo', permission: 'finance.view' }
      ]
    },
    {
      label: 'Commercial',
      icon: 'crm',
      expanded: false,
      items: [
        { path: '/crm', label: 'CRM · Companies', icon: 'crm', permission: 'crm.view' },
        { path: '/contacts', label: 'Contacts', icon: 'crm', permission: 'contacts.view' },
        { path: '/deals', label: 'Deals & Pipeline', icon: 'deals', permission: 'deals.view' },
        { path: '/sales', label: 'Sales & Culls', icon: 'deals', permission: 'sales.view' },
        { path: '/campaigns', label: 'Campaigns', icon: 'marketing', permission: 'campaigns.view' },
        { path: '/marketing', label: 'Content Calendar', icon: 'marketing', permission: 'marketing.view' },
        { path: '/media-assets', label: 'Media Library', icon: 'marketing', permission: 'marketing.view' },
        { path: '/marketing/performance', label: 'Channel Performance', icon: 'marketing', permission: 'marketing.view' }
      ]
    },
    {
      label: 'Governance',
      icon: 'risks',
      expanded: false,
      items: [
        { path: '/risks', label: 'Risks', icon: 'risks', permission: 'governance.view' },
        { path: '/decisions', label: 'Decisions', icon: 'decisions', permission: 'governance.view' },
        { path: '/sops', label: 'SOPs', icon: 'sops', permission: 'governance.view' },
        { path: '/roles', label: 'Roles & Permissions', icon: 'settings', permission: 'settings.manage_roles' },
        { path: '/settings', label: 'System Settings', icon: 'settings', permission: 'settings.view' }
      ]
    }
  ];

  isItemPermitted(item: { path: string; label: string; icon: string; permission?: string }): boolean {
    if (!item.permission) return true;
    return this.auth.hasPermission(item.permission);
  }

  isGroupVisible(group: { label: string; icon: string; expanded: boolean; items: any[] }): boolean {
    return group.items.some(item => this.isItemPermitted(item));
  }

  getPermittedItems(group: { items: any[] }): any[] {
    return group.items.filter(item => this.isItemPermitted(item));
  }

  constructor(
    public auth: AuthService,
    private router: Router,
    private notificationSvc: NotificationService,
    private settingsSvc: SettingsService
  ) {
    // Load dark mode preference from localStorage
    const savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode) {
      this.darkMode = savedDarkMode === 'true';
    }
  }

  ngOnInit(): void {
    this.checkTwoFactorStatus();
    this.auth.user$.subscribe(() => {
      this.checkTwoFactorStatus();
    });

    // Live notification badge + dropdown content
    this.notificationSvc.unreadCount$.subscribe((count) => (this.notificationCount = count));
    this.notificationSvc.recent$.subscribe((items) => (this.notifications = items));
    this.notificationSvc.refresh();
    this.notificationRefreshTimer = setInterval(() => this.notificationSvc.refresh(), 60_000);

    // Display the uploaded company logo in the sidebar (fall back to the default asset).
    this.settingsSvc.getSettings().subscribe({
      next: (res) => {
        const logo = res.settings?.['general']?.['site_logo'];
        if (logo) {
          this.logoUrl = logo;
        }
      },
      error: () => void 0, // keep default asset if settings are unavailable
    });
  }

  ngOnDestroy(): void {
    if (this.popupCloseTimer) {
      clearTimeout(this.popupCloseTimer);
    }
    if (this.notificationRefreshTimer) {
      clearInterval(this.notificationRefreshTimer);
    }
  }

  checkTwoFactorStatus(): void {
    // 2FA activation is voluntary from Settings -> Security, never forced
    this.showTwoFactorModal = false;
  }

  loadTwoFactorSetup(): void {
    this.auth.setup2fa().subscribe({
      next: (data) => { this.twoFactorSetup = data; },
      error: (err)  => { console.error('Failed to load 2FA setup in shell:', err); }
    });
  }

  shellSelectMethod(m: TwoFaMethod): void {
    this.shellSelectedMethod = m;
  }

  shellProceedToVerify(): void {
    this.twoFactorActivationCode = '';
    this.twoFactorError = '';
    this.shellOtpSent = false;
    this.shellOtpSendHint = '';
    this.shellActivationStep = this.shellSelectedMethod === 'totp' ? 'verify' : 'send-otp';
  }

  shellSendOtp(): void {
    if (this.shellOtpSending) return;
    this.shellOtpSending = true;
    this.twoFactorError  = '';
    this.auth.sendOtp(this.shellSelectedMethod as 'email' | 'whatsapp').subscribe({
      next: (res) => {
        this.shellOtpSending    = false;
        this.shellOtpSent       = true;
        this.shellOtpSendHint   = res.message;
        this.shellActivationStep = 'verify';
      },
      error: (err) => {
        this.shellOtpSending = false;
        this.twoFactorError  = err?.error?.message || 'Failed to send code. Please try again.';
      }
    });
  }

  shellBackToSelect(): void {
    this.shellActivationStep = 'select-method';
    this.twoFactorActivationCode = '';
    this.twoFactorError = '';
    this.shellOtpSent = false;
  }

  submitTwoFactorActivation(): void {
    if (!this.twoFactorActivationCode || this.twoFactorActivationCode.trim().length < 6) {
      this.twoFactorError = 'Please enter a valid 6-digit verification code.';
      return;
    }

    this.twoFactorActivating = true;
    this.twoFactorError = '';

    this.auth.activate2fa(this.twoFactorActivationCode.trim(), this.shellSelectedMethod).subscribe({
      next: () => {
        this.twoFactorActivating = false;
        this.twoFactorSuccess = true;
        setTimeout(() => {
          this.showTwoFactorModal = false;
          this.twoFactorSuccess = false;
        }, 1500);
      },
      error: (err) => {
        this.twoFactorActivating = false;
        this.twoFactorError = err?.error?.message || 'Invalid verification code. Please try again.';
      }
    });
  }

  copySecretKey(): void {
    if (this.twoFactorSetup?.secret) {
      navigator.clipboard.writeText(this.twoFactorSetup.secret);
      this.copiedKey = true;
      setTimeout(() => this.copiedKey = false, 2500);
    }
  }

  copyRecoveryCodes(): void {
    if (this.twoFactorSetup?.recovery_codes) {
      navigator.clipboard.writeText(this.twoFactorSetup.recovery_codes.join('\n'));
      this.copiedCodes = true;
      setTimeout(() => this.copiedCodes = false, 2500);
    }
  }

  get shellMethodLabel(): string {
    const map: Record<TwoFaMethod, string> = { totp: 'Authenticator App', email: 'Email', whatsapp: 'WhatsApp' };
    return map[this.shellSelectedMethod];
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent) {
    const target = event.target as HTMLElement;
    if (!target.closest('.notification-btn') && !target.closest('.notifications-dropdown')) {
      this.showNotifications = false;
    }
    if (!target.closest('.user-menu')) {
      this.showUserMenu = false;
    }
  }

  @HostListener('document:keydown', ['$event'])
  onKeyDown(event: KeyboardEvent) {
    if (event.key === 'Escape' && this.showLogoutConfirm) {
      this.showLogoutConfirm = false;
      return;
    }
    // Ctrl+K or Cmd+K for search focus
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
      event.preventDefault();
      const searchInput = document.querySelector('.search-bar input') as HTMLInputElement;
      searchInput?.focus();
    }
  }

  toggleSidebar() {
    this.sidebarCollapsed = !this.sidebarCollapsed;
  }

  toggleDarkMode() {
    this.darkMode = !this.darkMode;
    localStorage.setItem('darkMode', this.darkMode.toString());
  }

  toggleNotifications() {
    this.showNotifications = !this.showNotifications;
    this.showUserMenu = false;
    if (this.showNotifications) {
      this.notificationSvc.refresh();
    }
  }

  toggleUserMenu() {
    this.showUserMenu = !this.showUserMenu;
    this.showNotifications = false;
  }

  onSearch() {
    console.log('Searching for:', this.searchQuery);
    // Implement search logic here
  }

  markAllRead() {
    this.notificationSvc.markAllRead().subscribe({
      next: () => this.notificationSvc.refresh(),
      error: () => void 0
    });
  }

  openNotification(notif: AppNotification) {
    this.showNotifications = false;
    this.router.navigate(['/notifications'], { queryParams: { id: notif.id } });
  }

  openAllNotifications() {
    this.showNotifications = false;
    this.router.navigate(['/notifications']);
  }

  notifTimeAgo(value: string | null | undefined): string {
    if (!value) return '';
    const then = new Date(value).getTime();
    if (Number.isNaN(then)) return '';
    const seconds = Math.max(0, Math.floor((Date.now() - then) / 1000));
    if (seconds < 45) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} min ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} day${days === 1 ? '' : 's'} ago`;
    return new Date(value).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }

  getUserInitials(): string {
    const name = this.auth.user?.name || 'User';
    return name
      .split(' ')
      .map(n => n[0])
      .join('')
      .toUpperCase()
      .substring(0, 2);
  }

  navigateTo(path: string) {
    this.showUserMenu = false;
    this.router.navigate([path]);
  }

  toggleNavGroup(group: any) {
    if (!this.sidebarCollapsed) {
      // Close all other groups
      this.navGroups.forEach(g => {
        if (g !== group) {
          g.expanded = false;
        }
      });
      // Toggle current group
      group.expanded = !group.expanded;
    }
  }

  showPopup(group: any, event?: MouseEvent) {
    if (this.popupCloseTimer) {
      clearTimeout(this.popupCloseTimer);
      this.popupCloseTimer = null;
    }
    if (this.sidebarCollapsed) {
      this.activePopupGroup = group;
      if (event) {
        const target = (event.currentTarget as HTMLElement) || (event.target as HTMLElement);
        if (target) {
          const rect = target.getBoundingClientRect();
          const popupEstimatedHeight = Math.min(360, (group.items?.length || 4) * 44 + 60);
          let top = rect.top;
          if (top + popupEstimatedHeight > window.innerHeight) {
            top = Math.max(12, window.innerHeight - popupEstimatedHeight - 16);
          }
          this.popupTop = Math.max(12, top);
        }
      }
    }
  }

  scheduleHidePopup() {
    if (!this.sidebarCollapsed) return;
    if (this.popupCloseTimer) {
      clearTimeout(this.popupCloseTimer);
    }
    this.popupCloseTimer = setTimeout(() => {
      this.activePopupGroup = null;
      this.popupCloseTimer = null;
    }, 180);
  }

  cancelHidePopup() {
    if (this.popupCloseTimer) {
      clearTimeout(this.popupCloseTimer);
      this.popupCloseTimer = null;
    }
  }

  togglePopup(group: any, event: MouseEvent) {
    if (this.sidebarCollapsed) {
      if (this.activePopupGroup === group) {
        this.closePopup();
      } else {
        this.showPopup(group, event);
      }
    }
  }

  closePopup() {
    if (this.popupCloseTimer) {
      clearTimeout(this.popupCloseTimer);
      this.popupCloseTimer = null;
    }
    this.activePopupGroup = null;
  }

  // ─── Logout Confirmation ──────────────────────────────────────────────────
  confirmLogout(): void {
    this.showUserMenu = false;
    this.showLogoutConfirm = true;
  }

  cancelLogout(): void {
    this.showLogoutConfirm = false;
  }

  performLogout(): void {
    this.showLogoutConfirm = false;
    this.auth.logout();
  }
}
