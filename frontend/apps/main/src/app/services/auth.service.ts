import { Injectable, signal } from '@angular/core';

export type UserRole = 'user' | 'org';

export interface Child {
  id: string;
  name: string;
  age: number;
}

export interface AuthUser {
  id: string;
  role: UserRole;
  name: string;
  email: string;
  children: Child[]; // пусто по умолчанию, добавляются в профиле
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  readonly user = signal<AuthUser | null>(null);

  login(email: string, _password: string, role: UserRole): void {
    this.user.set({ id: 'mock-1', role, name: email.split('@')[0], email, children: [] });
  }

  logout(): void {
    this.user.set(null);
  }

  redirectAfterLogin(role: UserRole): void {
    window.location.href = role === 'org' ? '/org' : '/personal';
  }
}
