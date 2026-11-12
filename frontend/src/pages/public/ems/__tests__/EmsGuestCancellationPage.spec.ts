import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { defineComponent, h } from 'vue';
import type { GuestCancellationInspection } from '@/types/ems/public';

const inspectGuestCancellation = vi.fn();
const confirmGuestCancellation = vi.fn();
let mockRouteToken = 'valid-token-123';

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { token: mockRouteToken },
  }),
  useRouter: () => ({
    push: vi.fn(),
  }),
}));

vi.mock('lucide-vue-next', () => {
  const icon = (name: string) =>
    defineComponent({
      name,
      setup: (_, { attrs }) => () => h('svg', { 'data-icon': name, ...attrs }),
    });
  return {
    Calendar: icon('Calendar'),
    Clock: icon('Clock'),
    MapPin: icon('MapPin'),
    Loader2: icon('Loader2'),
    ArrowRight: icon('ArrowRight'),
    Ticket: icon('Ticket'),
    ShieldAlert: icon('ShieldAlert'),
    CheckCircle2: icon('CheckCircle2'),
    AlertTriangle: icon('AlertTriangle'),
    XCircle: icon('XCircle'),
  };
});

vi.mock('@/composables/useSeo', () => ({
  useSeo: () => undefined,
}));

vi.mock('@/composables/ems/useEventFormatting', () => ({
  useEventFormatting: () => ({
    formatDateRange: () => 'Sep 15, 2026',
    formatTimeRange: () => '5:00 PM - 8:00 PM',
  }),
}));

vi.mock('@/services/ems/publicEventsService', () => ({
  default: {
    inspectGuestCancellation: (...args: unknown[]) => inspectGuestCancellation(...args),
    confirmGuestCancellation: (...args: unknown[]) => confirmGuestCancellation(...args),
  },
}));

vi.mock('@/components/feedback/toast', () => ({
  useToastStore: () => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  }),
}));

import EmsGuestCancellationPage from '../EmsGuestCancellationPage.vue';

function mockValidInspection(): GuestCancellationInspection {
  return {
    state: 'valid',
    message: 'Valid cancellation link.',
    registration: {
      reference: 'REG-GUEST-01',
      attendee_name: 'Fatima Guest',
      attendee_email_masked: 'f*****t@example.com',
      ticket_type_name: 'General Admission',
      quantity: 1,
      status: 'confirmed',
      is_cancelled: false,
      cancelled_at: null,
      event: {
        name: 'MSA Welcome BBQ',
        slug: 'msa-welcome-bbq',
        start_at: new Date(Date.now() + 86400000).toISOString(),
        end_at: new Date(Date.now() + 90000000).toISOString(),
        location: 'Burnaby Mountain Campus',
      },
    },
  };
}

async function mountPage() {
  const wrapper = mount(EmsGuestCancellationPage, {
    global: {
      stubs: {
        RouterLink: {
          props: ['to'],
          template: '<a :href="typeof to === \'string\' ? to : (to.path || \'#\')"><slot /></a>',
        },
      },
    },
  });
  await flushPromises();
  return wrapper;
}

describe('EmsGuestCancellationPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    mockRouteToken = 'valid-token-123';
  });

  it('renders valid cancellation preview with attendee and event details', async () => {
    inspectGuestCancellation.mockResolvedValue(mockValidInspection());

    const wrapper = await mountPage();

    expect(inspectGuestCancellation).toHaveBeenCalledWith('valid-token-123');
    expect(wrapper.text()).toContain('Confirm Ticket Cancellation');
    expect(wrapper.text()).toContain('MSA Welcome BBQ');
    expect(wrapper.text()).toContain('Fatima Guest');
    expect(wrapper.text()).toContain('f*****t@example.com');
    expect(wrapper.text()).toContain('REG-GUEST-01');
    expect(wrapper.text()).toContain('Free Registration');
    expect(wrapper.find('button').text()).toContain('Confirm Cancellation');
  });

  it('executes cancellation on confirm button click and displays success state', async () => {
    inspectGuestCancellation.mockResolvedValue(mockValidInspection());
    confirmGuestCancellation.mockResolvedValue({ status: 'cancelled' });

    const wrapper = await mountPage();

    const confirmBtn = wrapper.findAll('button').find((btn) => btn.text().includes('Confirm Cancellation'))!;
    await confirmBtn.trigger('click');
    await flushPromises();

    expect(confirmGuestCancellation).toHaveBeenCalledWith('valid-token-123');
    expect(wrapper.text()).toContain('Registration Cancelled');
    expect(wrapper.text()).toContain('Browse More Events');
  });

  it('renders expired token state when token has expired', async () => {
    inspectGuestCancellation.mockResolvedValue({
      state: 'expired',
      message: 'This cancellation link has expired.',
    });

    const wrapper = await mountPage();

    expect(wrapper.text()).toContain('Cancellation Link Expired');
    expect(wrapper.text()).toContain('events@sfumsa.ca');
  });

  it('renders already-cancelled state when token has already been used', async () => {
    inspectGuestCancellation.mockResolvedValue({
      state: 'already_cancelled',
      message: 'This registration has already been cancelled.',
      registration: mockValidInspection().registration,
    });

    const wrapper = await mountPage();

    expect(wrapper.text()).toContain('Registration Has Already Been Cancelled');
    expect(wrapper.text()).toContain('REG-GUEST-01');
  });

  it('renders forbidden state when paid ticket is inspected via guest link', async () => {
    inspectGuestCancellation.mockResolvedValue({
      state: 'forbidden',
      message: 'Paid registrations cannot be cancelled through guest links.',
    });

    const wrapper = await mountPage();

    expect(wrapper.text()).toContain('Paid Registration Cancellation');
    expect(wrapper.text()).toContain('events@sfumsa.ca');
  });

  it('renders invalid link state for malformed or unknown token', async () => {
    inspectGuestCancellation.mockResolvedValue({
      state: 'invalid',
      message: 'Invalid cancellation link.',
    });

    const wrapper = await mountPage();

    expect(wrapper.text()).toContain('Invalid Cancellation Link');
  });
});

