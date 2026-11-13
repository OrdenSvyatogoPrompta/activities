import { ClubCategory, PriceType, ScheduleSlot, VisitType } from './club.model';
import { EnrollmentStatus } from './enrollment.model';

export type ActivityStatus = 'pending' | 'approved' | 'rejected';

export const ACTIVITY_STATUS_LABEL: Record<ActivityStatus, string> = {
  pending: 'На модерации',
  approved: 'Опубликована',
  rejected: 'Отклонена',
};

export interface ActivityBranch {
  id: string;
  name: string;
  address: string;
  phone: string | null;
  /** Группы слотов через `\n`. */
  schedule: string;
  slots: ScheduleSlot[];
}

export interface IncomingEnrollment {
  id: string;
  personName: string;
  childName: string | null;
  /** `null` — филиал кружка не сопоставлен с филиалом активности. */
  activityBranchId: string | null;
  branchName: string | null;
  enrolledAt: string;
  status: EnrollmentStatus;
}

export interface Activity {
  id: string;
  name: string;
  category: ClubCategory;
  emoji: string;
  description: string;
  status: ActivityStatus;
  moderationComment: string | null;
  /** slug кружка; `null`, пока активность не одобрена. */
  clubId: string | null;
  ageMin: number;
  ageMax: number;
  age: string;
  visitType: VisitType;
  priceType: PriceType;
  priceAmount: number;
  price: string;
  tags: string[];
  branches: ActivityBranch[];
  enrollments: IncomingEnrollment[];
}

export interface CreateActivityBranchPayload {
  name: string;
  address: string;
  phone: string;
  slots: ScheduleSlot[];
}

export interface CreateActivityPayload {
  name: string;
  category: ClubCategory;
  emoji: string;
  description: string;
  ageMin: number;
  ageMax: number;
  visitType: VisitType;
  priceType: PriceType;
  priceAmount: number;
  tags: string[];
  branches: CreateActivityBranchPayload[];
}
