import { ActivatedRoute } from '@angular/router';

/** Куда вернуть пользователя после входа: `?returnUrl=` (только внутренний путь) или ЛК. */
export function getReturnUrl(route: ActivatedRoute): string {
  const url = route.snapshot.queryParamMap.get('returnUrl');
  return url && url.startsWith('/') && !url.startsWith('//') && !url.startsWith('/auth/') ? url : '/personal';
}
