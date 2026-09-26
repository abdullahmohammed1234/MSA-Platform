import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import HomeView from '../pages/public/HomePage.vue';
import CoursesView from '../pages/academy/CourseCatalogPage.vue';
import AcademyDashboardView from '../pages/academy/AcademyDashboardPage.vue';

// Dynamically mock all Lucide icons used in the views
vi.mock('lucide-vue-next', () => {
  const componentCache: Record<string, any> = {};
  return new Proxy(
    { __esModule: true },
    {
      get: (target, prop: string) => {
        if (prop in target) return (target as any)[prop];
        if (typeof prop === 'symbol' || prop === 'default') return undefined;
        if (!componentCache[prop]) {
          componentCache[prop] = {
            name: prop,
            template: `<div class="lucide-${prop}"><slot /></div>`,
          };
        }
        return componentCache[prop];
      },
      has: () => true,
    }
  );
});

// Stub components that might load child routes/complex motion
const MotionStub = {
  template: '<div><slot /></div>'
};

describe('Major Pages Rendering Tests', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('renders Public Website Home page correctly', () => {
    const wrapper = mount(HomeView, {
      global: {
        plugins: [createPinia()],
        stubs: {
          Motion: MotionStub,
          RouterLink: true
        }
      }
    });

    expect(wrapper.text()).toContain('Simon Fraser University');
    expect(wrapper.find('h1').exists()).toBe(true);
  });

  it('renders Dawah Academy Courses catalog page', () => {
    const wrapper = mount(CoursesView, {
      global: {
        plugins: [createPinia()],
        stubs: {
          Motion: MotionStub,
          RouterLink: true
        }
      }
    });

    expect(wrapper.text()).toContain('Academy Programs');
  });

  it('renders Academy Dashboard page', () => {
    const wrapper = mount(AcademyDashboardView, {
      global: {
        plugins: [createPinia()],
        stubs: {
          Motion: MotionStub,
          RouterLink: true
        }
      }
    });

    expect(wrapper.text()).toContain('Welcome back');
    expect(wrapper.text()).toContain('Avg Progress');
  });
});
