import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { reactive } from 'vue';
import PublicNavbar from '../components/navigation/navbar/PublicNavbar.vue';

// Mock routing hook with dynamic route path
const mockRoute = reactive({ path: '/' });
vi.mock('vue-router', async () => {
  return {
    useRoute: () => mockRoute,
    useRouter: () => ({ push: vi.fn() }),
  };
});

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

describe('PublicNavbar.vue — Grouped Dropdown Navigation & Interactions', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    localStorage.clear();
    mockRoute.path = '/';
  });

  const mountNavbar = () => {
    return mount(PublicNavbar, {
      global: {
        stubs: {
          RouterLink: {
            props: ['to'],
            template: '<a :href="to"><slot /></a>',
          },
          Teleport: true,
          Motion: {
            template: '<div><slot /></div>',
          },
          Presence: {
            template: '<div><slot /></div>',
          },
        },
      },
    });
  };

  it('renders About dropdown with all 4 destinations (About, Team, Sponsorship, Media)', async () => {
    const wrapper = mountNavbar();

    // Trigger About dropdown click
    const aboutBtn = wrapper.find('button[aria-controls="about-desktop-menu"]');
    expect(aboutBtn.exists()).toBe(true);
    expect(aboutBtn.text()).toContain('About');

    await aboutBtn.trigger('click');

    const aboutMenu = wrapper.find('#about-desktop-menu');
    expect(aboutMenu.exists()).toBe(true);

    const links = aboutMenu.findAll('a').map((el) => ({
      href: el.attributes('href'),
      text: el.text(),
    }));

    expect(links).toHaveLength(4);
    expect(links.some((l) => l.href === '/about' && l.text.includes('About'))).toBe(true);
    expect(links.some((l) => l.href === '/team' && l.text.includes('Team'))).toBe(true);
    expect(links.some((l) => l.href === '/sponsorship' && l.text.includes('Sponsorship'))).toBe(true);
    expect(links.some((l) => l.href === '/media' && l.text.includes('Media'))).toBe(true);
  });

  it('renders Library dropdown with both destinations (Library, Learning Resources)', async () => {
    const wrapper = mountNavbar();

    // Trigger Library dropdown click
    const libraryBtn = wrapper.find('button[aria-controls="library-desktop-menu"]');
    expect(libraryBtn.exists()).toBe(true);
    expect(libraryBtn.text()).toContain('Library');

    await libraryBtn.trigger('click');

    const libraryMenu = wrapper.find('#library-desktop-menu');
    expect(libraryMenu.exists()).toBe(true);

    const links = libraryMenu.findAll('a').map((el) => ({
      href: el.attributes('href'),
      text: el.text(),
    }));

    expect(links.some((l) => l.href === '/featured-opportunities' && l.text.includes('Learning Resources'))).toBe(true);
  });

  it('toggles dropdown on click and maintains mutual exclusion', async () => {
    const wrapper = mountNavbar();

    const aboutBtn = wrapper.find('button[aria-controls="about-desktop-menu"]');
    const libraryBtn = wrapper.find('button[aria-controls="library-desktop-menu"]');

    // Click About -> opens About
    await aboutBtn.trigger('click');
    expect(wrapper.find('#about-desktop-menu').exists()).toBe(true);
    expect(wrapper.find('#library-desktop-menu').exists()).toBe(false);

    // Click Library -> closes About and opens Library
    await libraryBtn.trigger('click');
    expect(wrapper.find('#about-desktop-menu').exists()).toBe(false);
    expect(wrapper.find('#library-desktop-menu').exists()).toBe(true);

    // Click Library again -> toggles closed
    await libraryBtn.trigger('click');
    expect(wrapper.find('#library-desktop-menu').exists()).toBe(false);
  });

  it('closes dropdowns when Escape key is pressed', async () => {
    const wrapper = mountNavbar();

    const aboutBtn = wrapper.find('button[aria-controls="about-desktop-menu"]');
    await aboutBtn.trigger('click');
    expect(wrapper.find('#about-desktop-menu').exists()).toBe(true);

    // Press Escape
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    await wrapper.vm.$nextTick();

    expect(wrapper.find('#about-desktop-menu').exists()).toBe(false);
  });

  it('supports keyboard down-arrow to open dropdown', async () => {
    const wrapper = mountNavbar();

    const aboutBtn = wrapper.find('button[aria-controls="about-desktop-menu"]');
    await aboutBtn.trigger('keydown', { key: 'ArrowDown' });

    expect(wrapper.find('#about-desktop-menu').exists()).toBe(true);
  });

  it('highlights About active state when current route matches any child route', async () => {
    mockRoute.path = '/team';
    const wrapper = mountNavbar();

    const aboutBtn = wrapper.find('button[aria-controls="about-desktop-menu"]');
    expect(aboutBtn.classes()).toContain('text-primary');
  });

  it('highlights Library active state when current route matches child route', async () => {
    mockRoute.path = '/featured-opportunities';
    const wrapper = mountNavbar();

    const libraryBtn = wrapper.find('button[aria-controls="library-desktop-menu"]');
    expect(libraryBtn.classes()).toContain('text-primary');
  });

  it('provides mobile accordion expansion for About and Library destinations', async () => {
    const wrapper = mountNavbar();

    // Open mobile menu
    const mobileToggle = wrapper.find('button[aria-controls="mobile-menu-drawer"]');
    expect(mobileToggle.exists()).toBe(true);
    await mobileToggle.trigger('click');

    // Expand mobile About group
    const aboutAccordionBtn = wrapper.findAll('button').find(b => b.text().includes('About') && b.text().includes('02'));
    expect(aboutAccordionBtn).toBeDefined();
    await aboutAccordionBtn!.trigger('click');

    // Verify all 4 About links rendered in mobile drawer
    const drawerLinks = wrapper.findAll('a').map(a => a.attributes('href'));
    expect(drawerLinks).toContain('/about');
    expect(drawerLinks).toContain('/team');
    expect(drawerLinks).toContain('/sponsorship');
    expect(drawerLinks).toContain('/media');

    // Expand mobile Library group
    const libraryAccordionBtn = wrapper.findAll('button').find(b => b.text().includes('Library') && b.text().includes('05'));
    expect(libraryAccordionBtn).toBeDefined();
    await libraryAccordionBtn!.trigger('click');

    const updatedLinks = wrapper.findAll('a').map(a => a.attributes('href'));
    expect(updatedLinks).toContain('/featured-opportunities');
  });
});
