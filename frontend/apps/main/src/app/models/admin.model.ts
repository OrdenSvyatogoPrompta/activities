import { Activity } from './activity.model';

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

export interface ClubPublication {
  id: string;
  name: string;
  isPublished: boolean;
}
