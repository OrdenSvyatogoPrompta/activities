import { ChangeDetectionStrategy, Component, OnInit, computed, inject, input, output, signal } from '@angular/core';
import { Observable } from 'rxjs';

import { formErrorMessage, toApiError } from '../api/api-error';
import { Child, CreateChildPayload, Gender, UpdateChildPayload } from '../models/user.model';
import { ChildrenService } from '../services/children.service';
import { toInt } from '../utils/format';

const CHILD_FIELDS = ['firstName', 'lastName', 'age', 'gender'];

@Component({
  selector: 'act-child-form',
  styleUrl: './child-form.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="add-form" [class.add-form--inline]="isEdit()">
      <p class="form-title">{{ isEdit() ? 'Редактирование' : 'Добавить ребёнка' }}</p>
      <div class="form-row">
        <div class="field">
          <label class="field__label">Имя</label>
          <input class="field__input" type="text" placeholder="Артём" maxlength="50"
            [value]="firstName()" (input)="firstName.set($any($event.target).value)" />
          @if (errors()['firstName']; as msg) { <span class="field__error">{{ msg }}</span> }
        </div>
        <div class="field">
          <label class="field__label">Фамилия</label>
          <input class="field__input" type="text" placeholder="Иванов" maxlength="50"
            [value]="lastName()" (input)="lastName.set($any($event.target).value)" />
          @if (errors()['lastName']; as msg) { <span class="field__error">{{ msg }}</span> }
        </div>
      </div>
      <div class="form-row">
        <div class="field field--narrow">
          <label class="field__label">Возраст</label>
          <input class="field__input field__input--no-spin" type="number" min="1" max="18" placeholder="8"
            [value]="age()" (input)="age.set($any($event.target).value)" />
        </div>
        <div class="field">
          <label class="field__label">Пол</label>
          <div class="gender-row">
            <button type="button" class="filter-pill" [class.filter-pill--active]="gender() === 'male'"
              (click)="gender.set('male')">👦 Мальчик</button>
            <button type="button" class="filter-pill" [class.filter-pill--active]="gender() === 'female'"
              (click)="gender.set('female')">👧 Девочка</button>
          </div>
        </div>
      </div>
      @if (errors()['age']; as msg) { <span class="field__error">{{ msg }}</span> }
      @if (errors()['gender']; as msg) { <span class="field__error">{{ msg }}</span> }
      @if (formError()) { <p class="inline-error">{{ formError() }}</p> }

      @if (isEdit()) {
        <div class="form-actions">
          <button type="button" class="btn-primary" [disabled]="saving()" (click)="submit()">
            {{ saving() ? 'Сохраняем…' : 'Сохранить' }}
          </button>
          <button type="button" class="btn-ghost" [disabled]="saving()" (click)="cancelled.emit()">Отмена</button>
        </div>
      } @else {
        <button type="button" class="btn-add-child" [disabled]="saving()" (click)="submit()">
          {{ saving() ? 'Добавляем…' : 'Добавить' }}
        </button>
      }
    </div>
  `,
})
export class ChildFormComponent implements OnInit {
  private readonly childrenService = inject(ChildrenService);

  /** `null` — форма добавления. */
  readonly child = input<Child | null>(null);

  readonly saved     = output<Child>();
  readonly cancelled = output<void>();

  readonly isEdit = computed(() => this.child() !== null);

  readonly firstName = signal('');
  readonly lastName  = signal('');
  readonly age       = signal('');
  readonly gender    = signal<Gender>('male');
  readonly errors    = signal<Record<string, string>>({});
  readonly formError = signal('');
  readonly saving    = signal(false);

  ngOnInit(): void {
    const child = this.child();
    if (!child) return;
    this.firstName.set(child.firstName);
    this.lastName.set(child.lastName ?? '');
    this.age.set(String(child.age));
    this.gender.set(child.gender);
  }

  submit(): void {
    if (this.saving()) return;
    const firstName = this.firstName().trim();
    const lastName = this.lastName().trim();
    const age = toInt(this.age());
    const errors: Record<string, string> = {};
    if (!firstName) errors['firstName'] = 'Укажите имя';
    if (age === null || age < 1 || age > 18) errors['age'] = 'Возраст — от 1 до 18 лет';
    this.errors.set(errors);
    this.formError.set('');
    if (Object.keys(errors).length > 0) return;

    const child = this.child();
    let request: Observable<Child>;
    if (child) {
      const payload: UpdateChildPayload = { firstName, lastName: lastName || null, age, gender: this.gender() };
      request = this.childrenService.update(child.id, payload);
    } else {
      const payload: CreateChildPayload = { firstName, age, gender: this.gender() };
      if (lastName) payload.lastName = lastName;
      request = this.childrenService.create(payload);
    }

    this.saving.set(true);
    request.subscribe({
      next: saved => {
        this.saving.set(false);
        if (!child) {
          this.firstName.set(''); this.lastName.set(''); this.age.set('');
        }
        this.saved.emit(saved);
      },
      error: (err: unknown) => {
        this.saving.set(false);
        const apiError = toApiError(err, child ? 'Не удалось сохранить' : 'Не удалось добавить ребёнка');
        this.errors.set(apiError.fieldErrors);
        this.formError.set(apiError.status === 404 ? 'Ребёнок не найден — обновите страницу'
          : formErrorMessage(apiError, CHILD_FIELDS));
      },
    });
  }
}
