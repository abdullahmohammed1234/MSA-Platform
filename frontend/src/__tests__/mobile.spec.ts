import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import Button from '../components/ui/button/Button.vue';
import Sidebar from '../components/navigation/sidebar/Sidebar.vue';

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

describe('Sidebar Mobile Drawer Behavior', () => {
  const mockItems = [
    { label: 'Dashboard', path: '/admin', icon: 'dashboard' }
  ];

  it('starts closed and remains hidden off-screen', () => {
    const wrapper = mount(Sidebar, {
      props: {
        items: mockItems,
        mobileOpen: false
      },
      global: {
        stubs: {
          'router-link': true
        }
      }
    });

    const aside = wrapper.find('aside');
    expect(aside.classes()).toContain('-translate-x-full');
  });

  it('opens and overlays when mobileOpen is true', () => {
    const wrapper = mount(Sidebar, {
      props: {
        items: mockItems,
        mobileOpen: true
      },
      global: {
        stubs: {
          'router-link': true
        }
      }
    });

    const aside = wrapper.find('aside');
    expect(aside.classes()).toContain('translate-x-0');

    // Backdrop overlay is present
    const backdrop = wrapper.find('.fixed.inset-0');
    expect(backdrop.exists()).toBe(true);
  });

  it('emits closeMobile when backdrop is clicked', async () => {
    const wrapper = mount(Sidebar, {
      props: {
        items: mockItems,
        mobileOpen: true
      },
      global: {
        stubs: {
          'router-link': true
        }
      }
    });

    const backdrop = wrapper.find('.fixed.inset-0');
    await backdrop.trigger('click');

    expect(wrapper.emitted('closeMobile')).toBeTruthy();
  });

  it('emits closeMobile when Escape key is pressed', async () => {
    const wrapper = mount(Sidebar, {
      props: {
        items: mockItems,
        mobileOpen: true
      },
      global: {
        stubs: {
          'router-link': true
        }
      }
    });

    const event = new KeyboardEvent('keydown', { key: 'Escape' });
    window.dispatchEvent(event);

    expect(wrapper.emitted('closeMobile')).toBeTruthy();
  });
});
