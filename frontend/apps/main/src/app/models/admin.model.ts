import { Activity } from './activity.model';
import { Enrollment, EnrollmentStatus } from './enrollment.model';
import { User } from './user.model';

/** Роль администратора в `User.roles` (`GET /api/me`). */
export const ADMIN_ROLE = 'ROLE_ADMIN';

export function isAdmin(user: Pick<User, 'roles'> | null): boolean {
  return !!user?.roles?.includes(ADMIN_ROLE);
}

/** Краткие данные пользователя: автор активности, заявитель, владелец кружка. */
export interface UserRef {
  id: string;
  name: string;
  email: string;
}

/** Активность в очереди модерации (`GET /api/admin/activities`): формат `Activity` + автор. */
export interface AdminActivity extends Activity {
  owner: UserRef;
}

/** Кружок в заявке для администратора; `owner: null` — кружок без владельца. */
export interface AdminClubRef {
  /** slug. */
  id: string;
  name: string;
  isPublished: boolean;
  owner: UserRef | null;
}

/** Заявка для администратора (`GET /api/admin/enrollments`): `Enrollment` + кружок и заявитель. */
export interface AdminEnrollment extends Enrollment {
  club: AdminClubRef;
  user: UserRef;
}

/** Ответ `PATCH /api/admin/clubs/{slug}`. */
export interface ClubPublication {
  id: string;
  name: string;
  isPublished: boolean;
}

export interface AdminEnrollmentFilter {
  status: EnrollmentStatus;
  /** Только кружки без владельца — их заявки подтверждает администратор. */
  ownerless: boolean;
}
