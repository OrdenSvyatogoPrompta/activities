import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

/** Пути без `/api`: `api.get('/clubs')`. `withCredentials` — ради refresh-cookie. */
@Injectable({ providedIn: 'root' })
export class ApiClient {
  private readonly http = inject(HttpClient);

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

  private url(path: string): string {
    return `${environment.apiUrl}${path}`;
  }
}
