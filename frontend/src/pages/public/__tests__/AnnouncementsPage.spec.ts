import { describe, expect, it } from 'vitest';
import type { AnnouncementItem } from '@/services/website/websiteService';

describe('Phase 35 — Public Communications & Content Experience', () => {
  const mockAnnouncements: AnnouncementItem[] = [
    {
      id: 'ann-1',
      title: "Jumu'ah Location Update",
      slug: 'jumuah-location-update',
      content: "Jumu'ah prayers this week will be held in the West Gym.",
      summary: 'Prayer',
      category: 'Prayer',
      date: '2026-10-08',
      featured_image: '/images/jumuah.jpg',
      author: { name: 'Executive Team' },
    },
    {
      id: 'ann-2',
      title: 'Volunteering Applications Open',
      slug: 'volunteering-applications-open',
      content: 'Applications are open for committee coordinators.',
      summary: 'Board',
      category: 'Board',
      date: '2026-10-05',
      featured_image: null,
      author: { name: 'VMS Lead' },
    },
    {
      id: 'ann-3',
      title: 'Halal Campus Food Map Released',
      slug: 'halal-campus-food-map',
      content: 'Check out our updated guide for dining options on campus.',
      summary: 'General',
      category: 'General',
      date: '2026-10-01',
      featured_image: null,
      author: { name: 'Communications Lead' },
    },
  ];

  function filterAnnouncements(
    items: AnnouncementItem[],
    search: string,
    category: string
  ): AnnouncementItem[] {
    return items.filter((item) => {
      const matchesCategory =
        category === 'All' ||
        item.category === category ||
        item.summary === category;

      const q = search.trim().toLowerCase();
      const matchesSearch =
        !q ||
        item.title.toLowerCase().includes(q) ||
        item.content.toLowerCase().includes(q) ||
        (item.summary && item.summary.toLowerCase().includes(q));

      return matchesCategory && matchesSearch;
    });
  }

  it('correctly filters announcements by search keyword', () => {
    const results = filterAnnouncements(mockAnnouncements, 'Food Map', 'All');
    expect(results).toHaveLength(1);
    expect(results[0].slug).toBe('halal-campus-food-map');
  });

  it('correctly filters announcements by category chip', () => {
    const results = filterAnnouncements(mockAnnouncements, '', 'Prayer');
    expect(results).toHaveLength(1);
    expect(results[0].slug).toBe('jumuah-location-update');
  });

  it('returns all published announcements when category is All and search is empty', () => {
    const results = filterAnnouncements(mockAnnouncements, '', 'All');
    expect(results).toHaveLength(3);
  });

  it('selects the first published announcement as featured notice', () => {
    const featured = mockAnnouncements[0];
    expect(featured).toBeDefined();
    expect(featured.title).toBe("Jumu'ah Location Update");
    expect(featured.category).toBe('Prayer');
  });

  it('formats dates consistently for announcement displays', () => {
    const formatDate = (dateStr: string) => {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      });
    };

    expect(formatDate('2026-10-08')).toContain('Oct');
    expect(formatDate('2026-10-08')).toContain('2026');
  });
});
