import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { LoadStatus } from '../api/api-client';
import { formErrorMessage, toApiError } from '../api/api-error';
import { Branch, Club, ClubCategory } from '../models/club.model';
import { CreateEnrollmentPayload, isActiveEnrollment } from '../models/enrollment.model';
import { Child } from '../models/user.model';
import { CATEGORY_BADGE } from '../home/home.component';
import { AuthService } from '../services/auth.service';
import { ChildrenService } from '../services/children.service';
import { ClubsService } from '../services/clubs.service';
import { EnrollmentsService } from '../services/enrollments.service';
import { getIllustration } from '../utils/illustrations';

/** Ключ участника «для себя» (у ребёнка ключ — его id). */
const SELF = '__self__';

type ClubLoadState = LoadStatus | 'notFound';

@Component({
  selector: 'act-club',
  imports: [RouterLink],
  styleUrl: './club.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (club(); as club) {
      <div class="page">
        <header class="header">
          <a class="back" routerLink="/">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m15 18-6-6 6-6"/>
            </svg>
            Все кружки
          </a>
          @if (authService.user()) {
            <a routerLink="/personal" class="personal-link">Мой аккаунт</a>
          }
        </header>

        <main class="content">
          <div class="hero" [style.background]="club.color + '12'">
            <div class="hero__emoji"
              [style.backgroundImage]="'url(' + illust(club.categories[0], club.color) + ')'"
              [style.backgroundSize]="'cover'"
              [style.backgroundPosition]="'center'">
            </div>
            <div class="hero__info">
              <div class="hero__badges">
                @for (cat of club.categories; track cat) {
                  <span class="badge"
                    [style.background]="categoryBadge[cat].bg"
                    [style.color]="categoryBadge[cat].color">{{ cat }}</span>
                }
              </div>
              <h1 class="hero__title">{{ club.name }}</h1>
              <p class="hero__subtitle">{{ club.shortDescription }}</p>
            </div>
          </div>

          <div class="body">
            <div class="section">
              <div class="section__title">О кружке</div>
              <p class="section__text">{{ club.fullDescription }}</p>
            </div>

            @if (club.branches.length > 1) {
              <div class="section">
                <div class="section__title">Филиал</div>
                <div class="branches">
                  @for (branch of club.branches; track branch.id) {
                    <button class="branch-tab"
                      [class.branch-tab--active]="displayBranch()?.id === branch.id"
                      (click)="activeBranch.set(branch)">
                      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
                      </svg>
                      {{ branch.name }}
                    </button>
                  }
                </div>
                @if (displayBranch(); as branch) {
                  <div class="branch-info">
                    <dl class="details">
                      <div class="detail-row"><dt>Адрес</dt><dd>{{ branch.address }}</dd></div>
                      <div class="detail-row"><dt>Телефон</dt><dd><a [href]="'tel:' + branch.phone" class="phone-link">{{ branch.phone }}</a></dd></div>
                      <div class="detail-row"><dt>Расписание</dt><dd class="schedule">{{ branch.schedule }}</dd></div>
                    </dl>
                  </div>
                }
              </div>
            } @else {
              @if (club.branches[0]; as branch) {
                <div class="section">
                  <div class="section__title">Контакты и расписание</div>
                  <dl class="details">
                    <div class="detail-row"><dt>Адрес</dt><dd>{{ branch.address }}</dd></div>
                    <div class="detail-row"><dt>Телефон</dt><dd><a [href]="'tel:' + branch.phone" class="phone-link">{{ branch.phone }}</a></dd></div>
                    <div class="detail-row"><dt>Расписание</dt><dd class="schedule">{{ branch.schedule }}</dd></div>
                  </dl>
                </div>
              }
            }

            <div class="section">
              <div class="section__title">Подробности</div>
              <dl class="details">
                <div class="detail-row"><dt>Возраст</dt><dd>{{ club.age }}</dd></div>
                <div class="detail-row"><dt>Стоимость</dt><dd>{{ club.price }}</dd></div>
              </dl>
            </div>

            @if (club.tags.length > 0) {
              <div class="section">
                <div class="section__title">Теги</div>
                <div class="tags">
                  @for (tag of club.tags; track tag) {
                    <span class="tag">#{{ tag }}</span>
                  }
                </div>
              </div>
            }

            <div class="cta-section">
              @if (enrolledLabels().length > 0) {
                <div class="enrolled-badge">✓ Заявка: {{ enrolledLabels().join(', ') }}</div>
              }
              @if (allEnrolled()) {
                <a routerLink="/personal" class="btn-secondary">Мои записи</a>
              } @else {
                <button class="btn-primary" (click)="openEnrollment()">Записаться</button>
              }
            </div>
          </div>
        </main>
      </div>

      <!-- Bottom sheet записи -->
      @if (enrollmentOpen()) {
        <div class="sheet-backdrop" (click)="closeEnrollment()"></div>
        <div class="sheet">
          <div class="sheet__handle"></div>
          <h2 class="sheet__title">Запись в кружок</h2>
          <p class="sheet__club">{{ club.name }}</p>

          @if (club.branches.length > 1) {
            <div class="sheet__section">
              <div class="sheet__label">Выберите филиал</div>
              <div class="sheet__chips">
                @for (branch of club.branches; track branch.id) {
                  <button class="s-chip" [class.s-chip--active]="selectedBranch()?.id === branch.id"
                    (click)="selectedBranch.set(branch)">{{ branch.name }}</button>
                }
              </div>
            </div>
          }

          <div class="sheet__section">
            <div class="sheet__label">Для кого?</div>
            <div class="sheet__chips">
              <button class="s-chip" [class.s-chip--active]="forChild() === null"
                (click)="selectParticipant(null)">Для себя{{ isEnrolled(null) ? ' ✓' : '' }}</button>
              @for (child of childrenService.children(); track child.id) {
                <button class="s-chip" [class.s-chip--active]="forChild()?.id === child.id"
                  (click)="selectParticipant(child)">
                  {{ child.firstName }}, {{ child.age }} л.{{ isEnrolled(child) ? ' ✓' : '' }}
                </button>
              }
            </div>
            @if (selectedEnrolled()) {
              <p class="sheet__hint">У этого участника уже есть активная заявка в этот кружок</p>
            }
          </div>

          @if (enrollError()) {
            <p class="inline-error sheet__error">{{ enrollError() }}</p>
          }

          <button class="sheet__submit" (click)="confirmEnrollment(club)"
            [disabled]="submitting() || selectedEnrolled()">
            {{ submitting() ? 'Отправляем…' : 'Отправить заявку' }}
          </button>
        </div>
      }
      <!-- Модалка: заявка отправлена -->
      @if (enrollmentDone()) {
        <div class="modal-backdrop" (click)="enrollmentDone.set(false)">
          <div class="modal" (click)="$event.stopPropagation()">
            <div class="modal__icon">📨</div>
            <h2 class="modal__title">Заявка отправлена</h2>
            <p class="modal__text">Ждите подтверждения от организатора. Статус заявки — в личном кабинете.</p>
            <div class="modal__actions">
              <a routerLink="/personal" class="modal__btn-primary">Мои записи</a>
              <button class="modal__btn-ghost" (click)="enrollmentDone.set(false)">Остаться здесь</button>
            </div>
          </div>
        </div>
      }

    } @else if (loadState() === 'loading') {
      <div class="not-found">
        <div class="spinner"></div>
        <p>Загружаем кружок…</p>
      </div>
    } @else if (loadState() === 'error') {
      <div class="not-found">
        <p>{{ loadError() }}</p>
        <button class="state__retry" (click)="loadClub()">Повторить</button>
        <a routerLink="/" class="btn-secondary">На главную</a>
      </div>
    } @else {
      <div class="not-found">
        <p>Кружок не найден</p>
        <a routerLink="/" class="btn-primary">На главную</a>
      </div>
    }
  `,
})
export class ClubComponent {
  private readonly route      = inject(ActivatedRoute);
  private readonly router     = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly clubsService = inject(ClubsService);
  private readonly enrollments  = inject(EnrollmentsService);

  readonly authService     = inject(AuthService);
  readonly childrenService = inject(ChildrenService);
  readonly categoryBadge   = CATEGORY_BADGE;

  readonly club      = signal<Club | null>(null);
  readonly loadState = signal<ClubLoadState>('idle');
  readonly loadError = signal('');

  readonly activeBranch   = signal<Branch | null>(null);
  readonly enrollmentOpen = signal(false);
  readonly enrollmentDone = signal(false);
  readonly selectedBranch = signal<Branch | null>(null);
  readonly forChild       = signal<Child | null>(null);
  readonly submitting     = signal(false);
  readonly enrollError    = signal('');

  readonly displayBranch = computed(() =>
    this.activeBranch() ?? this.club()?.branches[0] ?? null,
  );

  /** Активные (`pending|confirmed`) заявки в этот кружок. */
  private readonly activeEnrollments = computed(() => {
    const c = this.club();
    if (!c) return [];
    return this.enrollments.mine().filter(e => e.clubId === c.id && isActiveEnrollment(e));
  });

  /** Ключи участников, у которых уже есть активная заявка. */
  private readonly enrolledKeys = computed(() =>
    new Set(this.activeEnrollments().map(e => e.forChild?.id ?? SELF)),
  );

  readonly enrolledLabels = computed(() => [
    ...new Set(this.activeEnrollments().map(e => e.forChild ? e.forChild.firstName : 'для себя')),
  ]);

  /** Все участники (сам пользователь и каждый ребёнок) уже подали заявку. */
  readonly allEnrolled = computed(() => {
    if (!this.authService.user()) return false;
    const keys = this.enrolledKeys();
    return keys.has(SELF) && this.childrenService.children().every(c => keys.has(c.id));
  });

  readonly selectedEnrolled = computed(() => this.enrolledKeys().has(this.forChild()?.id ?? SELF));

  constructor() {
    this.loadClub();
    if (this.authService.user()) {
      this.enrollments.loadMine();
      this.childrenService.load();
    }
  }

  loadClub(): void {
    const slug = this.route.snapshot.paramMap.get('id') ?? '';
    this.loadState.set('loading');
    this.clubsService.getBySlug(slug)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: club => {
          this.club.set(club);
          this.loadState.set('loaded');
        },
        error: (err: unknown) => {
          const apiError = toApiError(err, 'Не удалось загрузить кружок');
          this.loadError.set(apiError.message);
          this.loadState.set(apiError.status === 404 ? 'notFound' : 'error');
        },
      });
  }

  illust(category: ClubCategory, color: string): string {
    return getIllustration(category, color);
  }

  isEnrolled(child: Child | null): boolean {
    return this.enrolledKeys().has(child?.id ?? SELF);
  }

  selectParticipant(child: Child | null): void {
    this.forChild.set(child);
    this.enrollError.set('');
  }

  openEnrollment(): void {
    if (!this.authService.user()) {
      this.router.navigate(['/auth/login'], { queryParams: { returnUrl: this.router.url } });
      return;
    }
    const club = this.club();
    this.selectedBranch.set(this.activeBranch() ?? club?.branches[0] ?? null);
    // по умолчанию — первый участник без активной заявки
    const free = this.childrenService.children().find(c => !this.isEnrolled(c));
    this.forChild.set(this.isEnrolled(null) && free ? free : null);
    this.enrollError.set('');
    this.enrollmentOpen.set(true);
  }

  closeEnrollment(): void {
    if (!this.submitting()) this.enrollmentOpen.set(false);
  }

  confirmEnrollment(club: Club): void {
    if (this.submitting() || this.selectedEnrolled()) return;
    const payload: CreateEnrollmentPayload = { clubId: club.id };
    const branch = this.selectedBranch() ?? club.branches[0];
    if (branch) payload.branchId = branch.id;
    const child = this.forChild();
    if (child) payload.childId = child.id;

    this.submitting.set(true);
    this.enrollError.set('');
    this.enrollments.create(payload).subscribe({
      next: () => {
        this.submitting.set(false);
        this.enrollmentOpen.set(false);
        this.enrollmentDone.set(true);
      },
      error: (err: unknown) => {
        this.submitting.set(false);
        const apiError = toApiError(err, 'Не удалось отправить заявку');
        if (apiError.status === 409) {
          this.enrollError.set('У этого участника уже есть активная заявка в этот кружок');
          this.enrollments.loadMine(true);
        } else if (apiError.status === 404) {
          this.enrollError.set('Кружок больше недоступен для записи');
        } else {
          this.enrollError.set(formErrorMessage(apiError, []));
        }
      },
    });
  }
}
