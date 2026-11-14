export type Gender = 'male' | 'female';

export interface User {
  id: string;
  name: string;
  email: string;
  roles: string[];
  stats: { enrollments: number; activities: number; children: number };
}

export interface Child {
  id: string;
  firstName: string;
  lastName: string | null;
  age: number;
  gender: Gender;
}

export interface CreateChildPayload {
  firstName: string;
  lastName?: string;
  age: number;
  gender: Gender;
}

export interface UpdateChildPayload {
  firstName?: string;
  /** `null` стирает фамилию. */
  lastName?: string | null;
  age?: number;
  gender?: Gender;
}

export interface AuthResponse {
  accessToken: string;
  user: User;
}
