import { DayOfWeek } from './club.model';
import { Child } from './user.model';

export type EnrollmentStatus = 'pending' | 'confirmed' | 'rejected' | 'cancelled';

export const ENROLLMENT_STATUS_LABEL: Record<EnrollmentStatus, string> = {
  pending: 'На рассмотрении',
  confirmed: 'Подтверждена',
  rejected: 'Отклонена',
  cancelled: 'Отменена',
};

/** Активная заявка блокирует повторную запись того же участника. */
export function isActiveEnrollment(e: { status: EnrollmentStatus }): boolean {
  return e.status === 'pending' || e.status === 'confirmed';
}

export interface Enrollment {
  id: string;
  clubId: string;
  clubName: string;
  branchId: string | null;
  branchName: string | null;
  schedule: string;
  days: DayOfWeek[];
  color: string;
  /** `null` — запись «для себя». */
  forChild: Child | null;
  enrolledAt: string;
  status: EnrollmentStatus;
  rejectReason: string | null;
}

export interface CreateEnrollmentPayload {
  clubId: string;
  branchId?: string;
  childId?: string;
}
