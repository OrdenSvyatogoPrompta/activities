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

/** Тело `POST /api/me/children`. */
export interface CreateChildPayload {
  firstName: string;
  lastName?: string;
  age: number;
  gender: Gender;
}

/**
 * Тело `PATCH /api/me/children/{id}` — частичное обновление.
 * `lastName: null` (или пустая строка) стирает фамилию.
 */
export interface UpdateChildPayload {
  firstName?: string;
  lastName?: string | null;
  age?: number;
  gender?: Gender;
}

/** Тело `PATCH /api/me`. */
export interface UpdateProfilePayload {
  name?: string;
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
