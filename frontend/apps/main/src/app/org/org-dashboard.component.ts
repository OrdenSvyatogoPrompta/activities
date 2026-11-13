import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'act-org-dashboard',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styles: [`
    .page { min-height: 100vh; background: #f8fafc; }

    .header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 24px; height: 52px;
      background: #fff; border-bottom: 1px solid #e2e8f0;
    }

    .logo { font-size: 15px; font-weight: 800; color: #f25e02; letter-spacing: -0.02em; }
    .city { font-weight: 400; font-size: 14px; color: #94a3b8; }

    .content { max-width: 720px; margin: 0 auto; padding: 32px 24px; }

    h1 { font-size: 22px; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; margin: 0 0 4px; }
    .sub { font-size: 14px; color: #64748b; margin: 0 0 32px; }

    .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }

    .stub-card {
      background: #fff; border-radius: 12px; padding: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,.05); cursor: pointer;
      transition: box-shadow 0.15s;
      &:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
    }
    .stub-card__icon { font-size: 28px; margin-bottom: 10px; }
    .stub-card__title { font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
    .stub-card__text { font-size: 12px; color: #94a3b8; }

    .back { font-size: 13px; color: #6880f8; text-decoration: none; font-weight: 600; }
    .back:hover { text-decoration: underline; }
  `],
  template: `
    <div class="page">
      <header class="header">
        <span class="logo">Заодно <span class="city">· Кабинет организации</span></span>
        <a class="back" routerLink="/">← На главную</a>
      </header>
      <div class="content">
        <h1>Кабинет организации 🏢</h1>
        <p class="sub">Управляйте своими кружками и записями</p>
        <div class="cards">
          <div class="stub-card">
            <div class="stub-card__icon">🎯</div>
            <div class="stub-card__title">Мои кружки</div>
            <div class="stub-card__text">Управление кружками и филиалами</div>
          </div>
          <div class="stub-card">
            <div class="stub-card__icon">📅</div>
            <div class="stub-card__title">Расписание</div>
            <div class="stub-card__text">Расписание по филиалам</div>
          </div>
          <div class="stub-card">
            <div class="stub-card__icon">👥</div>
            <div class="stub-card__title">Записи</div>
            <div class="stub-card__text">Заявки от пользователей</div>
          </div>
        </div>
      </div>
    </div>
  `,
})
export class OrgDashboardComponent {}
