import { NgTemplateOutlet } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import { toApiError } from '../api/api-error';
import { ACTIVITY_STATUS_LABEL, Activity, IncomingEnrollment } from '../models/activity.model';
import { DayOfWeek, VISIT_TYPE_LABEL, WEEK_DAYS } from '../models/club.model';
import { ENROLLMENT_STATUS_LABEL, Enrollment, isActiveEnrollment } from '../models/enrollment.model';
import { Child } from '../models/user.model';
import { ActivitiesService } from '../services/activities.service';
import { AuthService } from '../services/auth.service';
import { ChildrenService } from '../services/children.service';
import { EnrollmentsService } from '../services/enrollments.service';
import { RemoteList } from '../services/remote-list';
import { formatDate, yearsLabel } from '../utils/format';
import { ActivityFormComponent } from './activity-form.component';
import { ChildFormComponent } from './child-form.component';

type Tab = 'enrollments' | 'activities' | 'children';
type EnrollView = 'list' | 'calendar';

interface CalendarEvent {
  who: string;
  label: string;
  title: string;
  color: string;
  pending: boolean;
}

const MAX_DAY_EVENTS = 2;

interface IncomingByBranch {
  byBranch: Record<string, IncomingEnrollment[]>;
  other: IncomingEnrollment[];
}

/** Индекс — `Date.getDay()`. */
const DAY_NAMES: DayOfWeek[] = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

