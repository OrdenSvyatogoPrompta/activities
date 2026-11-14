import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';

import { formErrorMessage, toApiError } from '../api/api-error';
import { ACTIVITY_STATUS_LABEL, ActivityStatus } from '../models/activity.model';
import { AdminActivity } from '../models/admin.model';
import { VISIT_TYPE_LABEL } from '../models/club.model';
import { AdminService } from '../services/admin.service';
import { AuthService } from '../services/auth.service';
import { RemoteList } from '../services/remote-list';
import { HeaderUserComponent } from '../shared/header-user.component';

function actionError(err: unknown): string {
  const e = toApiError(err);
  switch (e.status) {
    case 409:
      return e.message || 'Активность уже обработана — обновите список';
    case 403:
      return 'Нет прав на это действие';
    case 404:
      return 'Активность не найдена — обновите список';
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

    <main class="content">
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
              @if (rejecting() === a.id) {
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
                    (click)="openReject(a.id)">Отклонить</button>
                </div>
              }
            }
          </article>
        }
      }
    </main>
  `,
})
export class AdminDashboardComponent {
  private readonly admin  = inject(AdminService);
  private readonly auth   = inject(AuthService);
  private readonly router = inject(Router);

  readonly activityStatuses    = Object.keys(ACTIVITY_STATUS_LABEL) as ActivityStatus[];
  readonly activityStatusLabel = ACTIVITY_STATUS_LABEL;
  readonly visitTypeLabel      = VISIT_TYPE_LABEL;

  readonly actStatus  = signal<ActivityStatus>('pending');
  readonly activities = new RemoteList(() => this.admin.activities(this.actStatus()), 'Не удалось загрузить активности');

  /** id карточек и slug кружков, по которым идёт запрос. */
  readonly busy      = signal<ReadonlySet<string>>(new Set());
  readonly errors    = signal<Record<string, string>>({});
  /** id активности, для которой открыта форма отклонения. */
  readonly rejecting = signal<string | null>(null);
  readonly rejectText = signal('');
  /** slug → isPublished, изменённые в этой сессии. */
  private readonly published = signal<Record<string, boolean>>({});

  constructor() {
    this.loadActivities();
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
    this.run(a.id, this.admin.approveActivity(a.id), updated => this.replaceActivity(updated));
  }

  rejectActivity(a: AdminActivity): void {
    const comment = this.rejectText().trim();
    if (!comment) {
      this.setError(a.id, 'Укажите причину отклонения — её увидит автор');
      return;
    }
    this.run(a.id, this.admin.rejectActivity(a.id, comment), updated => {
      this.replaceActivity(updated);
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

  openReject(id: string): void {
    this.rejecting.set(id);
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

  private run<T>(id: string, request: Observable<T>, done: (res: T) => void): void {
    this.setBusy(id, true);
    this.setError(id, '');
    request.subscribe({
      next: res => {
        this.setBusy(id, false);
        done(res);
      },
      error: (err: unknown) => {
        this.setBusy(id, false);
        this.setError(id, actionError(err));
      },
    });
  }

  /** Обработанная карточка остаётся в списке с новым статусом до обновления. */
  private replaceActivity(updated: AdminActivity): void {
    this.activities.items.update(list => list.map(a => a.id === updated.id ? { ...a, ...updated } : a));
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
