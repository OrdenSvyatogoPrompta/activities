import { HttpErrorResponse, HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, switchMap, throwError } from 'rxjs';

import { environment } from '../../environments/environment';
import { AuthService } from '../services/auth.service';

/** На этих путях 401 не лечится refresh-ем. */
const PUBLIC_AUTH_PATHS = ['/auth/login', '/auth/register', '/auth/refresh'];

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
          router.navigate(['/auth/login'], { queryParams: { returnUrl: router.url } });
          return throwError(() => err);
        }),
        switchMap(token => next(withToken(req, token))),
      );
    }),
  );
};
