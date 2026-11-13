import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { AuthService, UserRole } from '../services/auth.service';

@Component({
  selector: 'act-register',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styleUrl: './auth.component.scss',
  template: `
    <div class="auth-page">
      <a class="auth-page__back" routerLink="/">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
          fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m15 18-6-6 6-6"/>
        </svg>
        Activities
      </a>

      <div class="auth-card">

        @if (!step()) {
          <h1 class="auth-card__title">Создать аккаунт</h1>
          <p class="auth-card__sub">Кто вы?</p>

          <div class="type-cards">
            <button class="type-card" (click)="step.set('user')">
              <span class="type-card__icon">👤</span>
              <span class="type-card__title">Пользователь</span>
              <span class="type-card__desc">Ищу кружки для себя или своих детей</span>
            </button>
            <button class="type-card" (click)="step.set('org')">
              <span class="type-card__icon">🏢</span>
              <span class="type-card__title">Организация</span>
              <span class="type-card__desc">Веду кружки, принимаю записи</span>
            </button>
          </div>

          <p class="auth-card__footer">
            Уже есть аккаунт? <a routerLink="/auth/login">Войти</a>
          </p>
        }

        @if (step() === 'user') {
          <button class="auth-card__back-step" (click)="step.set(null)">← Назад</button>
          <h1 class="auth-card__title">Аккаунт пользователя</h1>
          <p class="auth-card__sub">Детей можно добавить после регистрации в профиле</p>

          <form class="auth-form" (submit)="submit($event, 'user')">
            <div class="field">
              <label class="field__label">Имя</label>
              <input class="field__input" type="text" placeholder="Иванова Мария"
                [value]="name()" (input)="name.set($any($event.target).value)" />
            </div>
            <div class="field">
              <label class="field__label">Email</label>
              <input class="field__input" type="email" placeholder="mail@example.com"
                [value]="email()" (input)="email.set($any($event.target).value)" />
            </div>
            <div class="field">
              <label class="field__label">Пароль</label>
              <input class="field__input" type="password" placeholder="••••••••"
                [value]="password()" (input)="password.set($any($event.target).value)" />
            </div>

            @if (error()) { <div class="auth-error">{{ error() }}</div> }
            <button class="btn-primary" type="submit">Зарегистрироваться</button>
          </form>
        }

        @if (step() === 'org') {
          <button class="auth-card__back-step" (click)="step.set(null)">← Назад</button>
          <h1 class="auth-card__title">Аккаунт организации</h1>

          <form class="auth-form" (submit)="submit($event, 'org')">
            <div class="field">
              <label class="field__label">Название организации</label>
              <input class="field__input" type="text" placeholder='ООО "Технарь"'
                [value]="orgName()" (input)="orgName.set($any($event.target).value)" />
            </div>
            <div class="field">
              <label class="field__label">Контактное лицо</label>
              <input class="field__input" type="text" placeholder="Петров Иван"
                [value]="name()" (input)="name.set($any($event.target).value)" />
            </div>
            <div class="field">
              <label class="field__label">Email</label>
              <input class="field__input" type="email" placeholder="org@example.com"
                [value]="email()" (input)="email.set($any($event.target).value)" />
            </div>
            <div class="field">
              <label class="field__label">Телефон</label>
              <input class="field__input" type="tel" placeholder="+7 423 000-00-00"
                [value]="phone()" (input)="phone.set($any($event.target).value)" />
            </div>
            <div class="field">
              <label class="field__label">Пароль</label>
              <input class="field__input" type="password" placeholder="••••••••"
                [value]="password()" (input)="password.set($any($event.target).value)" />
            </div>

            @if (error()) { <div class="auth-error">{{ error() }}</div> }
            <button class="btn-primary" type="submit">Зарегистрироваться</button>
          </form>
        }

      </div>
    </div>
  `,
})
export class RegisterComponent {
  private readonly authService = inject(AuthService);

  readonly step    = signal<UserRole | null>(null);
  readonly name    = signal('');
  readonly email   = signal('');
  readonly password = signal('');
  readonly phone   = signal('');
  readonly orgName = signal('');
  readonly error   = signal('');

  submit(e: Event, role: UserRole): void {
    e.preventDefault();
    if (!this.email() || !this.password()) {
      this.error.set('Заполните обязательные поля');
      return;
    }
    this.authService.login(this.email(), this.password(), role);
    this.authService.redirectAfterLogin(role);
  }
}
