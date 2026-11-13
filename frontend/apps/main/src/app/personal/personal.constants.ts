import { ClubCategory, DayOfWeek, PriceType, VisitType } from '../models/club.model';

/** Дни недели в порядке Пн…Вс. */
export const WEEK_DAYS: DayOfWeek[] = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

export const CATEGORIES: ClubCategory[] = ['Спорт', 'Творчество', 'Наука', 'Музыка', 'Танцы'];

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
