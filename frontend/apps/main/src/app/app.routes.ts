import { Routes } from '@angular/router';

import { adminGuard } from './auth/admin.guard';
import { authGuard } from './auth/auth.guard';

export const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('./home/home.component').then((m) => m.HomeComponent),
  },
  {
    path: 'club/:id',
    loadComponent: () => import('./club/club.component').then((m) => m.ClubComponent),
  },
  {
    path: 'auth/login',
    loadComponent: () => import('./auth/login.component').then((m) => m.LoginComponent),
  },
  {
    path: 'auth/register',
    loadComponent: () => import('./auth/register.component').then((m) => m.RegisterComponent),
  },
  {
    path: 'personal',
    canActivate: [authGuard],
    loadComponent: () => import('./personal/personal-dashboard.component').then((m) => m.PersonalDashboardComponent),
  },
  {
    path: 'admin',
    canActivate: [adminGuard],
    loadComponent: () => import('./admin/admin-dashboard.component').then((m) => m.AdminDashboardComponent),
  },
  // /org редиректит в /personal — один аккаунт для всех
  { path: 'org', redirectTo: 'personal' },
  { path: '**', redirectTo: '' },
];
