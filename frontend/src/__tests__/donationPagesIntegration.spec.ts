import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import DonatePage from '@/pages/public/DonatePage.vue';
import DonationsPage from '@/pages/public/DonationsPage.vue';

// Mock Lucide icons
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

// Stubs for motion/transitions
const ScrollRevealStub = {
  name: 'ScrollReveal',
  template: '<div class="scroll-reveal-stub"><slot /></div>',
};

const MotionStub = {
  name: 'Motion',
  template: '<div><slot /></div>',
};

describe('Donation Pages Video Feature Integration', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    window.HTMLMediaElement.prototype.play = vi.fn().mockResolvedValue(undefined);
    window.HTMLMediaElement.prototype.pause = vi.fn();
    window.HTMLMediaElement.prototype.load = vi.fn();
  });

  it('renders DonatePage (/donate) with DonationVideoFeature targeting #donation-console', () => {
    const wrapper = mount(DonatePage, {
      global: {
        stubs: {
          ScrollReveal: ScrollRevealStub,
          Motion: MotionStub,
          RouterLink: true,
        },
      },
    });

    // 1. Verify Video Feature is present
    expect(wrapper.text()).toContain('Your support makes a difference.');
    expect(wrapper.text()).toContain('Building faith & belonging.');

    // 2. Verify CTA targets #donation-console
    const ctaLink = wrapper.find('a[href="#donation-console"]');
    expect(ctaLink.exists()).toBe(true);

    // 3. Verify target container exists in the DOM
    const donationConsole = wrapper.find('#donation-console');
    expect(donationConsole.exists()).toBe(true);

    // 4. Verify original donation console elements are present and unhampered
    expect(wrapper.text()).toContain('Select Donation Amount');
    expect(wrapper.text()).toContain('Square API v2');
    expect(wrapper.find('input[type="email"]').exists()).toBe(true);
  });

  it('renders DonationsPage (/donations) with DonationVideoFeature targeting #donation-portal', () => {
    const wrapper = mount(DonationsPage, {
      global: {
        stubs: {
          ScrollReveal: ScrollRevealStub,
          Motion: MotionStub,
          RouterLink: true,
        },
      },
    });

    // 1. Verify Video Feature is present
    expect(wrapper.text()).toContain('Your support makes a difference.');
    expect(wrapper.text()).toContain('Building faith & belonging.');

    // 2. Verify CTA targets #donation-portal
    const ctaLink = wrapper.find('a[href="#donation-portal"]');
    expect(ctaLink.exists()).toBe(true);

    // 3. Verify target container exists in the DOM
    const donationPortal = wrapper.find('#donation-portal');
    expect(donationPortal.exists()).toBe(true);

    // 4. Verify impact sections and portal elements are present
    expect(wrapper.text()).toContain('Maintain Musallas');
    expect(wrapper.text()).toContain('Free Iftars & Dinners');
    expect(wrapper.text()).toContain('Sadaqah & Giving');
  });
});