@Component({
  selector: 'act-personal-dashboard',
  imports: [RouterLink, NgTemplateOutlet, ActivityFormComponent, ChildFormComponent],
  styleUrl: './personal-dashboard.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (user(); as user) {
      <header class="header">
        <a class="header__logo" routerLink="/">Заодно</a>
        <div class="header__actions">
          @if (isAdmin()) {
            <a class="header__link" routerLink="/admin">Модерация</a>
          }
          <button class="header__logout" (click)="logout()">Выйти</button>
        </div>
      </header>

      <div class="hero">
        <div class="hero__top">
          <div class="hero__avatar">{{ (user.name || user.email)[0].toUpperCase() }}</div>
          <div class="hero__info">
            <p class="hero__name">{{ user.name || 'Без имени' }}</p>
            <p class="hero__email">{{ user.email }}</p>
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

        @if (tab() === 'enrollments') {
          @if (enrollments.status() === 'loading' && enrollments.items().length === 0) {
            <div class="state"><div class="spinner"></div></div>
          } @else if (enrollments.status() === 'error') {
            <div class="state">
              <span>{{ enrollments.error() }}</span>
              <button class="state__retry" (click)="enrollments.load(true)">Повторить</button>
            </div>
          } @else {
            <div class="view-toggle">
              <button class="vtog" [class.vtog--active]="enrollView() === 'list'" (click)="enrollView.set('list')">Список</button>
              <button class="vtog" [class.vtog--active]="enrollView() === 'calendar'" (click)="enrollView.set('calendar')">Календарь</button>
            </div>

            @if (enrollView() === 'calendar') {
              <div class="cal">
                <div class="cal__nav">
                  <button class="cal__arrow" aria-label="Предыдущий месяц" (click)="monthOffset.update(m => m - 1)">‹</button>
                  <span class="cal__month">{{ calendarDays().monthLabel }}</span>
                  <button class="cal__arrow" aria-label="Следующий месяц" (click)="monthOffset.update(m => m + 1)">›</button>
                  @if (monthOffset() !== 0) {
                    <button class="btn-link cal__today" (click)="monthOffset.set(0)">Сегодня</button>
                  }
                </div>
                <div class="cal__grid">
                  @for (h of weekDays; track h) { <div class="cal__head">{{ h }}</div> }
                  @for (day of calendarDays().days; track $index) {
                    <div class="cal__day" [class.cal__day--today]="day.today">
                      @if (day.date) {
                        <span class="cal__num">{{ day.date }}</span>
                        @for (ev of day.events.slice(0, maxDayEvents); track $index) {
                          <span class="cal__event" [class.cal__event--pending]="ev.pending"
                            [style.background]="ev.color" [title]="ev.title"><span class="cal__who">{{ ev.who }}</span>{{ ev.label }}</span>
                        }
                        @if (day.events.length > maxDayEvents) {
                          <span class="cal__more">+{{ day.events.length - maxDayEvents }}</span>
                        }
                      }
                    </div>
                  }
                </div>
                <div class="cal__legend">
                  @for (e of activeEnrollments(); track e.id) {
                    <div class="cal__legend-item">
                      <span class="cal__dot" [class.cal__dot--pending]="e.status === 'pending'" [style.background]="e.color"></span>
                      <span>{{ e.clubName }} — {{ participantName(e) }}{{ e.status === 'pending' ? ' · на рассмотрении' : '' }}</span>
                    </div>
                  }
                  @if (activeEnrollments().length === 0) {
                    <p class="ie-empty">Подтверждённых записей пока нет</p>
                  }
                </div>
              </div>
            }

            @if (enrollView() === 'list') {
              @if (children.items().length > 0) {
                <div class="child-filter">
                  <button class="filter-pill" [class.filter-pill--active]="childFilter() === null" (click)="childFilter.set(null)">Все</button>
                  <button class="filter-pill" [class.filter-pill--active]="childFilter() === 'self'" (click)="childFilter.set('self')">Я</button>
                  @for (child of children.items(); track child.id) {
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
                      <p class="enrollment-card__meta">@if (e.branchName) { {{ e.branchName }} · }{{ e.schedule }}</p>
                      <div class="enrollment-card__tags">
                        <span [class]="'status-badge status-badge--' + e.status">{{ enrollmentStatusLabel[e.status] }}</span>
                        @if (e.forChild) {
                          <span class="etag etag--child">{{ e.forChild.gender === 'male' ? '👦' : '👧' }} {{ e.forChild.firstName }}</span>
                        } @else {
                          <span class="etag">Для себя</span>
                        }
                        <span class="etag etag--date">с {{ formatDate(e.enrolledAt) }}</span>
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

        @if (tab() === 'activities') {

          @if (selectedActivity(); as act) {
            <button class="btn-link back-btn" (click)="selectActivity(null)">← Все активности</button>

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
                {{ act.age }} · {{ visitTypeLabel[act.visitType] }} · {{ act.price }}{{ act.priceType === 'pushkin' ? ' · Пушкинская карта' : '' }}
              </p>
              @if (act.description) {
                <p class="act-detail__desc">{{ act.description }}</p>
              }
            </div>

            <p class="section-title incoming-title">Филиалы и заявки</p>
            @if (act.status !== 'approved') {
              <p class="ie-empty incoming-note">Заявки появятся после публикации активности</p>
            }
            @for (branch of act.branches; track branch.id) {
              <div class="branch-block">
                <div class="branch-block__head">
                  <span class="branch-block__name">📍 {{ branch.name }}</span>
                  <span class="branch-block__addr">{{ branch.address }}</span>
                </div>
                @if (branch.phone) {
                  <div class="branch-block__sched">📞 {{ branch.phone }}</div>
                }
                <div class="branch-block__sched">{{ branch.schedule }}</div>
                @if (act.status === 'approved') {
                  <ng-container
                    *ngTemplateOutlet="incomingList; context: { $implicit: incoming().byBranch[branch.id] ?? [] }" />
                }
              </div>
            }
            @if (incoming().other.length > 0) {
              <div class="branch-block">
                <div class="branch-block__head">
                  <span class="branch-block__name">Прочие</span>
                </div>
                <ng-container *ngTemplateOutlet="incomingList; context: { $implicit: incoming().other, showBranch: true }" />
              </div>
            }

            <ng-template #incomingList let-items let-showBranch="showBranch">
              <div class="branch-block__enrolls">
                @for (ie of $any(items); track ie.id) {
                  <div class="ie-row">
                    <span class="ie-row__name">{{ ie.personName }}</span>
                    @if (ie.childName) { <span class="ie-row__child">👶 {{ ie.childName }}</span> }
                    @if (showBranch && ie.branchName) { <span class="ie-row__child">📍 {{ ie.branchName }}</span> }
                    <span class="ie-row__date">{{ formatDate(ie.enrolledAt) }}</span>
                    <span [class]="'status-badge status-badge--' + ie.status">{{ enrollmentStatusLabel[ie.status] }}</span>
                  </div>
                  @if (ie.status === 'pending') {
                    @if (rejectingId() === ie.id) {
                      <div class="ie-actions">
                        <input class="ie-actions__input" type="text" placeholder="Причина отказа (необязательно)" maxlength="500"
                          [value]="rejectReason()" (input)="rejectReason.set($any($event.target).value)" />
                        <button class="btn-small btn-small--danger" [disabled]="busyId() === ie.id" (click)="submitReject(ie)">Отклонить</button>
                        <button class="btn-small" [disabled]="busyId() === ie.id" (click)="rejectingId.set(null)">Отмена</button>
                      </div>
                    } @else {
                      <div class="ie-actions">
                        <button class="btn-small btn-small--ok" [disabled]="busyId() === ie.id" (click)="confirmIncoming(ie)">Подтвердить</button>
                        <button class="btn-small" [disabled]="busyId() === ie.id" (click)="startReject(ie)">Отклонить</button>
                      </div>
                    }
                  }
                  @if (itemErrors()[ie.id]; as msg) { <p class="inline-error">{{ msg }}</p> }
                } @empty {
                  <p class="ie-empty">Пока нет заявок</p>
                }
              </div>
            </ng-template>
          }

          @if (!selectedActivity()) {
            @if (showCreateForm()) {
              <act-activity-form (created)="showCreateForm.set(false)" (cancelled)="showCreateForm.set(false)" />
            } @else {
              @if (activities.status() === 'loading' && activities.items().length === 0) {
                <div class="state"><div class="spinner"></div></div>
              } @else if (activities.status() === 'error') {
                <div class="state">
                  <span>{{ activities.error() }}</span>
                  <button class="state__retry" (click)="activities.load(true)">Повторить</button>
                </div>
              } @else {
                @for (a of activities.items(); track a.id) {
                  <div class="activity-card" (click)="selectActivity(a)">
                    <div class="activity-card__icon">{{ a.emoji }}</div>
                    <div>
                      <p class="activity-card__name">{{ a.name }}</p>
                      <p class="activity-card__category">{{ a.category }} · {{ a.age }} · {{ a.price }}</p>
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
                @if (activities.items().length === 0) {
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
        }

        @if (tab() === 'children') {
          @if (children.status() === 'loading' && children.items().length === 0) {
            <div class="state"><div class="spinner"></div></div>
          } @else if (children.status() === 'error') {
            <div class="state">
              <span>{{ children.error() }}</span>
              <button class="state__retry" (click)="children.load(true)">Повторить</button>
            </div>
          }

          @for (child of children.items(); track child.id) {
            @if (editingChildId() === child.id) {
              <act-child-form [child]="child" (saved)="onChildSaved($event)" (cancelled)="editingChildId.set(null)" />
            } @else {
              <div class="child-card">
                <div class="child-card__avatar">{{ child.gender === 'male' ? '👦' : '👧' }}</div>
                <div>
                  <p class="child-card__name">{{ child.firstName }} {{ child.lastName }}</p>
                  <p class="child-card__age">{{ child.age }} {{ yearsLabel(child.age) }}</p>
                  @if (itemErrors()[child.id]; as msg) { <p class="inline-error item-error">{{ msg }}</p> }
                </div>
                <button class="child-card__btn child-card__btn--edit" title="Изменить" aria-label="Изменить"
                  [disabled]="busyId() === child.id" (click)="editChild(child.id)">✎</button>
                <button class="child-card__btn" title="Удалить" aria-label="Удалить" [disabled]="busyId() === child.id"
                  (click)="removeChild(child.id)">✕</button>
              </div>
            }
          }

          <act-child-form />

        }

      </div>
    }
  `,
})
export class PersonalDashboardComponent {
  private readonly authService = inject(AuthService);
  private readonly router      = inject(Router);
  private readonly enrollmentsService = inject(EnrollmentsService);
  private readonly childrenService    = inject(ChildrenService);
  private readonly activitiesService  = inject(ActivitiesService);

  readonly user        = this.authService.user;
  readonly isAdmin     = this.authService.isAdmin;
  readonly enrollments = this.enrollmentsService.list;
  readonly children    = this.childrenService.list;
  readonly activities  = this.activitiesService.list;

  readonly tab         = signal<Tab>('enrollments');
  readonly enrollView  = signal<EnrollView>('list');
  readonly childFilter = signal<string | null>(null);
  readonly selectedActivityId = signal<string | null>(null);
  readonly showCreateForm = signal(false);
  readonly editingChildId = signal<string | null>(null);

  readonly weekDays = WEEK_DAYS;
  readonly visitTypeLabel = VISIT_TYPE_LABEL;
  readonly enrollmentStatusLabel = ENROLLMENT_STATUS_LABEL;
  readonly activityStatusLabel   = ACTIVITY_STATUS_LABEL;
  readonly yearsLabel = yearsLabel;
  readonly formatDate = formatDate;
  readonly canCancel  = isActiveEnrollment;
  readonly monthOffset = signal(0);

  /** id заявки или ребёнка, по которому идёт запрос. */
  readonly busyId     = signal<string | null>(null);
  readonly itemErrors = signal<Record<string, string>>({});

  readonly rejectingId  = signal<string | null>(null);
  readonly rejectReason = signal('');

  /** Пока списки не загружены — счётчики из `user.stats`. */
  readonly stats = computed(() => {
    const s = this.user()?.stats;
    const loaded = (list: RemoteList<unknown>) => list.status() === 'loaded';
    return {
      enrollments: loaded(this.enrollments) ? this.activeEnrollments().length : s?.enrollments ?? 0,
      activities: loaded(this.activities) ? this.activities.items().length : s?.activities ?? 0,
      children: loaded(this.children) ? this.children.items().length : s?.children ?? 0,
    };
  });

  readonly filteredEnrollments = computed(() => {
    const list = this.enrollments.items();
    const f = this.childFilter();
    if (f === null) return list;
    if (f === 'self') return list.filter(e => !e.forChild);
    return list.filter(e => e.forChild?.id === f);
  });

  readonly maxDayEvents = MAX_DAY_EVENTS;

  participantName(e: Enrollment): string {
    return e.forChild ? e.forChild.firstName : 'вы';
  }

  readonly activeEnrollments = computed(() => this.enrollments.items().filter(isActiveEnrollment));

  readonly calendarDays = computed(() => {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth() + this.monthOffset(), 1);
    const year = firstDay.getFullYear();
    const month = firstDay.getMonth();
    const lastDay = new Date(year, month + 1, 0);
    const isCurrentMonth = this.monthOffset() === 0;
    const startOffset = (firstDay.getDay() + 6) % 7;
    const enrollments = this.activeEnrollments();
    const days: Array<{ date: number | null; today: boolean; events: CalendarEvent[] }> = [];
    for (let i = 0; i < startOffset; i++) days.push({ date: null, today: false, events: [] });
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const weekday = DAY_NAMES[new Date(year, month, d).getDay()];
      const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      const events = enrollments
        .filter(e => e.days.includes(weekday) && e.enrolledAt <= iso)
        .map(e => ({
          who: e.forChild ? e.forChild.firstName[0] : 'Я',
          label: e.clubName.split(' · ')[0],
          title: `${e.clubName} — ${this.participantName(e)}` + (e.status === 'pending' ? ' (на рассмотрении)' : ''),
          color: e.color,
          pending: e.status === 'pending',
        }));
      days.push({ date: d, today: isCurrentMonth && d === now.getDate(), events });
    }
    return { days, monthLabel: firstDay.toLocaleString('ru', { month: 'long', year: 'numeric' }) };
  });

  readonly selectedActivity = computed(() => {
    const id = this.selectedActivityId();
    return id ? this.activities.items().find(a => a.id === id) ?? null : null;
  });

  /** Заявки с неизвестным филиалом активности попадают в «прочие». */
  readonly incoming = computed<IncomingByBranch>(() => {
    const result: IncomingByBranch = { byBranch: {}, other: [] };
    const act = this.selectedActivity();
    if (!act) return result;
    const branchIds = new Set(act.branches.map(b => b.id));
    for (const ie of act.enrollments) {
      if (ie.activityBranchId && branchIds.has(ie.activityBranchId)) {
        (result.byBranch[ie.activityBranchId] ??= []).push(ie);
      } else {
        result.other.push(ie);
      }
    }
    return result;
  });

  constructor() {
    // статусы заявок меняет организатор — перезагружаем при каждом входе
    this.authService.reloadUser().catch(() => undefined);
    this.enrollments.load(true);
    this.children.load(true);
    this.activities.load(true);
  }

  async logout(): Promise<void> {
    await this.authService.logout();
    await this.router.navigate(['/']);
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
          this.enrollments.load(true);
        } else {
          this.failItem(e.id, apiError.message);
        }
      },
    });
  }

  selectActivity(a: Activity | null): void {
    this.selectedActivityId.set(a?.id ?? null);
    this.rejectingId.set(null);
  }

  confirmIncoming(ie: IncomingEnrollment): void {
    if (this.busyId()) return;
    this.startItem(ie.id);
    this.enrollmentsService.confirm(ie.id).subscribe({
      next: res => {
        this.busyId.set(null);
        this.activitiesService.setIncomingStatus(ie.id, res.status);
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
        this.activitiesService.setIncomingStatus(ie.id, res.status);
      },
      error: (err: unknown) => this.failIncoming(ie.id, err),
    });
  }

  editChild(id: string): void {
    this.clearItemError(id);
    this.editingChildId.set(id);
  }

  onChildSaved(child: Child): void {
    this.editingChildId.set(null);
    this.enrollmentsService.replaceChild(child);
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

  private startItem(id: string): void {
    this.busyId.set(id);
    this.clearItemError(id);
  }

  private clearItemError(id: string): void {
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
      this.activities.load(true);
    } else if (apiError.status === 404) {
      this.failItem(id, 'Заявка больше недоступна');
      this.activities.load(true);
    } else {
      this.failItem(id, apiError.message);
    }
  }
}
