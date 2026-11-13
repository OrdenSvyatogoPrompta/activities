import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiClient } from '../api/api-client';
import { ActivityStatus } from '../models/activity.model';
import {
  AdminActivity, AdminEnrollment, AdminEnrollmentFilter, ClubPublication,
} from '../models/admin.model';

/**
 * Модерация (`/api/admin/*`, только `ROLE_ADMIN`). Состояние списков держит страница админки:
 * данные зависят от фильтров и после действий модератора перезапрашиваются.
 * Подтверждение/отклонение заявок — общие эндпоинты `EnrollmentsService.confirm|reject`.
 */
@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly api = inject(ApiClient);

  /** `GET /api/admin/activities?status=` — старые первыми. */
  activities(status: ActivityStatus): Observable<AdminActivity[]> {
    return this.api.get<AdminActivity[]>('/admin/activities', { status });
  }

  /** `POST /api/admin/activities/{id}/approve` — публикует кружок, в ответе заполнен `clubId`. */
  approveActivity(id: string): Observable<AdminActivity> {
    return this.api.post<AdminActivity>(`/admin/activities/${encodeURIComponent(id)}/approve`);
  }

  /** `POST /api/admin/activities/{id}/reject` — комментарий обязателен, его увидит автор. */
  rejectActivity(id: string, comment: string): Observable<AdminActivity> {
    return this.api.post<AdminActivity>(`/admin/activities/${encodeURIComponent(id)}/reject`, { comment });
  }

  /** `GET /api/admin/enrollments?status=&ownerless=`. */
  enrollments(filter: AdminEnrollmentFilter): Observable<AdminEnrollment[]> {
    const params: Record<string, string> = { status: filter.status };
    if (filter.ownerless) params['ownerless'] = 'true';
    return this.api.get<AdminEnrollment[]>('/admin/enrollments', params);
  }

  /** `PATCH /api/admin/clubs/{slug}` — снять кружок с публикации или вернуть в каталог. */
  setClubPublished(slug: string, isPublished: boolean): Observable<ClubPublication> {
    return this.api.patch<ClubPublication>(`/admin/clubs/${encodeURIComponent(slug)}`, { isPublished });
  }
}
