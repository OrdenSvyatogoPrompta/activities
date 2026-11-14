import { effect, signal, untracked } from '@angular/core';
import { Observable, Subscription } from 'rxjs';

import { toApiError } from '../api/api-error';

export type LoadStatus = 'idle' | 'loading' | 'loaded' | 'error';

export class RemoteList<T> {
  readonly items = signal<T[]>([]);
  readonly status = signal<LoadStatus>('idle');
  readonly error = signal('');

  private request?: Subscription;
  private loadedFor: unknown = null;

  /** `owner` — чьи это данные (id пользователя): при его смене список сбрасывается. */
  constructor(
    private readonly fetch: () => Observable<T[]>,
    private readonly errorMessage: string,
    private readonly owner?: () => unknown,
  ) {
    if (owner) {
      effect(() => {
        owner();
        untracked(() => { if (this.isStale()) this.reset(); });
      });
    }
  }

  /** Без `force` не перезапрашивает уже загруженный список; идущий запрос не дублирует. */
  load(force = false): void {
    const status = this.status();
    if (!this.isStale() && (status === 'loading' || (status === 'loaded' && !force))) return;
    this.reload();
  }

  /** Отменяет текущий запрос, чтобы ответ по старому фильтру не перезаписал новый. */
  reload(): void {
    this.request?.unsubscribe();
    this.loadedFor = this.owner?.() ?? null;
    this.status.set('loading');
    this.error.set('');
    this.request = this.fetch().subscribe({
      next: items => {
        this.items.set(items);
        this.status.set('loaded');
      },
      error: (err: unknown) => {
        this.error.set(toApiError(err, this.errorMessage).message);
        this.status.set('error');
      },
    });
  }

  /** Эффект срабатывает асинхронно, поэтому `load` тоже сверяет владельца сам. */
  private isStale(): boolean {
    return (this.owner?.() ?? null) !== this.loadedFor;
  }

  private reset(): void {
    this.request?.unsubscribe();
    this.loadedFor = this.owner?.() ?? null;
    this.items.set([]);
    this.status.set('idle');
    this.error.set('');
  }
}
