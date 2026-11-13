import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

/** Состояние загрузки данных с сервера. */
export type LoadStatus = 'idle' | 'loading' | 'loaded' | 'error';

/**
 * Тонкая обёртка над `HttpClient`: префикс `environment.apiUrl` и `withCredentials`
 * (refresh-токен живёт в HttpOnly cookie). Пути передаются без `/api`: `api.get('/clubs')`.
 */
@Injectable({ providedIn: 'root' })
export class ApiClient {
  private readonly http = inject(HttpClient);

  url(path: string): string {
    return `${environment.apiUrl}${path}`;
  }

  get<T>(path: string, params?: Record<string, string>): Observable<T> {
    return this.http.get<T>(this.url(path), { params, withCredentials: true });
  }

  post<T>(path: string, body: unknown = {}): Observable<T> {
    return this.http.post<T>(this.url(path), body, { withCredentials: true });
  }

  patch<T>(path: string, body: unknown): Observable<T> {
    return this.http.patch<T>(this.url(path), body, { withCredentials: true });
  }

  delete<T = void>(path: string): Observable<T> {
    return this.http.delete<T>(this.url(path), { withCredentials: true });
  }
}
