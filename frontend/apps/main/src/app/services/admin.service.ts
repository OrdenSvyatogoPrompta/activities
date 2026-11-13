import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { ApiClient } from '../api/api-client';
import { ActivityStatus } from '../models/activity.model';
import { AdminActivity, AdminEnrollment, AdminEnrollmentFilter, ClubPublication } from '../models/admin.model';

@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly api = inject(ApiClient);

  activities(status: ActivityStatus): Observable<AdminActivity[]> {
    return this.api.get<AdminActivity[]>('/admin/activities', { status });
  }

  approveActivity(id: string): Observable<AdminActivity> {
    return this.api.post<AdminActivity>(`/admin/activities/${encodeURIComponent(id)}/approve`);
  }

  rejectActivity(id: string, comment: string): Observable<AdminActivity> {
    return this.api.post<AdminActivity>(`/admin/activities/${encodeURIComponent(id)}/reject`, { comment });
  }

  enrollments(filter: AdminEnrollmentFilter): Observable<AdminEnrollment[]> {
    const params: Record<string, string> = { status: filter.status };
    if (filter.ownerless) params['ownerless'] = 'true';
    return this.api.get<AdminEnrollment[]>('/admin/enrollments', params);
  }

  setClubPublished(slug: string, isPublished: boolean): Observable<ClubPublication> {
    return this.api.patch<ClubPublication>(`/admin/clubs/${encodeURIComponent(slug)}`, { isPublished });
  }
}
