import { ChangeDetectionStrategy, Component, inject, output, signal } from '@angular/core';

import { ApiError, toApiError } from '../api/api-error';
import { Activity, CreateActivityBranchPayload, CreateActivityPayload } from '../models/activity.model';
import { ClubCategory, DayOfWeek, PriceType, ScheduleSlot, VisitType } from '../models/club.model';
import { ActivitiesService } from '../services/activities.service';
import { CATEGORIES, PRICE_TYPE_LABEL, VISIT_TYPE_LABEL, WEEK_DAYS } from './personal.constants';

/** Группа слотов в форме: несколько дней с одним временем. */
interface SlotGroup {
  key: number;
  days: DayOfWeek[];
  timeFrom: string;
  timeTo: string;
}

/** Черновик филиала в форме. */
interface BranchDraft {
  key: number;
  name: string;
  address: string;
  phone: string;
  groups: SlotGroup[];
}

type BranchField = 'name' | 'address' | 'phone';

const MAX_BRANCHES = 10;
const MAX_SLOTS_PER_BRANCH = 50;
const TOP_FIELDS = [
  'name', 'category', 'emoji', 'description', 'ageMin', 'ageMax', 'visitType', 'priceType',
  'priceAmount', 'tags', 'branches',
];

let nextKey = 0;

function newGroup(): SlotGroup {
  return { key: nextKey++, days: [], timeFrom: '10:00', timeTo: '12:00' };
}

function newBranch(name = ''): BranchDraft {
  return { key: nextKey++, name, address: '', phone: '', groups: [newGroup()] };
}

/** Неотрицательное целое из поля ввода или `null`. */
function toInt(value: string): number | null {
  const trimmed = value.trim();
  return /^\d+$/.test(trimmed) ? Number(trimmed) : null;
}

/** Ключ ошибки поля филиала. */
function branchKey(bi: number, field: BranchField | 'slots'): string {
  return `branches[${bi}].${field}`;
}

/** Ключ ошибки группы слотов (на сервере групп нет — туда сводятся ошибки её слотов). */
function groupKey(bi: number, gi: number): string {
  return `branches[${bi}].groups[${gi}]`;
}

/**
 * Форма создания активности (`POST /api/me/activities`): общие поля и 1–10 филиалов,
 * у каждого — группы слотов «дни + время». Ошибки сервера (`branches[1].slots[0].timeTo`)
 * показываются у соответствующего филиала/группы, остальные — списком под формой.
 */
