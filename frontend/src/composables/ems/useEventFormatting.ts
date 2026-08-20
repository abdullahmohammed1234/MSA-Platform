import type { EventStatusTone } from '@/types/ems';

/**
 * Presentation helpers shared by the event list, detail and dashboard.
 *
 * The API sends ISO 8601 timestamps and a semantic status *tone*; mapping the
 * tone onto the design-system palette happens here, once, rather than in each
 * component.
 */

type BadgeVariant = 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info' | 'gold' | 'outline';

const TONE_TO_BADGE: Record<EventStatusTone, BadgeVariant> = {
  neutral: 'outline',
  info: 'info',
  success: 'success',
  warning: 'warning',
  live: 'error',
  muted: 'secondary',
  danger: 'error',
};

const TONE_TO_DOT: Record<EventStatusTone, string> = {
  neutral: 'bg-neutral-400',
  info: 'bg-sky-500',
  success: 'bg-emerald-500',
  warning: 'bg-amber-500',
  live: 'bg-red-500',
  muted: 'bg-neutral-300',
  danger: 'bg-red-600',
};

export function useEventFormatting() {
  const badgeVariant = (tone: EventStatusTone | undefined): BadgeVariant =>
    (tone && TONE_TO_BADGE[tone]) || 'outline';

  const dotClass = (tone: EventStatusTone | undefined): string =>
    (tone && TONE_TO_DOT[tone]) || TONE_TO_DOT.neutral;

  const parseDate = (value: string | null | undefined): Date | null => {
    if (!value) return null;

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
  };

  const sameLocalDay = (a: Date, b: Date): boolean =>
    a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

  const localDateKey = (date: Date): string =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

  const formatDate = (value: string | null | undefined): string => {
    const date = parseDate(value);

    if (!date) return '—';

    return date.toLocaleDateString(undefined, {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  };

  const formatTime = (value: string | null | undefined): string => {
    const date = parseDate(value);

    if (!date) return '—';

    return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  };

  const formatDateTime = (value: string | null | undefined): string => {
    if (!parseDate(value)) return '—';

    return `${formatDate(value)} · ${formatTime(value)}`;
  };

  /** Single-day events stay as one date; multi-day events include the end date. */
  const formatDateRange = (
    startAt: string | null | undefined,
    endAt: string | null | undefined,
  ): string => {
    const start = parseDate(startAt);

    if (!start) return '—';

    const end = parseDate(endAt);

    if (!end || sameLocalDay(start, end)) {
      return formatDate(startAt);
    }

    return `${formatDate(startAt)} – ${formatDate(endAt)}`;
  };

  const formatTimeRange = (
    startAt: string | null | undefined,
    endAt: string | null | undefined,
  ): string => {
    const start = parseDate(startAt);

    if (!start) return '—';

    const end = parseDate(endAt);

    if (!end) return formatTime(startAt);

    return `${formatTime(startAt)} – ${formatTime(endAt)}`;
  };

  /**
   * Local calendar days an event occupies, inclusive of start and end.
   * Used by the public month/week calendar so multi-day events appear on every day.
   */
  const eventLocalDateKeys = (
    startAt: string | null | undefined,
    endAt: string | null | undefined,
    maxDays = 366,
  ): string[] => {
    const start = parseDate(startAt);

    if (!start) return [];

    const parsedEnd = parseDate(endAt);
    const end = parsedEnd && parsedEnd >= start ? parsedEnd : start;
    const keys: string[] = [];
    const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());
    const last = new Date(end.getFullYear(), end.getMonth(), end.getDate());

    for (let i = 0; i < maxDays && cursor <= last; i++) {
      keys.push(localDateKey(cursor));
      cursor.setDate(cursor.getDate() + 1);
    }

    return keys;
  };

  /** "in 3 days" / "2 hours ago", used by the activity feed. */
  const formatRelative = (value: string | null | undefined): string => {
    if (!value) return '—';

    const deltaSeconds = (new Date(value).getTime() - Date.now()) / 1000;
    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
      ['year', 31536000],
      ['month', 2592000],
      ['week', 604800],
      ['day', 86400],
      ['hour', 3600],
      ['minute', 60],
    ];

    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    for (const [unit, seconds] of units) {
      if (Math.abs(deltaSeconds) >= seconds) {
        return formatter.format(Math.round(deltaSeconds / seconds), unit);
      }
    }

    return formatter.format(Math.round(deltaSeconds), 'second');
  };

  /**
   * An ISO timestamp as the value a `datetime-local` input expects, in the
   * browser's timezone.
   */
  const toDateTimeLocal = (value: string | null | undefined): string => {
    if (!value) return '';

    const date = new Date(value);
    const offsetMs = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - offsetMs).toISOString().slice(0, 16);
  };

  /** The inverse of `toDateTimeLocal`, for submitting a form value. */
  const fromDateTimeLocal = (value: string): string | null => {
    if (!value) return null;

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : date.toISOString();
  };

  const getStatusStyle = (status: string | undefined) => {
    const s = status?.toLowerCase() || '';

    // Registered — sky, distinct from attending
    if (['confirmed', 'registered'].includes(s)) {
      return {
        bg: 'bg-sky-50/80',
        text: 'text-sky-700',
        border: 'border-sky-200/60',
        icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
      };
    }

    // Attending / paid / open — emerald
    if (['paid', 'checked_in', 'attending', 'registration_open'].includes(s)) {
      return {
        bg: 'bg-emerald-50/80',
        text: 'text-emerald-700',
        border: 'border-emerald-200/60',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
      };
    }

    // Didn't come
    if (['no_show', 'didnt_come', "didn't come"].includes(s)) {
      return {
        bg: 'bg-rose-50/80',
        text: 'text-rose-700',
        border: 'border-rose-200/60',
        icon: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
      };
    }

    // Orange/Yellow statuses
    if (['awaiting_payment', 'pending', 'live'].includes(s)) {
      return {
        bg: 'bg-amber-50/80',
        text: 'text-amber-700',
        border: 'border-amber-200/60',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
      };
    }

    // Purple statuses
    if (['waitlisted', 'registration_closed'].includes(s)) {
      return {
        bg: 'bg-purple-50/80',
        text: 'text-purple-700',
        border: 'border-purple-200/60',
        icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
      };
    }

    // Red statuses
    if (['cancelled', 'refunded', 'failed'].includes(s)) {
      return {
        bg: 'bg-rose-50/80',
        text: 'text-rose-700',
        border: 'border-rose-200/60',
        icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
      };
    }

    // Blue statuses
    if (['published'].includes(s)) {
      return {
        bg: 'bg-blue-50/80',
        text: 'text-blue-700',
        border: 'border-blue-200/60',
        icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
      };
    }

    // Default Gray status (not_checked_in, draft, completed, archived)
    return {
      bg: 'bg-neutral-50/80',
      text: 'text-neutral-600',
      border: 'border-neutral-200/60',
      icon: 'M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z',
    };
  };

  const FALLBACK_CATEGORY_COLOR = '#6b7280';

  const normalizeHexColor = (value: string | null | undefined): string => {
    const raw = (value ?? '').trim();
    if (/^#([0-9a-f]{6})$/i.test(raw)) return raw;
    if (/^#([0-9a-f]{3})$/i.test(raw)) {
      const [, short] = raw.match(/^#([0-9a-f]{3})$/i) ?? [];
      if (!short) return FALLBACK_CATEGORY_COLOR;
      return `#${short[0]}${short[0]}${short[1]}${short[1]}${short[2]}${short[2]}`;
    }
    return FALLBACK_CATEGORY_COLOR;
  };

  const hexToRgba = (hex: string, alpha: number): string => {
    const normalized = normalizeHexColor(hex);
    const r = Number.parseInt(normalized.slice(1, 3), 16);
    const g = Number.parseInt(normalized.slice(3, 5), 16);
    const b = Number.parseInt(normalized.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  };

  const isDarkHex = (hex: string): boolean => {
    const normalized = normalizeHexColor(hex);
    const r = Number.parseInt(normalized.slice(1, 3), 16);
    const g = Number.parseInt(normalized.slice(3, 5), 16);
    const b = Number.parseInt(normalized.slice(5, 7), 16);
    return (r * 299 + g * 587 + b * 114) / 1000 < 150;
  };

  /** Soft pill used on event lists and calendar chips. */
  const categoryTintStyle = (color: string | null | undefined) => {
    const hex = normalizeHexColor(color);
    return {
      backgroundColor: hexToRgba(hex, 0.14),
      color: hex,
      borderColor: hexToRgba(hex, 0.38),
    };
  };

  /** Solid pill for selected filters and dark overlays. */
  const categorySolidStyle = (color: string | null | undefined) => {
    const hex = normalizeHexColor(color);
    return {
      backgroundColor: hex,
      color: isDarkHex(hex) ? '#ffffff' : '#111827',
      borderColor: hex,
    };
  };

  return {
    badgeVariant,
    dotClass,
    formatDate,
    formatTime,
    formatDateTime,
    formatDateRange,
    formatTimeRange,
    eventLocalDateKeys,
    localDateKey,
    formatRelative,
    toDateTimeLocal,
    fromDateTimeLocal,
    getStatusStyle,
    categoryTintStyle,
    categorySolidStyle,
  };
}
