import { NgModule } from '@angular/core';
import { PreloadAllModules, RouterModule, Routes } from '@angular/router';
import { AuthGuard } from './guards/auth.guard';
import { AdminGuard } from './guards/admin.guard';

const routes: Routes = [
  {
    path: '',
    redirectTo: 'dashboard',
    pathMatch: 'full',
  },
  {
    path: 'login',
    loadChildren: () => import('./pages/login/login.module').then((m) => m.LoginPageModule),
  },
  {
    path: 'register',
    loadChildren: () => import('./pages/register/register.module').then((m) => m.RegisterPageModule),
  },
  {
    path: 'paywall',
    loadChildren: () => import('./pages/paywall/paywall.module').then((m) => m.PaywallPageModule),
  },
  {
    path: 'subscribe',
    loadChildren: () => import('./pages/subscribe/subscribe.module').then((m) => m.SubscribePageModule),
  },
  {
    path: 'dashboard',
    loadChildren: () => import('./pages/dashboard/dashboard.module').then((m) => m.DashboardPageModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'appointments',
    loadChildren: () => import('./pages/appointments/appointments.module').then((m) => m.AppointmentsPageModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'book-appointment',
    loadChildren: () => import('./pages/book-appointment/book-appointment.module').then((m) => m.BookAppointmentPageModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'profile',
    loadChildren: () => import('./pages/profile/profile.module').then((m) => m.ProfilePageModule),
    canActivate: [AuthGuard],
  },
  {
    path: 'admin/users',
    loadChildren: () => import('./pages/admin-users/admin-users.module').then((m) => m.AdminUsersPageModule),
    canActivate: [AuthGuard, AdminGuard],
  },
  {
    path: 'admin/gyms',
    loadChildren: () => import('./pages/admin-gyms/admin-gyms.module').then((m) => m.AdminGymsPageModule),
    canActivate: [AuthGuard, AdminGuard],
  },
  {
    path: 'admin/services',
    loadChildren: () => import('./pages/admin-services/admin-services.module').then((m) => m.AdminServicesPageModule),
    canActivate: [AuthGuard, AdminGuard],
  },
  {
    path: 'admin/appointments',
    loadChildren: () => import('./pages/admin-appointments/admin-appointments.module').then((m) => m.AdminAppointmentsPageModule),
    canActivate: [AuthGuard, AdminGuard],
  },
  {
    path: 'admin/settings',
    loadChildren: () => import('./pages/admin-settings/admin-settings.module').then((m) => m.AdminSettingsPageModule),
    canActivate: [AuthGuard, AdminGuard],
  },
  {
    path: '**',
    redirectTo: 'dashboard',
  },
];

@NgModule({
  imports: [
    RouterModule.forRoot(routes, {
      preloadingStrategy: PreloadAllModules,
    }),
  ],
  exports: [RouterModule],
})
export class AppRoutingModule {}
