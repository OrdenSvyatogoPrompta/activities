import { Routes } from '@angular/router';

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
    loadComponent: () => import('./personal/personal-dashboard.component').then((m) => m.PersonalDashboardComponent),
  },
  {
    path: 'org',
    loadComponent: () => import('./org/org-dashboard.component').then((m) => m.OrgDashboardComponent),
  },
  {
    path: '**',
    redirectTo: '',
  },
];
