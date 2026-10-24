import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import EventFormPage from '@/pages/ems/events/EventFormPage.vue';
import EmsPublicEventDetailPage from '@/pages/public/ems/EmsPublicEventDetailPage.vue';
import publicEventsService from '@/services/ems/publicEventsService';

// Mock vue-router
const pushMock = vi.fn();
vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { uuid: undefined, slug: 'test-event-slug' },
    query: {},
  }),
  useRouter: () => ({
    push: pushMock,
  }),
}));

const RouterLinkMock = {
  name: 'RouterLink',
  props: ['to'],
  template: '<a :href="typeof to === \'string\' ? to : to?.path || \'\'"><slot /></a>',
};

// Mock lucide icons
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
            template: `<svg data-icon="${prop}" />`,
          };
        }
        return componentCache[prop];
      },
      has: () => true,
    }
  );
});

// Mock publicEventsService
vi.mock('@/services/ems/publicEventsService', () => ({
  default: {
    getEvent: vi.fn(),
    getCalendarLinks: vi.fn().mockResolvedValue({ google: '#', outlook: '#', yahoo: '#', ics: '#' }),
    getIcsDownloadUrl: vi.fn().mockReturnValue('#'),
    resumeCheckout: vi.fn(),
  },
}));

// Mock api client
vi.mock('@/services/api/client', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: [] }),
    post: vi.fn().mockResolvedValue({ data: {} }),
    put: vi.fn().mockResolvedValue({ data: {} }),
    delete: vi.fn().mockResolvedValue({ data: {} }),
  },
}));

describe('EMS Volunteer with Us button and automatic signup link', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('renders EventFormPage and automatically synchronizes volunteer signup link to /volunteer/:slug', async () => {
    const wrapper = mount(EventFormPage, {
      global: {
        components: {
          RouterLink: RouterLinkMock,
        },
        stubs: {
          EmsPageHeader: true,
          ImageInput: true,
          EmsDateTimeField: true,
          Select: true,
          Textarea: true,
        },
      },
    });

    // 1. Enter an event name
    const nameInput = wrapper.find('input[placeholder="MSA Welcome Night"]');
    expect(nameInput.exists()).toBe(true);
    await nameInput.setValue('Brothers Soccer Tournament');

    // Slug should now be brothers-soccer-tournament
    const slugInput = wrapper.find('input[placeholder="e.g. sfu-msa-welcome-dinner"]');
    expect(slugInput.exists()).toBe(true);
    expect((slugInput.element as HTMLInputElement).value).toBe('brothers-soccer-tournament');

    // 2. Toggle on "Enable 'Volunteer with us' button"
    const volunteerLabel = wrapper.findAll('span').find((s) => s.text().includes("Enable 'Volunteer with us' button"));
    expect(volunteerLabel?.exists()).toBe(true);
    await volunteerLabel?.trigger('click');

    // 3. Volunteer Signup Link input should appear and be automatically populated with /volunteer/brothers-soccer-tournament
    const volunteerUrlInput = wrapper.find('input[placeholder="/volunteer/your-event-slug"]');
    expect(volunteerUrlInput.exists()).toBe(true);
    expect((volunteerUrlInput.element as HTMLInputElement).value).toBe('/volunteer/brothers-soccer-tournament');

    // 4. Changing event name updates the volunteer URL automatically
    await nameInput.setValue('Brothers Futsal Cup');
    expect((volunteerUrlInput.element as HTMLInputElement).value).toBe('/volunteer/brothers-futsal-cup');

    // 5. Customizing the URL switches to Custom Link mode
    await volunteerUrlInput.setValue('https://forms.gle/custom-volunteer-form');
    expect(wrapper.text()).toContain('Custom Link');

    // Changing title does not overwrite the custom link
    await nameInput.setValue('Sisters Badminton League');
    expect((volunteerUrlInput.element as HTMLInputElement).value).toBe('https://forms.gle/custom-volunteer-form');

    // 6. Clicking Reset to Automatic restores the /volunteer/:slug link
    const resetToAutoBtn = wrapper.findAll('button').find((b) => b.text().includes('Reset to Automatic'));
    expect(resetToAutoBtn?.exists()).toBe(true);
    await resetToAutoBtn?.trigger('click');
    expect((volunteerUrlInput.element as HTMLInputElement).value).toBe('/volunteer/sisters-badminton-league');
  });

  it('renders Volunteer with Us callout on EmsPublicEventDetailPage when show_volunteer_button is enabled', async () => {
    vi.mocked(publicEventsService.getEvent).mockResolvedValueOnce({
      id: 1,
      uuid: 'test-uuid-1',
      name: 'Annual Ramadan Iftar',
      slug: 'annual-ramadan-iftar',
      short_description: 'Join us for a blessed community iftar',
      description: 'Full details here',
      banner_url: null,
      location: 'SFU SUB Ballroom',
      start_at: '2026-03-20T18:00:00Z',
      end_at: '2026-03-20T21:00:00Z',
      timezone: 'America/Vancouver',
      capacity: 200,
      remaining_capacity: 50,
      is_full: false,
      is_sold_out: false,
      is_accepting_registrations: true,
      waitlist_enabled: false,
      status: 'published',
      status_label: 'Published',
      status_tone: 'success',
      is_public: true,
      audience: 'both',
      registration_label: 'Open for registration',
      ticket_types: [],
      show_volunteer_button: true,
      volunteer_url: '/volunteer/annual-ramadan-iftar',
      documents: [],
    } as any);

    const wrapper = mount(EmsPublicEventDetailPage, {
      global: {
        components: {
          RouterLink: RouterLinkMock,
        },
        stubs: {
          ScrollReveal: { template: '<div><slot /></div>' },
        },
      },
    });

    // Wait for event to load
    await new Promise((resolve) => setTimeout(resolve, 50));
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain('Want to Volunteer for this Event?');
    expect(wrapper.text()).toContain('Help us organize and run Annual Ramadan Iftar.');
    expect(wrapper.text()).toContain('Volunteer for this Event');
    expect(wrapper.text()).toContain('All Opportunities');

    const volunteerLinks = wrapper.findAll('a');
    const eventVolunteerLink = volunteerLinks.find((l) => l.text().includes('Volunteer for this Event'));
    expect(eventVolunteerLink?.attributes('href')).toBe('/volunteer/annual-ramadan-iftar');
  });
});
