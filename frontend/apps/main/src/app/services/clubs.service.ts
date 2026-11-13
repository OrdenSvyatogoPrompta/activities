import { Injectable, inject, signal } from '@angular/core';
import { Observable, of } from 'rxjs';

import { ApiClient, LoadStatus } from '../api/api-client';
import { toApiError } from '../api/api-error';
import { Club } from '../models/club.model';

/**
 * Каталог кружков. Весь опубликованный каталог приходит одним списком (`GET /api/clubs`)
 * и кешируется в signal — фильтрация идёт на клиенте.
 */
@Injectable({ providedIn: 'root' })
export class ClubsService {
  private readonly api = inject(ApiClient);

  readonly clubs  = signal<Club[]>([]);
  readonly status = signal<LoadStatus>('idle');
  readonly error  = signal('');

  /** Загружает каталог один раз; `force` — перезагрузить (например, после ошибки). */
  load(force = false): void {
    const status = this.status();
    if (status === 'loading' || (status === 'loaded' && !force)) return;
    this.status.set('loading');
    this.error.set('');
    this.api.get<Club[]>('/clubs').subscribe({
      next: clubs => {
        this.clubs.set(clubs);
        this.status.set('loaded');
      },
      error: (err: unknown) => {
        this.error.set(toApiError(err, 'Не удалось загрузить каталог').message);
        this.status.set('error');
      },
    });
  }

  /** Кружок по slug: из кеша каталога, если он уже загружен, иначе `GET /api/clubs/{slug}`. */
  getBySlug(slug: string): Observable<Club> {
    const cached = this.clubs().find(c => c.id === slug);
    if (cached) return of(cached);
    return this.api.get<Club>(`/clubs/${encodeURIComponent(slug)}`);
  }
}
