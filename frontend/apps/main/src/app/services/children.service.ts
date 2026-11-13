import { Injectable, effect, inject, signal, untracked } from '@angular/core';

import { ApiClient, LoadStatus } from '../api/api-client';
import { toApiError } from '../api/api-error';
import { Child } from '../models/user.model';
import { AuthService } from './auth.service';

/** Дети текущего пользователя (`/api/me/children`). */
@Injectable({ providedIn: 'root' })
export class ChildrenService {
  private readonly api  = inject(ApiClient);
  private readonly auth = inject(AuthService);

  readonly children = signal<Child[]>([]);
  readonly status   = signal<LoadStatus>('idle');
  readonly error    = signal('');

  constructor() {
    effect(() => {
      if (!this.auth.user()) untracked(() => this.reset());
    });
  }

  load(force = false): void {
    const status = this.status();
    if (status === 'loading' || (status === 'loaded' && !force)) return;
    this.status.set('loading');
    this.error.set('');
    this.api.get<Child[]>('/me/children').subscribe({
      next: list => {
        this.children.set(list);
        this.status.set('loaded');
      },
      error: (err: unknown) => {
        this.error.set(toApiError(err, 'Не удалось загрузить список детей').message);
        this.status.set('error');
      },
    });
  }

  private reset(): void {
    this.children.set([]);
    this.status.set('idle');
    this.error.set('');
  }
}
