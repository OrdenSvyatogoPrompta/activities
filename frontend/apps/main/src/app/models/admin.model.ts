import { Activity } from './activity.model';
import { Enrollment, EnrollmentStatus } from './enrollment.model';

export interface UserRef {
  id: string;
  name: string;
  email: string;
}

export interface AdminActivity extends Activity {
  owner: UserRef;
  /** `null`, пока активность не одобрена. */
  clubIsPublished: boolean | null;
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
