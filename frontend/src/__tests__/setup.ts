import { vi } from 'vitest';

// Stub Motion component from @motionone/vue
vi.mock('@motionone/vue', () => ({
  Motion: {
    name: 'Motion',
    template: '<component :is="as || \'div\'" v-bind="$attrs"><slot /></component>',
    props: ['as']
  },
  // Presence only orchestrates enter/exit animations, so rendering its
  // children directly is the correct behaviour under test.
  Presence: {
    name: 'Presence',
    template: '<slot />'
  }
}));

// Stub global IntersectionObserver for JSDOM
class MockIntersectionObserver {
  observe = vi.fn();
  unobserve = vi.fn();
  disconnect = vi.fn();
}
vi.stubGlobal('IntersectionObserver', MockIntersectionObserver);

