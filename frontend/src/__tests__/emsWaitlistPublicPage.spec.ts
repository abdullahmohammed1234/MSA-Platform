import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import EmsPublicEventDetailPage from '@/pages/public/ems/EmsPublicEventDetailPage.vue';
import EventFormPage from '@/pages/ems/events/EventFormPage.vue';
import publicEventsService from '@/services/ems/publicEventsService';

// Mock vue-router
const pushMock = vi.fn();
vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { slug: 'annual-gala-2026' },
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
    joinWaitlist: vi.fn(),
    checkout: vi.fn(),
    registerFree: vi.fn(),
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

function baseEvent(overrides: Record<string, any> = {}) {
  return {
    uuid: 'evt-uuid-1',
    name: 'Annual Community Gala',
    slug: 'annual-gala-2026',
    short_description: 'An evening of unity and fellowship.',
    description: 'Detailed description of the gala event.',
    banner_url: null,
    location: 'SFU Student Union Building',
    start_at: '2026-11-20T18:00:00Z',
    end_at: '2026-11-20T21:00:00Z',
    timezone: 'America/Vancouver',
    capacity: 100,
    remaining_capacity: 50,
    is_full: false,
    is_sold_out: false,
    waitlist_enabled: true,
    status: 'registration_open',
    status_label: 'Registration Open',
    status_tone: 'success',
    lifecycle_state: 'UPCOMING',
    registration_availability: 'OPEN',
    is_accepting_registrations: true,
    is_accepting_waitlist: false,
    registration_label: 'Registration Open',
    is_public: true,
    audience: 'both',
    ticket_types: [
      {
        uuid: 'tkt-uuid-1',
        name: 'General Admission',
        description: 'Standard guest ticket',
        price: 0,
        currency: 'CAD',
        is_free: true,
        is_on_sale: true,
        is_sold_out: false,
        remaining_quantity: 50,
      },
    ],
    documents: [],
    show_volunteer_button: false,
    volunteer_url: null,
    ...overrides,
  };
}

