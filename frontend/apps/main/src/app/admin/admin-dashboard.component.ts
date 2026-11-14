import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';

import { formErrorMessage, toApiError } from '../api/api-error';
import { ACTIVITY_STATUS_LABEL, ActivityStatus } from '../models/activity.model';
import { AdminActivity, AdminEnrollment, AdminEnrollmentFilter } from '../models/admin.model';
import { VISIT_TYPE_LABEL } from '../models/club.model';
import { ENROLLMENT_STATUS_LABEL, Enrollment, EnrollmentStatus } from '../models/enrollment.model';
import { AdminService } from '../services/admin.service';
import { AuthService } from '../services/auth.service';
import { EnrollmentsService } from '../services/enrollments.service';
import { RemoteList } from '../services/remote-list';
import { HeaderUserComponent } from '../shared/header-user.component';
import { formatDate, yearsLabel } from '../utils/format';

type Tab = 'activities' | 'enrollments';
type Target = 'activity' | 'enrollment';

function actionError(err: unknown, target: Target): string {
  const e = toApiError(err);
  switch (e.status) {
    case 409:
      return target === 'activity'
        ? e.message || 'Активность уже обработана — обновите список'
        : 'Заявка уже обработана — обновите список';
    case 403:
      return target === 'enrollment'
        ? 'Нет прав на это действие: заявки в этот кружок обрабатывает его владелец'
        : 'Нет прав на это действие';
    case 404:
      return target === 'activity' ? 'Активность не найдена — обновите список' : 'Не найдено — обновите список';
    default:
      return formErrorMessage(e, []);
  }
}

