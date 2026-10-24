import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import StoreLayout from '@/layouts/store/StoreLayout.vue';
import MlibmsLayout from '@/layouts/mlibms/MlibmsLayout.vue';

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/store/admin' }),
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { name: 'Admin User', email: 'admin@example.com' },
    logout: vi.fn().mockResolvedValue(true),
  }),
  UNCONFIRMED_LOGOUT_MESSAGE: 'Logout unconfirmed',
}));

describe('StoreLayout Mobile PWA Navigation (STORE-01)', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    document.body.style.overflow = '';
  });

  it('renders mobile menu toggle with proper aria attributes', () => {
    const wrapper = mount(StoreLayout, {
      global: {
        stubs: {
          RouterLink: { template: '<a><slot /></a>' },
          RouterView: { template: '<div>View</div>' },
          ToastContainer: { template: '<div>Toast</div>' },
        },
      },
    });

    const toggleBtn = wrapper.find('button[aria-label="Toggle navigation menu"]');
    expect(toggleBtn.exists()).toBe(true);
    expect(toggleBtn.attributes('aria-expanded')).toBe('false');
  });

  it('opens mobile drawer when toggle button is clicked and closes on close button', async () => {
    const wrapper = mount(StoreLayout, {
      global: {
        stubs: {
          RouterLink: { template: '<a><slot /></a>' },
          RouterView: { template: '<div>View</div>' },
          ToastContainer: { template: '<div>Toast</div>' },
        },
      },
    });

    const toggleBtn = wrapper.find('button[aria-label="Toggle navigation menu"]');
    await toggleBtn.trigger('click');

    expect(toggleBtn.attributes('aria-expanded')).toBe('true');
    expect(document.body.style.overflow).toBe('hidden');

    const drawer = wrapper.find('aside[aria-label="Store navigation menu"]');
    expect(drawer.exists()).toBe(true);
    expect(drawer.classes()).toContain('translate-x-0');

    // Close button
    const closeBtn = wrapper.find('button[aria-label="Close navigation menu"]');
    expect(closeBtn.exists()).toBe(true);
    await closeBtn.trigger('click');

    expect(toggleBtn.attributes('aria-expanded')).toBe('false');
    expect(document.body.style.overflow).toBe('');
    expect(drawer.classes()).toContain('-translate-x-full');
  });
});

describe('MlibmsLayout Mobile Navigation (LIB-04)', () => {
  beforeEach(() => {
    document.body.style.overflow = '';
  });

  it('renders mobile menu toggle with proper aria attributes', () => {
    const wrapper = mount(MlibmsLayout, {
      global: {
        stubs: {
          RouterLink: { template: '<a><slot /></a>' },
          RouterView: { template: '<div>View</div>' },
          ToastContainer: { template: '<div>Toast</div>' },
        },
      },
    });

    const toggleBtn = wrapper.find('button[aria-label="Toggle library menu"]');
    expect(toggleBtn.exists()).toBe(true);
    expect(toggleBtn.attributes('aria-expanded')).toBe('false');
  });

  it('opens and closes mobile drawer upon toggle and close actions', async () => {
    const wrapper = mount(MlibmsLayout, {
      global: {
        stubs: {
          RouterLink: { template: '<a><slot /></a>' },
          RouterView: { template: '<div>View</div>' },
          ToastContainer: { template: '<div>Toast</div>' },
        },
      },
    });

    const toggleBtn = wrapper.find('button[aria-label="Toggle library menu"]');
    await toggleBtn.trigger('click');

    expect(toggleBtn.attributes('aria-expanded')).toBe('true');
    expect(document.body.style.overflow).toBe('hidden');

    const drawer = wrapper.find('aside[aria-label="Library navigation menu"]');
    expect(drawer.exists()).toBe(true);
    expect(drawer.classes()).toContain('translate-x-0');

    const closeBtn = wrapper.find('button[aria-label="Close navigation menu"]');
    await closeBtn.trigger('click');

    expect(toggleBtn.attributes('aria-expanded')).toBe('false');
    expect(document.body.style.overflow).toBe('');
    expect(drawer.classes()).toContain('-translate-x-full');
  });
});
