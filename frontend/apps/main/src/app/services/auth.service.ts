import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, finalize, firstValueFrom, map, shareReplay, tap } from 'rxjs';

import { ApiClient } from '../api/api-client';
import { AuthResponse, Child, RefreshResponse, User } from '../models/user.model';

export type { Child } from '../models/user.model';

// ─── Временные типы ЛК (до перевода ЛК на API) ───────────────────────────────

export interface Enrollment {
  id: string;
  clubId: string;
  clubName: string;
  branchId: string | null;
  branchName: string;
  schedule: string;
  days: string[];  // ['Пн','Ср','Пт'] для календаря
  color: string;   // цвет кружка для точек в календаре
  forChild: Child | null;
  enrolledAt: string;
}

export interface ActivityBranch {
  id: string;
  name: string;
  address: string;
  days: string[];
  timeFrom: string;
  timeTo: string;
}

export interface IncomingEnrollment {
  id: string;
  personName: string;
  childName: string | null;
  branchName: string;
  enrolledAt: string;
}

export interface ManagedActivity {
  id: string;
  name: string;
  category: string;
  emoji: string;
  description: string;
  branches: ActivityBranch[];
  enrollments: IncomingEnrollment[];
}

export interface AuthUser extends User {
  children: Child[];
  enrollments: Enrollment[];
  activities: ManagedActivity[];
}

function withLocalData(user: User): AuthUser {
  return { ...user, children: [], enrollments: [], activities: [] };
}

/**
 * Сессия пользователя. Access-токен хранится только в памяти; refresh-токен — в HttpOnly cookie,
 * поэтому после перезагрузки страницы сессия восстанавливается через `restoreSession()`.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly api = inject(ApiClient);

  private readonly token = signal<string | null>(null);
  private refreshInFlight: Observable<string> | null = null;

  readonly user = signal<AuthUser | null>(null);
  readonly accessToken = this.token.asReadonly();
  readonly isAuthenticated = computed(() => this.user() !== null);

  /** Тихое восстановление сессии при старте приложения. Ошибки не пробрасывает. */
  async restoreSession(): Promise<void> {
    try {
      await firstValueFrom(this.refresh());
      await this.reloadUser();
    } catch {
      this.clearSession();
    }
  }

  async login(email: string, password: string): Promise<void> {
    const res = await firstValueFrom(this.api.post<AuthResponse>('/auth/login', { email, password }));
    this.startSession(res);
  }

  async register(name: string, email: string, password: string): Promise<void> {
    const res = await firstValueFrom(this.api.post<AuthResponse>('/auth/register', { name, email, password }));
    this.startSession(res);
  }

  /** Выход: инвалидирует refresh на сервере; локальная сессия очищается в любом случае. */
  async logout(): Promise<void> {
    try {
      await firstValueFrom(this.api.post<void>('/auth/logout'));
    } catch {
      // сессия всё равно завершается локально
    } finally {
      this.clearSession();
    }
  }

  /** `GET /api/me` — обновляет профиль и счётчики. */
  async reloadUser(): Promise<void> {
    const user = await firstValueFrom(this.api.get<User>('/me'));
    const current = this.user();
    this.user.set(current ? { ...current, ...user } : withLocalData(user));
  }

  /**
   * Обновление access-токена по cookie. Параллельные вызовы (несколько запросов получили 401)
   * разделяют один запрос к серверу.
   */
  refresh(): Observable<string> {
    if (!this.refreshInFlight) {
      this.refreshInFlight = this.api.post<RefreshResponse>('/auth/refresh').pipe(
        map(res => res.accessToken),
        tap(token => this.token.set(token)),
        finalize(() => { this.refreshInFlight = null; }),
        shareReplay({ bufferSize: 1, refCount: false }),
      );
    }
    return this.refreshInFlight;
  }

  clearSession(): void {
    this.token.set(null);
    this.user.set(null);
  }

  private startSession(res: AuthResponse): void {
    this.token.set(res.accessToken);
    this.user.set(withLocalData(res.user));
  }

  // ─── Временные локальные операции ЛК (до перевода ЛК на API) ──────────────

  addChild(firstName: string, lastName: string, age: number, gender: Child['gender']): void {
    const u = this.user();
    if (!u) return;
    const child: Child = { id: `c${Date.now()}`, firstName, lastName, age, gender };
    this.user.set({ ...u, children: [...u.children, child] });
  }

  removeChild(id: string): void {
    const u = this.user();
    if (!u) return;
    this.user.set({ ...u, children: u.children.filter(c => c.id !== id) });
  }

  enroll(enrollment: Omit<Enrollment, 'id' | 'enrolledAt' | 'days' | 'color'> & { days?: string[]; color?: string }): void {
    const u = this.user();
    if (!u) return;
    const e: Enrollment = { days: [], color: '#6880f8', ...enrollment, id: `e${Date.now()}`, enrolledAt: new Date().toISOString().slice(0, 10) };
    this.user.set({ ...u, enrollments: [...u.enrollments, e] });
  }

  cancelEnrollment(id: string): void {
    const u = this.user();
    if (!u) return;
    this.user.set({ ...u, enrollments: u.enrollments.filter(e => e.id !== id) });
  }

  addActivity(activity: Omit<ManagedActivity, 'id' | 'enrollments'>): void {
    const u = this.user();
    if (!u) return;
    const a: ManagedActivity = { ...activity, id: `a${Date.now()}`, enrollments: [] };
    this.user.set({ ...u, activities: [...u.activities, a] });
  }
}
