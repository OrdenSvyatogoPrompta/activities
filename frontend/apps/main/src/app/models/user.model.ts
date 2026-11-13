export type Gender = 'male' | 'female';

export interface UserStats {
  /** Активные заявки (`pending|confirmed`). */
  enrollments: number;
  activities: number;
  children: number;
}

export interface User {
  id: string;
  name: string;
  email: string;
  roles: string[];
  stats: UserStats;
}

export interface Child {
  id: string;
  firstName: string;
  lastName: string | null;
  age: number;
  gender: Gender;
}

/** Ответ `POST /api/auth/login|register`. */
export interface AuthResponse {
  accessToken: string;
  user: User;
}

/** Ответ `POST /api/auth/refresh`. */
export interface RefreshResponse {
  accessToken: string;
}
