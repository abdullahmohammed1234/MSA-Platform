import { describe, expect, it } from 'vitest';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';

describe('useEventFormatting date ranges', () => {
  const { formatDate, formatDateRange, formatTime, formatTimeRange, eventLocalDateKeys } = useEventFormatting();

  it('shows a single date when start and end fall on the same local day', () => {
    const start = new Date(2026, 7, 20, 10, 0).toISOString();
    const end = new Date(2026, 7, 20, 18, 0).toISOString();

    expect(formatDateRange(start, end)).toBe(formatDate(start));
  });

  it('includes the end date for multi-day events', () => {
    const start = new Date(2026, 7, 20, 10, 0).toISOString();
    const end = new Date(2026, 7, 22, 16, 0).toISOString();
    const range = formatDateRange(start, end);

    expect(range).toContain(formatDate(start));
    expect(range).toContain(formatDate(end));
    expect(range).toContain('–');
  });

  it('shows a time range when an end time is present', () => {
    const start = new Date(2026, 7, 20, 18, 0).toISOString();
    const end = new Date(2026, 7, 20, 21, 30).toISOString();
    expect(formatTimeRange(start, end)).toBe(`${formatTime(start)} – ${formatTime(end)}`);
  });

  it('lists every local calendar day a multi-day event occupies', () => {
    const start = new Date(2026, 7, 20, 18, 0).toISOString();
    const end = new Date(2026, 7, 22, 16, 0).toISOString();

    expect(eventLocalDateKeys(start, end)).toEqual(['2026-08-20', '2026-08-21', '2026-08-22']);
  });

  it('occupies only the start day when there is no end date', () => {
    const start = new Date(2026, 7, 20, 18, 0).toISOString();

    expect(eventLocalDateKeys(start, null)).toEqual(['2026-08-20']);
  });
});

describe('useEventFormatting attendance colors', () => {
  const { getStatusStyle, categoryTintStyle, categorySolidStyle } = useEventFormatting();

  it('gives Registered, Attending, and Didn’t come unique colors', () => {
    const registered = getStatusStyle('confirmed');
    const attending = getStatusStyle('checked_in');
    const noShow = getStatusStyle('no_show');

    expect(registered.text).toBe('text-sky-700');
    expect(attending.text).toBe('text-emerald-700');
    expect(noShow.text).toBe('text-rose-700');

    const palette = new Set([registered.bg, attending.bg, noShow.bg]);
    expect(palette.size).toBe(3);
  });

  it('builds category tint and solid styles from hex colors', () => {
    const tint = categoryTintStyle('#2f5d8c');
    const solid = categorySolidStyle('#2f5d8c');

    expect(tint.color).toBe('#2f5d8c');
    expect(tint.backgroundColor).toMatch(/^rgba\(47, 93, 140,/);
    expect(solid.backgroundColor).toBe('#2f5d8c');
    expect(solid.color).toBe('#ffffff');
  });
});
