import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import { AuthService, UserRole } from '../services/auth.service';

@Component({
  selector: 'act-login',
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
        <h1 class="auth-card__title">Вход</h1>
        <p class="auth-card__sub">Выберите тип аккаунта</p>

        <div class="role-tabs">
          <button class="role-tab" [class.role-tab--active]="role() === 'user'"
            (click)="role.set('user')">Пользователь</button>
          <button class="role-tab" [class.role-tab--active]="role() === 'org'"
            (click)="role.set('org')">Организация</button>
        </div>

        <form class="auth-form" (submit)="submit($event)">
          <div class="field">
            <label class="field__label">Email</label>
            <input class="field__input" type="email" placeholder="mail@example.com"
              [value]="email()" (input)="email.set($any($event.target).value)" required />
          </div>
          <div class="field">
            <label class="field__label">Пароль</label>
            <input class="field__input" type="password" placeholder="••••••••"
              [value]="password()" (input)="password.set($any($event.target).value)" required />
          </div>

          @if (error()) {
            <div class="auth-error">{{ error() }}</div>
          }

          <button class="btn-primary" type="submit">Войти</button>
        </form>

        <p class="auth-card__footer">
          Нет аккаунта?
          <a routerLink="/auth/register">Зарегистрироваться</a>
        </p>
      </div>
    </div>
  `,
})
export class LoginComponent {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  readonly role     = signal<UserRole>('user');
  readonly email    = signal('');
  readonly password = signal('');
  readonly error    = signal('');

  submit(e: Event): void {
    e.preventDefault();
    if (!this.email() || !this.password()) {
      this.error.set('Заполните все поля');
      return;
    }
    this.authService.login(this.email(), this.password(), this.role());
    this.authService.redirectAfterLogin(this.role());
  }
}
