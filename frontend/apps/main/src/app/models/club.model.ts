export type ClubCategory = 'Спорт' | 'Творчество' | 'Наука' | 'Музыка' | 'Танцы';

export type PriceType = 'free' | 'pushkin' | 'paid';

export type DayOfWeek = 'Пн' | 'Вт' | 'Ср' | 'Чт' | 'Пт' | 'Сб' | 'Вс';

export type VisitType = 'recurring' | 'oneTime' | 'both';

/** Слот расписания: день недели и время `HH:MM`. */
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
  /** Человекочитаемое расписание (формирует сервер, группы через `\n`). */
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
  /** Всегда ≥ 1 филиала; `address/phone/schedule` верхнего уровня — данные первого. */
  branches: Branch[];
}
