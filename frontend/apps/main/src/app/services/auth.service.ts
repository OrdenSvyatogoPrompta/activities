import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, finalize, firstValueFrom, map, shareReplay, tap } from 'rxjs';

import { ApiClient } from '../api/api-client';
import { AuthResponse, User } from '../models/user.model';

/** Access-токен живёт только в памяти, refresh — в HttpOnly cookie. */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly api = inject(ApiClient);

  private readonly token = signal<string | null>(null);
  private refreshInFlight: Observable<string> | null = null;

  readonly user = signal<User | null>(null);
  readonly accessToken = this.token.asReadonly();
  readonly isAdmin = computed(() => !!this.user()?.roles.includes('ROLE_ADMIN'));

  async restoreSession(): Promise<void> {
    try {
      await firstValueFrom(this.refresh());
      await this.reloadUser();
    } catch {
      this.clearSession();
    }
  }

  async login(email: string, password: string): Promise<void> {
    this.startSession(await firstValueFrom(this.api.post<AuthResponse>('/auth/login', { email, password })));
  }

  async register(name: string, email: string, password: string): Promise<void> {
    this.startSession(await firstValueFrom(this.api.post<AuthResponse>('/auth/register', { name, email, password })));
  }

  async logout(): Promise<void> {
    try {
      await firstValueFrom(this.api.post<void>('/auth/logout'));
    } catch {
      // локальная сессия завершается в любом случае
    } finally {
      this.clearSession();
    }
  }

  async reloadUser(): Promise<void> {
    this.user.set(await firstValueFrom(this.api.get<User>('/me')));
  }

  async updateName(name: string): Promise<void> {
    this.user.set(await firstValueFrom(this.api.patch<User>('/me', { name })));
  }

  /** Параллельные 401 разделяют один запрос обновления. */
  refresh(): Observable<string> {
    if (!this.refreshInFlight) {
      this.refreshInFlight = this.api.post<Pick<AuthResponse, 'accessToken'>>('/auth/refresh').pipe(
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
    this.user.set(res.user);
  }
}
