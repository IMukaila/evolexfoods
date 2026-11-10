import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { ShellComponent } from './layout/shell.component';
import { LoginComponent } from './features/login/login.component';
import { HubComponent } from './features/hub/hub.component';
import { CeoComponent } from './features/ceo/ceo.component';
import { HealthComponent } from './features/health/health.component';
import { BreedingComponent } from './features/breeding/breeding.component';
import { FeedComponent } from './features/feed/feed.component';
import { LivestockComponent } from './features/livestock/livestock.component';
import { CrudPageComponent } from './features/records/crud-page.component';

export const routes: Routes = [
  { path: 'login', component: LoginComponent },
  {
    path: '',
    component: ShellComponent,
    canActivate: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'ceo' },
      { path: 'hub', component: HubComponent },
      { path: 'notifications', loadComponent: () => import('./features/notifications/notifications.component').then((module) => module.NotificationsComponent) },
      { path: 'ceo', component: CeoComponent },
      { path: 'livestock', component: LivestockComponent },
      { path: 'health', component: HealthComponent, data: { tab: 'cases' } },
      { path: 'treatments', component: HealthComponent, data: { tab: 'treatments' } },
      { path: 'breeding', component: BreedingComponent },
      { path: 'kidding-radar', loadComponent: () => import('./features/kidding-radar/kidding-radar.component').then((module) => module.KiddingRadarComponent) },
      { path: 'feed', component: FeedComponent },
      { path: 'inventory', component: CrudPageComponent, data: { resource: 'inventory' } },
      { path: 'procurement', loadComponent: () => import('./features/procurement/procurement.component').then((module) => module.ProcurementComponent) },
      { path: 'suppliers', component: CrudPageComponent, data: { resource: 'suppliers' } },
      { path: 'assets', component: CrudPageComponent, data: { resource: 'assets' } },
      { path: 'maintenance', component: CrudPageComponent, data: { resource: 'maintenance' } },
      { path: 'hr', component: CrudPageComponent, data: { resource: 'employees' } },
      { path: 'tasks', component: CrudPageComponent, data: { resource: 'tasks' } },
      { path: 'finance/reports', loadComponent: () => import('./features/finance/finance-reports.component').then((module) => module.FinanceReportsComponent) },
      { path: 'finance/expenses', pathMatch: 'full', redirectTo: 'expenses' },
      { path: 'finance/payments', pathMatch: 'full', redirectTo: 'payments' },
      { path: 'finance', pathMatch: 'full', loadComponent: () => import('./features/finance/finance-dashboard.component').then((module) => module.FinanceDashboardComponent) },
      { path: 'expenses', component: CrudPageComponent, data: { resource: 'expenses' } },
      { path: 'invoices', loadComponent: () => import('./features/finance/invoices.component').then((module) => module.InvoicesComponent) },
      { path: 'payments', component: CrudPageComponent, data: { resource: 'payments' } },
      { path: 'crm', component: CrudPageComponent, data: { resource: 'companies' } },
      { path: 'contacts', component: CrudPageComponent, data: { resource: 'contacts' } },
      { path: 'deals', component: CrudPageComponent, data: { resource: 'deals' } },
      { path: 'sales', component: CrudPageComponent, data: { resource: 'sales' } },
      { path: 'campaigns', component: CrudPageComponent, data: { resource: 'campaigns' } },
      { path: 'media-assets', component: CrudPageComponent, data: { resource: 'media_assets' } },
      { path: 'marketing/performance', component: CrudPageComponent, data: { resource: 'channel_metrics' } },
      { path: 'marketing', pathMatch: 'full', component: CrudPageComponent, data: { resource: 'marketing' } },
      { path: 'governance', pathMatch: 'full', redirectTo: 'risks' },
      { path: 'risks', component: CrudPageComponent, data: { resource: 'risks' } },
      { path: 'decisions', component: CrudPageComponent, data: { resource: 'decisions' } },
      { path: 'sops', component: CrudPageComponent, data: { resource: 'sops' } },
      { path: 'report', redirectTo: 'reports', pathMatch: 'full' },
      { path: 'reports', loadComponent: () => import('./features/reports/reports.component').then((module) => module.ReportsComponent) },
      { path: 'help', loadComponent: () => import('./features/help/help.component').then((module) => module.HelpComponent) },
      { path: 'settings', loadComponent: () => import('./features/settings/settings.component').then((module) => module.SettingsComponent) },
      { path: 'roles', loadComponent: () => import('./features/settings/role-management.component').then((module) => module.RoleManagementComponent) },
      { path: 'users', loadComponent: () => import('./features/users/user-management.component').then((m) => m.UserManagementComponent) }
    ]
  },
  { path: '**', redirectTo: 'ceo' }
];
