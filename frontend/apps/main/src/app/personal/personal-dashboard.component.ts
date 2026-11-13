import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import { ActivityBranch, AuthService, ManagedActivity } from '../services/auth.service';

type Tab = 'enrollments' | 'activities' | 'children';
type EnrollView = 'list' | 'calendar';

const DAY_NAMES = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
const DAY_HEADERS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const WEEK_DAYS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const CATEGORIES = ['Спорт', 'Творчество', 'Наука', 'Музыка', 'Танцы'];

@Component({
  selector: 'act-personal-dashboard',
  imports: [RouterLink],
  styleUrl: './personal-dashboard.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (user(); as user) {
      <header class="header">
        <a class="header__logo" routerLink="/">Заодно</a>
        <button class="header__logout" (click)="logout()">Выйти</button>
      </header>

      <div class="hero">
        <div class="hero__top">
          <div class="hero__avatar">{{ user.name[0].toUpperCase() }}</div>
          <div class="hero__info">
            <p class="hero__name">{{ user.name }}</p>
            <p class="hero__email">{{ user.email }}</p>
          </div>
        </div>
        <div class="hero__stats">
          <div class="hero__stat">
            <span class="hero__stat-num">{{ user.enrollments.length }}</span>
            <span class="hero__stat-label">записей</span>
          </div>
          <div class="hero__stat">
            <span class="hero__stat-num">{{ user.activities.length }}</span>
            <span class="hero__stat-label">активностей</span>
          </div>
          <div class="hero__stat">
            <span class="hero__stat-num">{{ user.children.length }}</span>
            <span class="hero__stat-label">детей</span>
          </div>
        </div>
      </div>

      <div class="tabs">
        <button class="tab" [class.tab--active]="tab() === 'enrollments'" (click)="tab.set('enrollments')">
          Мои записи <span class="tab__count">{{ user.enrollments.length }}</span>
        </button>
        <button class="tab" [class.tab--active]="tab() === 'activities'" (click)="tab.set('activities')">
          Активности <span class="tab__count">{{ user.activities.length }}</span>
        </button>
        <button class="tab" [class.tab--active]="tab() === 'children'" (click)="tab.set('children')">
          Дети <span class="tab__count">{{ user.children.length }}</span>
        </button>
      </div>

      <div class="content">

        <!-- ── Мои записи ── -->
        @if (tab() === 'enrollments') {
          <div class="view-toggle">
            <button class="vtog" [class.vtog--active]="enrollView() === 'list'" (click)="enrollView.set('list')">Список</button>
            <button class="vtog" [class.vtog--active]="enrollView() === 'calendar'" (click)="enrollView.set('calendar')">Календарь</button>
          </div>

          @if (enrollView() === 'calendar') {
            <div class="cal">
              <div class="cal__month">{{ calendarDays().monthLabel }}</div>
              <div class="cal__grid">
                @for (h of dayHeaders; track h) { <div class="cal__head">{{ h }}</div> }
                @for (day of calendarDays().days; track $index) {
                  <div class="cal__day" [class.cal__day--today]="day.date === today">
                    @if (day.date) {
                      <span class="cal__num">{{ day.date }}</span>
                      @if (day.dots.length > 0) {
                        <div class="cal__dots">
                          @for (color of day.dots; track color) {
                            <span class="cal__dot" [style.background]="color"></span>
                          }
                        </div>
                      }
                    }
                  </div>
                }
              </div>
              <div class="cal__legend">
                @for (e of user.enrollments; track e.id) {
                  <div class="cal__legend-item">
                    <span class="cal__dot" [style.background]="e.color"></span>
                    <span>{{ e.clubName }}</span>
                  </div>
                }
              </div>
            </div>
          }

          @if (enrollView() === 'list') {
            @if (user.children.length > 0) {
              <div class="child-filter">
                <button class="filter-pill" [class.filter-pill--active]="childFilter() === null" (click)="childFilter.set(null)">Все</button>
                <button class="filter-pill" [class.filter-pill--active]="childFilter() === 'self'" (click)="childFilter.set('self')">Я</button>
                @for (child of user.children; track child.id) {
                  <button class="filter-pill" [class.filter-pill--active]="childFilter() === child.id" (click)="childFilter.set(child.id)">{{ child.firstName }}</button>
                }
              </div>
            }
            @if (filteredEnrollments().length === 0) {
              <div class="empty">
                <div class="empty__icon">📋</div>
                <div class="empty__title">Записей пока нет</div>
                <div class="empty__sub">Найдите активность и запишитесь</div>
                <a routerLink="/" class="empty__link">Смотреть активности</a>
              </div>
            } @else {
              @for (e of filteredEnrollments(); track e.id) {
                <div class="enrollment-card">
                  <div class="enrollment-card__accent">🎯</div>
                  <div class="enrollment-card__body">
                    <p class="enrollment-card__name">{{ e.clubName }}</p>
                    <p class="enrollment-card__meta">{{ e.branchName }} · {{ e.schedule }}</p>
                    <div class="enrollment-card__tags">
                      @if (e.forChild) {
                        <span class="etag etag--child">{{ e.forChild.gender === 'male' ? '👦' : '👧' }} {{ e.forChild.firstName }}</span>
                      } @else {
                        <span class="etag">Для себя</span>
                      }
                      <span class="etag etag--date">с {{ e.enrolledAt }}</span>
                    </div>
                  </div>
                  <button class="enrollment-card__cancel" (click)="cancelEnrollment(e.id)">✕</button>
                </div>
              }
            }
          }
        }

        <!-- ── Активности ── -->
        @if (tab() === 'activities') {

          <!-- Детальный просмотр активности -->
          @if (selectedActivity(); as act) {
            <button class="back-btn" (click)="selectedActivity.set(null)">← Все активности</button>

            <div class="act-detail">
              <div class="act-detail__header">
                <div class="act-detail__icon">{{ act.emoji }}</div>
                <div>
                  <p class="act-detail__name">{{ act.name }}</p>
                  <p class="act-detail__cat">{{ act.category }}</p>
                </div>
                <div class="act-detail__total">
                  <span class="act-detail__num">{{ act.enrollments.length }}</span>
                  <span class="act-detail__label">записей</span>
                </div>
              </div>
              @if (act.description) {
                <p class="act-detail__desc">{{ act.description }}</p>
              }
            </div>

            @for (branch of act.branches; track branch.id) {
              <div class="branch-block">
                <div class="branch-block__head">
                  <span class="branch-block__name">📍 {{ branch.name }}</span>
                  <span class="branch-block__addr">{{ branch.address }}</span>
                </div>
                <div class="branch-block__sched">{{ branch.days.join(', ') }} · {{ branch.timeFrom }}–{{ branch.timeTo }}</div>
                <div class="branch-block__enrolls">
                  @for (ie of branchEnrollments(act, branch.name); track ie.id) {
                    <div class="ie-row">
                      <span class="ie-row__name">{{ ie.personName }}</span>
                      @if (ie.childName) { <span class="ie-row__child">👶 {{ ie.childName }}</span> }
                      <span class="ie-row__date">{{ ie.enrolledAt }}</span>
                    </div>
                  }
                  @if (branchEnrollments(act, branch.name).length === 0) {
                    <p class="ie-empty">Нет записей в этот филиал</p>
                  }
                </div>
              </div>
            }
          }

          <!-- Список активностей / форма создания -->
          @if (!selectedActivity()) {
            @if (showCreateForm()) {
              <div class="add-form">
                <p class="form-title">Новая активность</p>

                <div class="form-row">
                  <div class="field">
                    <label class="field__label">Название</label>
                    <input class="field__input" type="text" placeholder="Название активности"
                      [value]="newName()" (input)="newName.set($any($event.target).value)" />
                  </div>
                  <div class="field field--narrow">
                    <label class="field__label">Эмодзи</label>
                    <input class="field__input" type="text" placeholder="🎨"
                      [value]="newEmoji()" (input)="newEmoji.set($any($event.target).value)" />
                  </div>
                </div>

                <div class="field">
                  <label class="field__label">Категория</label>
                  <select class="field__input field__select"
                    [value]="newCategory()" (change)="newCategory.set($any($event.target).value)">
                    <option value="" disabled>Выберите категорию</option>
                    @for (cat of categories; track cat) {
                      <option [value]="cat">{{ cat }}</option>
                    }
                  </select>
                </div>

                <div class="field">
                  <label class="field__label">Описание</label>
                  <textarea class="field__input field__textarea" placeholder="Расскажите об активности..."
                    [value]="newDescription()" (input)="newDescription.set($any($event.target).value)"></textarea>
                </div>

                <!-- Основной филиал -->
                <div class="branch-form">
                  <p class="branch-form__title">Основной филиал</p>
                  <div class="field">
                    <label class="field__label">Адрес</label>
                    <input class="field__input" type="text" placeholder="ул. Примерная, 1"
                      [value]="branchAddress()" (input)="branchAddress.set($any($event.target).value)" />
                  </div>
                  <div class="field">
                    <label class="field__label">Дни занятий</label>
                    <div class="day-picker">
                      @for (d of weekDays; track d) {
                        <button class="day-btn" [class.day-btn--active]="branchDays().includes(d)"
                          (click)="toggleBranchDay(d)">{{ d }}</button>
                      }
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="field">
                      <label class="field__label">Начало</label>
                      <input class="field__input" type="time" [value]="branchTimeFrom()"
                        (change)="branchTimeFrom.set($any($event.target).value)" />
                    </div>
                    <div class="field">
                      <label class="field__label">Конец</label>
                      <input class="field__input" type="time" [value]="branchTimeTo()"
                        (change)="branchTimeTo.set($any($event.target).value)" />
                    </div>
                  </div>
                </div>

                <div class="form-actions">
                  <button class="btn-primary" (click)="createActivity()">Создать активность</button>
                  <button class="btn-ghost" (click)="showCreateForm.set(false)">Отмена</button>
                </div>
              </div>
            } @else {
              @for (a of user.activities; track a.id) {
                <div class="activity-card" (click)="selectedActivity.set(a)">
                  <div class="activity-card__icon">{{ a.emoji }}</div>
                  <div>
                    <p class="activity-card__name">{{ a.name }}</p>
                    <p class="activity-card__category">{{ a.category }}</p>
                  </div>
                  <div class="activity-card__count">
                    <span class="activity-card__count-num">{{ a.enrollments.length }}</span>
                    <span class="activity-card__count-label">записей</span>
                  </div>
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                    fill="none" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"/>
                  </svg>
                </div>
              }
              @if (user.activities.length === 0) {
                <div class="empty">
                  <div class="empty__icon">🎯</div>
                  <div class="empty__title">Нет активностей</div>
                  <div class="empty__sub">Создайте свою секцию или кружок</div>
                </div>
              }
              <button class="btn-create" (click)="showCreateForm.set(true)">+ Создать активность</button>
            }
          }
        }

        <!-- ── Дети ── -->
        @if (tab() === 'children') {
          @for (child of user.children; track child.id) {
            <div class="child-card">
              <div class="child-card__avatar">{{ child.gender === 'male' ? '👦' : '👧' }}</div>
              <div>
                <p class="child-card__name">{{ child.firstName }} {{ child.lastName }}</p>
                <p class="child-card__age">{{ child.age }} лет</p>
              </div>
              <button class="child-card__remove" (click)="removeChild(child.id)">✕</button>
            </div>
          }

          <div class="add-form">
            <p class="form-title">Добавить ребёнка</p>
            <div class="form-row">
              <div class="field">
                <label class="field__label">Имя</label>
                <input class="field__input" type="text" placeholder="Артём"
                  [value]="childFirstName()" (input)="childFirstName.set($any($event.target).value)" />
              </div>
              <div class="field">
                <label class="field__label">Фамилия</label>
                <input class="field__input" type="text" placeholder="Иванов"
                  [value]="childLastName()" (input)="childLastName.set($any($event.target).value)" />
              </div>
            </div>
            <div class="form-row">
              <div class="field field--narrow">
                <label class="field__label">Возраст</label>
                <input class="field__input field__input--no-spin" type="number" min="1" max="18" placeholder="8"
                  [value]="childAge() || ''" (input)="childAge.set(+$any($event.target).value)" />
              </div>
              <div class="field">
                <label class="field__label">Пол</label>
                <div class="gender-row">
                  <button class="filter-pill" [class.filter-pill--active]="childGender() === 'male'"
                    (click)="childGender.set('male')">👦 Мальчик</button>
                  <button class="filter-pill" [class.filter-pill--active]="childGender() === 'female'"
                    (click)="childGender.set('female')">👧 Девочка</button>
                </div>
              </div>
            </div>
            <button class="btn-add-child" (click)="addChild()">Добавить</button>
          </div>
        }

      </div>
    } @else {
      <div class="unauth">
        <p class="unauth__text">Необходимо войти в аккаунт</p>
        <a routerLink="/auth/login" class="unauth__link">Войти</a>
      </div>
    }
  `,
})
export class PersonalDashboardComponent {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  readonly user        = this.authService.user;
  readonly tab         = signal<Tab>('enrollments');
  readonly enrollView  = signal<EnrollView>('list');
  readonly childFilter = signal<string | null>(null);
  readonly selectedActivity = signal<ManagedActivity | null>(null);
  readonly showCreateForm = signal(false);
  readonly dayHeaders  = DAY_HEADERS;
  readonly weekDays    = WEEK_DAYS;
  readonly categories  = CATEGORIES;
  readonly today       = new Date().getDate();

  // форма создания активности
  readonly newName        = signal('');
  readonly newCategory    = signal('');
  readonly newEmoji       = signal('');
  readonly newDescription = signal('');
  readonly branchAddress  = signal('');
  readonly branchDays     = signal<string[]>([]);
  readonly branchTimeFrom = signal('10:00');
  readonly branchTimeTo   = signal('12:00');

  // форма добавления ребёнка
  readonly childFirstName = signal('');
  readonly childLastName  = signal('');
  readonly childAge       = signal(0);
  readonly childGender    = signal<'male' | 'female'>('male');

  readonly filteredEnrollments = computed(() => {
    const u = this.user();
    if (!u) return [];
    const f = this.childFilter();
    if (f === null) return u.enrollments;
    if (f === 'self') return u.enrollments.filter(e => !e.forChild);
    return u.enrollments.filter(e => e.forChild?.id === f);
  });

  readonly calendarDays = computed(() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startOffset = (firstDay.getDay() + 6) % 7;
    const days: Array<{ date: number | null; dots: string[] }> = [];
    for (let i = 0; i < startOffset; i++) days.push({ date: null, dots: [] });
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const weekday = DAY_NAMES[new Date(year, month, d).getDay()];
      const dots = (this.user()?.enrollments ?? [])
        .filter(e => e.days.includes(weekday)).map(e => e.color);
      days.push({ date: d, dots });
    }
    return { days, monthLabel: firstDay.toLocaleString('ru', { month: 'long', year: 'numeric' }) };
  });

  toggleBranchDay(day: string): void {
    const curr = this.branchDays();
    this.branchDays.set(curr.includes(day) ? curr.filter(d => d !== day) : [...curr, day]);
  }

  async logout(): Promise<void> {
    await this.authService.logout();
    await this.router.navigate(['/']);
  }
  cancelEnrollment(id: string): void { this.authService.cancelEnrollment(id); }
  removeChild(id: string): void { this.authService.removeChild(id); }

  addChild(): void {
    if (!this.childFirstName() || !this.childAge()) return;
    this.authService.addChild(this.childFirstName(), this.childLastName(), this.childAge(), this.childGender());
    this.childFirstName.set(''); this.childLastName.set(''); this.childAge.set(0);
  }

  createActivity(): void {
    if (!this.newName() || !this.newCategory()) return;
    const branch: ActivityBranch = {
      id: `b${Date.now()}`,
      name: 'Основной',
      address: this.branchAddress(),
      days: this.branchDays(),
      timeFrom: this.branchTimeFrom(),
      timeTo: this.branchTimeTo(),
    };
    this.authService.addActivity({
      name: this.newName(),
      category: this.newCategory(),
      emoji: this.newEmoji() || '🎯',
      description: this.newDescription(),
      branches: [branch],
    });
    this.newName.set(''); this.newCategory.set(''); this.newEmoji.set('');
    this.newDescription.set(''); this.branchAddress.set('');
    this.branchDays.set([]); this.branchTimeFrom.set('10:00'); this.branchTimeTo.set('12:00');
    this.showCreateForm.set(false);
  }

  branchEnrollments(act: ManagedActivity, branchName: string) {
    return act.enrollments.filter(e => e.branchName === branchName);
  }
}
