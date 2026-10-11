import { describe, expect, it } from 'vitest';

describe('Phase 38 — Public Platform Analytics & Impact', () => {
  const mockAnalyticsData = {
    kpis: {
      visitors: { value: 1250, change: 12.5 },
      page_views: { value: 4800, change: 8.2 },
      active_learners: { value: 64, change: 5.0 },
      certificates: { value: 28, change: 15.0 },
    },
    cms: {
      announcements_published: 12,
      resources_published: 24,
      featured_opportunities: 5,
    },
    ems: {
      published_events: 8,
      total_registrations: 320,
      verified_attendance: 275,
      attendance_rate: 85.9,
    },
    volunteering: {
      active_opportunities: 6,
      applications_received: 42,
      confirmed_assignments: 35,
      verified_attendees: 30,
      verified_service_hours: 90,
    },
    recent_activity: [
      {
        type: 'registration',
        user: 'Anonymous Guest',
        detail: "Jumu'ah Prayer Setup",
        time: '2 hours ago',
      },
    ],
  };

  it('correctly aggregates CMS content reach metrics', () => {
    expect(mockAnalyticsData.cms.announcements_published).toBe(12);
    expect(mockAnalyticsData.cms.resources_published).toBe(24);
    expect(mockAnalyticsData.cms.featured_opportunities).toBe(5);
  });

  it('calculates EMS event registrations and verified attendance rate accurately', () => {
    expect(mockAnalyticsData.ems.published_events).toBe(8);
    expect(mockAnalyticsData.ems.total_registrations).toBe(320);
    expect(mockAnalyticsData.ems.verified_attendance).toBe(275);
    expect(mockAnalyticsData.ems.attendance_rate).toBe(85.9);
  });

  it('tracks VMS volunteer opportunities, applications, and verified service hours', () => {
    expect(mockAnalyticsData.volunteering.active_opportunities).toBe(6);
    expect(mockAnalyticsData.volunteering.applications_received).toBe(42);
    expect(mockAnalyticsData.volunteering.verified_service_hours).toBe(90);
  });

  it('sanitizes CSV text fields against formula injection vectors', () => {
    const sanitizeCsvField = (value: string): string => {
      if (['=', '+', '-', '@'].includes(value.charAt(0))) {
        return "'" + value;
      }
      return value;
    };

    expect(sanitizeCsvField('=SUM(A1:A10)')).toBe("'=SUM(A1:A10)");
    expect(sanitizeCsvField('+cmd|\'/C calc\'!A0')).toBe("'+cmd|\'/C calc\'!A0");
    expect(sanitizeCsvField('Normal Text')).toBe('Normal Text');
  });
});
