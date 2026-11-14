import { HttpErrorResponse, HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, of, switchMap, throwError } from 'rxjs';

import { environment } from '../../environments/environment';
import { AuthService } from '../services/auth.service';

/** На этих путях 401 не лечится refresh-ем. */
// Выходу токен не нужен: бэкенд завершает сессию по refresh-cookie.
const PUBLIC_AUTH_PATHS = ['/auth/login', '/auth/register', '/auth/refresh', '/auth/logout'];

/** Доступны и без входа: с протухшей сессией их повторяем анонимно, а не ведём на логин. */
const PUBLIC_GET_PATHS = ['/clubs', '/dictionaries'];

function isPublicRead(req: HttpRequest<unknown>, apiUrl: string): boolean {
  return req.method === 'GET' && PUBLIC_GET_PATHS.some(p => {
    const url = `${apiUrl}${p}`;
    return req.url === url || req.url.startsWith(`${url}/`);
  });
}

function withToken<T>(req: HttpRequest<T>, token: string | null): HttpRequest<T> {
  return token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;
}

/** На 401 один раз обновляет токен и повторяет запрос, иначе — выход на страницу входа. */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const apiUrl = environment.apiUrl;
  if (!req.url.startsWith(`${apiUrl}/`)) return next(req);
  if (PUBLIC_AUTH_PATHS.some(p => req.url.startsWith(`${apiUrl}${p}`))) return next(req);

  const auth = inject(AuthService);
  const router = inject(Router);

  return next(withToken(req, auth.accessToken())).pipe(
    catchError((err: unknown) => {
      if (!(err instanceof HttpErrorResponse) || err.status !== 401) return throwError(() => err);
      return auth.refresh().pipe(
        catchError(() => {
          auth.clearSession();
          if (isPublicRead(req, apiUrl)) return of(null);
          router.navigate(['/auth/login'], { queryParams: { returnUrl: router.url } });
          return throwError(() => err);
        }),
        switchMap(token => next(withToken(req, token))),
      );
    }),
  );
};