@Component({
  selector: 'act-activity-form',
  styleUrl: './activity-form.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="add-form">
      <p class="form-title">Новая активность</p>

      <div class="form-row">
        <div class="field">
          <label class="field__label">Название</label>
          <input class="field__input" type="text" placeholder="Название активности" maxlength="100"
            [value]="name()" (input)="name.set($any($event.target).value)" />
          @if (errors()['name']; as msg) { <span class="field__error">{{ msg }}</span> }
        </div>
        <div class="field field--narrow">
          <label class="field__label">Эмодзи</label>
          <input class="field__input" type="text" placeholder="🎯"
            [value]="emoji()" (input)="emoji.set($any($event.target).value)" />
        </div>
      </div>
      @if (errors()['emoji']; as msg) { <span class="field__error">{{ msg }}</span> }

      <div class="field">
        <label class="field__label">Категория</label>
        <select class="field__input field__select"
          [value]="category()" (change)="category.set($any($event.target).value)">
          <option value="" disabled>Выберите категорию</option>
          @for (cat of categories; track cat) {
            <option [value]="cat">{{ cat }}</option>
          }
        </select>
        @if (errors()['category']; as msg) { <span class="field__error">{{ msg }}</span> }
      </div>

      <div class="field">
        <label class="field__label">Описание</label>
        <textarea class="field__input field__textarea" placeholder="Расскажите об активности..." maxlength="5000"
          [value]="description()" (input)="description.set($any($event.target).value)"></textarea>
        @if (errors()['description']; as msg) { <span class="field__error">{{ msg }}</span> }
      </div>

      <div class="form-row">
        <div class="field">
          <label class="field__label">Возраст от</label>
          <input class="field__input field__input--no-spin" type="number" min="0" max="99" placeholder="5"
            [value]="ageMin()" (input)="ageMin.set($any($event.target).value)" />
          @if (errors()['ageMin']; as msg) { <span class="field__error">{{ msg }}</span> }
        </div>
        <div class="field">
          <label class="field__label">Возраст до</label>
          <input class="field__input field__input--no-spin" type="number" min="0" max="99" placeholder="14"
            [value]="ageMax()" (input)="ageMax.set($any($event.target).value)" />
          @if (errors()['ageMax']; as msg) { <span class="field__error">{{ msg }}</span> }
        </div>
      </div>

      <div class="field">
        <label class="field__label">Формат</label>
        <select class="field__input field__select"
          [value]="visitType()" (change)="visitType.set($any($event.target).value)">
          @for (vt of visitTypes; track vt) {
            <option [value]="vt">{{ visitTypeLabel[vt] }}</option>
          }
        </select>
        @if (errors()['visitType']; as msg) { <span class="field__error">{{ msg }}</span> }
      </div>

      <div class="field">
        <label class="field__label">Стоимость</label>
        <div class="gender-row">
          @for (pt of priceTypes; track pt) {
            <button type="button" class="filter-pill" [class.filter-pill--active]="priceType() === pt"
              (click)="priceType.set(pt)">{{ priceTypeLabel[pt] }}</button>
          }
        </div>
        @if (priceType() !== 'free') {
          <input class="field__input field__input--no-spin" type="number" min="0" placeholder="Сумма, ₽ в месяц"
            [value]="priceAmount()" (input)="priceAmount.set($any($event.target).value)" />
        }
        @if (errors()['priceAmount'] || errors()['priceType']; as msg) {
          <span class="field__error">{{ msg }}</span>
        }
      </div>

      <div class="field">
        <label class="field__label">Теги (через запятую)</label>
        <input class="field__input" type="text" placeholder="рисование, акварель"
          [value]="tags()" (input)="tags.set($any($event.target).value)" />
        @if (errors()['tags']; as msg) { <span class="field__error">{{ msg }}</span> }
      </div>

      <!-- Филиалы -->
      @for (branch of branches(); track branch.key; let bi = $index) {
        <div class="branch-form">
          <div class="branch-form__head">
            <p class="branch-form__title">Филиал {{ branches().length > 1 ? bi + 1 : '' }}</p>
            @if (branches().length > 1) {
              <button type="button" class="branch-form__remove" (click)="removeBranch(bi)">Удалить филиал</button>
            }
          </div>
          <div class="field">
            <label class="field__label">Название филиала</label>
            <input class="field__input" type="text" placeholder="Основной" maxlength="100"
              [value]="branch.name" (input)="setBranchField(bi, 'name', $any($event.target).value)" />
            @if (branchError(bi, 'name'); as msg) { <span class="field__error">{{ msg }}</span> }
          </div>
          <div class="field">
            <label class="field__label">Адрес</label>
            <input class="field__input" type="text" placeholder="ул. Примерная, 1" maxlength="255"
              [value]="branch.address" (input)="setBranchField(bi, 'address', $any($event.target).value)" />
            @if (branchError(bi, 'address'); as msg) { <span class="field__error">{{ msg }}</span> }
          </div>
          <div class="field">
            <label class="field__label">Телефон</label>
            <input class="field__input" type="tel" placeholder="+7 423 000-00-00" maxlength="32"
              [value]="branch.phone" (input)="setBranchField(bi, 'phone', $any($event.target).value)" />
            @if (branchError(bi, 'phone'); as msg) { <span class="field__error">{{ msg }}</span> }
          </div>

          <span class="field__label">Расписание</span>
          @for (group of branch.groups; track group.key; let gi = $index) {
            <div class="slot-group" [class.slot-group--invalid]="!!groupError(bi, gi)">
              <div class="day-picker">
                @for (d of weekDays; track d) {
                  <button type="button" class="day-btn" [class.day-btn--active]="group.days.includes(d)"
                    (click)="toggleGroupDay(bi, gi, d)">{{ d }}</button>
                }
              </div>
              <div class="form-row">
                <div class="field">
                  <label class="field__label">Начало</label>
                  <input class="field__input" type="time" [value]="group.timeFrom"
                    (change)="setGroupTime(bi, gi, 'timeFrom', $any($event.target).value)" />
                </div>
                <div class="field">
                  <label class="field__label">Конец</label>
                  <input class="field__input" type="time" [value]="group.timeTo"
                    (change)="setGroupTime(bi, gi, 'timeTo', $any($event.target).value)" />
                </div>
              </div>
              @if (groupError(bi, gi); as msg) { <span class="field__error">{{ msg }}</span> }
              @if (branch.groups.length > 1) {
                <button type="button" class="slot-group__remove" title="Убрать время" (click)="removeSlotGroup(bi, gi)">✕</button>
              }
            </div>
          }
          <button type="button" class="btn-link" (click)="addSlotGroup(bi)">+ Добавить другое время</button>
          @if (branchError(bi, 'slots'); as msg) { <span class="field__error">{{ msg }}</span> }
        </div>
      }
      @if (branches().length < maxBranches) {
        <button type="button" class="btn-add-branch" (click)="addBranch()">+ Добавить филиал</button>
      }
      @if (errors()['branches']; as msg) { <span class="field__error">{{ msg }}</span> }

      @if (formErrors().length > 0) {
        <ul class="form-errors">
          @for (msg of formErrors(); track $index) { <li class="inline-error">{{ msg }}</li> }
        </ul>
      }

      <div class="form-actions">
        <button type="button" class="btn-primary" [disabled]="creating()" (click)="submit()">
          {{ creating() ? 'Отправляем…' : 'Отправить на модерацию' }}
        </button>
        <button type="button" class="btn-ghost" [disabled]="creating()" (click)="cancelled.emit()">Отмена</button>
      </div>
    </div>
  `,
})
export class ActivityFormComponent {
  private readonly activitiesService = inject(ActivitiesService);

  readonly created   = output<Activity>();
  readonly cancelled = output<void>();

  readonly weekDays   = WEEK_DAYS;
  readonly categories = CATEGORIES;
  readonly visitTypes = Object.keys(VISIT_TYPE_LABEL) as VisitType[];
  readonly priceTypes: PriceType[] = ['free', 'paid', 'pushkin'];
  readonly visitTypeLabel = VISIT_TYPE_LABEL;
  readonly priceTypeLabel = PRICE_TYPE_LABEL;
  readonly maxBranches    = MAX_BRANCHES;

  readonly name        = signal('');
  readonly category    = signal<ClubCategory | ''>('');
  readonly emoji       = signal('');
  readonly description = signal('');
  readonly ageMin      = signal('');
  readonly ageMax      = signal('');
  readonly visitType   = signal<VisitType>('recurring');
  readonly priceType   = signal<PriceType>('free');
  readonly priceAmount = signal('');
  readonly tags        = signal('');
  readonly branches    = signal<BranchDraft[]>([newBranch('Основной')]);

  /** Ошибки по ключам: поля верхнего уровня, `branches[i].field`, `branches[i].groups[j]`. */
  readonly errors     = signal<Record<string, string>>({});
  /** Ошибки, которые не удалось привязать к полю. */
  readonly formErrors = signal<string[]>([]);
  readonly creating   = signal(false);

  /**
   * Для последней отправки: индекс слота в `branches[i].slots` → индекс группы в форме.
   * Нужен, чтобы ошибку сервера по слоту показать у группы.
   */
  private slotToGroup: number[][] = [];

  branchError(bi: number, field: BranchField | 'slots'): string {
    return this.errors()[branchKey(bi, field)] ?? '';
  }

  groupError(bi: number, gi: number): string {
    return this.errors()[groupKey(bi, gi)] ?? '';
  }

  // ─── Филиалы и расписание ──────────────────────────────────────────────────

  addBranch(): void {
    if (this.branches().length >= MAX_BRANCHES) return;
    this.branches.update(list => [...list, newBranch()]);
  }

  removeBranch(bi: number): void {
    this.branches.update(list => list.filter((_, i) => i !== bi));
    this.clearBranchErrors();
  }

  setBranchField(bi: number, field: BranchField, value: string): void {
    this.updateBranch(bi, b => ({ ...b, [field]: value }));
  }

  toggleGroupDay(bi: number, gi: number, day: DayOfWeek): void {
    this.updateGroup(bi, gi, g => ({
      ...g,
      days: g.days.includes(day) ? g.days.filter(d => d !== day) : [...g.days, day],
    }));
  }

  setGroupTime(bi: number, gi: number, field: 'timeFrom' | 'timeTo', value: string): void {
    this.updateGroup(bi, gi, g => ({ ...g, [field]: value }));
  }

  addSlotGroup(bi: number): void {
    this.updateBranch(bi, b => ({ ...b, groups: [...b.groups, newGroup()] }));
  }

  removeSlotGroup(bi: number, gi: number): void {
    this.updateBranch(bi, b => ({ ...b, groups: b.groups.filter((_, i) => i !== gi) }));
    this.clearBranchErrors();
  }

  // ─── Отправка ──────────────────────────────────────────────────────────────

  submit(): void {
    if (this.creating()) return;
    const payload = this.buildPayload();
    if (!payload) return;

    this.creating.set(true);
    this.activitiesService.create(payload).subscribe({
      next: activity => {
        this.creating.set(false);
        this.created.emit(activity);
      },
      error: (err: unknown) => {
        this.creating.set(false);
        this.applyServerErrors(toApiError(err, 'Не удалось создать активность'));
      },
    });
  }

  /** Клиентская валидация по контракту; при ошибках возвращает `null` и показывает их в форме. */
  private buildPayload(): CreateActivityPayload | null {
    const errors: Record<string, string> = {};
    const name = this.name().trim();
    const category = this.category();
    const description = this.description().trim();
    const ageMin = toInt(this.ageMin());
    const ageMax = toInt(this.ageMax());
    const priceType = this.priceType();
    const priceAmount = priceType === 'free' ? 0 : toInt(this.priceAmount());

    if (name.length < 2 || name.length > 100) errors['name'] = 'Название — от 2 до 100 символов';
    if (!category) errors['category'] = 'Выберите категорию';
    if (!description) errors['description'] = 'Добавьте описание';
    if (ageMin === null || ageMin > 99) errors['ageMin'] = 'Укажите возраст от 0 до 99';
    if (ageMax === null || ageMax > 99) errors['ageMax'] = 'Укажите возраст от 0 до 99';
    else if (ageMin !== null && ageMin > ageMax) errors['ageMax'] = 'Не меньше, чем «возраст от»';
    if (priceAmount === null) errors['priceAmount'] = 'Укажите сумму в рублях';

    const slotToGroup: number[][] = [];
    const branches: CreateActivityBranchPayload[] = this.branches().map((b, bi) => {
      const address = b.address.trim();
      if (!address) errors[branchKey(bi, 'address')] = 'Укажите адрес';

      const map: number[] = [];
      const slots: ScheduleSlot[] = [];
      b.groups.forEach((g, gi) => {
        if (g.days.length === 0) return;
        if (!g.timeFrom || !g.timeTo || g.timeFrom >= g.timeTo) {
          errors[groupKey(bi, gi)] = 'Время начала должно быть раньше окончания';
        }
        for (const day of [...g.days].sort((x, y) => WEEK_DAYS.indexOf(x) - WEEK_DAYS.indexOf(y))) {
          slots.push({ day, timeFrom: g.timeFrom, timeTo: g.timeTo });
          map.push(gi);
        }
      });
      if (slots.length === 0) errors[branchKey(bi, 'slots')] = 'Выберите хотя бы один день занятий';
      else if (slots.length > MAX_SLOTS_PER_BRANCH) {
        errors[branchKey(bi, 'slots')] = `Не больше ${MAX_SLOTS_PER_BRANCH} занятий в расписании филиала`;
      }
      slotToGroup.push(map);

      return { name: b.name.trim() || 'Основной', address, phone: b.phone.trim(), slots };
    });

    this.errors.set(errors);
    this.formErrors.set([]);
    if (Object.keys(errors).length > 0) return null;

    this.slotToGroup = slotToGroup;
    return {
      name,
      category: category as ClubCategory,
      emoji: this.emoji().trim() || '🎯',
      description,
      ageMin,
      ageMax,
      visitType: this.visitType(),
      priceType,
      priceAmount,
      tags: [...new Set(this.tags().split(',').map(t => t.trim()).filter(Boolean))],
      branches,
    };
  }

  /** Раскладывает `violations` по полям формы; что не сопоставилось — в список под формой. */
  private applyServerErrors(apiError: ApiError): void {
    const errors: Record<string, string> = {};
    const unmatched: string[] = [];
    const branchCount = this.branches().length;

    for (const v of apiError.violations) {
      const key = this.violationKey(v.propertyPath, branchCount);
      if (key === null) unmatched.push(v.message);
      else if (!(key in errors)) errors[key] = v.message;
    }

    this.errors.set(errors);
    this.formErrors.set(
      apiError.violations.length > 0 ? unmatched : [apiError.message],
    );
  }

  /** Ключ поля формы для `propertyPath` сервера или `null`, если поля в форме нет. */
  private violationKey(path: string, branchCount: number): string | null {
    if (TOP_FIELDS.includes(path)) return path;
    if (/^tags\[\d+\]$/.test(path)) return 'tags';

    const m = /^branches\[(\d+)\]\.(\w+)(?:\[(\d+)\])?/.exec(path);
    if (!m) return null;
    const bi = Number(m[1]);
    if (bi >= branchCount) return 'branches';
    const field = m[2];
    if (field === 'name' || field === 'address' || field === 'phone') return branchKey(bi, field);
    if (field !== 'slots') return null;
    if (m[3] === undefined) return branchKey(bi, 'slots');
    const gi = this.slotToGroup[bi]?.[Number(m[3])];
    return gi === undefined ? branchKey(bi, 'slots') : groupKey(bi, gi);
  }

  private updateBranch(bi: number, fn: (b: BranchDraft) => BranchDraft): void {
    this.branches.update(list => list.map((b, i) => (i === bi ? fn(b) : b)));
  }

  private updateGroup(bi: number, gi: number, fn: (g: SlotGroup) => SlotGroup): void {
    this.updateBranch(bi, b => ({ ...b, groups: b.groups.map((g, i) => (i === gi ? fn(g) : g)) }));
  }

  /** Индексы филиалов/групп сдвинулись — ошибки по ним больше не соответствуют полям. */
  private clearBranchErrors(): void {
    this.errors.update(errors => Object.fromEntries(
      Object.entries(errors).filter(([key]) => !key.startsWith('branches[')),
    ));
  }
}
