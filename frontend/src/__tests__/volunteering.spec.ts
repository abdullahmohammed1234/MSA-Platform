import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { volunteeringService } from '../services/volunteeringService';
import VolunteerCapacityBadge from '../components/volunteering/VolunteerCapacityBadge.vue';
import VolunteerOpportunityCard from '../components/volunteering/VolunteerOpportunityCard.vue';
import publicRoutes from '../router/public';
import adminRoutes from '../router/admin';

vi.mock('@/services/api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('Volunteering Subsystem Architecture & Routes', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('registers public volunteer routes cleanly', () => {
    const parentRoute = publicRoutes.find((r) => r.path === '/');
    const children = parentRoute?.children || [];
    const paths = children.map((c) => c.path);

    expect(paths).toContain('volunteer');
    expect(paths).toContain('volunteer/:slug');
    expect(paths).toContain('volunteer/my-history');
  });

  it('registers admin volunteer routes under central admin', () => {
    const parentRoute = adminRoutes.find((r) => r.path === '/admin');
    const children = parentRoute?.children || [];
    const paths = children.map((c) => c.path);

    expect(paths).toContain('volunteering');
    expect(paths).toContain('volunteering/:id');
  });
});

describe('VolunteerCapacityBadge Component', () => {
  it('renders Open Spots state correctly', () => {
    const wrapper = mount(VolunteerCapacityBadge, {
      props: {
        status: 'open',
        capacity: 10,
        signupsCount: 2,
      },
    });

    expect(wrapper.text()).toContain('8 Spots Open');
    expect(wrapper.classes()).toContain('border');
  });

  it('renders Almost Full warning state', () => {
    const wrapper = mount(VolunteerCapacityBadge, {
      props: {
        status: 'open',
        capacity: 10,
        signupsCount: 8,
      },
    });

    expect(wrapper.text()).toContain('Almost Full (2 left)');
  });

  it('renders Full (Waitlist) state when capacity reached', () => {
    const wrapper = mount(VolunteerCapacityBadge, {
      props: {
        status: 'open',
        capacity: 5,
        signupsCount: 5,
      },
    });

    expect(wrapper.text()).toContain('Full (Waitlist)');
  });

  it('renders Closed state when position is closed', () => {
    const wrapper = mount(VolunteerCapacityBadge, {
      props: {
        status: 'closed',
        capacity: 10,
        signupsCount: 0,
      },
    });

    expect(wrapper.text()).toContain('Closed');
  });
});

describe('VolunteerOpportunityCard Component', () => {
  const mockOpportunity = {
    id: 1,
    uuid: 'opp-123',
    title: 'Ramadan Setup Team',
    slug: 'ramadan-setup-team',
    description: 'Help set up prayer mats and audio equipment.',
    location: 'SFU Student Centre',
    status: 'open',
    capacity: 20,
    signups_count: 5,
    teams: [{ id: 1, opportunity_id: 1, name: 'Setup', status: 'open', ordering: 1 }],
    shifts: [{ id: 1, opportunity_id: 1, start_at: '2026-04-01T10:00:00Z', end_at: '2026-04-01T14:00:00Z', capacity: 20, status: 'open' }],
  };

  it('renders title, description, and metadata pills', () => {
    const wrapper = mount(VolunteerOpportunityCard, {
      props: {
        opportunity: mockOpportunity,
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    });

    expect(wrapper.text()).toContain('Ramadan Setup Team');
    expect(wrapper.text()).toContain('Help set up prayer mats');
    expect(wrapper.text()).toContain('SFU Student Centre');
    expect(wrapper.text()).toContain('Community Volunteer');
  });

  it('renders EMS Event link badge when associated with event', () => {
    const oppWithEvent = {
      ...mockOpportunity,
      event: {
        id: 42,
        name: 'Annual Eid Banquet',
        slug: 'annual-eid-banquet',
      },
    };

    const wrapper = mount(VolunteerOpportunityCard, {
      props: {
        opportunity: oppWithEvent,
      },
      global: {
        stubs: {
          RouterLink: {
            template: '<a><slot /></a>',
          },
        },
      },
    });

    expect(wrapper.text()).toContain('Event: Annual Eid Banquet');
  });
});
