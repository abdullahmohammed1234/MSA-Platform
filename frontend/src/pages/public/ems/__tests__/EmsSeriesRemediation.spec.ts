import { describe, expect, it } from 'vitest';
import type { PublicEvent } from '@/types/ems/public';

describe('Phase 34.1 — Authoritative Event Series Grouping', () => {
  function computeSeriesGroups(events: PublicEvent[]) {
    const map = new Map<string, { title: string; category?: string; events: PublicEvent[] }>();
    for (const item of events) {
      if (!item.series?.uuid || !item.series?.name) continue;
      const key = item.series.uuid;
      const title = item.series.name;
      if (!map.has(key)) {
        map.set(key, { title, category: item.category?.name, events: [] });
      }
      map.get(key)!.events.push(item);
    }
    return Array.from(map.values());
  }

  it('groups two events belonging to the same series into one group', () => {
    const mockEvents: PublicEvent[] = [
      {
        uuid: 'evt-1',
        name: 'Halaqa Week 1',
        slug: 'halaqa-week-1',
        short_description: null,
        banner_url: null,
        category: { uuid: 'cat-1', name: 'Education', slug: 'education', color: '#008080' },
        series: { uuid: 'series-1', name: 'Weekly Halaqa Series' },
        location: 'MSA Hall',
        start_at: '2026-10-15T18:00:00Z',
        end_at: '2026-10-15T20:00:00Z',
        timezone: 'America/Vancouver',
        status: 'published',
        status_label: 'Published',
        status_tone: 'success',
        capacity: 50,
        remaining_capacity: 40,
        is_full: false,
        is_accepting_registrations: true,
        registration_label: 'Open',
      },
      {
        uuid: 'evt-2',
        name: 'Halaqa Week 2',
        slug: 'halaqa-week-2',
        short_description: null,
        banner_url: null,
        category: { uuid: 'cat-1', name: 'Education', slug: 'education', color: '#008080' },
        series: { uuid: 'series-1', name: 'Weekly Halaqa Series' },
        location: 'MSA Hall',
        start_at: '2026-10-22T18:00:00Z',
        end_at: '2026-10-22T20:00:00Z',
        timezone: 'America/Vancouver',
        status: 'published',
        status_label: 'Published',
        status_tone: 'success',
        capacity: 50,
        remaining_capacity: 35,
        is_full: false,
        is_accepting_registrations: true,
        registration_label: 'Open',
      },
    ];

    const groups = computeSeriesGroups(mockEvents);
    expect(groups).toHaveLength(1);
    expect(groups[0].title).toBe('Weekly Halaqa Series');
    expect(groups[0].events).toHaveLength(2);
  });

  it('separates events in the same category that belong to different series', () => {
    const mockEvents: PublicEvent[] = [
      {
        uuid: 'evt-1',
        name: 'Halaqa Session',
        slug: 'halaqa-session',
        short_description: null,
        banner_url: null,
        category: { uuid: 'cat-1', name: 'Education', slug: 'education', color: '#008080' },
        series: { uuid: 'series-1', name: 'Halaqa Series' },
        location: 'MSA Hall',
        start_at: '2026-10-15T18:00:00Z',
        end_at: null,
        timezone: 'America/Vancouver',
        status: 'published',
        status_label: 'Published',
        status_tone: 'success',
        capacity: 50,
        remaining_capacity: 40,
        is_full: false,
        is_accepting_registrations: true,
        registration_label: 'Open',
      },
      {
        uuid: 'evt-2',
        name: 'Tafseer Class',
        slug: 'tafseer-class',
        short_description: null,
        banner_url: null,
        category: { uuid: 'cat-1', name: 'Education', slug: 'education', color: '#008080' },
        series: { uuid: 'series-2', name: 'Tafseer Series' },
        location: 'MSA Hall',
        start_at: '2026-10-16T18:00:00Z',
        end_at: null,
        timezone: 'America/Vancouver',
        status: 'published',
        status_label: 'Published',
        status_tone: 'success',
        capacity: 50,
        remaining_capacity: 35,
        is_full: false,
        is_accepting_registrations: true,
        registration_label: 'Open',
      },
    ];

    const groups = computeSeriesGroups(mockEvents);
    expect(groups).toHaveLength(2);
    expect(groups[0].title).toBe('Halaqa Series');
    expect(groups[1].title).toBe('Tafseer Series');
  });

  it('excludes events without an authoritative series relationship from series groups', () => {
    const mockEvents: PublicEvent[] = [
      {
        uuid: 'evt-standalone',
        name: 'One-time Workshop',
        slug: 'one-time-workshop',
        short_description: null,
        banner_url: null,
        category: { uuid: 'cat-1', name: 'Education', slug: 'education', color: '#008080' },
        series: null,
        location: 'SFU SUB',
        start_at: '2026-10-20T18:00:00Z',
        end_at: null,
        timezone: 'America/Vancouver',
        status: 'published',
        status_label: 'Published',
        status_tone: 'success',
        capacity: 100,
        remaining_capacity: 80,
        is_full: false,
        is_accepting_registrations: true,
        registration_label: 'Open',
      },
    ];

    const groups = computeSeriesGroups(mockEvents);
    expect(groups).toHaveLength(0);
  });
});
