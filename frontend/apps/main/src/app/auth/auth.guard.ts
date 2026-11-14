import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from '../services/auth.service';

export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  if (auth.user()) return true;
  return inject(Router).createUrlTree(['/auth/login'], { queryParams: { returnUrl: state.url } });
};
