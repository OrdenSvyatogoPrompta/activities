import { Injectable, effect, inject, signal, untracked } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { ApiClient, LoadStatus } from '../api/api-client';
import { toApiError } from '../api/api-error';
import { Child, CreateChildPayload, UpdateChildPayload } from '../models/user.model';
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

  /** `POST /api/me/children`. */
  create(payload: CreateChildPayload): Observable<Child> {
    return this.api.post<Child>('/me/children', payload).pipe(
      tap(child => this.children.update(list => [...list, child])),
    );
  }

  /** `PATCH /api/me/children/{id}` — частичное обновление. */
  update(id: string, payload: UpdateChildPayload): Observable<Child> {
    return this.api.patch<Child>(`/me/children/${encodeURIComponent(id)}`, payload).pipe(
      tap(child => this.children.update(list => list.map(c => (c.id === child.id ? child : c)))),
    );
  }

  /** `DELETE /api/me/children/{id}`; `409`, если у ребёнка есть активные заявки. */
  remove(id: string): Observable<void> {
    return this.api.delete<void>(`/me/children/${encodeURIComponent(id)}`).pipe(
      tap(() => this.children.update(list => list.filter(c => c.id !== id))),
    );
  }

  private reset(): void {
    this.children.set([]);
    this.status.set('idle');
    this.error.set('');
  }
}
