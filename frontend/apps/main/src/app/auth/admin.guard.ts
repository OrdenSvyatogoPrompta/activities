import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { isAdmin } from '../models/admin.model';
import { AuthService } from '../services/auth.service';

/** Только для администратора: гостя — на вход с `?returnUrl=`, остальных — в ЛК. */
export const adminGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const user = auth.user();
  if (!user) return router.createUrlTree(['/auth/login'], { queryParams: { returnUrl: state.url } });
  return isAdmin(user) ? true : router.createUrlTree(['/personal']);
};
