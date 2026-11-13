import { ClubCategory, DayOfWeek, PriceType, ScheduleSlot, VisitType } from './club.model';
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
  /** Человекочитаемое расписание (формирует сервер, группы через `\n`). */
  schedule: string;
  /** Совместимость: дни всех слотов и время первого слота. */
  days: DayOfWeek[];
  timeFrom: string | null;
  timeTo: string | null;
  slots: ScheduleSlot[];
}

/** Входящая заявка в кружок, созданный из активности. */
export interface IncomingEnrollment {
  id: string;
  personName: string;
  childName: string | null;
  /** id филиала **кружка** (не активности). */
  branchId: string | null;
  branchName: string | null;
  /** id филиала активности, из которого создан филиал кружка; `null` — сопоставить не удалось. */
  activityBranchId: string | null;
  enrolledAt: string;
  status: EnrollmentStatus;
}

/** Активность организатора (`GET /api/me/activities`). */
export interface Activity {
  id: string;
  name: string;
  category: ClubCategory;
  emoji: string;
  description: string;
  status: ActivityStatus;
  moderationComment: string | null;
  /** slug опубликованного кружка; `null`, пока активность не одобрена. */
  clubId: string | null;
  ageMin: number;
  ageMax: number;
  /** Подпись возраста от сервера: `5–14 лет`. */
  age: string;
  visitType: VisitType;
  priceType: PriceType;
  priceAmount: number;
  /** Подпись цены от сервера: `Бесплатно` / `2 500 ₽ / мес`. */
  price: string;
  tags: string[];
  /** ISO 8601. */
  createdAt: string;
  branches: ActivityBranch[];
  /** Заявки в связанный кружок, кроме `cancelled`. */
  enrollments: IncomingEnrollment[];
}

export interface CreateActivityBranchPayload {
  name: string;
  address: string;
  phone: string;
  slots: ScheduleSlot[];
}

/** Тело `POST /api/me/activities`. */
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
