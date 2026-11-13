import { DayOfWeek } from './club.model';
import { Child } from './user.model';

export type EnrollmentStatus = 'pending' | 'confirmed' | 'rejected' | 'cancelled';

/** Активные статусы: такая заявка блокирует повторную запись того же участника. */
export const ACTIVE_ENROLLMENT_STATUSES: readonly EnrollmentStatus[] = ['pending', 'confirmed'];

export const ENROLLMENT_STATUS_LABEL: Record<EnrollmentStatus, string> = {
  pending: 'На рассмотрении',
  confirmed: 'Подтверждена',
  rejected: 'Отклонена',
  cancelled: 'Отменена',
};

/** Заявка пользователя на запись (`GET /api/me/enrollments`). */
export interface Enrollment {
  id: string;
  clubId: string;
  clubName: string;
  branchId: string | null;
  branchName: string;
  schedule: string;
  days: DayOfWeek[];
  color: string;
  /** `null` — запись «для себя». */
  forChild: Child | null;
  /** `YYYY-MM-DD`. */
  enrolledAt: string;
  status: EnrollmentStatus;
  rejectReason: string | null;
}

/** Тело `POST /api/enrollments`. */
export interface CreateEnrollmentPayload {
  /** slug кружка. */
  clubId: string;
  branchId?: string;
  childId?: string;
}

export function isActiveEnrollment(e: { status: EnrollmentStatus }): boolean {
  return ACTIVE_ENROLLMENT_STATUSES.includes(e.status);
}
