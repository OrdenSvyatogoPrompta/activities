import { Activity, ActivityBranch } from './activity.model';
import { Enrollment, EnrollmentStatus } from './enrollment.model';

export interface UserRef {
  id: string;
  name: string;
  email: string;
}

/** Админский API не отдаёт готовые подписи `age`, `price` и `schedule`. */
export interface AdminActivity extends Omit<Activity, 'age' | 'price' | 'branches'> {
  branches: Omit<ActivityBranch, 'schedule'>[];
  owner: UserRef;
}

export interface AdminClubRef {
  id: string;
  name: string;
  isPublished: boolean;
  owner: UserRef | null;
}

export interface AdminEnrollment extends Enrollment {
  club: AdminClubRef;
  user: UserRef;
}

export interface ClubPublication {
  id: string;
  name: string;
  isPublished: boolean;
}

export interface AdminEnrollmentFilter {
  status: EnrollmentStatus;
  ownerless: boolean;
}
