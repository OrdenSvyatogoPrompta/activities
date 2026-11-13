import { ChangeDetectionStrategy, Component } from '@angular/core';

@Component({
  selector: 'acp-dashboard',
  changeDetection: ChangeDetectionStrategy.OnPush,
  styles: [`
    .page { min-height: 100vh; }

    .header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 24px; height: 52px;
      background: #fff; border-bottom: 1px solid #e2e8f0;
    }

    .logo { font-size: 15px; font-weight: 800; color: var(--brand-orange); }
    .city { font-weight: 400; font-size: 14px; color: #94a3b8; }

    .content { max-width: 720px; margin: 0 auto; padding: 32px 24px; }

    h1 { font-size: 22px; font-weight: 800; color: #1e293b; letter-spacing: -0.02em; margin: 0 0 4px; }
    .sub { font-size: 14px; color: #64748b; margin: 0 0 32px; }

    .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }

    .stub-card {
      background: #fff; border-radius: 12px; padding: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,.05);
    }
    .stub-card__icon { font-size: 28px; margin-bottom: 10px; }
    .stub-card__title { font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
    .stub-card__text { font-size: 12px; color: #94a3b8; }

    .back { font-size: 13px; color: var(--brand-purple); text-decoration: none; font-weight: 600; }
    .back:hover { text-decoration: underline; }
  `],
  template: `
    <div class="page">
      <header class="header">
        <span class="logo">Activities <span class="city">· Мой аккаунт</span></span>
        <a class="back" href="/">← На главную</a>
      </header>

      <div class="content">
        <h1>Привет! 👋</h1>
        <p class="sub">Здесь будет ваш личный кабинет</p>

        <div class="cards">
          <div class="stub-card">
            <div class="stub-card__icon">📋</div>
            <div class="stub-card__title">Мои записи</div>
            <div class="stub-card__text">Текущие и прошлые записи в кружки</div>
          </div>
          <div class="stub-card">
            <div class="stub-card__icon">👨‍👩‍👧</div>
            <div class="stub-card__title">Мои дети</div>
            <div class="stub-card__text">Добавь детей, чтобы записывать их в кружки</div>
          </div>
<div class="stub-card">
            <div class="stub-card__icon">🔔</div>
            <div class="stub-card__title">Уведомления</div>
            <div class="stub-card__text">Напоминания и новости от кружков</div>
          </div>
        </div>
      </div>
    </div>
  `,
})
export class DashboardComponent {}
