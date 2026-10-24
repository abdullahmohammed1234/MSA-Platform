import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import HomeVideoFeature from '@/components/website/HomeVideoFeature.vue';

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

// Stub ScrollReveal and Motion to directly render slots
const ScrollRevealStub = {
  name: 'ScrollReveal',
  template: '<div class="scroll-reveal-stub"><slot /></div>',
};

const MotionStub = {
  name: 'Motion',
  template: '<div><slot /></div>',
};

describe('HomeVideoFeature Component', () => {
  const mountComponent = (props = {}) => {
    return mount(HomeVideoFeature, {
      props,
      global: {
        stubs: {
          ScrollReveal: ScrollRevealStub,
          Motion: MotionStub,
          RouterLink: {
            props: ['to'],
            template: '<a :href="typeof to === \'string\' ? to : to?.path"><slot /></a>',
          },
        },
      },
    });
  };

  it('renders the video preview thumbnail and play button before activation', () => {
    const wrapper = mountComponent();

    // Headings and editorial text
    expect(wrapper.text()).toContain('More than a community.');
    expect(wrapper.text()).toContain('A place to belong.');
    expect(wrapper.text()).toContain(
      'Faith, friendship, and a shared journey at SFU. Discover the people, experiences, and purpose that bring our community together.'
    );

    // Call to Action
    expect(wrapper.text()).toContain('Discover the MSA');
    const ctaLink = wrapper.find('a[href="/about"]');
    expect(ctaLink.exists()).toBe(true);

    // Preview state: Thumbnail and Play Button visible, Iframe NOT rendered
    const img = wrapper.find('img');
    expect(img.exists()).toBe(true);
    expect(img.attributes('src')).toBe('https://i.ytimg.com/vi/NTEjb3FcTZw/maxresdefault.jpg');

    const playButton = wrapper.find('button[aria-label*="Play video"]');
    expect(playButton.exists()).toBe(true);
    expect(playButton.attributes('aria-label')).toBe(
      'Play video: More than a community. A place to belong.'
    );

    expect(wrapper.find('iframe').exists()).toBe(false);
  });

  it('activates the YouTube player upon user click with privacy-enhanced embed and autoplay', async () => {
    const wrapper = mountComponent();

    const playButton = wrapper.find('button[aria-label*="Play video"]');
    expect(playButton.exists()).toBe(true);

    await playButton.trigger('click');

    // After click: Iframe mounts with privacy-enhanced embed domain and autoplay parameters
    const iframe = wrapper.find('iframe');
    expect(iframe.exists()).toBe(true);
    expect(iframe.attributes('src')).toBe(
      'https://www.youtube-nocookie.com/embed/NTEjb3FcTZw?autoplay=1&rel=0&playsinline=1&modestbranding=1'
    );

    // Thumbnail and button are replaced
    expect(wrapper.find('button[aria-label*="Play video"]').exists()).toBe(false);
  });

  it('sets correct accessibility and security attributes on the iframe', async () => {
    const wrapper = mountComponent();
    await wrapper.find('button[aria-label*="Play video"]').trigger('click');

    const iframe = wrapper.find('iframe');
    expect(iframe.exists()).toBe(true);

    // Accessibility attributes
    expect(iframe.attributes('title')).toBe('SFU MSA — More than a community, a place to belong');

    // Security & policy attributes
    expect(iframe.attributes('referrerpolicy')).toBe('strict-origin-when-cross-origin');
    expect(iframe.attributes('allow')).toContain('accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
    expect(iframe.attributes('allowfullscreen')).toBeDefined();
    expect(iframe.attributes('loading')).toBe('lazy');
  });

  it('prevents repeated activations from creating duplicate players', async () => {
    const wrapper = mountComponent();
    const playButton = wrapper.find('button[aria-label*="Play video"]');

    await playButton.trigger('click');
    expect(wrapper.findAll('iframe').length).toBe(1);

    // Repeated action does not duplicate
    const vm = wrapper.vm as any;
    if (typeof vm.playVideo === 'function') {
      vm.playVideo();
    }
    await wrapper.vm.$nextTick();

    expect(wrapper.findAll('iframe').length).toBe(1);
  });

  it('falls back to hqdefault thumbnail when maxresdefault encounters an error', async () => {
    const wrapper = mountComponent();

    const img = wrapper.find('img');
    expect(img.attributes('src')).toBe('https://i.ytimg.com/vi/NTEjb3FcTZw/maxresdefault.jpg');

    await img.trigger('error');

    expect(wrapper.find('img').attributes('src')).toBe(
      'https://i.ytimg.com/vi/NTEjb3FcTZw/hqdefault.jpg'
    );
  });

  it('supports custom props for videoId, title, and CTA', async () => {
    const wrapper = mountComponent({
      videoId: 'Custom123',
      title: 'Our Journey',
      subtitle: 'Our Vision',
      ctaText: 'Join Us',
      ctaUrl: '/contact',
    });

    expect(wrapper.text()).toContain('Our Journey');
    expect(wrapper.text()).toContain('Our Vision');
    expect(wrapper.find('a[href="/contact"]').exists()).toBe(true);

    const img = wrapper.find('img');
    expect(img.attributes('src')).toBe('https://i.ytimg.com/vi/Custom123/maxresdefault.jpg');

    await wrapper.find('button[aria-label*="Play video"]').trigger('click');
    const iframe = wrapper.find('iframe');
    expect(iframe.attributes('src')).toContain('https://www.youtube-nocookie.com/embed/Custom123');
  });
});
