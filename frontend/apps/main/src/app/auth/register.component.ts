import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { formErrorMessage, toApiError } from '../api/api-error';
import { AuthService } from '../services/auth.service';
import { getReturnUrl } from './return-url';

const FIELDS = ['name', 'email', 'password'];
const PASSWORD_MIN = 8;

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
        Заодно
      </a>

      <div class="auth-card">
        <h1 class="auth-card__title">Создать аккаунт</h1>

        <form class="auth-form" (submit)="submit($event)">
          <div class="field">
            <label class="field__label">Имя</label>
            <input class="field__input" type="text" placeholder="Иванова Мария" autocomplete="name"
              maxlength="100" required
              [class.field__input--invalid]="fieldErrors()['name']"
              [value]="name()" (input)="name.set($any($event.target).value)" />
            @if (fieldErrors()['name']; as msg) { <span class="field__error">{{ msg }}</span> }
          </div>
          <div class="field">
            <label class="field__label">Email</label>
            <input class="field__input" type="email" placeholder="mail@example.com" autocomplete="email" required
              [class.field__input--invalid]="fieldErrors()['email']"
              [value]="email()" (input)="email.set($any($event.target).value)" />
            @if (fieldErrors()['email']; as msg) { <span class="field__error">{{ msg }}</span> }
          </div>
          <div class="field">
            <label class="field__label">Пароль</label>
            <input class="field__input" type="password" placeholder="Не короче 8 символов" autocomplete="new-password" required
              [class.field__input--invalid]="fieldErrors()['password']"
              [value]="password()" (input)="password.set($any($event.target).value)" />
            @if (fieldErrors()['password']; as msg) { <span class="field__error">{{ msg }}</span> }
          </div>

          @if (error()) {
            <div class="auth-error">{{ error() }}</div>
          }

          <button class="btn-primary" type="submit" [disabled]="loading()">
            {{ loading() ? 'Создаём аккаунт…' : 'Зарегистрироваться' }}
          </button>
        </form>

        <p class="auth-card__footer">
          Уже есть аккаунт? <a routerLink="/auth/login" [queryParams]="route.snapshot.queryParams">Войти</a>
        </p>
      </div>
    </div>
  `,
})
export class RegisterComponent {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  readonly route = inject(ActivatedRoute);

  readonly name        = signal('');
  readonly email       = signal('');
  readonly password    = signal('');
  readonly error       = signal('');
  readonly fieldErrors = signal<Record<string, string>>({});
  readonly loading     = signal(false);

  async submit(e: Event): Promise<void> {
    e.preventDefault();
    if (this.loading()) return;
    this.error.set('');

    const name = this.name().trim();
    const email = this.email().trim();
    const errors: Record<string, string> = {};
    if (!name) errors['name'] = 'Укажите имя';
    if (!email) errors['email'] = 'Укажите email';
    if (this.password().length < PASSWORD_MIN) errors['password'] = `Пароль — не короче ${PASSWORD_MIN} символов`;
    this.fieldErrors.set(errors);
    if (Object.keys(errors).length > 0) return;

    this.loading.set(true);
    try {
      await this.authService.register(name, email, this.password());
      await this.router.navigateByUrl(getReturnUrl(this.route));
    } catch (err) {
      const apiError = toApiError(err, 'Не удалось зарегистрироваться');
      this.fieldErrors.set(apiError.fieldErrors);
      this.error.set(formErrorMessage(apiError, FIELDS));
    } finally {
      this.loading.set(false);
    }
  }
}
