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
    path: '**',
    redirectTo: '',
  },
];
