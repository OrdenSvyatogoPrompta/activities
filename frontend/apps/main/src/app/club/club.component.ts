import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { Branch } from '../models/club.model';
import { CATEGORY_BADGE } from '../home/home.component';
import { AuthService } from '../services/auth.service';
import { ClubsService } from '../services/clubs.service';
import { getIllustration } from '../utils/illustrations';

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

            @if (club.branches && club.branches.length > 0) {
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
              <div class="section">
                <div class="section__title">Контакты и расписание</div>
                <dl class="details">
                  <div class="detail-row"><dt>Адрес</dt><dd>{{ club.address }}</dd></div>
                  <div class="detail-row"><dt>Телефон</dt><dd><a [href]="'tel:' + club.phone" class="phone-link">{{ club.phone }}</a></dd></div>
                  <div class="detail-row"><dt>Расписание</dt><dd>{{ club.schedule }}</dd></div>
                </dl>
              </div>
            }

            <div class="section">
              <div class="section__title">Подробности</div>
              <dl class="details">
                <div class="detail-row"><dt>Возраст</dt><dd>{{ club.age }}</dd></div>
                <div class="detail-row"><dt>Стоимость</dt><dd>{{ club.price }}</dd></div>
              </dl>
            </div>

            <div class="section">
              <div class="section__title">Теги</div>
              <div class="tags">
                @for (tag of club.tags; track tag) {
                  <span class="tag">#{{ tag }}</span>
                }
              </div>
            </div>

            <div class="cta-section">
              @if (alreadyEnrolled()) {
                <div class="enrolled-badge">✓ Вы записаны</div>
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
        <div class="sheet-backdrop" (click)="enrollmentOpen.set(false)"></div>
        <div class="sheet">
          <div class="sheet__handle"></div>
          <h2 class="sheet__title">Запись в кружок</h2>
          <p class="sheet__club">{{ club.name }}</p>

          @if (club.branches && club.branches.length > 1) {
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
                (click)="forChild.set(null)">Для себя</button>
              @for (child of authService.user()?.children ?? []; track child.id) {
                <button class="s-chip" [class.s-chip--active]="forChild()?.id === child.id"
                  (click)="forChild.set(child)">{{ child.firstName }}, {{ child.age }} л.</button>
              }
            </div>
            @if (!authService.user()) {
              <p class="sheet__hint">
                <a routerLink="/auth/login" style="color:#6880f8">Войдите</a>, чтобы записаться
              </p>
            }
          </div>

          <button class="sheet__submit" (click)="confirmEnrollment(club)"
            [disabled]="!authService.user()">
            Подтвердить запись
          </button>
        </div>
      }
      <!-- Модалка успешной записи -->
      @if (enrollmentDone()) {
        <div class="modal-backdrop" (click)="enrollmentDone.set(false)">
          <div class="modal" (click)="$event.stopPropagation()">
            <div class="modal__icon">🎉</div>
            <h2 class="modal__title">Запись подтверждена!</h2>
            <p class="modal__text">Вы записались. Подробности в личном кабинете.</p>
            <div class="modal__actions">
              <a routerLink="/personal" class="modal__btn-primary">Мои записи</a>
              <button class="modal__btn-ghost" (click)="enrollmentDone.set(false)">Остаться здесь</button>
            </div>
          </div>
        </div>
      }

    } @else {
      <div class="not-found">
        <p>Кружок не найден</p>
        <a routerLink="/" class="btn-primary">На главную</a>
      </div>
    }
  `,
})
export class ClubComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly clubsService = inject(ClubsService);

  readonly authService   = inject(AuthService);
  readonly categoryBadge = CATEGORY_BADGE;
  readonly activeBranch  = signal<Branch | null>(null);
  readonly enrollmentOpen   = signal(false);
  readonly enrollmentDone   = signal(false);
  readonly selectedBranch   = signal<Branch | null>(null);
  readonly forChild         = signal<import('../services/auth.service').Child | null>(null);

  readonly club = computed(() => {
    const id = this.route.snapshot.paramMap.get('id') ?? '';
    return this.clubsService.getById(id);
  });

  readonly displayBranch = computed(() =>
    this.activeBranch() ?? this.club()?.branches?.[0] ?? null,
  );

  readonly alreadyEnrolled = computed(() => {
    const u = this.authService.user();
    const c = this.club();
    if (!u || !c) return false;
    return u.enrollments.some(e => e.clubId === c.id);
  });

  illust(category: string, color: string): string {
    return getIllustration(category as any, color);
  }

  openEnrollment(): void {
    if (!this.authService.user()) {
      this.router.navigate(['/auth/login']);
      return;
    }
    const branches = this.club()?.branches;
    this.selectedBranch.set(branches?.[0] ?? null);
    this.enrollmentOpen.set(true);
  }

  confirmEnrollment(club: NonNullable<ReturnType<typeof this.club>>): void {
    const branch = this.selectedBranch() ?? this.displayBranch();
    this.authService.enroll({
      clubId: club.id,
      clubName: club.name,
      branchId: branch?.id ?? null,
      branchName: branch?.name ?? club.address,
      schedule: branch?.schedule ?? club.schedule,
      days: (branch as any)?.days ?? club.days ?? [],
      color: club.color,
      forChild: this.forChild(),
    });
    this.enrollmentOpen.set(false);
    this.enrollmentDone.set(true);
  }
}
