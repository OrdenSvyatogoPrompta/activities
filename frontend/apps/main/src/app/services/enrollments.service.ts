import { Injectable, inject } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { ApiClient } from '../api/api-client';
import { CreateEnrollmentPayload, Enrollment } from '../models/enrollment.model';
import { Child } from '../models/user.model';
import { AuthService } from './auth.service';
import { RemoteList } from './remote-list';

@Injectable({ providedIn: 'root' })
export class EnrollmentsService {
  private readonly api = inject(ApiClient);

  /** Сервер не возвращает отменённые заявки. */
  readonly list = new RemoteList(() => this.api.get<Enrollment[]>('/me/enrollments'), 'Не удалось загрузить записи');

  constructor() {
    const auth = inject(AuthService);
    this.list.resetUnless(auth.user);
  }

  create(payload: CreateEnrollmentPayload): Observable<Enrollment> {
    return this.api.post<Enrollment>('/enrollments', payload).pipe(
      tap(created => this.list.items.update(list => [created, ...list.filter(e => e.id !== created.id)])),
    );
  }

  cancel(id: string): Observable<Enrollment> {
    return this.api.post<Enrollment>(`/enrollments/${encodeURIComponent(id)}/cancel`).pipe(
      tap(() => this.list.items.update(list => list.filter(e => e.id !== id))),
    );
  }

  /** Подтверждение и отклонение — для организатора кружка и администратора. */
  confirm(id: string): Observable<Enrollment> {
    return this.api.post<Enrollment>(`/enrollments/${encodeURIComponent(id)}/confirm`);
  }

  reject(id: string, reason?: string): Observable<Enrollment> {
    return this.api.post<Enrollment>(`/enrollments/${encodeURIComponent(id)}/reject`, reason ? { reason } : {});
  }

  /** У заявок своя копия ребёнка — обновляем её после редактирования. */
  replaceChild(child: Child): void {
    this.list.items.update(list => list.map(e => (e.forChild?.id === child.id ? { ...e, forChild: child } : e)));
  }
}
