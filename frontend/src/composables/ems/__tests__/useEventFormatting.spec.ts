import { describe, expect, it } from 'vitest';
import { useEventFormatting } from '@/composables/ems/useEventFormatting';

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
