import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { formErrorMessage, toApiError } from '../api/api-error';
import { AuthService } from '../services/auth.service';
import { getReturnUrl } from './return-url';

const FIELDS = ['email', 'password'];

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
        Заодно
      </a>

      <div class="auth-card">
        <h1 class="auth-card__title">Вход</h1>

        <form class="auth-form" (submit)="submit($event)">
          <div class="field">
            <label class="field__label">Email</label>
            <input class="field__input" type="email" placeholder="mail@example.com" autocomplete="email"
              [class.field__input--invalid]="fieldErrors()['email']"
              [value]="email()" (input)="email.set($any($event.target).value)" required />
            @if (fieldErrors()['email']; as msg) { <span class="field__error">{{ msg }}</span> }
          </div>
          <div class="field">
            <label class="field__label">Пароль</label>
            <input class="field__input" type="password" placeholder="••••••••" autocomplete="current-password"
              [class.field__input--invalid]="fieldErrors()['password']"
              [value]="password()" (input)="password.set($any($event.target).value)" required />
            @if (fieldErrors()['password']; as msg) { <span class="field__error">{{ msg }}</span> }
          </div>

          @if (error()) {
            <div class="auth-error">{{ error() }}</div>
          }

          <button class="btn-primary" type="submit" [disabled]="loading()">
            {{ loading() ? 'Входим…' : 'Войти' }}
          </button>
        </form>

        <p class="auth-card__footer">
          Нет аккаунта? <a routerLink="/auth/register" [queryParams]="route.snapshot.queryParams">Зарегистрироваться</a>
        </p>
      </div>
    </div>
  `,
})
export class LoginComponent {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  readonly route = inject(ActivatedRoute);

  readonly email       = signal('');
  readonly password    = signal('');
  readonly error       = signal('');
  readonly fieldErrors = signal<Record<string, string>>({});
  readonly loading     = signal(false);

  async submit(e: Event): Promise<void> {
    e.preventDefault();
    if (this.loading()) return;
    this.fieldErrors.set({});
    if (!this.email() || !this.password()) {
      this.error.set('Заполните все поля');
      return;
    }
    this.error.set('');
    this.loading.set(true);
    try {
      await this.authService.login(this.email().trim(), this.password());
      await this.router.navigateByUrl(getReturnUrl(this.route));
    } catch (err) {
      const apiError = toApiError(err, 'Не удалось войти');
      this.fieldErrors.set(apiError.fieldErrors);
      // 401 — единое сообщение по контракту, не зависим от текста сервера
      this.error.set(apiError.status === 401 ? 'Неверный email или пароль' : formErrorMessage(apiError, FIELDS));
    } finally {
      this.loading.set(false);
    }
  }
}
