import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';

import { toApiError } from '../api/api-error';
import { AuthService } from '../services/auth.service';

const MAX_NAME_LENGTH = 100;

@Component({
  selector: 'act-profile-name',
  styleUrl: './profile-name.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (editing()) {
      <form class="pn-form" (submit)="$event.preventDefault(); save()">
        <input class="pn-form__input" type="text" placeholder="Ваше имя" aria-label="Имя"
          [attr.maxlength]="maxLength" [value]="draft()" (input)="draft.set($any($event.target).value)"
          (keydown.escape)="cancel()" />
        <button type="submit" class="pn-btn pn-btn--save" [disabled]="saving()">
          {{ saving() ? '…' : 'Сохранить' }}
        </button>
        <button type="button" class="pn-btn" [disabled]="saving()" (click)="cancel()">Отмена</button>
      </form>
      @if (error()) { <p class="pn-error">{{ error() }}</p> }
    } @else {
      <p class="pn-name">
        <span>{{ auth.user()?.name || 'Без имени' }}</span>
        <button type="button" class="pn-edit" title="Изменить имя" aria-label="Изменить имя" (click)="start()">✎</button>
      </p>
    }
  `,
})
export class ProfileNameComponent {
  readonly auth = inject(AuthService);

  readonly maxLength = MAX_NAME_LENGTH;
  readonly editing = signal(false);
  readonly draft   = signal('');
  readonly saving  = signal(false);
  readonly error   = signal('');

  start(): void {
    this.draft.set(this.auth.user()?.name ?? '');
    this.error.set('');
    this.editing.set(true);
  }

  cancel(): void {
    if (this.saving()) return;
    this.editing.set(false);
  }

  async save(): Promise<void> {
    if (this.saving()) return;
    const name = this.draft().trim();
    if (!name) {
      this.error.set('Укажите имя');
      return;
    }
    if (name === this.auth.user()?.name) {
      this.editing.set(false);
      return;
    }
    this.saving.set(true);
    this.error.set('');
    try {
      await this.auth.updateName(name);
      this.editing.set(false);
    } catch (err) {
      const apiError = toApiError(err, 'Не удалось сохранить имя');
      this.error.set(apiError.fieldErrors['name'] ?? apiError.message);
    } finally {
      this.saving.set(false);
    }
  }
}
