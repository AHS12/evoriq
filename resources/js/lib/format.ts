import { appLocale } from '@/lib/locale';

/**
 * The single, locale-aware formatting source of truth (FND-02).
 *
 * Every formatter is a pure function driven by {@link appLocale}; none of them
 * use hooks, so they are safe in module-scope helpers (accept `t` for unit
 * labels when translating is needed). Invalid or missing input always renders
 * the placeholder instead of `NaN` / `Invalid Date`, and the server is expected
 * to send raw values (seconds, amounts, ISO timestamps) — never pre-formatted
 * strings.
 */

/** Rendered when a value is null, undefined or invalid. */
export const FORMAT_PLACEHOLDER = '—';

type Numeric = number | null | undefined;
type DateInput = string | number | Date | null | undefined;

function toFinite(value: Numeric): number | null {
    if (value === null || value === undefined) {
        return null;
    }

    const numeric = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(numeric) ? numeric : null;
}

function toDate(value: DateInput): Date | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

/**
 * Grouped number, e.g. `1,284`. `compact` yields `1.2K` / `3.4M` for tight
 * spaces (sparklines, badges).
 */
export function formatNumber(
    value: Numeric,
    options: { compact?: boolean; digits?: number } = {},
): string {
    const numeric = toFinite(value);

    if (numeric === null) {
        return FORMAT_PLACEHOLDER;
    }

    return new Intl.NumberFormat(appLocale(), {
        notation: options.compact ? 'compact' : 'standard',
        maximumFractionDigits: options.digits ?? (options.compact ? 1 : 3),
    }).format(numeric);
}

/**
 * Human duration from seconds: `0m`, `45m`, `1h 5m`, `2h 0m`, `1d 3h`.
 * Sub-minute values render `0m`; hours always carry minutes, days carry hours.
 */
export function formatDuration(seconds: Numeric): string {
    const total = toFinite(seconds);

    if (total === null) {
        return FORMAT_PLACEHOLDER;
    }

    const value = Math.max(0, Math.floor(total));

    if (value < 60) {
        return '0m';
    }

    if (value < 3600) {
        return `${Math.floor(value / 60)}m`;
    }

    if (value < 86400) {
        const hours = Math.floor(value / 3600);
        const minutes = Math.floor((value % 3600) / 60);

        return `${hours}h ${minutes}m`;
    }

    const days = Math.floor(value / 86400);
    const hours = Math.floor((value % 86400) / 3600);

    return `${days}d ${hours}h`;
}

/**
 * Clock-style elapsed time for timers: `04:32`, `1:02:03`.
 */
export function formatClock(seconds: Numeric): string {
    const total = toFinite(seconds);

    if (total === null) {
        return FORMAT_PLACEHOLDER;
    }

    const value = Math.max(0, Math.floor(total));
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    const secs = value % 60;
    const pad = (part: number): string => String(part).padStart(2, '0');

    if (hours > 0) {
        return `${hours}:${pad(minutes)}:${pad(secs)}`;
    }

    return `${pad(minutes)}:${pad(secs)}`;
}

/**
 * Hours with a unit suffix: `1,284 h`, `1,284.5 h`. Accepts seconds by default;
 * pass `{ from: 'hours' }` when the value is already decimal hours.
 */
export function formatHours(
    value: Numeric,
    options: { from?: 'seconds' | 'hours'; digits?: number } = {},
): string {
    const numeric = toFinite(value);

    if (numeric === null) {
        return FORMAT_PLACEHOLDER;
    }

    const hours = options.from === 'hours' ? numeric : numeric / 3600;

    const formatted = new Intl.NumberFormat(appLocale(), {
        maximumFractionDigits: options.digits ?? 1,
    }).format(hours);

    return `${formatted} h`;
}

/**
 * Percentage from a 0–100 value: `78%` (default) or `78.4%` (`{ digits: 1 }`).
 */
