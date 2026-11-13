import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import { ApiError, formErrorMessage, toApiError } from '../api/api-error';
import {
  ACTIVITY_STATUS_LABEL, Activity, ActivityBranch, CreateActivityPayload, IncomingEnrollment,
} from '../models/activity.model';
import { ClubCategory, DayOfWeek, PriceType, ScheduleSlot, VisitType } from '../models/club.model';
import { ENROLLMENT_STATUS_LABEL, Enrollment, isActiveEnrollment } from '../models/enrollment.model';
import { CreateChildPayload, Gender } from '../models/user.model';
import { ActivitiesService } from '../services/activities.service';
import { AuthService } from '../services/auth.service';
import { ChildrenService } from '../services/children.service';
import { EnrollmentsService } from '../services/enrollments.service';

type Tab = 'enrollments' | 'activities' | 'children';
type EnrollView = 'list' | 'calendar';

/** Группа слотов в форме: несколько дней с одним временем. */
interface SlotGroup {
  days: DayOfWeek[];
  timeFrom: string;
  timeTo: string;
}

interface CalendarDot {
  color: string;
  pending: boolean;
}

interface IncomingGroup {
  branchId: string;
  branchName: string;
  items: IncomingEnrollment[];
}

const DAY_NAMES: DayOfWeek[] = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
const WEEK_DAYS: DayOfWeek[] = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const CATEGORIES: ClubCategory[] = ['Спорт', 'Творчество', 'Наука', 'Музыка', 'Танцы'];

const VISIT_TYPE_LABEL: Record<VisitType, string> = {
  recurring: 'Постоянные занятия',
  oneTime: 'Разовые занятия',
  both: 'Постоянные и разовые',
};
const VISIT_TYPES = Object.keys(VISIT_TYPE_LABEL) as VisitType[];

const PRICE_TYPE_LABEL: Record<PriceType, string> = {
  free: 'Бесплатно',
  paid: 'Платно',
  pushkin: '🎭 Пушкинская карта',
};
const PRICE_TYPES: PriceType[] = ['free', 'paid', 'pushkin'];

const ACTIVITY_FIELDS = [
  'name', 'category', 'emoji', 'description', 'ageMin', 'ageMax', 'visitType', 'priceType',
  'priceAmount', 'tags', 'branches[0].name', 'branches[0].address', 'branches[0].phone',
];
/** Ключ ошибки для блока расписания (все `branches[0].slots…` от сервера). */
const SLOTS_KEY = 'branches[0].slots';
const CHILD_FIELDS = ['firstName', 'lastName', 'age', 'gender'];

const EMPTY_SLOT_GROUP: SlotGroup = { days: [], timeFrom: '10:00', timeTo: '12:00' };

/** «Пн, Ср · 15:00–18:00\nВт · 10:00–12:00» — слоты с одинаковым временем вместе. */
function formatSlots(slots: ScheduleSlot[]): string {
  const groups = new Map<string, DayOfWeek[]>();
  const sorted = [...slots].sort((a, b) => WEEK_DAYS.indexOf(a.day) - WEEK_DAYS.indexOf(b.day));
  for (const s of sorted) {
    const key = `${s.timeFrom}–${s.timeTo}`;
    groups.set(key, [...(groups.get(key) ?? []), s.day]);
  }
  return [...groups].map(([time, days]) => `${days.join(', ')} · ${time}`).join('\n');
}

