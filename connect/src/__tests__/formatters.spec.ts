import test from 'node:test';
import assert from 'node:assert/strict';
import {
  formatEventDate,
  formatEventTime,
  formatEventDateTime,
  formatCurrency,
} from '../utils/formatters.ts';

test('Formatters & Timezone Handling', async (t) => {
  await t.test('formats event date accurately', () => {
    const isoDate = '2026-10-14T18:00:00Z';
    const formatted = formatEventDate(isoDate, 'UTC');
    assert.match(formatted, /Oct 14, 2026/);
  });

  await t.test('returns "Date TBA" for null, undefined, or malformed date', () => {
    assert.equal(formatEventDate(null), 'Date TBA');
    assert.equal(formatEventDate(undefined), 'Date TBA');
    assert.equal(formatEventDate('invalid-date'), 'Date TBA');
  });

  await t.test('formats event time range accurately with UTC timezone', () => {
    const start = '2026-10-14T18:00:00Z';
    const end = '2026-10-14T20:30:00Z';
    const formatted = formatEventTime(start, end, 'UTC');
    assert.match(formatted, /6:00/);
    assert.match(formatted, /8:30/);
  });

  await t.test('returns "Time TBA" for null or undefined start time', () => {
    assert.equal(formatEventTime(null), 'Time TBA');
    assert.equal(formatEventTime(undefined), 'Time TBA');
  });

  await t.test('formats event date and time combined', () => {
    const isoDate = '2026-10-14T18:00:00Z';
    const formatted = formatEventDateTime(isoDate, 'UTC');
    assert.match(formatted, /Oct 14, 2026/);
    assert.match(formatted, /6:00/);
  });

  await t.test('formats currency and free admission', () => {
    assert.equal(formatCurrency(0), 'Free');
    assert.equal(formatCurrency(null), 'Free');
    assert.equal(formatCurrency(undefined), 'Free');
    assert.match(formatCurrency(15.5), /\$15\.50/);
    assert.match(formatCurrency(100), /\$100\.00/);
  });
});