export function formatPercent(
    value: Numeric,
    options: { digits?: number } = {},
): string {
    const numeric = toFinite(value);

    if (numeric === null) {
        return FORMAT_PLACEHOLDER;
    }

    const digits = options.digits ?? 0;

    const formatted = new Intl.NumberFormat(appLocale(), {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(numeric);

    return `${formatted}%`;
}

/**
 * Currency with the workspace currency (ISO 4217), falling back to `USD` for
 * an invalid code. Two decimals unless `compact`.
 */
export function formatCurrency(
    amount: Numeric,
    currency = 'USD',
    options: { compact?: boolean } = {},
): string {
    const numeric = toFinite(amount);

    if (numeric === null) {
        return FORMAT_PLACEHOLDER;
    }

    const code = /^[A-Za-z]{3}$/.test(currency)
        ? currency.toUpperCase()
        : 'USD';

    return new Intl.NumberFormat(appLocale(), {
        style: 'currency',
        currency: code,
        notation: options.compact ? 'compact' : 'standard',
        minimumFractionDigits: options.compact ? 0 : 2,
        maximumFractionDigits: options.compact ? 1 : 2,
    }).format(numeric);
}

/**
 * Byte size: `512 B`, `2.4 MB`. Zero is a valid size (`0 B`).
 */
export function formatBytes(bytes: Numeric): string {
    const numeric = toFinite(bytes);

    if (numeric === null) {
        return FORMAT_PLACEHOLDER;
    }

    if (numeric <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    let size = numeric;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    const formatted = new Intl.NumberFormat(appLocale(), {
        maximumFractionDigits: size >= 10 || unit === 0 ? 0 : 1,
    }).format(size);

    return `${formatted} ${units[unit]}`;
}

/**
 * Locale relative time (`3 minutes ago`, `in 2 days`). Beyond a week it falls
 * back to an absolute date.
 */
export function formatRelativeTime(value: DateInput): string {
    const date = toDate(value);

    if (date === null) {
        return FORMAT_PLACEHOLDER;
    }

    const diff = date.getTime() - Date.now();
    const abs = Math.abs(diff);
    const formatter = new Intl.RelativeTimeFormat(appLocale(), {
        numeric: 'auto',
    });

    if (abs < 60_000) {
        return formatter.format(Math.round(diff / 1000), 'second');
    }

    if (abs < 3_600_000) {
        return formatter.format(Math.round(diff / 60_000), 'minute');
    }

    if (abs < 86_400_000) {
        return formatter.format(Math.round(diff / 3_600_000), 'hour');
    }

    if (abs < 604_800_000) {
        return formatter.format(Math.round(diff / 86_400_000), 'day');
    }

    return formatDate(date);
}

/**
 * Date: `Sep 28, 2026`. Pass `{ month: 'long' }` for `September 28, 2026`.
 */
export function formatDate(
    value: DateInput,
    options: { month?: 'short' | 'long' } = {},
): string {
    const date = toDate(value);

    if (date === null) {
        return FORMAT_PLACEHOLDER;
    }

    return new Intl.DateTimeFormat(appLocale(), {
        year: 'numeric',
        month: options.month ?? 'short',
        day: 'numeric',
    }).format(date);
}

/**
 * Time: `4:32 PM`.
 */
export function formatTime(value: DateInput): string {
    const date = toDate(value);

    if (date === null) {
        return FORMAT_PLACEHOLDER;
    }

    return new Intl.DateTimeFormat(appLocale(), {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

/**
 * Month label for chart axes: `Sep` (default) or `Sep 2026` (`{ year: true }`).
 */
export function formatMonth(
    value: DateInput,
    options: { year?: boolean } = {},
): string {
    const date = toDate(value);

    if (date === null) {
        return FORMAT_PLACEHOLDER;
    }

    return new Intl.DateTimeFormat(appLocale(), {
        month: 'short',
        ...(options.year ? { year: 'numeric' } : {}),
    }).format(date);
}

/**
 * Date + time: `Sep 28, 2026, 4:32 PM`. Pass `{ seconds: true }` to include
 * seconds (audit trails).
 */
export function formatDateTime(
    value: DateInput,
    options: { seconds?: boolean } = {},
): string {
    const date = toDate(value);

    if (date === null) {
        return FORMAT_PLACEHOLDER;
    }

    return new Intl.DateTimeFormat(appLocale(), {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        ...(options.seconds ? { second: '2-digit' } : {}),
    }).format(date);
}

/**
 * Throughput: `1,240 rows/s`. The `unit` label is translatable — pass
 * `t('rows/s')` from the calling component.
 */
export function formatThroughput(
    records: Numeric,
    seconds: Numeric,
    unit = 'rows/s',
): string {
    const count = toFinite(records);
    const duration = toFinite(seconds);

    if (count === null || duration === null || duration <= 0) {
        return FORMAT_PLACEHOLDER;
    }

    const formatted = new Intl.NumberFormat(appLocale(), {
        maximumFractionDigits: 0,
    }).format(count / duration);

    return `${formatted} ${unit}`;
}
