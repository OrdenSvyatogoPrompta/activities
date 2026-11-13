import { Injectable, effect, inject, signal, untracked } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { ApiClient, LoadStatus } from '../api/api-client';
import { toApiError } from '../api/api-error';
import { Activity, CreateActivityPayload } from '../models/activity.model';
import { EnrollmentStatus } from '../models/enrollment.model';
import { AuthService } from './auth.service';

/** Активности организатора и входящие заявки в их кружки. */
@Injectable({ providedIn: 'root' })
export class ActivitiesService {
  private readonly api  = inject(ApiClient);
  private readonly auth = inject(AuthService);

  readonly mine   = signal<Activity[]>([]);
  readonly status = signal<LoadStatus>('idle');
  readonly error  = signal('');

  constructor() {
    effect(() => {
      if (!this.auth.user()) untracked(() => this.reset());
    });
  }

  loadMine(force = false): void {
    const status = this.status();
    if (status === 'loading' || (status === 'loaded' && !force)) return;
    this.status.set('loading');
    this.error.set('');
    this.api.get<Activity[]>('/me/activities').subscribe({
      next: list => {
        this.mine.set(list);
        this.status.set('loaded');
      },
      error: (err: unknown) => {
        this.error.set(toApiError(err, 'Не удалось загрузить активности').message);
        this.status.set('error');
      },
    });
  }

  /** `POST /api/me/activities` → активность в статусе `pending` (на модерации). */
  create(payload: CreateActivityPayload): Observable<Activity> {
    return this.api.post<Activity>('/me/activities', payload).pipe(
      tap(created => this.mine.update(list => [created, ...list])),
    );
  }

  /** Локально обновляет статус входящей заявки после confirm/reject. */
  setIncomingStatus(enrollmentId: string, status: EnrollmentStatus): void {
    this.mine.update(list => list.map(a =>
      a.enrollments.some(e => e.id === enrollmentId)
        ? { ...a, enrollments: a.enrollments.map(e => e.id === enrollmentId ? { ...e, status } : e) }
        : a,
    ));
  }

  private reset(): void {
    this.mine.set([]);
    this.status.set('idle');
    this.error.set('');
  }
}
