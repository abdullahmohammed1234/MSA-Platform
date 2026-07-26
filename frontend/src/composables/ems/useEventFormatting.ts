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

  const formatDate = (value: string | null | undefined): string => {
    if (!value) return '—';

    return new Date(value).toLocaleDateString(undefined, {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  };

  const formatTime = (value: string | null | undefined): string => {
    if (!value) return '—';

    return new Date(value).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  };

  const formatDateTime = (value: string | null | undefined): string => {
    if (!value) return '—';

    return `${formatDate(value)} · ${formatTime(value)}`;
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

    // Green statuses
    if (['confirmed', 'paid', 'checked_in', 'registration_open'].includes(s)) {
      return {
        bg: 'bg-emerald-50/80',
        text: 'text-emerald-700',
        border: 'border-emerald-200/60',
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', // Check circle
      };
    }

    // Orange/Yellow statuses
    if (['awaiting_payment', 'pending', 'live'].includes(s)) {
      return {
        bg: 'bg-amber-50/80',
        text: 'text-amber-700',
        border: 'border-amber-200/60',
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', // Clock
      };
    }

    // Purple statuses
    if (['waitlisted', 'registration_closed'].includes(s)) {
      return {
        bg: 'bg-purple-50/80',
        text: 'text-purple-700',
        border: 'border-purple-200/60',
        icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', // Waitlist/Briefcase
      };
    }

    // Red statuses
    if (['cancelled', 'refunded', 'failed'].includes(s)) {
      return {
        bg: 'bg-rose-50/80',
        text: 'text-rose-700',
        border: 'border-rose-200/60',
        icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z', // Cancel/X
      };
    }

    // Blue statuses
    if (['published'].includes(s)) {
      return {
        bg: 'bg-blue-50/80',
        text: 'text-blue-700',
        border: 'border-blue-200/60',
        icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', // Info circle
      };
    }

    // Default Gray status (not_checked_in, draft, completed, archived)
    return {
      bg: 'bg-neutral-50/80',
      text: 'text-neutral-600',
      border: 'border-neutral-200/60',
      icon: 'M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z', // Minus circle
    };
  };

  return {
    badgeVariant,
    dotClass,
    formatDate,
    formatTime,
    formatDateTime,
    formatRelative,
    toDateTimeLocal,
    fromDateTimeLocal,
    getStatusStyle,
  };
}
