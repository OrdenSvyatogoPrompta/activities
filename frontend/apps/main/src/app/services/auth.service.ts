import { Injectable, signal } from '@angular/core';

export interface Child {
  id: string;
  firstName: string;
  lastName: string;
  age: number;
  gender: 'male' | 'female';
}

export interface Enrollment {
  id: string;
  clubId: string;
  clubName: string;
  branchId: string | null;
  branchName: string;
  schedule: string;
  days: string[];  // ['Пн','Ср','Пт'] для календаря
  color: string;   // цвет кружка для точек в календаре
  forChild: Child | null;
  enrolledAt: string;
}

export interface ActivityBranch {
  id: string;
  name: string;
  address: string;
  days: string[];
  timeFrom: string;
  timeTo: string;
}

export interface IncomingEnrollment {
  id: string;
  personName: string;
  childName: string | null;
  branchName: string;
  enrolledAt: string;
}

export interface ManagedActivity {
  id: string;
  name: string;
  category: string;
  emoji: string;
  description: string;
  branches: ActivityBranch[];
  enrollments: IncomingEnrollment[];
}

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  children: Child[];
  enrollments: Enrollment[];
  activities: ManagedActivity[];
}

const MOCK_USER: AuthUser = {
  id: 'u1',
  name: 'Иванова Мария',
  email: 'maria@example.com',
  children: [
    { id: 'c1', firstName: 'Артём', lastName: 'Иванов', age: 8, gender: 'male' },
    { id: 'c2', firstName: 'Соня', lastName: 'Иванова', age: 5, gender: 'female' },
  ],
  enrollments: [
    {
      id: 'e1', clubId: 'swimming-dyush', clubName: 'Плавание · ДЮСШ №1',
      branchId: 'borissenko', branchName: 'Борисенко',
      schedule: 'Пн, Ср, Пт · 15:00–18:00',
      days: ['Пн', 'Ср', 'Пт'], color: '#0ea5e9',
      forChild: { id: 'c1', firstName: 'Артём', lastName: 'Иванов', age: 8, gender: 'male' as const },
      enrolledAt: '2026-10-15',
    },
    {
      id: 'e2', clubId: 'gimnastika-voskhod', clubName: 'Восход',
      branchId: null, branchName: 'Нейбута',
      schedule: 'Пн, Ср, Пт · 16:00–19:00',
      days: ['Пн', 'Ср', 'Пт'], color: '#f43f5e',
      forChild: { id: 'c2', firstName: 'Соня', lastName: 'Иванова', age: 5, gender: 'female' as const },
      enrolledAt: '2026-11-01',
    },
  ],
  activities: [
    {
      id: 'a1',
      name: 'Акварель',
      category: 'Творчество',
      emoji: '🎨',
      description: 'Художественная студия для детей. Занятия акварелью, гуашью, пастелью. Небольшие группы — индивидуальный подход.',
      branches: [
        { id: 'b1', name: 'Основной', address: 'пр. Океанский, 44', days: ['Вт', 'Чт', 'Сб'], timeFrom: '15:30', timeTo: '17:30' },
        { id: 'b2', name: 'Снеговая', address: 'ул. Снеговая, 10', days: ['Ср', 'Пт'], timeFrom: '16:00', timeTo: '18:00' },
      ],
      enrollments: [
        { id: 'ie1', personName: 'Петрова Анна', childName: 'Маша', branchName: 'Основной', enrolledAt: '2026-10-20' },
        { id: 'ie2', personName: 'Сидоров Игорь', childName: null, branchName: 'Основной', enrolledAt: '2026-10-22' },
        { id: 'ie3', personName: 'Козлова Вера', childName: 'Дима', branchName: 'Снеговая', enrolledAt: '2026-11-01' },
        { id: 'ie4', personName: 'Новикова Елена', childName: 'Катя', branchName: 'Снеговая', enrolledAt: '2026-11-05' },
      ],
    },
  ],
};

@Injectable({ providedIn: 'root' })
export class AuthService {
  readonly user = signal<AuthUser | null>(null);

  login(email: string, _password: string): void {
    this.user.set({ ...MOCK_USER, email, name: email.split('@')[0] });
  }

  logout(): void {
    this.user.set(null);
  }

  addChild(firstName: string, lastName: string, age: number, gender: Child['gender']): void {
    const u = this.user();
    if (!u) return;
    const child: Child = { id: `c${Date.now()}`, firstName, lastName, age, gender };
    this.user.set({ ...u, children: [...u.children, child] });
  }

  removeChild(id: string): void {
    const u = this.user();
    if (!u) return;
    this.user.set({ ...u, children: u.children.filter(c => c.id !== id) });
  }

  enroll(enrollment: Omit<Enrollment, 'id' | 'enrolledAt' | 'days' | 'color'> & { days?: string[]; color?: string }): void {
    const u = this.user();
    if (!u) return;
    const e: Enrollment = { days: [], color: '#6880f8', ...enrollment, id: `e${Date.now()}`, enrolledAt: new Date().toISOString().slice(0, 10) };
    this.user.set({ ...u, enrollments: [...u.enrollments, e] });
  }

  cancelEnrollment(id: string): void {
    const u = this.user();
    if (!u) return;
    this.user.set({ ...u, enrollments: u.enrollments.filter(e => e.id !== id) });
  }

  addActivity(activity: Omit<ManagedActivity, 'id' | 'enrollments'>): void {
    const u = this.user();
    if (!u) return;
    const a: ManagedActivity = { ...activity, id: `a${Date.now()}`, enrollments: [] };
    this.user.set({ ...u, activities: [...u.activities, a] });
  }
}
