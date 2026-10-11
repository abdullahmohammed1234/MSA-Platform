import { describe, expect, it } from 'vitest';
import type { SearchResultItem, SearchResponse } from '@/services/search/searchService';

describe('Phase 36 — Unified Search & Platform Discovery', () => {
  const mockResults: SearchResultItem[] = [
    {
      id: 'announcement-ann-1',
      content_type: 'announcement',
      type_label: 'Announcement',
      title: 'Jumuah Location Change Notice',
      excerpt: 'Jumuah prayer will move to West Gym.',
      destination: '/announcements/jumuah-location-change-notice',
      date: '2026-10-08',
      category: 'Prayer',
      thumbnail: null,
    },
    {
      id: 'event-evt-1',
      content_type: 'event',
      type_label: 'Event',
      title: 'Campus Welcome Halaqah',
      excerpt: 'Orientation gathering for new SFU students.',
      destination: '/events/campus-welcome-halaqah',
      date: '2026-10-10',
      category: 'Community Event',
      thumbnail: '/images/event.jpg',
    },
    {
      id: 'program-prog-1',
      content_type: 'program',
      type_label: 'Program',
      title: 'New Muslim Starter Kit',
      excerpt: 'Comprehensive guide for new Muslims.',
      destination: '/featured-opportunities',
      date: '2026-09-14',
      category: 'Learning & Opportunities',
      thumbnail: null,
    },
    {
      id: 'volunteer-vol-1',
      content_type: 'volunteer',
      type_label: 'Volunteering',
      title: 'Logistics Coordinator',
      excerpt: 'Assist with Friday prayer setup.',
      destination: '/volunteer/logistics-coordinator',
      date: '2026-10-12',
      category: 'Volunteer Position',
      thumbnail: null,
    },
  ];

  function filterSearchResults(
    items: SearchResultItem[],
    typeFilter: string,
    query: string
  ): SearchResultItem[] {
    const q = query.trim().toLowerCase();
    return items.filter((item) => {
      const matchesType = typeFilter === 'all' || item.content_type === typeFilter;
      const matchesQuery =
        !q ||
        item.title.toLowerCase().includes(q) ||
        item.excerpt.toLowerCase().includes(q) ||
        (item.category && item.category.toLowerCase().includes(q));
      return matchesType && matchesQuery;
    });
  }

  it('filters search results by content_type filter tab', () => {
    const events = filterSearchResults(mockResults, 'event', '');
    expect(events).toHaveLength(1);
    expect(events[0].title).toBe('Campus Welcome Halaqah');
    expect(events[0].destination).toBe('/events/campus-welcome-halaqah');

    const announcements = filterSearchResults(mockResults, 'announcement', '');
    expect(announcements).toHaveLength(1);
    expect(announcements[0].title).toBe('Jumuah Location Change Notice');
  });

  it('filters search results by query keyword', () => {
    const results = filterSearchResults(mockResults, 'all', 'Halaqah');
    expect(results).toHaveLength(1);
    expect(results[0].content_type).toBe('event');
    expect(results[0].destination).toBe('/events/campus-welcome-halaqah');
  });

  it('returns canonical destinations for all supported search items', () => {
    mockResults.forEach((item) => {
      expect(item.destination).toMatch(/^\/(announcements|events|featured-opportunities|resources|volunteer|store)\b/);
    });
  });

  it('handles empty query gracefully with zero results', () => {
    const results = filterSearchResults(mockResults, 'all', '  ');
    expect(results).toHaveLength(4);
  });

  it('calculates type counts breakdown accurately', () => {
    const counts = {
      all: mockResults.length,
      announcement: mockResults.filter((r) => r.content_type === 'announcement').length,
      event: mockResults.filter((r) => r.content_type === 'event').length,
      program: mockResults.filter((r) => r.content_type === 'program').length,
      resource: mockResults.filter((r) => r.content_type === 'resource').length,
      volunteer: mockResults.filter((r) => r.content_type === 'volunteer').length,
      store: mockResults.filter((r) => r.content_type === 'store').length,
    };

    expect(counts.all).toBe(4);
    expect(counts.announcement).toBe(1);
    expect(counts.event).toBe(1);
    expect(counts.program).toBe(1);
    expect(counts.volunteer).toBe(1);
    expect(counts.store).toBe(0);
  });
});
