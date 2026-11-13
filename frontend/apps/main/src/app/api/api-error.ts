import { HttpErrorResponse } from '@angular/common/http';

/** Нарушение валидации из `problem+json`. */
export interface Violation {
  propertyPath: string;
  message: string;
}

/** Тело ошибки API (RFC 7807). */
export interface ProblemDetails {
  type?: string;
  title?: string;
  status?: number;
  detail?: string;
  violations?: Violation[];
}

/** Ошибка API в удобном для форм виде. */
export interface ApiError {
  /** HTTP-статус; `0` — нет связи с сервером. */
  status: number;
  /** Общее сообщение для пользователя. */
  message: string;
  /** Первое сообщение по каждому `propertyPath`. */
  fieldErrors: Record<string, string>;
  violations: Violation[];
}

const DEFAULT_MESSAGE = 'Что-то пошло не так. Попробуйте ещё раз';

export function toApiError(err: unknown, fallback = DEFAULT_MESSAGE): ApiError {
  if (!(err instanceof HttpErrorResponse)) {
    return { status: 0, message: fallback, fieldErrors: {}, violations: [] };
  }
  const body: ProblemDetails | null =
    err.error && typeof err.error === 'object' ? (err.error as ProblemDetails) : null;
  const violations = Array.isArray(body?.violations) ? body.violations : [];
  const fieldErrors: Record<string, string> = {};
  for (const v of violations) {
    if (!(v.propertyPath in fieldErrors)) fieldErrors[v.propertyPath] = v.message;
  }
  const message = err.status === 0
    ? 'Нет связи с сервером. Проверьте подключение'
    : body?.detail || body?.title || fallback;
  return { status: err.status, message, fieldErrors, violations };
}

/**
 * Сообщение для общего блока ошибки формы: пусто, если ошибка уже показана у одного из полей
 * `shownFields`; иначе — первое «непривязанное» нарушение или общее сообщение.
 */
export function formErrorMessage(error: ApiError, shownFields: readonly string[]): string {
  const rest = error.violations.filter(v => !shownFields.includes(v.propertyPath));
  if (rest.length > 0) return rest.map(v => v.message).join('. ');
  if (error.violations.length > 0) return '';
  return error.message;
}