/** Неотрицательное целое из поля ввода или `null`. */
function toInt(value: string): number | null {
  const trimmed = value.trim();
  return /^\d+$/.test(trimmed) ? Number(trimmed) : null;
}

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
          <div class="hero__avatar">{{ (user.name || user.email)[0].toUpperCase() }}</div>
          <div class="hero__info">
            <p class="hero__name">{{ user.name }}</p>
            <p class="hero__email">{{ user.email }}</p>
          </div>
        </div>
        <div class="hero__stats">
          <div class="hero__stat">
            <span class="hero__stat-num">{{ stats().enrollments }}</span>
            <span class="hero__stat-label">записей</span>
          </div>
          <div class="hero__stat">
            <span class="hero__stat-num">{{ stats().activities }}</span>
            <span class="hero__stat-label">активностей</span>
          </div>
          <div class="hero__stat">
            <span class="hero__stat-num">{{ stats().children }}</span>
            <span class="hero__stat-label">детей</span>
          </div>
        </div>
      </div>

      <div class="tabs">
        <button class="tab" [class.tab--active]="tab() === 'enrollments'" (click)="tab.set('enrollments')">
          Мои записи <span class="tab__count">{{ stats().enrollments }}</span>
        </button>
        <button class="tab" [class.tab--active]="tab() === 'activities'" (click)="tab.set('activities')">
          Активности <span class="tab__count">{{ stats().activities }}</span>
        </button>
        <button class="tab" [class.tab--active]="tab() === 'children'" (click)="tab.set('children')">
          Дети <span class="tab__count">{{ stats().children }}</span>
        </button>
      </div>

      <div class="content">

        <!-- ── Мои записи ── -->
        @if (tab() === 'enrollments') {
          @if (enrollmentsService.status() === 'loading' && enrollments().length === 0) {
            <div class="state"><div class="spinner"></div></div>
          } @else if (enrollmentsService.status() === 'error') {
            <div class="state">
              <span>{{ enrollmentsService.error() }}</span>
              <button class="state__retry" (click)="enrollmentsService.loadMine(true)">Повторить</button>
            </div>
          } @else {
            <div class="view-toggle">
              <button class="vtog" [class.vtog--active]="enrollView() === 'list'" (click)="enrollView.set('list')">Список</button>
              <button class="vtog" [class.vtog--active]="enrollView() === 'calendar'" (click)="enrollView.set('calendar')">Календарь</button>
            </div>

            @if (enrollView() === 'calendar') {
              <div class="cal">
                <div class="cal__month">{{ calendarDays().monthLabel }}</div>
                <div class="cal__grid">
                  @for (h of weekDays; track h) { <div class="cal__head">{{ h }}</div> }
                  @for (day of calendarDays().days; track $index) {
                    <div class="cal__day" [class.cal__day--today]="day.date === today">
                      @if (day.date) {
                        <span class="cal__num">{{ day.date }}</span>
                        @if (day.dots.length > 0) {
                          <div class="cal__dots">
                            @for (dot of day.dots; track $index) {
                              <span class="cal__dot" [class.cal__dot--pending]="dot.pending" [style.background]="dot.color"></span>
                            }
                          </div>
                        }
                      }
                    </div>
                  }
                </div>
                <div class="cal__legend">
                  @for (e of calendarEnrollments(); track e.id) {
                    <div class="cal__legend-item">
                      <span class="cal__dot" [class.cal__dot--pending]="e.status === 'pending'" [style.background]="e.color"></span>
                      <span>{{ e.clubName }}{{ e.status === 'pending' ? ' · на рассмотрении' : '' }}</span>
                    </div>
                  }
                  @if (calendarEnrollments().length === 0) {
                    <p class="ie-empty">Подтверждённых записей пока нет</p>
                  }
                </div>
              </div>
            }

            @if (enrollView() === 'list') {
              @if (children().length > 0) {
                <div class="child-filter">
                  <button class="filter-pill" [class.filter-pill--active]="childFilter() === null" (click)="childFilter.set(null)">Все</button>
                  <button class="filter-pill" [class.filter-pill--active]="childFilter() === 'self'" (click)="childFilter.set('self')">Я</button>
                  @for (child of children(); track child.id) {
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
                  <div class="enrollment-card" [class.enrollment-card--muted]="e.status === 'rejected'">
                    <div class="enrollment-card__accent">🎯</div>
                    <div class="enrollment-card__body">
                      <p class="enrollment-card__name">{{ e.clubName }}</p>
                      <p class="enrollment-card__meta">{{ e.branchName }} · {{ e.schedule }}</p>
                      <div class="enrollment-card__tags">
                        <span [class]="'status-badge status-badge--' + e.status">{{ enrollmentStatusLabel[e.status] }}</span>
                        @if (e.forChild) {
                          <span class="etag etag--child">{{ e.forChild.gender === 'male' ? '👦' : '👧' }} {{ e.forChild.firstName }}</span>
                        } @else {
                          <span class="etag">Для себя</span>
                        }
                        <span class="etag etag--date">с {{ e.enrolledAt }}</span>
                      </div>
                      @if (e.status === 'rejected' && e.rejectReason) {
                        <p class="note">Причина: {{ e.rejectReason }}</p>
                      }
                      @if (itemErrors()[e.id]; as msg) { <p class="inline-error item-error">{{ msg }}</p> }
                    </div>
                    @if (canCancel(e)) {
                      <button class="enrollment-card__cancel" title="Отменить запись"
                        [disabled]="busyId() === e.id" (click)="cancelEnrollment(e)">✕</button>
                    }
                  </div>
                }
              }
            }
          }
        }

        <!-- ── Активности ── -->
        @if (tab() === 'activities') {

          <!-- Детальный просмотр активности -->
          @if (selectedActivity(); as act) {
            <button class="back-btn" (click)="selectActivity(null)">← Все активности</button>

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
              <div class="act-detail__status">
                <span [class]="'status-badge status-badge--' + act.status">{{ activityStatusLabel[act.status] }}</span>
                @if (act.clubId) {
                  <a class="act-detail__link" [routerLink]="['/club', act.clubId]">Открыть в каталоге →</a>
                }
              </div>
              @if (act.status === 'rejected' && act.moderationComment) {
                <p class="note">Комментарий модератора: {{ act.moderationComment }}</p>
              }
              <p class="act-detail__meta">
                {{ act.ageMin }}–{{ act.ageMax }} лет · {{ visitTypeLabel[act.visitType] }} · {{ priceLabel(act) }}
              </p>
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
                @if (branch.phone) {
                  <div class="branch-block__sched">📞 {{ branch.phone }}</div>
                }
                <div class="branch-block__sched">{{ branchSchedule(branch) }}</div>
              </div>
            }

            <p class="section-title incoming-title">Входящие заявки</p>
            @if (act.status !== 'approved') {
              <p class="ie-empty">Заявки появятся после публикации активности</p>
            } @else if (incomingGroups().length === 0) {
              <p class="ie-empty">Пока нет заявок</p>
            }
            @for (group of incomingGroups(); track group.branchId) {
              <div class="branch-block">
                <div class="branch-block__head">
                  <span class="branch-block__name">📍 {{ group.branchName }}</span>
                </div>
                <div class="branch-block__enrolls">
                  @for (ie of group.items; track ie.id) {
                    <div class="ie-row">
                      <span class="ie-row__name">{{ ie.personName }}</span>
                      @if (ie.childName) { <span class="ie-row__child">👶 {{ ie.childName }}</span> }
                      <span class="ie-row__date">{{ ie.enrolledAt }}</span>
                      <span [class]="'status-badge status-badge--' + ie.status">{{ enrollmentStatusLabel[ie.status] }}</span>
                    </div>
                    @if (ie.status === 'pending') {
                      @if (rejectingId() === ie.id) {
                        <div class="ie-actions">
                          <input class="field__input" type="text" placeholder="Причина отказа (необязательно)"
                            [value]="rejectReason()" (input)="rejectReason.set($any($event.target).value)" />
                          <button class="ie-btn ie-btn--danger" [disabled]="busyId() === ie.id" (click)="submitReject(ie)">Отклонить</button>
                          <button class="ie-btn" [disabled]="busyId() === ie.id" (click)="rejectingId.set(null)">Отмена</button>
                        </div>
                      } @else {
                        <div class="ie-actions">
                          <button class="ie-btn ie-btn--ok" [disabled]="busyId() === ie.id" (click)="confirmIncoming(ie)">Подтвердить</button>
                          <button class="ie-btn" [disabled]="busyId() === ie.id" (click)="startReject(ie)">Отклонить</button>
                        </div>
                      }
                    }
                    @if (itemErrors()[ie.id]; as msg) { <p class="inline-error">{{ msg }}</p> }
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
                    <input class="field__input" type="text" placeholder="Название активности" maxlength="100"
                      [value]="newName()" (input)="newName.set($any($event.target).value)" />
                    @if (activityErrors()['name']; as msg) { <span class="field__error">{{ msg }}</span> }
                  </div>
                  <div class="field field--narrow">
                    <label class="field__label">Эмодзи</label>
                    <input class="field__input" type="text" placeholder="🎯"
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
                  @if (activityErrors()['category']; as msg) { <span class="field__error">{{ msg }}</span> }
                </div>

                <div class="field">
                  <label class="field__label">Описание</label>
                  <textarea class="field__input field__textarea" placeholder="Расскажите об активности..."
                    [value]="newDescription()" (input)="newDescription.set($any($event.target).value)"></textarea>
                  @if (activityErrors()['description']; as msg) { <span class="field__error">{{ msg }}</span> }
                </div>

                <div class="form-row">
                  <div class="field">
                    <label class="field__label">Возраст от</label>
                    <input class="field__input field__input--no-spin" type="number" min="0" max="99" placeholder="5"
                      [value]="newAgeMin()" (input)="newAgeMin.set($any($event.target).value)" />
                    @if (activityErrors()['ageMin']; as msg) { <span class="field__error">{{ msg }}</span> }
                  </div>
                  <div class="field">
                    <label class="field__label">Возраст до</label>
                    <input class="field__input field__input--no-spin" type="number" min="0" max="99" placeholder="14"
                      [value]="newAgeMax()" (input)="newAgeMax.set($any($event.target).value)" />
                    @if (activityErrors()['ageMax']; as msg) { <span class="field__error">{{ msg }}</span> }
                  </div>
                </div>

                <div class="field">
                  <label class="field__label">Формат</label>
                  <select class="field__input field__select"
                    [value]="newVisitType()" (change)="newVisitType.set($any($event.target).value)">
                    @for (vt of visitTypes; track vt) {
                      <option [value]="vt">{{ visitTypeLabel[vt] }}</option>
                    }
                  </select>
                  @if (activityErrors()['visitType']; as msg) { <span class="field__error">{{ msg }}</span> }
                </div>

                <div class="field">
                  <label class="field__label">Стоимость</label>
                  <div class="gender-row">
                    @for (pt of priceTypes; track pt) {
                      <button class="filter-pill" [class.filter-pill--active]="newPriceType() === pt"
                        (click)="newPriceType.set(pt)">{{ priceTypeLabel[pt] }}</button>
                    }
                  </div>
                  @if (newPriceType() !== 'free') {
                    <input class="field__input field__input--no-spin" type="number" min="0" placeholder="Сумма, ₽ в месяц"
                      [value]="newPriceAmount()" (input)="newPriceAmount.set($any($event.target).value)" />
                  }
                  @if (activityErrors()['priceAmount'] || activityErrors()['priceType']; as msg) {
                    <span class="field__error">{{ msg }}</span>
                  }
                </div>

                <div class="field">
                  <label class="field__label">Теги (через запятую)</label>
                  <input class="field__input" type="text" placeholder="рисование, акварель"
                    [value]="newTags()" (input)="newTags.set($any($event.target).value)" />
                  @if (activityErrors()['tags']; as msg) { <span class="field__error">{{ msg }}</span> }
                </div>

                <!-- Филиал -->
                <div class="branch-form">
                  <p class="branch-form__title">Филиал</p>
                  <div class="field">
                    <label class="field__label">Название филиала</label>
                    <input class="field__input" type="text" placeholder="Основной"
                      [value]="branchName()" (input)="branchName.set($any($event.target).value)" />
                    @if (activityErrors()['branches[0].name']; as msg) { <span class="field__error">{{ msg }}</span> }
                  </div>
                  <div class="field">
                    <label class="field__label">Адрес</label>
                    <input class="field__input" type="text" placeholder="ул. Примерная, 1"
                      [value]="branchAddress()" (input)="branchAddress.set($any($event.target).value)" />
                    @if (activityErrors()['branches[0].address']; as msg) { <span class="field__error">{{ msg }}</span> }
                  </div>
                  <div class="field">
                    <label class="field__label">Телефон</label>
                    <input class="field__input" type="tel" placeholder="+7 423 000-00-00"
                      [value]="branchPhone()" (input)="branchPhone.set($any($event.target).value)" />
                    @if (activityErrors()['branches[0].phone']; as msg) { <span class="field__error">{{ msg }}</span> }
                  </div>

                  <span class="field__label">Расписание</span>
                  @for (group of slotGroups(); track $index; let i = $index) {
                    <div class="slot-group">
                      <div class="day-picker">
                        @for (d of weekDays; track d) {
                          <button class="day-btn" [class.day-btn--active]="group.days.includes(d)"
                            (click)="toggleGroupDay(i, d)">{{ d }}</button>
                        }
                      </div>
                      <div class="form-row">
                        <div class="field">
                          <label class="field__label">Начало</label>
                          <input class="field__input" type="time" [value]="group.timeFrom"
                            (change)="setGroupTime(i, 'timeFrom', $any($event.target).value)" />
                        </div>
                        <div class="field">
                          <label class="field__label">Конец</label>
                          <input class="field__input" type="time" [value]="group.timeTo"
                            (change)="setGroupTime(i, 'timeTo', $any($event.target).value)" />
                        </div>
                      </div>
                      @if (slotGroups().length > 1) {
                        <button class="slot-group__remove" title="Убрать время" (click)="removeSlotGroup(i)">✕</button>
                      }
                    </div>
                  }
                  <button class="btn-link" (click)="addSlotGroup()">+ Добавить другое время</button>
                  @if (activityErrors()[slotsKey]; as msg) { <span class="field__error">{{ msg }}</span> }
                </div>

                @if (activityFormError()) {
                  <p class="inline-error">{{ activityFormError() }}</p>
                }

                <div class="form-actions">
                  <button class="btn-primary" [disabled]="creating()" (click)="createActivity()">
                    {{ creating() ? 'Отправляем…' : 'Отправить на модерацию' }}
                  </button>
                  <button class="btn-ghost" [disabled]="creating()" (click)="showCreateForm.set(false)">Отмена</button>
                </div>
              </div>
            } @else {
              @if (activitiesService.status() === 'loading' && activities().length === 0) {
                <div class="state"><div class="spinner"></div></div>
              } @else if (activitiesService.status() === 'error') {
                <div class="state">
                  <span>{{ activitiesService.error() }}</span>
                  <button class="state__retry" (click)="activitiesService.loadMine(true)">Повторить</button>
                </div>
              } @else {
                @for (a of activities(); track a.id) {
                  <div class="activity-card" (click)="selectActivity(a)">
                    <div class="activity-card__icon">{{ a.emoji }}</div>
                    <div>
                      <p class="activity-card__name">{{ a.name }}</p>
                      <p class="activity-card__category">{{ a.category }}</p>
                      <span [class]="'status-badge status-badge--' + a.status">{{ activityStatusLabel[a.status] }}</span>
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
                @if (activities().length === 0) {
                  <div class="empty">
                    <div class="empty__icon">🎯</div>
                    <div class="empty__title">Нет активностей</div>
                    <div class="empty__sub">Создайте свою секцию или кружок</div>
                  </div>
                }
                <button class="btn-create" (click)="openCreateForm()">+ Создать активность</button>
              }
            }
          }
        }

        <!-- ── Дети ── -->
        @if (tab() === 'children') {
          @if (childrenService.status() === 'loading' && children().length === 0) {
            <div class="state"><div class="spinner"></div></div>
          } @else if (childrenService.status() === 'error') {
            <div class="state">
              <span>{{ childrenService.error() }}</span>
              <button class="state__retry" (click)="childrenService.load(true)">Повторить</button>
            </div>
          }

          @for (child of children(); track child.id) {
            <div class="child-card">
              <div class="child-card__avatar">{{ child.gender === 'male' ? '👦' : '👧' }}</div>
              <div>
                <p class="child-card__name">{{ child.firstName }} {{ child.lastName }}</p>
                <p class="child-card__age">{{ child.age }} лет</p>
                @if (itemErrors()[child.id]; as msg) { <p class="inline-error item-error">{{ msg }}</p> }
              </div>
              <button class="child-card__remove" title="Удалить" [disabled]="busyId() === child.id"
                (click)="removeChild(child.id)">✕</button>
            </div>
          }

          <div class="add-form">
            <p class="form-title">Добавить ребёнка</p>
            <div class="form-row">
              <div class="field">
                <label class="field__label">Имя</label>
                <input class="field__input" type="text" placeholder="Артём" maxlength="50"
                  [value]="childFirstName()" (input)="childFirstName.set($any($event.target).value)" />
                @if (childErrors()['firstName']; as msg) { <span class="field__error">{{ msg }}</span> }
              </div>
              <div class="field">
                <label class="field__label">Фамилия</label>
                <input class="field__input" type="text" placeholder="Иванов" maxlength="50"
                  [value]="childLastName()" (input)="childLastName.set($any($event.target).value)" />
                @if (childErrors()['lastName']; as msg) { <span class="field__error">{{ msg }}</span> }
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
            @if (childErrors()['age'] || childErrors()['gender']; as msg) { <span class="field__error">{{ msg }}</span> }
            @if (childFormError()) { <p class="inline-error">{{ childFormError() }}</p> }
            <button class="btn-add-child" [disabled]="addingChild()" (click)="addChild()">
              {{ addingChild() ? 'Добавляем…' : 'Добавить' }}
            </button>
          </div>
        }

      </div>
    }
  `,
})
export class PersonalDashboardComponent {
  private readonly authService = inject(AuthService);
  private readonly router      = inject(Router);

  readonly enrollmentsService = inject(EnrollmentsService);
  readonly childrenService    = inject(ChildrenService);
  readonly activitiesService  = inject(ActivitiesService);

  readonly user        = this.authService.user;
  readonly enrollments = this.enrollmentsService.mine;
  readonly children    = this.childrenService.children;
  readonly activities  = this.activitiesService.mine;

  readonly tab         = signal<Tab>('enrollments');
  readonly enrollView  = signal<EnrollView>('list');
  readonly childFilter = signal<string | null>(null);
  readonly selectedActivityId = signal<string | null>(null);
  readonly showCreateForm = signal(false);

  readonly weekDays   = WEEK_DAYS;
  readonly categories = CATEGORIES;
  readonly visitTypes = VISIT_TYPES;
  readonly priceTypes = PRICE_TYPES;
  readonly visitTypeLabel = VISIT_TYPE_LABEL;
  readonly priceTypeLabel = PRICE_TYPE_LABEL;
  readonly enrollmentStatusLabel = ENROLLMENT_STATUS_LABEL;
  readonly activityStatusLabel   = ACTIVITY_STATUS_LABEL;
  readonly slotsKey = SLOTS_KEY;
  readonly today    = new Date().getDate();

  /** id элемента (заявки, входящей заявки, ребёнка), по которому идёт запрос. */
  readonly busyId     = signal<string | null>(null);
  /** Ошибки действий над конкретными элементами, по id. */
  readonly itemErrors = signal<Record<string, string>>({});

  // отклонение входящей заявки
  readonly rejectingId  = signal<string | null>(null);
  readonly rejectReason = signal('');

  // форма создания активности
  readonly newName        = signal('');
  readonly newCategory    = signal<ClubCategory | ''>('');
  readonly newEmoji       = signal('');
  readonly newDescription = signal('');
  readonly newAgeMin      = signal('');
  readonly newAgeMax      = signal('');
  readonly newVisitType   = signal<VisitType>('recurring');
  readonly newPriceType   = signal<PriceType>('free');
  readonly newPriceAmount = signal('');
  readonly newTags        = signal('');
  readonly branchName     = signal('Основной');
  readonly branchAddress  = signal('');
  readonly branchPhone    = signal('');
  readonly slotGroups     = signal<SlotGroup[]>([{ ...EMPTY_SLOT_GROUP }]);
  readonly activityErrors    = signal<Record<string, string>>({});
  readonly activityFormError = signal('');
  readonly creating          = signal(false);

  // форма добавления ребёнка
  readonly childFirstName = signal('');
  readonly childLastName  = signal('');
  readonly childAge       = signal(0);
  readonly childGender    = signal<Gender>('male');
  readonly childErrors    = signal<Record<string, string>>({});
  readonly childFormError = signal('');
  readonly addingChild    = signal(false);

  /** Счётчики: по загруженным спискам, пока их нет — из `user.stats` (`GET /api/me`). */
  readonly stats = computed(() => {
    const s = this.user()?.stats;
    return {
      enrollments: this.enrollmentsService.status() === 'loaded'
        ? this.enrollments().filter(isActiveEnrollment).length : s?.enrollments ?? 0,
      activities: this.activitiesService.status() === 'loaded' ? this.activities().length : s?.activities ?? 0,
      children: this.childrenService.status() === 'loaded' ? this.children().length : s?.children ?? 0,
    };
  });

  readonly filteredEnrollments = computed(() => {
    const list = this.enrollments();
    const f = this.childFilter();
    if (f === null) return list;
    if (f === 'self') return list.filter(e => !e.forChild);
    return list.filter(e => e.forChild?.id === f);
  });

  /** В календаре — подтверждённые записи; заявки на рассмотрении показываются бледнее. */
  readonly calendarEnrollments = computed(() =>
    this.enrollments().filter(e => e.status === 'confirmed' || e.status === 'pending'),
  );

  readonly calendarDays = computed(() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startOffset = (firstDay.getDay() + 6) % 7;
    const enrollments = this.calendarEnrollments();
    const days: Array<{ date: number | null; dots: CalendarDot[] }> = [];
    for (let i = 0; i < startOffset; i++) days.push({ date: null, dots: [] });
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const weekday = DAY_NAMES[new Date(year, month, d).getDay()];
      const dots = enrollments
        .filter(e => e.days.includes(weekday))
        .map(e => ({ color: e.color, pending: e.status === 'pending' }));
      days.push({ date: d, dots });
    }
    return { days, monthLabel: firstDay.toLocaleString('ru', { month: 'long', year: 'numeric' }) };
  });

  readonly selectedActivity = computed(() => {
    const id = this.selectedActivityId();
    return id ? this.activities().find(a => a.id === id) ?? null : null;
  });

  /** Входящие заявки выбранной активности, сгруппированные по филиалу кружка (`branchId`). */
  readonly incomingGroups = computed<IncomingGroup[]>(() => {
    const act = this.selectedActivity();
    if (!act) return [];
    const groups = new Map<string, IncomingGroup>();
    for (const ie of act.enrollments) {
      const key = ie.branchId ?? '';
      const group = groups.get(key) ?? { branchId: key, branchName: ie.branchName, items: [] };
      group.items.push(ie);
      groups.set(key, group);
    }
    return [...groups.values()];
  });

  constructor() {
    // свежие данные при каждом входе в ЛК: статусы заявок меняются на стороне организатора
    this.authService.reloadUser().catch(() => undefined);
    this.enrollmentsService.loadMine(true);
    this.childrenService.load(true);
    this.activitiesService.loadMine(true);
  }

  async logout(): Promise<void> {
    await this.authService.logout();
    await this.router.navigate(['/']);
  }

  // ─── Записи ────────────────────────────────────────────────────────────────

  canCancel(e: Enrollment): boolean {
    return isActiveEnrollment(e);
  }

  cancelEnrollment(e: Enrollment): void {
    if (this.busyId()) return;
    this.startItem(e.id);
    this.enrollmentsService.cancel(e.id).subscribe({
      next: () => this.busyId.set(null),
      error: (err: unknown) => {
        const apiError = toApiError(err, 'Не удалось отменить запись');
        if (apiError.status === 409) {
          this.failItem(e.id, 'Эту заявку уже нельзя отменить');
          this.enrollmentsService.loadMine(true);
        } else {
          this.failItem(e.id, apiError.message);
        }
      },
    });
  }

  // ─── Активности ────────────────────────────────────────────────────────────

  selectActivity(a: Activity | null): void {
    this.selectedActivityId.set(a?.id ?? null);
    this.rejectingId.set(null);
  }

  branchSchedule(branch: ActivityBranch): string {
    if (branch.slots?.length) return formatSlots(branch.slots);
    return `${branch.days.join(', ')} · ${branch.timeFrom}–${branch.timeTo}`;
  }

  /** У активности нет серверного поля `price`, поэтому подпись цены собираю здесь. */
  priceLabel(a: Activity): string {
    if (a.priceType === 'free') return 'Бесплатно';
    const amount = `${String(a.priceAmount).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')} ₽ / мес`;
    return a.priceType === 'pushkin' ? `${amount} · Пушкинская карта` : amount;
  }

  confirmIncoming(ie: IncomingEnrollment): void {
    if (this.busyId()) return;
    this.startItem(ie.id);
    this.enrollmentsService.confirm(ie.id).subscribe({
      next: res => {
        this.busyId.set(null);
        this.activitiesService.setIncomingStatus(ie.id, res?.status ?? 'confirmed');
      },
      error: (err: unknown) => this.failIncoming(ie.id, err),
    });
  }

  startReject(ie: IncomingEnrollment): void {
    this.rejectingId.set(ie.id);
    this.rejectReason.set('');
  }

  submitReject(ie: IncomingEnrollment): void {
    if (this.busyId()) return;
    this.startItem(ie.id);
    this.enrollmentsService.reject(ie.id, this.rejectReason().trim() || undefined).subscribe({
      next: res => {
        this.busyId.set(null);
        this.rejectingId.set(null);
        this.activitiesService.setIncomingStatus(ie.id, res?.status ?? 'rejected');
      },
      error: (err: unknown) => this.failIncoming(ie.id, err),
    });
  }

  openCreateForm(): void {
    this.activityErrors.set({});
    this.activityFormError.set('');
    this.showCreateForm.set(true);
  }

  toggleGroupDay(index: number, day: DayOfWeek): void {
    this.updateGroup(index, g => ({
      ...g,
      days: g.days.includes(day) ? g.days.filter(d => d !== day) : [...g.days, day],
    }));
  }

  setGroupTime(index: number, field: 'timeFrom' | 'timeTo', value: string): void {
    this.updateGroup(index, g => (field === 'timeFrom' ? { ...g, timeFrom: value } : { ...g, timeTo: value }));
  }

  addSlotGroup(): void {
    this.slotGroups.update(groups => [...groups, { ...EMPTY_SLOT_GROUP }]);
  }

  removeSlotGroup(index: number): void {
    this.slotGroups.update(groups => groups.filter((_, i) => i !== index));
  }

  createActivity(): void {
    if (this.creating()) return;
    const payload = this.buildActivityPayload();
    if (!payload) return;

    this.creating.set(true);
    this.activitiesService.create(payload).subscribe({
      next: () => {
        this.creating.set(false);
        this.resetActivityForm();
        this.showCreateForm.set(false);
      },
      error: (err: unknown) => {
        this.creating.set(false);
        this.applyActivityErrors(toApiError(err, 'Не удалось создать активность'));
      },
    });
  }

  // ─── Дети ──────────────────────────────────────────────────────────────────

  addChild(): void {
    if (this.addingChild()) return;
    const firstName = this.childFirstName().trim();
    const lastName = this.childLastName().trim();
    const age = this.childAge();
    const errors: Record<string, string> = {};
    if (!firstName) errors['firstName'] = 'Укажите имя';
    if (!Number.isInteger(age) || age < 1 || age > 18) errors['age'] = 'Возраст — от 1 до 18 лет';
    this.childErrors.set(errors);
    this.childFormError.set('');
    if (Object.keys(errors).length > 0) return;

    const payload: CreateChildPayload = { firstName, age, gender: this.childGender() };
    if (lastName) payload.lastName = lastName;

    this.addingChild.set(true);
    this.childrenService.create(payload).subscribe({
      next: () => {
        this.addingChild.set(false);
        this.childFirstName.set(''); this.childLastName.set(''); this.childAge.set(0);
      },
      error: (err: unknown) => {
        this.addingChild.set(false);
        const apiError = toApiError(err, 'Не удалось добавить ребёнка');
        this.childErrors.set(apiError.fieldErrors);
        this.childFormError.set(formErrorMessage(apiError, CHILD_FIELDS));
      },
    });
  }

  removeChild(id: string): void {
    if (this.busyId()) return;
    this.startItem(id);
    this.childrenService.remove(id).subscribe({
      next: () => this.busyId.set(null),
      error: (err: unknown) => {
        const apiError = toApiError(err, 'Не удалось удалить');
        this.failItem(id, apiError.status === 409 ? 'Сначала отмените записи ребёнка' : apiError.message);
      },
    });
  }

  // ─── Вспомогательное ───────────────────────────────────────────────────────

  private startItem(id: string): void {
    this.busyId.set(id);
    this.itemErrors.update(errors => {
      const rest = { ...errors };
      delete rest[id];
      return rest;
    });
  }

  private failItem(id: string, message: string): void {
    this.busyId.set(null);
    this.itemErrors.update(errors => ({ ...errors, [id]: message }));
  }

  private failIncoming(id: string, err: unknown): void {
    const apiError = toApiError(err, 'Не удалось изменить статус заявки');
    if (apiError.status === 409) {
      this.failItem(id, 'Статус заявки уже изменился');
      this.activitiesService.loadMine(true);
    } else if (apiError.status === 403) {
      this.failItem(id, 'Нет прав на изменение этой заявки');
    } else {
      this.failItem(id, apiError.message);
    }
  }

  private updateGroup(index: number, fn: (g: SlotGroup) => SlotGroup): void {
    this.slotGroups.update(groups => groups.map((g, i) => (i === index ? fn(g) : g)));
  }

  /** Клиентская валидация по контракту; при ошибках возвращает `null` и показывает их в форме. */
  private buildActivityPayload(): CreateActivityPayload | null {
    const errors: Record<string, string> = {};
    const name = this.newName().trim();
    const category = this.newCategory();
    const ageMin = toInt(this.newAgeMin());
    const ageMax = toInt(this.newAgeMax());
    const priceType = this.newPriceType();
    const priceAmount = priceType === 'free' ? 0 : toInt(this.newPriceAmount());
    const address = this.branchAddress().trim();

    if (name.length < 2 || name.length > 100) errors['name'] = 'Название — от 2 до 100 символов';
    if (!category) errors['category'] = 'Выберите категорию';
    if (ageMin === null || ageMin > 99) errors['ageMin'] = 'Укажите возраст от 0 до 99';
    if (ageMax === null || ageMax > 99) errors['ageMax'] = 'Укажите возраст от 0 до 99';
    else if (ageMin !== null && ageMin > ageMax) errors['ageMax'] = 'Не меньше, чем «возраст от»';
    if (priceAmount === null) errors['priceAmount'] = 'Укажите сумму в рублях';
    if (!address) errors['branches[0].address'] = 'Укажите адрес';

    const groups = this.slotGroups().filter(g => g.days.length > 0);
    if (groups.length === 0) errors[SLOTS_KEY] = 'Выберите хотя бы один день занятий';
    else if (groups.some(g => !g.timeFrom || !g.timeTo || g.timeFrom >= g.timeTo)) {
      errors[SLOTS_KEY] = 'Время начала должно быть раньше окончания';
    }

    this.activityErrors.set(errors);
    this.activityFormError.set('');
    if (Object.keys(errors).length > 0) return null;

    const slots: ScheduleSlot[] = groups.flatMap(g =>
      [...g.days]
        .sort((a, b) => WEEK_DAYS.indexOf(a) - WEEK_DAYS.indexOf(b))
        .map(day => ({ day, timeFrom: g.timeFrom, timeTo: g.timeTo })),
    );
    const tags = [...new Set(this.newTags().split(',').map(t => t.trim()).filter(Boolean))];

    return {
      name,
      category: category as ClubCategory,
      emoji: this.newEmoji().trim() || '🎯',
      description: this.newDescription().trim(),
      ageMin,
      ageMax,
      visitType: this.newVisitType(),
      priceType,
      priceAmount,
      tags,
      branches: [{
        name: this.branchName().trim() || 'Основной',
        address,
        phone: this.branchPhone().trim(),
        slots,
      }],
    };
  }

  private applyActivityErrors(apiError: ApiError): void {
    const fieldErrors = { ...apiError.fieldErrors };
    const slotViolations = apiError.violations.filter(v => v.propertyPath.startsWith(SLOTS_KEY));
    if (slotViolations.length > 0) fieldErrors[SLOTS_KEY] = slotViolations[0].message;
    this.activityErrors.set(fieldErrors);
    this.activityFormError.set(formErrorMessage(apiError, [
      ...ACTIVITY_FIELDS, ...slotViolations.map(v => v.propertyPath),
    ]));
  }

  private resetActivityForm(): void {
    this.newName.set(''); this.newCategory.set(''); this.newEmoji.set(''); this.newDescription.set('');
    this.newAgeMin.set(''); this.newAgeMax.set(''); this.newVisitType.set('recurring');
    this.newPriceType.set('free'); this.newPriceAmount.set(''); this.newTags.set('');
    this.branchName.set('Основной'); this.branchAddress.set(''); this.branchPhone.set('');
    this.slotGroups.set([{ ...EMPTY_SLOT_GROUP }]);
    this.activityErrors.set({});
    this.activityFormError.set('');
  }
}
