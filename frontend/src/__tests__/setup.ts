import { vi } from 'vitest';

// Stub Motion component from @motionone/vue
vi.mock('@motionone/vue', () => ({
  Motion: {
    name: 'Motion',
    template: '<component :is="as || \'div\'" v-bind="$attrs"><slot /></component>',
    props: ['as']
  }
}));

// Stub global IntersectionObserver for JSDOM
class MockIntersectionObserver {
  observe = vi.fn();
  unobserve = vi.fn();
  disconnect = vi.fn();
}
vi.stubGlobal('IntersectionObserver', MockIntersectionObserver);

