import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from '../services/auth.service';

export const adminGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  if (!auth.user()) return router.createUrlTree(['/auth/login'], { queryParams: { returnUrl: state.url } });
  return auth.isAdmin() ? true : router.createUrlTree(['/personal']);
};
