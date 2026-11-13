export type ClubCategory = 'Спорт' | 'Творчество' | 'Наука' | 'Музыка' | 'Танцы';

export type PriceType = 'free' | 'pushkin' | 'paid';

export type DayOfWeek = 'Пн' | 'Вт' | 'Ср' | 'Чт' | 'Пт' | 'Сб' | 'Вс';

export type VisitType = 'recurring' | 'oneTime' | 'both';

export interface Branch {
  id: string;
  name: string;
  address: string;
  phone: string;
  schedule: string;
  days?: DayOfWeek[];
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
  branches?: Branch[];
}
