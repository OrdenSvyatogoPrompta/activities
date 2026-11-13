import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { Branch } from '../models/club.model';
import { CATEGORY_BADGE } from '../home/home.component';
import { ClubsService } from '../services/clubs.service';

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
        </header>

        <main class="content">

          <!-- Hero -->
          <div class="hero" [style.background]="club.color + '12'">
            <div class="hero__emoji" [style.background]="club.color + '25'">{{ club.emoji }}</div>
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

            <!-- Описание -->
            <div class="section">
              <div class="section__title">О кружке</div>
              <p class="section__text">{{ club.fullDescription }}</p>
            </div>

            <!-- Филиалы -->
            @if (club.branches && club.branches.length > 0) {
              <div class="section">
                <div class="section__title">Филиал</div>
                <div class="branches">
                  @for (branch of club.branches; track branch.id) {
                    <button
                      class="branch-tab"
                      [class.branch-tab--active]="displayBranch()?.id === branch.id"
                      (click)="activeBranch.set(branch)"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
                      </svg>
                      {{ branch.name }}
                    </button>
                  }
                </div>

                <!-- Данные выбранного филиала -->
                @if (displayBranch(); as branch) {
                  <div class="branch-info">
                    <dl class="details">
                      <div class="detail-row">
                        <dt>Адрес</dt>
                        <dd>{{ branch.address }}</dd>
                      </div>
                      <div class="detail-row">
                        <dt>Телефон</dt>
                        <dd><a [href]="'tel:' + branch.phone" class="phone-link">{{ branch.phone }}</a></dd>
                      </div>
                      <div class="detail-row">
                        <dt>Расписание</dt>
                        <dd class="schedule">{{ branch.schedule }}</dd>
                      </div>
                    </dl>
                  </div>
                }
              </div>
            } @else {
              <!-- Кружок без филиалов -->
              <div class="section">
                <div class="section__title">Контакты и расписание</div>
                <dl class="details">
                  <div class="detail-row">
                    <dt>Адрес</dt>
                    <dd>{{ club.address }}</dd>
                  </div>
                  <div class="detail-row">
                    <dt>Телефон</dt>
                    <dd><a [href]="'tel:' + club.phone" class="phone-link">{{ club.phone }}</a></dd>
                  </div>
                  <div class="detail-row">
                    <dt>Расписание</dt>
                    <dd>{{ club.schedule }}</dd>
                  </div>
                </dl>
              </div>
            }

            <!-- Общие данные -->
            <div class="section">
              <div class="section__title">Подробности</div>
              <dl class="details">
                <div class="detail-row">
                  <dt>Возраст</dt>
                  <dd>{{ club.age }}</dd>
                </div>
                <div class="detail-row">
                  <dt>Стоимость</dt>
                  <dd>{{ club.price }}</dd>
                </div>
              </dl>
            </div>

            <!-- Теги -->
            <div class="section">
              <div class="section__title">Теги</div>
              <div class="tags">
                @for (tag of club.tags; track tag) {
                  <span class="tag">#{{ tag }}</span>
                }
              </div>
            </div>

            <!-- CTA -->
            <div class="cta-section">
              <button class="btn-primary" disabled>Записаться</button>
              <span class="cta-hint">Запись откроется в ближайшее время</span>
            </div>

          </div>
        </main>
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
  private readonly route = inject(ActivatedRoute);
  private readonly clubsService = inject(ClubsService);

  readonly categoryBadge = CATEGORY_BADGE;
  readonly activeBranch = signal<Branch | null>(null);

  readonly club = computed(() => {
    const id = this.route.snapshot.paramMap.get('id') ?? '';
    return this.clubsService.getById(id);
  });

  // первый филиал по умолчанию если ничего не выбрано
  readonly displayBranch = computed(() =>
    this.activeBranch() ?? this.club()?.branches?.[0] ?? null,
  );
}
