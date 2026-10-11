import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from '../stores/auth';
import { useAppAccess } from '../composables/auth/useAppAccess';
import PublicNavbar from '../components/navigation/navbar/PublicNavbar.vue';
import { defineComponent, h } from 'vue';


// Stub lucide-vue-next icons
vi.mock('lucide-vue-next', async () => {
  const vue = await import('vue');
  const makeIcon = (name: string) =>
    vue.defineComponent({
      name: String(name),
      setup: (_, { attrs }) => () => vue.h('svg', { 'data-icon': String(name), ...attrs }),
    });
  return new Proxy(
    {},
    {
      get: (target, prop: string) => {
        if (prop === '__esModule') return true;
        if (prop === 'default') return target;
        return makeIcon(prop);
      },
      has: () => true,
    }
  );
});

// Stub SEO composable
vi.mock('@/composables/useSeo', () => ({
  useSeo: () => undefined,
}));

// Mock routing hook
vi.mock('vue-router', async () => {
  const { reactive } = await import('vue');
  return {
    useRoute: () => reactive({ path: '/' }),
    useRouter: () => ({ push: vi.fn() }),
  };
});

describe('Navbar Unified Access and Launcher Tests', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
  });

  describe('useAppAccess Composable Unit Tests', () => {
    it('grants full access to super-admin and admin', () => {
      const authStore = useAuthStore();
      authStore.token = 'admin-token';
      authStore.user = {
        id: 1,
        uuid: 'admin-uuid',
        name: 'Platform Admin',
        email: 'admin@sfu.ca',
        roles: ['admin'],
        permissions: [],
      };

      const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasAdminAccess } = useAppAccess();

      expect(hasCmsAccess.value).toBe(true);
      expect(hasDamsAccess.value).toBe(true);
      expect(hasEmsAccess.value).toBe(true); // admin/super-admin is in EMS roles
      expect(hasAdminAccess.value).toBe(true);
    });

    it('grants only CMS access to CMS-only users', () => {
      const authStore = useAuthStore();
      authStore.token = 'cms-token';
      authStore.user = {
        id: 2,
        uuid: 'cms-uuid',
        name: 'CMS Editor',
        email: 'cms@sfu.ca',
        roles: ['member'],
        permissions: [],
        application_access: {
          cms: { access: true, source: 'explicit' },
          dams: { access: false, source: 'none' },
          ems: { access: false, source: 'none' },
          'admin-portal': { access: false, source: 'none' }
        }
      };

      const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasAdminAccess } = useAppAccess();

      expect(hasCmsAccess.value).toBe(true);
      expect(hasDamsAccess.value).toBe(false);
      expect(hasEmsAccess.value).toBe(false);
      expect(hasAdminAccess.value).toBe(false);
    });

    it('grants only DAMS access to DAMS-only users', () => {
      const authStore = useAuthStore();
      authStore.token = 'dams-token';
      authStore.user = {
        id: 3,
        uuid: 'dams-uuid',
        name: 'DAMS Operator',
        email: 'dams@sfu.ca',
        roles: ['volunteer'],
        permissions: [],
        application_access: {
          cms: { access: false, source: 'none' },
          dams: { access: true, source: 'explicit' },
          ems: { access: false, source: 'none' },
          'admin-portal': { access: false, source: 'none' }
        }
      };

      const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasAdminAccess } = useAppAccess();

      expect(hasCmsAccess.value).toBe(false);
      expect(hasDamsAccess.value).toBe(true);
      expect(hasEmsAccess.value).toBe(false);
      expect(hasAdminAccess.value).toBe(false);
    });

    it('grants CMS & DAMS to user holding combined permissions', () => {
      const authStore = useAuthStore();
      authStore.token = 'combined-token';
      authStore.user = {
        id: 4,
        uuid: 'combined-uuid',
        name: 'Multi Editor',
        email: 'multi@sfu.ca',
        roles: ['member'],
        permissions: [],
        application_access: {
          cms: { access: true, source: 'explicit' },
          dams: { access: true, source: 'explicit' },
          ems: { access: false, source: 'none' },
          'admin-portal': { access: false, source: 'none' }
        }
      };

      const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasAdminAccess } = useAppAccess();

      expect(hasCmsAccess.value).toBe(true);
      expect(hasDamsAccess.value).toBe(true);
      expect(hasEmsAccess.value).toBe(false);
      expect(hasAdminAccess.value).toBe(false);
    });

    it('grants EMS to EMS-only user', () => {
      const authStore = useAuthStore();
      authStore.token = 'ems-token';
      authStore.user = {
        id: 5,
        uuid: 'ems-uuid',
        name: 'EMS Coordinator',
        email: 'ems@sfu.ca',
        roles: ['event-organizer'],
        permissions: [],
        application_access: {
          cms: { access: false, source: 'none' },
          dams: { access: false, source: 'none' },
          ems: { access: true, source: 'explicit' },
          'admin-portal': { access: false, source: 'none' }
        }
      };

      const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasAdminAccess } = useAppAccess();

      expect(hasCmsAccess.value).toBe(false);
      expect(hasDamsAccess.value).toBe(false);
      expect(hasEmsAccess.value).toBe(true);
      expect(hasAdminAccess.value).toBe(false);
    });

    it('denies access to all management portals for ordinary member', () => {
      const authStore = useAuthStore();
      authStore.token = 'member-token';
      authStore.user = {
        id: 6,
        uuid: 'member-uuid',
        name: 'Member User',
        email: 'member@sfu.ca',
        roles: ['member'],
        permissions: [],
      };

      const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasAdminAccess } = useAppAccess();

      expect(hasCmsAccess.value).toBe(false);
      expect(hasDamsAccess.value).toBe(false);
      expect(hasEmsAccess.value).toBe(false);
      expect(hasAdminAccess.value).toBe(false);
    });
  });

  describe('PublicNavbar.vue Launcher Rendering tests', () => {
    const mountNavbar = () => {
      return mount(PublicNavbar, {
        global: {
          stubs: {
            RouterLink: {
              props: ['to'],
              template: '<a :href="to"><slot /></a>',
            },
          },
        },
      });
    };

    it('renders exact dropdown items (My Tickets, My Account Portal, My Dashboard, Logout) for Platform Administrator', async () => {
      const authStore = useAuthStore();
      authStore.token = 'admin-token';
      authStore.user = {
        id: 1,
        uuid: 'admin-uuid',
        name: 'Platform Admin',
        email: 'admin@sfu.ca',
        roles: ['admin'],
        permissions: [],
      };

      const wrapper = mountNavbar();

      // Open user dropdown
      const button = wrapper.find('button.cursor-pointer');
      expect(button.exists()).toBe(true);
      await button.trigger('click');

      const menuText = wrapper.text();
      expect(menuText).toContain('My Tickets');
      expect(menuText).toContain('My Portal & Dashboard');
      expect(menuText).toContain('Logout');

      // Verify direct roots are used
      expect(wrapper.find('a[href="/my-tickets"]').exists()).toBe(true);
      expect(wrapper.find('a[href="/account"]').exists()).toBe(true);
    });

    it('renders only CMS for CMS-only user', async () => {
      const authStore = useAuthStore();
      authStore.token = 'cms-token';
      authStore.user = {
        id: 2,
        uuid: 'cms-uuid',
        name: 'CMS Editor',
        email: 'cms@sfu.ca',
        roles: ['member'],
        permissions: [],
        application_access: {
          cms: { access: true, source: 'explicit' },
          dams: { access: false, source: 'none' },
          ems: { access: false, source: 'none' },
          'admin-portal': { access: false, source: 'none' }
        }
      };

      const wrapper = mountNavbar();
      await wrapper.find('button.cursor-pointer').trigger('click');

      const menuText = wrapper.text();
      expect(menuText).toContain('My Tickets');
      expect(menuText).toContain('CMS');
      expect(menuText).not.toContain('EMS');
      expect(menuText).not.toContain('DAMS');
      expect(menuText).not.toContain('Admin Portal');
    });

    it('renders only DAMS for DAMS-only user', async () => {
      const authStore = useAuthStore();
      authStore.token = 'dams-token';
      authStore.user = {
        id: 3,
        uuid: 'dams-uuid',
        name: 'DAMS Operator',
        email: 'dams@sfu.ca',
        roles: ['volunteer'],
        permissions: [],
        application_access: {
          cms: { access: false, source: 'none' },
          dams: { access: true, source: 'explicit' },
          ems: { access: false, source: 'none' },
          'admin-portal': { access: false, source: 'none' }
        }
      };

      const wrapper = mountNavbar();
      await wrapper.find('button.cursor-pointer').trigger('click');

      const menuText = wrapper.text();
      expect(menuText).toContain('My Tickets');
      expect(menuText).toContain('DAMS');
      expect(menuText).not.toContain('CMS');
      expect(menuText).not.toContain('EMS');
      expect(menuText).not.toContain('Admin Portal');
    });

    it('renders nothing but My Tickets for ordinary member', async () => {
      const authStore = useAuthStore();
      authStore.token = 'member-token';
      authStore.user = {
        id: 6,
        uuid: 'member-uuid',
        name: 'Member User',
        email: 'member@sfu.ca',
        roles: ['member'],
        permissions: [],
      };

      const wrapper = mountNavbar();
      await wrapper.find('button.cursor-pointer').trigger('click');

      const menuText = wrapper.text();
      expect(menuText).toContain('My Tickets');
      expect(menuText).not.toContain('CMS');
      expect(menuText).not.toContain('DAMS');
      expect(menuText).not.toContain('EMS');
      expect(menuText).not.toContain('Admin Portal');
    });

    it('resolves launcher visibility using backend application_access dictionary when present', () => {
      const authStore = useAuthStore();
      authStore.token = 'custom-token';
      authStore.user = {
        id: 7,
        uuid: 'custom-uuid',
        name: 'Custom User',
        email: 'custom@sfu.ca',
        roles: ['member'],
        permissions: [],
        application_access: {
          cms: { access: true, source: 'explicit' },
          dams: { access: false, source: 'none' },
          ems: { access: true, source: 'explicit' },
          'admin-portal': { access: false, source: 'none' }
        }
      };

      const { hasCmsAccess, hasDamsAccess, hasEmsAccess, hasAdminAccess } = useAppAccess();

      expect(hasCmsAccess.value).toBe(true);
      expect(hasDamsAccess.value).toBe(false);
      expect(hasEmsAccess.value).toBe(true);
      expect(hasAdminAccess.value).toBe(false);
    });

    it('renders both Login and Register links in mobile view drawer for guest users', () => {
      const authStore = useAuthStore();
      authStore.token = '';
      authStore.user = null;

      const wrapper = mountNavbar();
      const links = wrapper.findAll('a');
      const hrefs = links.map(l => l.attributes('href'));

      expect(hrefs).toContain('/login');
      expect(hrefs).toContain('/register');
    });

    it('grants MLibMS access to MLibMS administrator staff', () => {
      const authStore = useAuthStore();
      authStore.token = 'mlibms-token';
      authStore.user = {
        id: 8,
        uuid: 'mlibms-uuid',
        name: 'Librarian Staff',
        email: 'librarian@sfu.ca',
        roles: ['mlibms-administrator'],
        permissions: ['library.admin'],
      };

      const { hasMlibmsAccess } = useAppAccess();

      expect(hasMlibmsAccess.value).toBe(true);
    });

    it('renders user menu dropdown for MLibMS staff user', async () => {
      const authStore = useAuthStore();
      authStore.token = 'mlibms-token';
      authStore.user = {
        id: 8,
        uuid: 'mlibms-uuid',
        name: 'Librarian Staff',
        email: 'librarian@sfu.ca',
        roles: ['mlibms-administrator'],
        permissions: ['library.admin'],
      };

      const wrapper = mountNavbar();
      await wrapper.find('button.cursor-pointer').trigger('click');

      const menuText = wrapper.text();
      expect(menuText).toContain('My Tickets');
      expect(menuText).toContain('My Portal & Dashboard');
      expect(menuText).toContain('Logout');
    });
  });
});
