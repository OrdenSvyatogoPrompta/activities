/** «год / года / лет» по последнему числу. */
export function yearsLabel(n: number): string {
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) return 'год';
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'года';
  return 'лет';
}

/** `YYYY-MM-DD` → `DD.MM.YYYY`. */
export function formatDate(iso: string): string {
  const [y, m, d] = iso.slice(0, 10).split('-');
  return d ? `${d}.${m}.${y}` : iso;
}

/** Неотрицательное целое из поля ввода или `null`. */
export function toInt(value: string): number | null {
  const trimmed = value.trim();
  return /^\d+$/.test(trimmed) ? Number(trimmed) : null;
}