describe('EMS Sold-Out Waitlist Registration and Public Event Page', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it('renders normal registration form for an OPEN event', async () => {
    vi.mocked(publicEventsService.getEvent).mockResolvedValueOnce(baseEvent() as any);

    const wrapper = mount(EmsPublicEventDetailPage, {
      global: {
        components: { RouterLink: RouterLinkMock },
        stubs: { ScrollReveal: { template: '<div><slot /></div>' } },
      },
    });

    await new Promise((r) => setTimeout(r, 60));
    await wrapper.vm.$nextTick();

    expect(wrapper.text()).toContain('Registration');
    expect(wrapper.text()).not.toContain('Waitlist Registration');
    expect(wrapper.text()).not.toContain('Waitlist Notice');

    // Registration form inputs exist
    expect(wrapper.find('input[autocomplete="given-name"]').exists()).toBe(true);
    expect(wrapper.find('input[autocomplete="email"]').exists()).toBe(true);

    // CTA button shows normal registration
    const submitBtn = wrapper.find('button[type="submit"]');
    expect(submitBtn.exists()).toBe(true);
    expect(submitBtn.text()).toContain('Register for free');
  });

  it('renders waitlist form with clear notice and no payment UI when event is SOLD_OUT and waitlist is enabled', async () => {
    vi.mocked(publicEventsService.getEvent).mockResolvedValueOnce(
      baseEvent({
        is_full: true,
        is_sold_out: true,
        remaining_capacity: 0,
        waitlist_enabled: true,
        is_accepting_waitlist: true,
        registration_availability: 'SOLD_OUT',
        registration_label: 'Join Waitlist',
        ticket_types: [
          {
            uuid: 'tkt-uuid-1',
            name: 'General Admission',
            description: 'Standard ticket',
            price: 25,
            currency: 'CAD',
            is_free: false,
            is_on_sale: true,
            is_sold_out: true,
            remaining_quantity: 0,
          },
        ],
      }) as any
    );

    const wrapper = mount(EmsPublicEventDetailPage, {
      global: {
        components: { RouterLink: RouterLinkMock },
        stubs: { ScrollReveal: { template: '<div><slot /></div>' } },
      },
    });

    await new Promise((r) => setTimeout(r, 60));
    await wrapper.vm.$nextTick();

    // 1. Waitlist heading & notice
    expect(wrapper.text()).toContain('Waitlist Registration');
    expect(wrapper.text()).toContain('Waitlist Notice');
    expect(wrapper.text()).toContain('Joining the waitlist does not guarantee a confirmed ticket or event admission.');

    // 2. Form remains visible
    expect(wrapper.find('form').exists()).toBe(true);
    expect(wrapper.find('input[autocomplete="given-name"]').exists()).toBe(true);
    expect(wrapper.find('input[autocomplete="email"]').exists()).toBe(true);

    // 3. Ticket type is selectable for waitlist (not blocked)
    const ticketRadio = wrapper.find('input[type="radio"]');
    expect(ticketRadio.exists()).toBe(true);
    expect(ticketRadio.attributes('disabled')).toBeUndefined();
    expect(wrapper.text()).toContain('Waitlist open');

    // 4. Promo code and payment order preview are hidden
    expect(wrapper.text()).not.toContain('Order preview');
    expect(wrapper.text()).not.toContain('Promo Code');
    expect(wrapper.text()).not.toContain('Complete payment later');

    // 5. CTA button displays Join waitlist
    const submitBtn = wrapper.find('button[type="submit"]');
    expect(submitBtn.exists()).toBe(true);
    expect(submitBtn.text()).toContain('Join waitlist');
  });

  it('hides registration form completely when event is SOLD_OUT and waitlist is disabled', async () => {
    vi.mocked(publicEventsService.getEvent).mockResolvedValueOnce(
      baseEvent({
        is_full: true,
        is_sold_out: true,
        remaining_capacity: 0,
        waitlist_enabled: false,
        is_accepting_waitlist: false,
        registration_availability: 'SOLD_OUT',
        registration_label: 'Sold Out',
      }) as any
    );

    const wrapper = mount(EmsPublicEventDetailPage, {
      global: {
        components: { RouterLink: RouterLinkMock },
        stubs: { ScrollReveal: { template: '<div><slot /></div>' } },
      },
    });

    await new Promise((r) => setTimeout(r, 60));
    await wrapper.vm.$nextTick();

    // Form is hidden
    expect(wrapper.find('form').exists()).toBe(false);
    expect(wrapper.find('input[autocomplete="given-name"]').exists()).toBe(false);

    // Displays sold out status
    const statusBox = wrapper.find('[role="status"]');
    expect(statusBox.exists()).toBe(true);
    expect(statusBox.text()).toContain('Sold out');
  });

  it('hides registration form when event status is registration_closed', async () => {
    vi.mocked(publicEventsService.getEvent).mockResolvedValueOnce(
      baseEvent({
        status: 'registration_closed',
        status_label: 'Registration Closed',
        registration_availability: 'CLOSED',
        is_accepting_registrations: false,
        is_accepting_waitlist: false,
        registration_label: 'Registration Closed',
      }) as any
    );

    const wrapper = mount(EmsPublicEventDetailPage, {
      global: {
        components: { RouterLink: RouterLinkMock },
        stubs: { ScrollReveal: { template: '<div><slot /></div>' } },
      },
    });

    await new Promise((r) => setTimeout(r, 60));
    await wrapper.vm.$nextTick();

    expect(wrapper.find('form').exists()).toBe(false);
    expect(wrapper.text()).toContain('Registration is closed.');
  });

  it('hides registration form when event has concluded (ended)', async () => {
    vi.mocked(publicEventsService.getEvent).mockResolvedValueOnce(
      baseEvent({
        status: 'completed',
        lifecycle_state: 'ENDED',
        registration_availability: 'CLOSED',
        is_accepting_registrations: false,
        is_accepting_waitlist: false,
        registration_label: 'Completed',
      }) as any
    );

    const wrapper = mount(EmsPublicEventDetailPage, {
      global: {
        components: { RouterLink: RouterLinkMock },
        stubs: { ScrollReveal: { template: '<div><slot /></div>' } },
      },
    });

    await new Promise((r) => setTimeout(r, 60));
    await wrapper.vm.$nextTick();

    expect(wrapper.find('form').exists()).toBe(false);
    expect(wrapper.text()).toContain('This event has concluded.');
  });

  it('submits waitlist request and displays position queue card upon success', async () => {
    vi.mocked(publicEventsService.getEvent).mockResolvedValueOnce(
      baseEvent({
        is_full: true,
        is_sold_out: true,
        remaining_capacity: 0,
        waitlist_enabled: true,
        is_accepting_waitlist: true,
        registration_availability: 'SOLD_OUT',
      }) as any
    );

    vi.mocked(publicEventsService.joinWaitlist).mockResolvedValueOnce({
      uuid: 'wl-uuid-123',
      position: 3,
      quantity: 1,
      status: 'waiting',
      attendee_name: 'Fatima Zahra',
      attendee_email: 'fatima@example.com',
    } as any);

    const wrapper = mount(EmsPublicEventDetailPage, {
      global: {
        components: { RouterLink: RouterLinkMock },
        stubs: { ScrollReveal: { template: '<div><slot /></div>' } },
      },
    });

    await new Promise((r) => setTimeout(r, 60));
    await wrapper.vm.$nextTick();

    // Fill form
    await wrapper.find('input[autocomplete="given-name"]').setValue('Fatima');
    await wrapper.find('input[autocomplete="family-name"]').setValue('Zahra');
    await wrapper.find('input[autocomplete="email"]').setValue('fatima@example.com');

    // Submit form
    await wrapper.find('form').trigger('submit');
    await wrapper.vm.$nextTick();
    await new Promise((r) => setTimeout(r, 60));
    await wrapper.vm.$nextTick();

    expect(publicEventsService.joinWaitlist).toHaveBeenCalledWith('annual-gala-2026', {
      first_name: 'Fatima',
      last_name: 'Zahra',
      email: 'fatima@example.com',
      phone: null,
      quantity: 1,
      ticket_type_id: 'tkt-uuid-1',
    });

    // Success card appears
    expect(wrapper.text()).toContain("You're on the waitlist!");
    expect(wrapper.text()).toContain('Queue Position: #3');
  });

  it('provides waitlist_enabled toggle switch on EventFormPage', async () => {
    const wrapper = mount(EventFormPage, {
      global: {
        components: { RouterLink: RouterLinkMock },
        stubs: {
          EmsPageHeader: true,
          ImageInput: true,
          EmsDateTimeField: true,
          Select: true,
          Textarea: true,
        },
      },
    });

    await wrapper.vm.$nextTick();

    // The switch for enabling waitlist exists
    const switches = wrapper.findAllComponents({ name: 'Switch' });
    const waitlistSwitch = switches.find((s) => s.props('label')?.includes('waitlist'));
    expect(waitlistSwitch).toBeDefined();
    expect(waitlistSwitch?.props('description')).toContain('join a waitlist queue');
  });
});
