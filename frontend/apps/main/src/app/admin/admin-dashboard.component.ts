import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';

import { LoadStatus } from '../api/api-client';
import { toApiError } from '../api/api-error';
import { ACTIVITY_STATUS_LABEL, ActivityStatus } from '../models/activity.model';
import { AdminActivity, AdminEnrollment, AdminEnrollmentFilter } from '../models/admin.model';
import { DayOfWeek, PriceType, ScheduleSlot, VisitType } from '../models/club.model';
import { ENROLLMENT_STATUS_LABEL, EnrollmentStatus } from '../models/enrollment.model';
import { AdminService } from '../services/admin.service';
import { AuthService } from '../services/auth.service';
import { EnrollmentsService } from '../services/enrollments.service';

type Tab = 'activities' | 'enrollments';
type Target = 'activity' | 'enrollment';

const WEEK_DAYS: DayOfWeek[] = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const ACTIVITY_STATUSES: ActivityStatus[] = ['pending', 'approved', 'rejected'];
const ENROLLMENT_STATUSES: EnrollmentStatus[] = ['pending', 'confirmed', 'rejected', 'cancelled'];

const VISIT_TYPE_LABEL: Record<VisitType, string> = {
  recurring: 'Постоянные занятия',
  oneTime: 'Разовые занятия',
  both: 'Постоянные и разовые',
};

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

/** Понятный текст ошибки действия модератора. */
function actionError(err: unknown, target: Target): string {
  const e = toApiError(err);
  switch (e.status) {
    case 409:
      return target === 'activity'
        ? e.message || 'Активность уже обработана — обновите список'
        : 'Заявка уже обработана — обновите список';
    case 422:
      return e.violations.map(v => v.message).join('. ') || 'Проверьте введённые данные';
    case 403:
      return target === 'enrollment'
        ? 'Нет прав на это действие: заявки в этот кружок обрабатывает его владелец'
        : 'Нет прав на это действие';
    case 404:
      return target === 'activity' ? 'Активность не найдена — обновите список' : 'Не найдено — обновите список';
    default:
      return e.message;
  }
}

