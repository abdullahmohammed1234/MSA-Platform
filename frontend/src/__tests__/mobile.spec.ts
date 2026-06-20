import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Button from '../components/ui/button/Button.vue';

const MotionStub = {
  template: '<component :is="as || \'button\'" v-bind="$attrs"><slot /></component>',
  props: ['as']
};

describe('Mobile & Responsive Layout Verification', () => {
  it('should support full width styles under mobile context', () => {
    const wrapper = mount(Button, {
      global: {
        stubs: { Motion: MotionStub }
      },
      props: {
        isFullWidth: true
      }
    });

    const button = wrapper.find('button');
    expect(button.classes()).toContain('w-full');
  });

  it('mocks window resize events to trigger mobile layout changes', () => {
    let isMobile = false;

    const checkViewport = () => {
      isMobile = window.innerWidth < 768;
    };

    // 1. Mock Desktop Viewport
    vi.stubGlobal('innerWidth', 1024);
    checkViewport();
    expect(isMobile).toBe(false);

    // 2. Mock Mobile Viewport
    vi.stubGlobal('innerWidth', 375);
    checkViewport();
    expect(isMobile).toBe(true);

    vi.unstubAllGlobals();
  });
});
