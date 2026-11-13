import { NgTemplateOutlet } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ClubCategory, PriceType } from '../models/club.model';
import { ClubsService } from '../services/clubs.service';

const CATEGORIES: Array<'Все' | ClubCategory> = ['Все', 'Спорт', 'Творчество', 'Наука', 'Музыка', 'Танцы'];
const AGE_MIN = 0;
const AGE_MAX = 18;
const PRICE_MIN = 0;
const PRICE_MAX = 5000;
const PRICE_STEP = 100;

export const CATEGORY_BADGE: Record<string, { bg: string; color: string }> = {
  Спорт:      { bg: '#dbeafe', color: '#1d4ed8' },
  Творчество: { bg: '#fef9c3', color: '#a16207' },
  Наука:      { bg: '#ede9fe', color: '#6d28d9' },
  Музыка:     { bg: '#fce7f3', color: '#be185d' },
  Танцы:      { bg: '#f3e8ff', color: '#7e22ce' },
};

export const CATEGORY_COLORS: Record<string, string> = Object.fromEntries(
  Object.entries(CATEGORY_BADGE).map(([k, v]) => [k, v.color]),
);

@Component({
  selector: 'act-home',
  imports: [RouterLink, NgTemplateOutlet],
  styleUrl: './home.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="page">
      <div class="sticky-top">

        <header class="header">
          <span class="header__logo">
            Activities <span class="header__city">· Владивосток</span>
          </span>
          <div class="header__right">
            <div class="header__search-wrap">
              <svg class="header__search-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
              </svg>
              <input class="header__search-input" type="text" placeholder="Поиск кружков..."
                [value]="searchQuery()"
                (input)="searchQuery.set($any($event.target).value)" />
              @if (searchQuery()) {
                <button class="header__search-clear" (click)="searchQuery.set('')">✕</button>
              }
            </div>
            <button class="header__auth" disabled>Войти</button>
          </div>
        </header>

        <div class="tabs-bar">
          <div class="categories">
            @for (cat of categories; track cat) {
              <button class="cat-tab" [class.cat-tab--active]="isCatActive(cat)"
                (click)="toggleCategory(cat)">{{ cat }}</button>
            }
          </div>
          @if (hasActiveFilters()) {
            <button class="reset-btn" (click)="resetFilters()">Сбросить</button>
          }
          <button class="filter-btn" [class.filter-btn--active]="filtersOpen() || hasSecondaryFilters()"
            (click)="filtersOpen.set(!filtersOpen())">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/>
              <line x1="11" y1="18" x2="13" y2="18"/>
            </svg>
            Фильтры
            @if (activeSecondaryCount() > 0) {
              <span class="filter-btn__badge">{{ activeSecondaryCount() }}</span>
            }
          </button>
        </div>

        @if (filtersOpen()) {
          <div class="filter-panel">

            <!-- Стоимость -->
            <div class="filter-section">
              <span class="filter-section__title">Стоимость</span>
              <div class="filter-section__tags">
                <button class="ftag" [class.ftag--active]="priceType() === 'free'"
                  (click)="togglePriceType('free')">Бесплатно</button>
                <button class="ftag" [class.ftag--active]="priceType() === 'pushkin'"
                  (click)="togglePriceType('pushkin')">🎭 Пушкинская карта</button>
              </div>
              @if (priceType() !== 'free') {
                <ng-container *ngTemplateOutlet="priceSliderTpl"></ng-container>
              }
            </div>

            <div class="filter-panel__divider"></div>

            <!-- Возраст -->
            <div class="filter-section">
              <span class="filter-section__title">Возраст ребёнка</span>
              <ng-container *ngTemplateOutlet="ageSliderTpl"></ng-container>
            </div>

          </div>

          <div class="backdrop" (click)="filtersOpen.set(false)"></div>
        }

      </div>

      <div class="grid-wrap">
        @if (filteredClubs().length === 0) {
          <div class="empty">
            <div class="empty__icon">🔍</div>
            <div class="empty__text">Ничего не найдено</div>
            @if (hasActiveFilters()) {
              <button class="empty__reset" (click)="resetFilters()">Сбросить фильтры</button>
            }
          </div>
        } @else {
          <div class="grid">
            @for (club of filteredClubs(); track club.id) {
              <a class="card" [routerLink]="['/club', club.id]">
                <div class="card__image"
                  [style.background]="'linear-gradient(145deg,' + club.color + '18,' + club.color + '38)'">
                  <span class="card__emoji">{{ club.emoji }}</span>
                  <div class="card__badges">
                    @for (cat of club.categories; track cat) {
                      <span class="badge"
                        [style.background]="categoryBadge[cat].bg"
                        [style.color]="categoryBadge[cat].color">{{ cat }}</span>
                    }
                  </div>
                  @if (club.priceType === 'pushkin') {
                    <span class="card__pushkin">🎭</span>
                  }
                </div>
                <div class="card__body">
                  <div class="card__name">{{ club.name }}</div>
                  <div class="card__desc">{{ club.shortDescription }}</div>
                  <div class="card__footer">
                    <span class="card__age">{{ club.age }}</span>
                    <span class="card__price" [class.card__price--free]="club.priceType === 'free'">
                      {{ club.price }}
                    </span>
                  </div>
                </div>
              </a>
            }
          </div>
        }
      </div>

      <!-- Шаблон ползунка цены -->
      <ng-template #priceSliderTpl>
        <div class="dual-slider-wrap">
          <div class="dual-slider">
            <div class="dual-slider__track">
              <div class="dual-slider__fill" [style]="priceFillStyle()"></div>
            </div>
            <input class="dual-slider__thumb" type="range"
              [min]="PRICE_MIN" [max]="PRICE_MAX" [step]="PRICE_STEP"
              [value]="priceFromVal()"
              [style.zIndex]="priceFromVal() >= PRICE_MAX - PRICE_STEP ? 4 : 3"
              (input)="onPriceFrom($any($event.target).value)" />
            <input class="dual-slider__thumb" type="range"
              [min]="PRICE_MIN" [max]="PRICE_MAX" [step]="PRICE_STEP"
              [value]="priceToVal()"
              [style.zIndex]="priceFromVal() >= PRICE_MAX - PRICE_STEP ? 3 : 4"
              (input)="onPriceTo($any($event.target).value)" />
          </div>
          <div class="dual-slider__labels">
            <span>{{ priceFromVal() === PRICE_MIN ? 'от 0 ₽' : priceFromVal() + ' ₽' }}</span>
            <span>{{ priceToVal() === PRICE_MAX ? 'до ' + PRICE_MAX + '+ ₽' : priceToVal() + ' ₽' }}</span>
          </div>
        </div>
      </ng-template>

      <!-- Шаблон ползунка возраста -->
      <ng-template #ageSliderTpl>
        <div class="dual-slider-wrap">
          <div class="dual-slider">
            <div class="dual-slider__track">
              <div class="dual-slider__fill" [style]="ageFillStyle()"></div>
            </div>
            <input class="dual-slider__thumb" type="range"
              [min]="AGE_MIN" [max]="AGE_MAX" step="1"
              [value]="ageFromVal()"
              [style.zIndex]="ageFromVal() >= AGE_MAX - 1 ? 4 : 3"
              (input)="onAgeFrom($any($event.target).value)" />
            <input class="dual-slider__thumb" type="range"
              [min]="AGE_MIN" [max]="AGE_MAX" step="1"
              [value]="ageToVal()"
              [style.zIndex]="ageFromVal() >= AGE_MAX - 1 ? 3 : 4"
              (input)="onAgeTo($any($event.target).value)" />
          </div>
          <div class="dual-slider__labels">
            <span>{{ ageFromVal() === AGE_MIN ? 'от 0 лет' : ageFromVal() + ' лет' }}</span>
            <span>{{ ageToVal() === AGE_MAX ? 'до 18+ лет' : ageToVal() + ' лет' }}</span>
          </div>
        </div>
      </ng-template>

    </div>
  `,
})
export class HomeComponent {
  private readonly clubsService = inject(ClubsService);

  readonly AGE_MIN    = AGE_MIN;
  readonly AGE_MAX    = AGE_MAX;
  readonly PRICE_MIN  = PRICE_MIN;
  readonly PRICE_MAX  = PRICE_MAX;
  readonly PRICE_STEP = PRICE_STEP;

  readonly categories    = CATEGORIES;
  readonly categoryBadge = CATEGORY_BADGE;

  readonly searchQuery         = signal('');
  readonly selectedCategories  = signal<Set<ClubCategory>>(new Set());
  readonly priceType        = signal<PriceType | null>(null);
  readonly priceFrom        = signal<number | null>(null);
  readonly priceTo          = signal<number | null>(null);
  readonly ageFrom          = signal<number | null>(null);
  readonly ageTo            = signal<number | null>(null);
  readonly filtersOpen      = signal(false);

  readonly ageFromVal   = computed(() => this.ageFrom()  ?? AGE_MIN);
  readonly ageToVal     = computed(() => this.ageTo()    ?? AGE_MAX);
  readonly priceFromVal = computed(() => this.priceFrom() ?? PRICE_MIN);
  readonly priceToVal   = computed(() => this.priceTo()   ?? PRICE_MAX);

  readonly ageFillStyle = computed(() =>
    `left: ${(this.ageFromVal() / AGE_MAX) * 100}%; right: ${100 - (this.ageToVal() / AGE_MAX) * 100}%`,
  );

  readonly priceFillStyle = computed(() =>
    `left: ${(this.priceFromVal() / PRICE_MAX) * 100}%; right: ${100 - (this.priceToVal() / PRICE_MAX) * 100}%`,
  );

  readonly filteredClubs = computed(() => {
    const q  = this.searchQuery().toLowerCase().trim();
    const pt = this.priceType();
    const af  = this.ageFrom();
    const at  = this.ageTo();
    const pf  = this.priceFrom();
    const pto = this.priceTo();

    const cats = this.selectedCategories();
    return this.clubsService.getAll().filter((club) => {
      if (cats.size > 0 && !club.categories.some(c => cats.has(c))) return false;
      if (q && !club.name.toLowerCase().includes(q) && !club.shortDescription.toLowerCase().includes(q)) return false;
      if (pt === 'free'    && club.priceType !== 'free')    return false;
      if (pt === 'pushkin' && club.priceType !== 'pushkin') return false;
      // ползунок цены работает при null и при pushkin
      if (pt !== 'free') {
        if (pf !== null && club.priceAmount < pf) return false;
        if (pto !== null && club.priceAmount > pto) return false;
      }
      if (af !== null && club.ageMax < af) return false;
      if (at !== null && club.ageMin > at) return false;
      return true;
    });
  });

  readonly hasSecondaryFilters = computed(() =>
    this.priceType() !== null || this.priceFrom() !== null || this.priceTo() !== null
    || this.ageFrom() !== null || this.ageTo() !== null,
  );

  readonly activeSecondaryCount = computed(() => {
    let n = 0;
    if (this.priceType() !== null) n++;
    if (this.priceFrom() !== null || this.priceTo() !== null) n++;
    if (this.ageFrom() !== null || this.ageTo() !== null) n++;
    return n;
  });

  readonly hasActiveFilters = computed(() =>
    this.searchQuery() !== '' || this.selectedCategories().size > 0 || this.hasSecondaryFilters(),
  );

  toggleCategory(cat: 'Все' | ClubCategory): void {
    if (cat === 'Все') {
      this.selectedCategories.set(new Set());
      return;
    }
    const next = new Set(this.selectedCategories());
    if (next.has(cat)) {
      next.delete(cat);
    } else {
      next.add(cat);
      // если выбраны все — сбросить на «Все»
      const allCats = CATEGORIES.filter(c => c !== 'Все') as ClubCategory[];
      if (allCats.every(c => next.has(c))) next.clear();
    }
    this.selectedCategories.set(next);
  }

  isCatActive(cat: 'Все' | ClubCategory): boolean {
    return cat === 'Все' ? this.selectedCategories().size === 0 : this.selectedCategories().has(cat as ClubCategory);
  }

  togglePriceType(type: PriceType): void {
    this.priceType.set(this.priceType() === type ? null : type);
  }

  onAgeFrom(val: string): void {
    const v = Math.min(+val, this.ageToVal() - 1);
    this.ageFrom.set(v <= AGE_MIN ? null : v);
  }

  onAgeTo(val: string): void {
    const v = Math.max(+val, this.ageFromVal() + 1);
    this.ageTo.set(v >= AGE_MAX ? null : v);
  }

  onPriceFrom(val: string): void {
    const v = Math.min(+val, this.priceToVal() - PRICE_STEP);
    this.priceFrom.set(v <= PRICE_MIN ? null : v);
  }

  onPriceTo(val: string): void {
    const v = Math.max(+val, this.priceFromVal() + PRICE_STEP);
    this.priceTo.set(v >= PRICE_MAX ? null : v);
  }

  resetFilters(): void {
    this.searchQuery.set('');
    this.selectedCategories.set(new Set());
    this.priceType.set(null);
    this.priceFrom.set(null);
    this.priceTo.set(null);
    this.ageFrom.set(null);
    this.ageTo.set(null);
    this.filtersOpen.set(false);
  }
}
