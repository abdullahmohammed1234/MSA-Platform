import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import DonationVideoFeature from '@/components/website/DonationVideoFeature.vue';

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

describe('DonationVideoFeature Component', () => {
  beforeEach(() => {
    // Mock HTMLMediaElement methods in JSDOM
    window.HTMLMediaElement.prototype.play = vi.fn().mockResolvedValue(undefined);
    window.HTMLMediaElement.prototype.pause = vi.fn();
    window.HTMLMediaElement.prototype.load = vi.fn();
  });

  const mountComponent = (props = {}) => {
    return mount(DonationVideoFeature, {
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

  it('renders the video preview poster and play button before playback', () => {
    const wrapper = mountComponent();

    // Check headings and copy
    expect(wrapper.text()).toContain('Your support makes a difference.');
    expect(wrapper.text()).toContain('Building faith & belonging.');
    expect(wrapper.text()).toContain('Discover how your generosity helps support the SFU Muslim community.');

    // Check CTA button
    expect(wrapper.text()).toContain('Make a Donation');

    // Poster and Play button are rendered
    const img = wrapper.find('img');
    expect(img.exists()).toBe(true);
    expect(img.attributes('src')).toBe('/MSA-poster.jpg');

    const playButton = wrapper.find('button[aria-label*="Play video"]');
    expect(playButton.exists()).toBe(true);
    expect(playButton.attributes('type')).toBe('button');
    expect(playButton.attributes('aria-label')).toBe(
      'Play video: Your support makes a difference. Building faith & belonging.'
    );

    // Native video is not rendered before playback
    expect(wrapper.find('video').exists()).toBe(false);
  });

  it('activates native HTML5 video player upon clicking play button and references correct MP4 URL', async () => {
    const wrapper = mountComponent();

    const playButton = wrapper.find('button[aria-label*="Play video"]');
    expect(playButton.exists()).toBe(true);

    await playButton.trigger('click');
    await wrapper.vm.$nextTick();

    // Native video element is mounted
    const video = wrapper.find('video');
    expect(video.exists()).toBe(true);

    // Verify video source
    const source = video.find('source');
    expect(source.exists()).toBe(true);
    expect(source.attributes('src')).toBe('/MSA.mp4');
    expect(source.attributes('type')).toBe('video/mp4');

    // Verify HTML5 video attributes
    expect(video.attributes('controls')).toBeDefined();
    expect(video.attributes('playsinline')).toBeDefined();
    expect(video.attributes('preload')).toBe('none');

    // Play method was called on the mock video element
    expect(window.HTMLMediaElement.prototype.play).toHaveBeenCalled();

    // Preview button is removed
    expect(wrapper.find('button[aria-label*="Play video"]').exists()).toBe(false);
  });

  it('prevents repeated interactions from creating duplicate players', async () => {
    const wrapper = mountComponent();

    await wrapper.find('button[aria-label*="Play video"]').trigger('click');
    await wrapper.vm.$nextTick();

    expect(wrapper.findAll('video').length).toBe(1);

    // Invoking playVideo method again does not duplicate video elements
    const vm = wrapper.vm as any;
    if (typeof vm.playVideo === 'function') {
      await vm.playVideo();
      await wrapper.vm.$nextTick();
    }

    expect(wrapper.findAll('video').length).toBe(1);
  });

  it('does not submit parent form when play button is clicked', async () => {
    const submitSpy = vi.fn();

    const FormContainer = {
      components: { DonationVideoFeature },
      template: `
        <form @submit.prevent="onSubmit">
          <DonationVideoFeature />
        </form>
      `,
      methods: {
        onSubmit: submitSpy,
      },
    };

    const wrapper = mount(FormContainer, {
      global: {
        stubs: {
          ScrollReveal: ScrollRevealStub,
          Motion: MotionStub,
          RouterLink: true,
        },
      },
    });

    const playButton = wrapper.find('button[aria-label*="Play video"]');
    expect(playButton.exists()).toBe(true);
    expect(playButton.attributes('type')).toBe('button');

    await playButton.trigger('click');

    expect(submitSpy).not.toHaveBeenCalled();
  });

  it('falls back to secondary poster image if primary poster fails to load', async () => {
    const wrapper = mountComponent();

    const img = wrapper.find('img');
    expect(img.attributes('src')).toBe('/MSA-poster.jpg');

    await img.trigger('error');

    expect(wrapper.find('img').attributes('src')).toBe('/Hero/jumuahprayer.webp');
  });

  it('displays error overlay with direct download fallback if video fails to play', async () => {
    const wrapper = mountComponent();

    await wrapper.find('button[aria-label*="Play video"]').trigger('click');
    await wrapper.vm.$nextTick();

    const video = wrapper.find('video');
    expect(video.exists()).toBe(true);

    // Trigger video error
    await video.trigger('error');

    expect(wrapper.text()).toContain('Video could not be played.');
    const downloadLink = wrapper.find('a[download]');
    expect(downloadLink.exists()).toBe(true);
    expect(downloadLink.attributes('href')).toBe('/MSA.mp4');
  });

  it('supports custom props for videoSrc, posterSrc, and CTA', async () => {
    const wrapper = mountComponent({
      videoSrc: '/custom-video.mp4',
      posterSrc: '/custom-poster.jpg',
      title: 'Our Community Voice',
      subtitle: 'Stories of SFU',
      ctaText: 'Donate Today',
      ctaTarget: '/donate',
    });

    expect(wrapper.text()).toContain('Our Community Voice');
    expect(wrapper.text()).toContain('Stories of SFU');
    expect(wrapper.text()).toContain('Donate Today');

    const img = wrapper.find('img');
    expect(img.attributes('src')).toBe('/custom-poster.jpg');

    await wrapper.find('button[aria-label*="Play video"]').trigger('click');
    await wrapper.vm.$nextTick();

    const source = wrapper.find('video source');
    expect(source.attributes('src')).toBe('/custom-video.mp4');
  });
});