@Component({
  selector: 'act-admin-dashboard',
  imports: [RouterLink],
  styleUrl: './admin-dashboard.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="header">
      <a class="header__logo" routerLink="/">Заодно</a>
      <span class="header__title">Модерация</span>
      <span class="header__links">
        <a class="header__link" routerLink="/personal">Кабинет</a>
        <button class="header__link header__link--muted" (click)="logout()">Выйти</button>
      </span>
    </header>

    <nav class="tabs">
      <button class="tab" [class.tab--active]="tab() === 'activities'" (click)="openTab('activities')">
        Активности
        @if (actLoad() === 'loaded') { <span class="tab__count">{{ activities().length }}</span> }
      </button>
      <button class="tab" [class.tab--active]="tab() === 'enrollments'" (click)="openTab('enrollments')">
        Заявки
        @if (enrLoad() === 'loaded') { <span class="tab__count">{{ enrollments().length }}</span> }
      </button>
    </nav>

    <main class="content">
      @if (tab() === 'activities') {
        <div class="filters">
          <div class="chips">
            @for (s of activityStatuses; track s) {
              <button class="chip" [class.chip--active]="actStatus() === s" (click)="setActStatus(s)">
                {{ activityStatusLabel[s] }}
              </button>
            }
          </div>
          <button class="btn-link" (click)="loadActivities()">Обновить</button>
        </div>

        @if (actLoad() === 'loading') {
          <div class="state"><div class="spinner"></div></div>
        } @else if (actLoad() === 'error') {
          <div class="state">
            <span>{{ actError() }}</span>
            <button class="state__retry" (click)="loadActivities()">Повторить</button>
          </div>
        } @else if (activities().length === 0) {
          <div class="state">
            {{ actStatus() === 'pending' ? 'Очередь модерации пуста 🎉' : 'Активностей с таким статусом нет' }}
          </div>
        } @else {
          @for (a of activities(); track a.id) {
            <article class="card">
              <div class="card__head">
                <div class="card__icon">{{ a.emoji }}</div>
                <div class="card__title">
                  <p class="card__name">{{ a.name }}</p>
                  <p class="card__meta">{{ a.category }} · {{ a.ageMin }}–{{ a.ageMax }} лет · {{ price(a.priceType, a.priceAmount) }}</p>
                </div>
                <span [class]="'status-badge status-badge--' + a.status">{{ activityStatusLabel[a.status] }}</span>
              </div>

              <p class="card__row">
                <span class="card__label">Автор</span>
                {{ a.owner.name }} · <a class="card__mail" [href]="'mailto:' + a.owner.email">{{ a.owner.email }}</a>
              </p>
              <p class="card__row"><span class="card__label">Формат</span>{{ visitTypeLabel[a.visitType] }}</p>
              @if (a.tags.length) {
                <p class="card__row"><span class="card__label">Теги</span>{{ a.tags.join(', ') }}</p>
              }
              <p class="card__desc">{{ a.description }}</p>

              @for (b of a.branches; track b.id) {
                <div class="branch">
                  <p class="branch__name">📍 {{ b.name }}</p>
                  <p class="branch__line">{{ b.address }}@if (b.phone) { · {{ b.phone }} }</p>
                  @if (b.slots.length) {
                    <p class="branch__slots">{{ slots(b.slots) }}</p>
                  }
                </div>
              } @empty {
                <p class="card__warn">Филиалов нет — одобрить такую активность нельзя</p>
              }

              @if (a.moderationComment) {
                <p class="card__comment"><b>Комментарий модератора:</b> {{ a.moderationComment }}</p>
              }

              @if (a.clubId; as clubId) {
                <div class="card__club">
                  <a class="btn-link" [routerLink]="['/club', clubId]">Кружок в каталоге →</a>
                  <button class="btn-small" [disabled]="busy().has(clubId)"
                    (click)="togglePublished(clubId, isPublished(clubId, true), a.id)">
                    {{ isPublished(clubId, true) ? 'Снять с публикации' : 'Вернуть в каталог' }}
                  </button>
                </div>
              }

              @if (errors()[a.id]; as err) { <p class="inline-error">{{ err }}</p> }

              @if (a.status === 'pending') {
                @if (rejecting()?.id === a.id) {
                  <div class="reject">
                    <textarea class="reject__input" rows="3" maxlength="1000"
                      placeholder="Причина отклонения — её увидит автор"
                      [value]="rejectText()" (input)="rejectText.set($any($event.target).value)"></textarea>
                    <div class="actions">
                      <button class="btn btn--danger" [disabled]="busy().has(a.id)" (click)="rejectActivity(a)">
                        Отклонить
                      </button>
                      <button class="btn btn--ghost" (click)="closeReject()">Отмена</button>
                    </div>
                  </div>
                } @else {
                  <div class="actions">
                    <button class="btn btn--ok" [disabled]="busy().has(a.id) || a.branches.length === 0"
                      (click)="approveActivity(a)">Одобрить</button>
                    <button class="btn btn--ghost" [disabled]="busy().has(a.id)"
                      (click)="openReject('activity', a.id)">Отклонить</button>
                  </div>
                }
              }
            </article>
          }
        }
      } @else {
        <div class="filters">
          <div class="chips">
            @for (s of enrollmentStatuses; track s) {
              <button class="chip" [class.chip--active]="enrFilter().status === s" (click)="setEnrFilter({ status: s })">
                {{ enrollmentStatusLabel[s] }}
              </button>
            }
          </div>
          <label class="check">
            <input type="checkbox" [checked]="enrFilter().ownerless"
              (change)="setEnrFilter({ ownerless: $any($event.target).checked })" />
            Только кружки без владельца
          </label>
          <button class="btn-link" (click)="loadEnrollments()">Обновить</button>
        </div>

        @if (enrLoad() === 'loading') {
          <div class="state"><div class="spinner"></div></div>
        } @else if (enrLoad() === 'error') {
          <div class="state">
            <span>{{ enrError() }}</span>
            <button class="state__retry" (click)="loadEnrollments()">Повторить</button>
          </div>
        } @else if (enrollments().length === 0) {
          <div class="state">Заявок с такими условиями нет</div>
        } @else {
          @for (e of enrollments(); track e.id) {
            <article class="card">
              <div class="card__head">
                <div class="card__title">
                  <p class="card__name">
                    @if (e.forChild; as child) {
                      {{ child.firstName }} {{ child.lastName }}, {{ child.age }} лет
                    } @else {
                      {{ e.user.name }} <span class="card__hint">(для себя)</span>
                    }
                  </p>
                  <p class="card__meta">Подана {{ date(e.enrolledAt) }}</p>
                </div>
                <span [class]="'status-badge status-badge--' + e.status">{{ enrollmentStatusLabel[e.status] }}</span>
              </div>

              <p class="card__row">
                <span class="card__label">Кружок</span>
                <a class="card__mail" [routerLink]="['/club', e.club.id]">{{ e.club.name }}</a>
                @if (!isPublished(e.club.id, e.club.isPublished)) { <span class="status-badge">скрыт</span> }
              </p>
              <p class="card__row">
                <span class="card__label">Владелец</span>
                @if (e.club.owner; as owner) { {{ owner.name }} · {{ owner.email }} } @else { без владельца }
              </p>
              <p class="card__row">
                <span class="card__label">Заявитель</span>
                {{ e.user.name }} · <a class="card__mail" [href]="'mailto:' + e.user.email">{{ e.user.email }}</a>
              </p>
              @if (e.branchName) {
                <p class="card__row"><span class="card__label">Филиал</span>{{ e.branchName }}</p>
              }
              @if (e.schedule) { <p class="branch__slots">{{ e.schedule }}</p> }
              @if (e.rejectReason) {
                <p class="card__comment"><b>Причина отклонения:</b> {{ e.rejectReason }}</p>
              }

              @if (errors()[e.id]; as err) { <p class="inline-error">{{ err }}</p> }

              @if (rejecting()?.id === e.id) {
                <div class="reject">
                  <textarea class="reject__input" rows="2" maxlength="1000"
                    placeholder="Причина (необязательно)"
                    [value]="rejectText()" (input)="rejectText.set($any($event.target).value)"></textarea>
                  <div class="actions">
                    <button class="btn btn--danger" [disabled]="busy().has(e.id)" (click)="rejectEnrollment(e)">
                      Отклонить
                    </button>
                    <button class="btn btn--ghost" (click)="closeReject()">Отмена</button>
                  </div>
                </div>
              } @else {
                <div class="actions">
                  @if (e.status === 'pending') {
                    <button class="btn btn--ok" [disabled]="busy().has(e.id)" (click)="confirmEnrollment(e)">
                      Подтвердить
                    </button>
                    <button class="btn btn--ghost" [disabled]="busy().has(e.id)"
                      (click)="openReject('enrollment', e.id)">Отклонить</button>
                  }
                  <button class="btn-small" [disabled]="busy().has(e.club.id)"
                    (click)="togglePublished(e.club.id, isPublished(e.club.id, e.club.isPublished), e.id)">
                    {{ isPublished(e.club.id, e.club.isPublished) ? 'Снять кружок с публикации' : 'Вернуть кружок в каталог' }}
                  </button>
                </div>
              }
            </article>
          }
        }
      }
    </main>
  `,
})
export class AdminDashboardComponent {
  private readonly admin       = inject(AdminService);
  private readonly enrollSvc   = inject(EnrollmentsService);
  private readonly auth        = inject(AuthService);
  private readonly router      = inject(Router);

  readonly activityStatuses      = ACTIVITY_STATUSES;
  readonly enrollmentStatuses    = ENROLLMENT_STATUSES;
  readonly activityStatusLabel   = ACTIVITY_STATUS_LABEL;
  readonly enrollmentStatusLabel = ENROLLMENT_STATUS_LABEL;
  readonly visitTypeLabel        = VISIT_TYPE_LABEL;

  readonly tab = signal<Tab>('activities');

  readonly actStatus  = signal<ActivityStatus>('pending');
  readonly activities = signal<AdminActivity[]>([]);
  readonly actLoad    = signal<LoadStatus>('idle');
  readonly actError   = signal('');

  readonly enrFilter   = signal<AdminEnrollmentFilter>({ status: 'pending', ownerless: true });
  readonly enrollments = signal<AdminEnrollment[]>([]);
  readonly enrLoad     = signal<LoadStatus>('idle');
  readonly enrError    = signal('');

  /** id активностей/заявок/кружков (slug), над которыми выполняется действие. */
  readonly busy      = signal<ReadonlySet<string>>(new Set());
  /** Ошибка последнего действия по id карточки. */
  readonly errors    = signal<Record<string, string>>({});
  readonly rejecting = signal<{ target: Target; id: string } | null>(null);
  readonly rejectText = signal('');
  /** Публикация кружков, изменённая в этой сессии (slug → isPublished). */
  private readonly published = signal<Record<string, boolean>>({});

  /** Номера запросов списков: ответы устаревших запросов (сменили фильтр) игнорируются. */
  private actSeq = 0;
  private enrSeq = 0;

  constructor() {
    this.loadActivities();
  }

  openTab(tab: Tab): void {
    this.tab.set(tab);
    this.closeReject();
    if (tab === 'enrollments' && this.enrLoad() === 'idle') this.loadEnrollments();
  }

  // ─── Активности ────────────────────────────────────────────────────────────

  setActStatus(status: ActivityStatus): void {
    if (status === this.actStatus()) return;
    this.actStatus.set(status);
    this.loadActivities();
  }

  loadActivities(): void {
    const seq = ++this.actSeq;
    this.closeReject();
    this.errors.set({});
    this.actLoad.set('loading');
    this.admin.activities(this.actStatus()).subscribe({
      next: list => {
        if (seq !== this.actSeq) return;
        this.activities.set(list);
        this.actLoad.set('loaded');
      },
      error: (err: unknown) => {
        if (seq !== this.actSeq) return;
        this.actError.set(toApiError(err, 'Не удалось загрузить активности').message);
        this.actLoad.set('error');
      },
    });
  }

  approveActivity(a: AdminActivity): void {
    this.run(a.id, 'activity', this.admin.approveActivity(a.id), updated => this.replaceActivity(updated));
  }

  rejectActivity(a: AdminActivity): void {
    const comment = this.rejectText().trim();
    if (!comment) {
      this.setError(a.id, 'Укажите причину отклонения — её увидит автор');
      return;
    }
    this.run(a.id, 'activity', this.admin.rejectActivity(a.id, comment), updated => {
      this.replaceActivity(updated);
      this.closeReject();
    });
  }

  // ─── Заявки ────────────────────────────────────────────────────────────────

  setEnrFilter(patch: Partial<AdminEnrollmentFilter>): void {
    this.enrFilter.update(f => ({ ...f, ...patch }));
    this.loadEnrollments();
  }

  loadEnrollments(): void {
    const seq = ++this.enrSeq;
    this.closeReject();
    this.errors.set({});
    this.enrLoad.set('loading');
    this.admin.enrollments(this.enrFilter()).subscribe({
      next: list => {
        if (seq !== this.enrSeq) return;
        this.enrollments.set(list);
        this.enrLoad.set('loaded');
      },
      error: (err: unknown) => {
        if (seq !== this.enrSeq) return;
        this.enrError.set(toApiError(err, 'Не удалось загрузить заявки').message);
        this.enrLoad.set('error');
      },
    });
  }

  confirmEnrollment(e: AdminEnrollment): void {
    this.run(e.id, 'enrollment', this.enrollSvc.confirm(e.id), updated => this.patchEnrollment(e.id, updated));
  }

  rejectEnrollment(e: AdminEnrollment): void {
    const reason = this.rejectText().trim() || undefined;
    this.run(e.id, 'enrollment', this.enrollSvc.reject(e.id, reason), updated => {
      this.patchEnrollment(e.id, updated);
      this.closeReject();
    });
  }

  // ─── Публикация кружка ─────────────────────────────────────────────────────

  isPublished(slug: string, fallback: boolean): boolean {
    return this.published()[slug] ?? fallback;
  }

  /** `cardId` — карточка, под которой показать ошибку. */
  togglePublished(slug: string, current: boolean, cardId: string): void {
    this.setBusy(slug, true);
    this.setError(cardId, '');
    this.admin.setClubPublished(slug, !current).subscribe({
      next: res => {
        this.setBusy(slug, false);
        this.published.update(m => ({ ...m, [res.id]: res.isPublished }));
      },
      error: (err: unknown) => {
        this.setBusy(slug, false);
        const e = toApiError(err, 'Не удалось изменить публикацию кружка');
        this.setError(cardId, e.status === 404 ? 'Кружок не найден' : e.message);
      },
    });
  }

  // ─── Общее ─────────────────────────────────────────────────────────────────

  openReject(target: Target, id: string): void {
    this.rejecting.set({ target, id });
    this.rejectText.set('');
    this.setError(id, '');
  }

  closeReject(): void {
    this.rejecting.set(null);
    this.rejectText.set('');
  }

  async logout(): Promise<void> {
    await this.auth.logout();
    this.router.navigateByUrl('/');
  }

  slots(list: ScheduleSlot[]): string {
    return formatSlots(list);
  }

  price(type: PriceType, amount: number): string {
    if (type === 'free') return 'Бесплатно';
    const sum = amount > 0 ? `${amount.toLocaleString('ru-RU')} ₽` : 'Платно';
    return type === 'pushkin' ? `🎭 Пушкинская карта · ${sum}` : sum;
  }

  /** `YYYY-MM-DD` → `DD.MM.YYYY`. */
  date(iso: string): string {
    const [y, m, d] = iso.slice(0, 10).split('-');
    return d ? `${d}.${m}.${y}` : iso;
  }

  private run<T>(id: string, target: Target, request: Observable<T>, done: (res: T) => void): void {
    this.setBusy(id, true);
    this.setError(id, '');
    request.subscribe({
      next: res => {
        this.setBusy(id, false);
        done(res);
      },
      error: (err: unknown) => {
        this.setBusy(id, false);
        this.setError(id, actionError(err, target));
      },
    });
  }

  /** Обработанная карточка остаётся на месте с новым статусом до обновления списка. */
  private replaceActivity(updated: AdminActivity): void {
    this.activities.update(list => list.map(a => a.id === updated.id ? { ...a, ...updated } : a));
  }

  private patchEnrollment(id: string, updated: { status: EnrollmentStatus; rejectReason: string | null }): void {
    this.enrollments.update(list => list.map(e =>
      e.id === id ? { ...e, status: updated.status, rejectReason: updated.rejectReason } : e,
    ));
  }

  private setBusy(id: string, on: boolean): void {
    this.busy.update(set => {
      const next = new Set(set);
      if (on) next.add(id); else next.delete(id);
      return next;
    });
  }

  private setError(id: string, message: string): void {
    this.errors.update(m => {
      const { [id]: _, ...rest } = m;
      return message ? { ...rest, [id]: message } : rest;
    });
  }
}