@Component({
  selector: 'act-admin-dashboard',
  imports: [RouterLink, HeaderUserComponent],
  styleUrl: './admin-dashboard.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="header">
      <a class="header__logo" routerLink="/">Заодно</a>
      <span class="header__title">Модерация</span>
      <div class="header__actions">
        <a class="header__link" routerLink="/personal">Кабинет</a>
        <act-header-user />
        <button class="header__logout" (click)="logout()">Выйти</button>
      </div>
    </header>

    <nav class="tabs">
      <button class="tab" [class.tab--active]="tab() === 'activities'" (click)="openTab('activities')">
        Активности
        @if (activities.status() === 'loaded') { <span class="tab__count">{{ activities.items().length }}</span> }
      </button>
      <button class="tab" [class.tab--active]="tab() === 'enrollments'" (click)="openTab('enrollments')">
        Заявки
        @if (enrollments.status() === 'loaded') { <span class="tab__count">{{ enrollments.items().length }}</span> }
      </button>
    </nav>

    <main class="content">
      @if (tab() === 'activities') {
        <div class="filters">
          <div class="chips">
            @for (s of activityStatuses; track s) {
              <button class="filter-pill" [class.filter-pill--active]="actStatus() === s" (click)="setActStatus(s)">
                {{ activityStatusLabel[s] }}
              </button>
            }
          </div>
          <button class="btn-link" (click)="loadActivities()">Обновить</button>
        </div>

        @if (activities.status() === 'loading') {
          <div class="state"><div class="spinner"></div></div>
        } @else if (activities.status() === 'error') {
          <div class="state">
            <span>{{ activities.error() }}</span>
            <button class="state__retry" (click)="loadActivities()">Повторить</button>
          </div>
        } @else if (activities.items().length === 0) {
          <div class="state">
            {{ actStatus() === 'pending' ? 'Очередь модерации пуста 🎉' : 'Активностей с таким статусом нет' }}
          </div>
        } @else {
          @for (a of activities.items(); track a.id) {
            <article class="card">
              <div class="card__head">
                <div class="card__icon">{{ a.emoji }}</div>
                <div class="card__title">
                  <p class="card__name">{{ a.name }}</p>
                  <p class="card__meta">{{ a.category }} · {{ a.age }} · {{ a.price }}</p>
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
                  @if (b.schedule) {
                    <p class="branch__slots">{{ b.schedule }}</p>
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
                    (click)="togglePublished(clubId, isPublished(clubId, a.clubIsPublished ?? true), a.id)">
                    {{ isPublished(clubId, a.clubIsPublished ?? true) ? 'Снять с публикации' : 'Вернуть в каталог' }}
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
                    <button class="btn btn--primary" [disabled]="busy().has(a.id) || a.branches.length === 0"
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
              <button class="filter-pill" [class.filter-pill--active]="enrFilter().status === s" (click)="setEnrFilter({ status: s })">
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

        @if (enrollments.status() === 'loading') {
          <div class="state"><div class="spinner"></div></div>
        } @else if (enrollments.status() === 'error') {
          <div class="state">
            <span>{{ enrollments.error() }}</span>
            <button class="state__retry" (click)="loadEnrollments()">Повторить</button>
          </div>
        } @else if (enrollments.items().length === 0) {
          <div class="state">Заявок с такими условиями нет</div>
        } @else {
          @for (e of enrollments.items(); track e.id) {
            <article class="card">
              <div class="card__head">
                <div class="card__title">
                  <p class="card__name">
                    @if (e.forChild; as child) {
                      {{ child.firstName }} {{ child.lastName }}, {{ child.age }} {{ yearsLabel(child.age) }}
                    } @else {
                      {{ e.user.name }} <span class="card__hint">(для себя)</span>
                    }
                  </p>
                  <p class="card__meta">Подана {{ formatDate(e.enrolledAt) }}</p>
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
                    <button class="btn btn--primary" [disabled]="busy().has(e.id)" (click)="confirmEnrollment(e)">
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
  private readonly admin     = inject(AdminService);
  private readonly enrollSvc = inject(EnrollmentsService);
  private readonly auth      = inject(AuthService);
  private readonly router    = inject(Router);

  readonly activityStatuses      = Object.keys(ACTIVITY_STATUS_LABEL) as ActivityStatus[];
  readonly enrollmentStatuses    = Object.keys(ENROLLMENT_STATUS_LABEL) as EnrollmentStatus[];
  readonly activityStatusLabel   = ACTIVITY_STATUS_LABEL;
  readonly enrollmentStatusLabel = ENROLLMENT_STATUS_LABEL;
  readonly visitTypeLabel        = VISIT_TYPE_LABEL;
  readonly yearsLabel            = yearsLabel;
  readonly formatDate            = formatDate;

  readonly tab = signal<Tab>('activities');

  readonly actStatus  = signal<ActivityStatus>('pending');
  readonly activities = new RemoteList(() => this.admin.activities(this.actStatus()), 'Не удалось загрузить активности');

  readonly enrFilter   = signal<AdminEnrollmentFilter>({ status: 'pending', ownerless: true });
  readonly enrollments = new RemoteList(() => this.admin.enrollments(this.enrFilter()), 'Не удалось загрузить заявки');

  /** id карточек и slug кружков, по которым идёт запрос. */
  readonly busy      = signal<ReadonlySet<string>>(new Set());
  readonly errors    = signal<Record<string, string>>({});
  readonly rejecting = signal<{ target: Target; id: string } | null>(null);
  readonly rejectText = signal('');
  /** slug → isPublished, изменённые в этой сессии. */
  private readonly published = signal<Record<string, boolean>>({});

  constructor() {
    this.loadActivities();
  }

  openTab(tab: Tab): void {
    this.tab.set(tab);
    this.closeReject();
    if (tab === 'enrollments' && this.enrollments.status() === 'idle') this.loadEnrollments();
  }

  setActStatus(status: ActivityStatus): void {
    if (status === this.actStatus()) return;
    this.actStatus.set(status);
    this.loadActivities();
  }

  loadActivities(): void {
    this.closeReject();
    this.errors.set({});
    this.activities.reload();
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

  setEnrFilter(patch: Partial<AdminEnrollmentFilter>): void {
    this.enrFilter.update(f => ({ ...f, ...patch }));
    this.loadEnrollments();
  }

  loadEnrollments(): void {
    this.closeReject();
    this.errors.set({});
    this.enrollments.reload();
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

  /** Обработанная карточка остаётся в списке с новым статусом до обновления. */
  private replaceActivity(updated: AdminActivity): void {
    this.activities.items.update(list => list.map(a => a.id === updated.id ? { ...a, ...updated } : a));
  }

  private patchEnrollment(id: string, { status, rejectReason }: Enrollment): void {
    this.enrollments.items.update(list => list.map(e => e.id === id ? { ...e, status, rejectReason } : e));
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
