export type ClubCategory = 'Спорт' | 'Творчество' | 'Наука' | 'Музыка' | 'Танцы';

export type PriceType = 'free' | 'pushkin' | 'paid';

export type DayOfWeek = 'Пн' | 'Вт' | 'Ср' | 'Чт' | 'Пт' | 'Сб' | 'Вс';

export type VisitType = 'recurring' | 'oneTime' | 'both';

export const CATEGORIES: ClubCategory[] = ['Спорт', 'Творчество', 'Наука', 'Музыка', 'Танцы'];

export const WEEK_DAYS: DayOfWeek[] = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

export const VISIT_TYPE_LABEL: Record<VisitType, string> = {
  recurring: 'Постоянные занятия',
  oneTime: 'Разовые занятия',
  both: 'Постоянные и разовые',
};

export const PRICE_TYPE_LABEL: Record<PriceType, string> = {
  free: 'Бесплатно',
  paid: 'Платно',
  pushkin: '🎭 Пушкинская карта',
};

export interface ScheduleSlot {
  day: DayOfWeek;
  timeFrom: string;
  timeTo: string;
}

export interface Branch {
  id: string;
  name: string;
  address: string;
  phone: string;
  /** Группы слотов через `\n`. */
  schedule: string;
  days: DayOfWeek[];
  slots: ScheduleSlot[];
}

export interface Club {
  id: string;
  name: string;
  categories: ClubCategory[];
  emoji: string;
  color: string;
  shortDescription: string;
  fullDescription: string;
  age: string;
  ageMin: number;
  ageMax: number;
  days: DayOfWeek[];
  visitType: VisitType;
  schedule: string;
  address: string;
  phone: string;
  price: string;
  priceAmount: number;
  priceType: PriceType;
  tags: string[];
  branches: Branch[];
}
