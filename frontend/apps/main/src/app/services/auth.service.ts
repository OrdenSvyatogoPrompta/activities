import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, finalize, firstValueFrom, map, shareReplay, tap } from 'rxjs';

import { ApiClient } from '../api/api-client';
import { AuthResponse, RefreshResponse, User } from '../models/user.model';

/**
 * Сессия пользователя. Access-токен хранится только в памяти; refresh-токен — в HttpOnly cookie,
 * поэтому после перезагрузки страницы сессия восстанавливается через `restoreSession()`.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly api = inject(ApiClient);

  private readonly token = signal<string | null>(null);
  private refreshInFlight: Observable<string> | null = null;

  readonly user = signal<User | null>(null);
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
    this.user.set(await firstValueFrom(this.api.get<User>('/me')));
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
    this.user.set(res.user);
  }
}
