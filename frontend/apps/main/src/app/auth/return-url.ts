import { ActivatedRoute } from '@angular/router';

/** Только внутренний путь — защита от open redirect. */
export function getReturnUrl(route: ActivatedRoute): string {
  const url = route.snapshot.queryParamMap.get('returnUrl');
  return url && url.startsWith('/') && !url.startsWith('//') && !url.startsWith('/auth/') ? url : '/personal';
}
