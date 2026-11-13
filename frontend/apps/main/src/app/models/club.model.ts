export type ClubCategory = 'Спорт' | 'Творчество' | 'Наука' | 'Музыка' | 'Танцы';

export type PriceType = 'free' | 'pushkin' | 'paid';

export interface Branch {
  id: string;
  name: string;
  address: string;
  phone: string;
  schedule: string;
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
  schedule: string;
  address: string;
  phone: string;
  price: string;
  priceAmount: number;
  priceType: PriceType;
  tags: string[];
  branches?: Branch[];
}
