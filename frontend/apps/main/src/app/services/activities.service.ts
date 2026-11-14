import { Injectable, inject } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { ApiClient } from '../api/api-client';
import { Activity, CreateActivityPayload } from '../models/activity.model';
import { EnrollmentStatus } from '../models/enrollment.model';
import { AuthService } from './auth.service';
import { RemoteList } from './remote-list';

@Injectable({ providedIn: 'root' })
export class ActivitiesService {
  private readonly api = inject(ApiClient);

  readonly list = new RemoteList(
    () => this.api.get<Activity[]>('/me/activities'),
    'Не удалось загрузить активности',
    inject(AuthService).userId,
  );

  create(payload: CreateActivityPayload): Observable<Activity> {
    return this.api.post<Activity>('/me/activities', payload).pipe(
      tap(created => this.list.items.update(list => [created, ...list])),
    );
  }

  setIncomingStatus(enrollmentId: string, status: EnrollmentStatus): void {
    this.list.items.update(list => list.map(a =>
      a.enrollments.some(e => e.id === enrollmentId)
        ? { ...a, enrollments: a.enrollments.map(e => e.id === enrollmentId ? { ...e, status } : e) }
        : a,
    ));
  }
}
