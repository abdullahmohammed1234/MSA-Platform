import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import AccountAppsView from '@/pages/account/AccountAppsView.vue';
import { accountService } from '@/services/account/accountService';

vi.mock('@/services/account/accountService', () => ({
  accountService: {
    getSummary: vi.fn(),
  },
}));

describe('AccountAppsView — Direct System Navigation', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.restoreAllMocks();
  });

  it('renders direct system paths for EMS and MLibMS matching all other systems', async () => {
    vi.mocked(accountService.getSummary).mockResolvedValueOnce({
      user: {
        uuid: 'test-user',
        name: 'Admin User',
        email: 'admin@sfu.ca',
        community_status: 'Administrator',
        roles: ['Administrator'],
      },
      counts: {
        upcoming_events: 0,
        orders: 0,
        volunteer_shifts: 0,
        courses: 0,
        unread_notifications: 0,
      },
      academy: {
        enrolled_courses: 0,
        completed_courses: 0,
        certificates: 0,
        recent_courses: [],
      },
      activity: {
        events: [],
        orders: [],
      },
      applications: [
        {
          slug: 'cms',
          title: 'Content Management System (CMS)',
          description: 'Manage website articles, homepage, and media.',
          path: '/cms',
          is_admin: true,
          source: 'privileged',
        },
        {
          slug: 'ems',
          title: 'Event Management System (EMS)',
          description: 'MSA events ticketing, registration, and attendance portal.',
          path: '/ems',
          is_admin: true,
          source: 'privileged',
        },
        {
          slug: 'mlibms',
          title: 'Library Management System (MLIBMS)',
          description: 'Library catalog search, book intake, and circulation control.',
          path: '/library/admin',
          is_admin: true,
          source: 'privileged',
        },
        {
          slug: 'store',
          title: 'MSA Store Management',
          description: 'Official merchandise catalog, inventory, and order fulfillment.',
          path: '/store/admin',
          is_admin: true,
          source: 'privileged',
        },
      ],
    } as any);

    const wrapper = mount(AccountAppsView, {
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="typeof to === \'string\' ? to : to.path" class="router-link-stub"><slot /></a>',
          },
        },
      },
    });

    await flushPromises();

    // Verify all admin applications are rendered
    const links = wrapper.findAll('a.router-link-stub');
    const hrefs = links.map(link => link.attributes('href'));

    // EMS must go directly to /ems (not /admin/systems/ems)
    expect(hrefs).toContain('/ems');
    expect(hrefs).not.toContain('/admin/systems/ems');

    // MLibMS must go directly to /library/admin (not /admin/systems/library)
    expect(hrefs).toContain('/library/admin');
    expect(hrefs).not.toContain('/admin/systems/library');

    // CMS and Store go directly to their systems
    expect(hrefs).toContain('/cms');
    expect(hrefs).toContain('/store/admin');
  });
});
