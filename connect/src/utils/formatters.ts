/**
 * Formatting utilities for dates, times, currencies, and event statuses.
 */

export function formatEventDate(dateString: string | null | undefined, timezone?: string): string {
  if (!dateString) return 'Date TBA';
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'Date TBA';

    return new Intl.DateTimeFormat('en-CA', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      timeZone: timezone || undefined,
    }).format(date);
  } catch {
    return dateString;
  }
}

export function formatEventTime(
  startAt: string | null | undefined,
  endAt?: string | null | undefined,
  timezone?: string
): string {
  if (!startAt) return 'Time TBA';
  try {
    const startDate = new Date(startAt);
    if (isNaN(startDate.getTime())) return 'Time TBA';

    const timeOptions: Intl.DateTimeFormatOptions = {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
      timeZone: timezone || undefined,
    };

    const startTime = new Intl.DateTimeFormat('en-CA', timeOptions).format(startDate);

    if (endAt) {
      const endDate = new Date(endAt);
      if (!isNaN(endDate.getTime())) {
        const endTime = new Intl.DateTimeFormat('en-CA', timeOptions).format(endDate);
        return `${startTime} – ${endTime}`;
      }
    }

    return startTime;
  } catch {
    return 'Time TBA';
  }
}

export function formatEventDateTime(
  dateString: string | null | undefined,
  timezone?: string
): string {
  if (!dateString) return 'Date TBA';
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'Date TBA';

    return new Intl.DateTimeFormat('en-CA', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
      timeZone: timezone || undefined,
    }).format(date);
  } catch {
    return dateString;
  }
}

export function formatRelativeTime(dateString: string | null | undefined): string {
  if (!dateString) return '';
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';

    const now = new Date();
    const diffMs = date.getTime() - now.getTime();
    const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Tomorrow';
    if (diffDays === -1) return 'Yesterday';
    if (diffDays > 1 && diffDays < 7) return `In ${diffDays} days`;
    if (diffDays < -1 && diffDays > -7) return `${Math.abs(diffDays)} days ago`;

    return formatEventDate(dateString);
  } catch {
    return '';
  }
}

export function formatCurrency(amount: number | null | undefined, currency = 'CAD'): string {
  if (amount === null || amount === undefined || amount === 0) {
    return 'Free';
  }
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency,
  }).format(amount);
}
