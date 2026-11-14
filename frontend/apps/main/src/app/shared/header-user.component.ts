import { ChangeDetectionStrategy, Component, inject } from '@angular/core';

import { AuthService } from '../services/auth.service';

/** Текущий пользователь в шапке ЛК и админки. */
@Component({
  selector: 'act-header-user',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { '[attr.title]': 'user()?.email' },
  styleUrl: './header-user.component.scss',
  template: `
    @if (user(); as user) {
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"
        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <circle cx="12" cy="10" r="3"/>
        <path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"/>
      </svg>
      <span>{{ user.name || user.email }}</span>
    }
  `,
})
export class HeaderUserComponent {
  readonly user = inject(AuthService).user;
}
