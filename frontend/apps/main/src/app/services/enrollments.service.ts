import { Injectable, effect, inject, signal, untracked } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { ApiClient, LoadStatus } from '../api/api-client';
import { toApiError } from '../api/api-error';
import { CreateEnrollmentPayload, Enrollment } from '../models/enrollment.model';
import { AuthService } from './auth.service';

/** Заявки текущего пользователя на запись в кружки. */
@Injectable({ providedIn: 'root' })
export class EnrollmentsService {
  private readonly api  = inject(ApiClient);
  private readonly auth = inject(AuthService);

  /** `GET /api/me/enrollments` — все статусы, кроме `cancelled`. */
  readonly mine   = signal<Enrollment[]>([]);
  readonly status = signal<LoadStatus>('idle');
  readonly error  = signal('');

  constructor() {
    // при выходе из аккаунта сбрасываем данные прошлой сессии
    effect(() => {
      if (!this.auth.user()) untracked(() => this.reset());
    });
  }

  loadMine(force = false): void {
    const status = this.status();
    if (status === 'loading' || (status === 'loaded' && !force)) return;
    this.status.set('loading');
    this.error.set('');
    this.api.get<Enrollment[]>('/me/enrollments').subscribe({
      next: list => {
        this.mine.set(list);
        this.status.set('loaded');
      },
      error: (err: unknown) => {
        this.error.set(toApiError(err, 'Не удалось загрузить записи').message);
        this.status.set('error');
      },
    });
  }

  /** `POST /api/enrollments` → заявка в статусе `pending`. */
  create(payload: CreateEnrollmentPayload): Observable<Enrollment> {
    return this.api.post<Enrollment>('/enrollments', payload).pipe(
      tap(created => this.mine.update(list => [created, ...list.filter(e => e.id !== created.id)])),
    );
  }

  private reset(): void {
    this.mine.set([]);
    this.status.set('idle');
    this.error.set('');
  }
}
