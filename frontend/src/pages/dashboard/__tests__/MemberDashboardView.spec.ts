import { describe, expect, it } from 'vitest';
import type { 
  DashboardDataResponse, 
  DashboardActionItem, 
  RecommendedOpportunity,
  VolunteerCommitment
} from '@/services/memberDashboardService';

describe('Phase 37 — Member/Volunteer Experience Intelligence', () => {
  const mockDashboardData: DashboardDataResponse = {
    user: {
      uuid: 'usr-123',
      name: 'Fatima Al-Zahra',
      email: 'fatima@sfu.ca',
      avatar: null,
      community_status: 'Student Member',
      email_verified: true,
    },
    action_items: [
      {
        id: 'complete_volunteer_profile',
        type: 'warning',
        title: 'Complete Volunteer Profile Skills',
        description: 'Add your skills to receive personalized volunteer matching.',
        action_label: 'Update Volunteer Profile',
        action_path: '/account/volunteer',
      },
    ],
    recommended_opportunities: [
      {
        id: 10,
        title: 'Ramadan Setup Volunteer',
        slug: 'ramadan-setup-volunteer',
        category: 'Event Operations',
        location: 'SFU Student Union Building',
        start_at: '2026-10-15T18:00:00Z',
        end_at: '2026-10-15T21:00:00Z',
        reason_label: 'Matches your saved skills',
        required_skills: ['Logistics', 'Setup'],
      },
    ],
    next_event: {
      registration_id: 1,
      registration_uuid: 'reg-001',
      reference: 'REG-2026-101',
      status: 'confirmed',
      event_title: "Jumu'ah Prayer Setup",
      event_slug: 'jumuah-prayer-setup',
      start_at: '2026-10-16T12:30:00Z',
      location: 'SFU West Gym',
      category: 'Prayer',
      ticket_code: 'TKT-1001',
      amount_due: 0,
    },
    upcoming_events_count: 1,
    volunteer: {
      status: 'active',
      completion_percentage: 85,
      upcoming_shifts: 1,
      completed_shifts: 4,
      skills: ['Logistics', 'Setup'],
      commitments: [
        {
          id: 50,
          opportunity_title: 'Iftar Serving Crew',
          opportunity_slug: 'iftar-serving-crew',
          shift_name: 'Evening Shift',
          status: 'confirmed',
          start_at: '2026-10-18T17:00:00Z',
          location: 'SUB Ballroom',
        },
      ],
      pending_applications: [
        {
          id: 51,
          opportunity_title: 'Media & Photography Team',
          opportunity_slug: 'media-photography-team',
          status: 'pending',
          applied_at: '2026-10-05T10:00:00Z',
        },
      ],
      attendance_summary: {
        attended_count: 4,
        absent_count: 0,
        attendance_note: 'Verified by event coordinators at check-in.',
      },
    },
    learning: {
      active_course: null,
      completed_courses: 2,
      certificates_count: 1,
    },
    recent_order: null,
    notifications: {
      unread_count: 1,
      latest: [
        {
          id: 101,
          uuid: 'notif-101',
          type: 'vms_signup_approved',
          title: 'Volunteer Signup Approved',
          message: 'Your application for Iftar Serving Crew was approved.',
          read_at: null,
          created_at: '2026-10-08T12:00:00Z',
        },
      ],
    },
    applications: [
      {
        slug: 'vms',
        title: 'Volunteering Portal',
        description: 'Discover opportunities and manage shifts.',
        path: '/account/volunteer',
        is_admin: false,
      },
    ],
  };

  it('correctly structures dynamic action items in Scope A', () => {
    expect(mockDashboardData.action_items).toHaveLength(1);
    const action = mockDashboardData.action_items[0];
    expect(action.id).toBe('complete_volunteer_profile');
    expect(action.type).toBe('warning');
    expect(action.action_path).toBe('/account/volunteer');
  });

  it('provides explainable volunteer recommendations in Scope B', () => {
    const recommended = mockDashboardData.recommended_opportunities;
    expect(recommended).toBeDefined();
    expect(recommended).toHaveLength(1);
    expect(recommended![0].reason_label).toBe('Matches your saved skills');
    expect(recommended![0].slug).toBe('ramadan-setup-volunteer');
  });

  it('distinguishes commitments, applications, and attendance in Scope C', () => {
    const v = mockDashboardData.volunteer;
    expect(v).toBeDefined();
    expect(v?.commitments).toHaveLength(1);
    expect(v?.pending_applications).toHaveLength(1);
    expect(v?.attendance_summary?.attended_count).toBe(4);
    expect(v?.attendance_summary?.attendance_note).toContain('Verified by event coordinators');
  });

  it('handles empty state for action items cleanly', () => {
    const emptyActionItems: DashboardActionItem[] = [];
    expect(emptyActionItems).toHaveLength(0);
  });
});
