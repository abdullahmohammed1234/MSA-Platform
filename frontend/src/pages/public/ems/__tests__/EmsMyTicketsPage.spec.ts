import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { defineComponent, h } from 'vue';
import type { PublicRegistration } from '@/types/ems/public';
import type { StoredPendingCheckout } from '@/services/ems/pendingCheckoutStorage';

const cancelCheckout = vi.fn();
const cancelRegistration = vi.fn();
const getMyTickets = vi.fn();
const resumeCheckout = vi.fn();

const pendingList = vi.fn(() => [] as StoredPendingCheckout[]);
const pendingRemove = vi.fn();
const pendingGet = vi.fn();

const hideRegistration = vi.fn();
const hidePending = vi.fn();
const hasRegistration = vi.fn(() => false);
const hasPending = vi.fn(() => false);

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
    Trash2: icon('Trash2'),
  };
});

vi.mock('@/composables/useSeo', () => ({
  useSeo: () => undefined,
}));

vi.mock('@/composables/ems/useEventFormatting', () => ({
  useEventFormatting: () => ({
    formatDateRange: () => 'Aug 23',
    formatTimeRange: () => '6:00 PM',
  }),
}));

vi.mock('@/services/ems/publicEventsService', () => ({
  default: {
    getMyTickets: (...args: unknown[]) => getMyTickets(...args),
    cancelCheckout: (...args: unknown[]) => cancelCheckout(...args),
    cancelRegistration: (...args: unknown[]) => cancelRegistration(...args),
    resumeCheckout: (...args: unknown[]) => resumeCheckout(...args),
  },
}));

vi.mock('@/services/ems/pendingCheckoutStorage', () => ({
  default: {
    list: () => pendingList(),
    remove: (...args: unknown[]) => pendingRemove(...args),
    get: (...args: unknown[]) => pendingGet(...args),
  },
}));

vi.mock('@/services/ems/hiddenTicketsStorage', () => ({
  default: {
    hasRegistration: (...args: unknown[]) => hasRegistration(...args),
    hasPending: (...args: unknown[]) => hasPending(...args),
    hideRegistration: (...args: unknown[]) => hideRegistration(...args),
    hidePending: (...args: unknown[]) => hidePending(...args),
  },
}));

vi.mock('@/components/feedback/toast', () => ({
  useToastStore: () => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  }),
}));

import EmsMyTicketsPage from '../EmsMyTicketsPage.vue';
import { EmsApiError } from '@/services/ems/emsClient';

function awaitingPaymentRegistration(overrides: Partial<PublicRegistration> = {}): PublicRegistration {
  return {
    reference: 'REG-1',
    uuid: 'reg-awaiting-uuid',
    status: 'awaiting_payment',
    status_label: 'Awaiting Payment',
    type: 'paid',
    attendee_name: 'Omar Hassan',
    attendee_email: 'omar@example.com',
    quantity: 1,
    amount_due: 25,
    currency: 'CAD',
    registered_at: new Date().toISOString(),
    confirmed_at: null,
    ticket_type: { uuid: 'tt-1', name: 'GA' },
    pending_checkout: {
      order_uuid: 'order-uuid-1',
      checkout_url: 'https://square.test/pay',
      amount: 25,
      currency: 'CAD',
      checkout_version: 1,
    },
    event: {
      uuid: 'event-uuid',
      name: 'Welcome Night',
      slug: 'welcome-night',
      start_at: new Date(Date.now() + 86400000).toISOString(),
      end_at: new Date(Date.now() + 90000000).toISOString(),
      location: 'Campus',
      status: 'registration_open',
      status_label: 'Registration Open',
    },
    tickets: [],
    ...overrides,
  };
}

function confirmedRegistration(): PublicRegistration {
  return awaitingPaymentRegistration({
    uuid: 'reg-confirmed-uuid',
    status: 'confirmed',
    status_label: 'Registered',
    confirmed_at: new Date().toISOString(),
    pending_checkout: null,
    tickets: [
      {
        code: 'MSA-1',
        uuid: 'tkt-1',
        status: 'issued',
        status_label: 'Issued',
        holder_name: 'Omar Hassan',
        issued_at: new Date().toISOString(),
        qr_payload: 'MSA-1',
        event: null,
        registration: null,
      },
    ],
  });
}

function localPending(): StoredPendingCheckout {
  return {
    slug: 'local-event',
    event_name: 'Local Pending Event',
    email: 'guest@example.com',
    first_name: 'Guest',
    last_name: 'User',
    phone: '',
    quantity: 1,
    ticket_type_id: 'tt-local',
    ticket_name: 'GA',
    promo_code: null,
    order_uuid: 'local-order-uuid',
    checkout_url: 'https://square.test/local',
    amount: 15,
    currency: 'CAD',
    saved_at: new Date().toISOString(),
  };
}

async function mountPage() {
  const wrapper = mount(EmsMyTicketsPage, {
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

describe('EmsMyTicketsPage pending payment cancellation', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    getMyTickets.mockResolvedValue([]);
    pendingList.mockReturnValue([]);
    hasRegistration.mockReturnValue(false);
    hasPending.mockReturnValue(false);
    cancelCheckout.mockResolvedValue(undefined);
  });

  it('shows Cancel Pending Payment for awaiting_payment but not for confirmed registrations', async () => {
    getMyTickets.mockResolvedValue([awaitingPaymentRegistration(), confirmedRegistration()]);

    const wrapper = await mountPage();

    const cancelButtons = wrapper
      .findAll('button')
      .filter((btn) => btn.text().includes('Cancel Pending Payment'));
    expect(cancelButtons).toHaveLength(1);
    expect(wrapper.text()).toContain('Complete Payment');
    expect(wrapper.text()).toContain('View Ticket');
    expect(wrapper.text()).toContain('Cancel Booking');
  });

  it('cancels pending payment through cancelCheckout and clears local storage after success', async () => {
    const reg = awaitingPaymentRegistration();
    getMyTickets
      .mockResolvedValueOnce([reg])
      .mockResolvedValueOnce([
        {
          ...reg,
          status: 'cancelled',
          status_label: 'Cancelled',
          pending_checkout: null,
        },
      ]);

    const wrapper = await mountPage();

    await wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('Cancel Pending Payment'))!
      .trigger('click');

    expect(wrapper.text()).toContain('Cancel Pending Payment?');
    expect(wrapper.text()).toContain('No payment was taken');

    await wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('Yes, Cancel Payment'))!
      .trigger('click');
    await flushPromises();

    expect(cancelCheckout).toHaveBeenCalledWith('welcome-night', 'omar@example.com', 'order-uuid-1');
    expect(pendingRemove).toHaveBeenCalledWith('welcome-night');
    expect(getMyTickets).toHaveBeenCalledTimes(2);
  });

  it('preserves local pending checkout when backend cancellation fails', async () => {
    getMyTickets.mockResolvedValue([awaitingPaymentRegistration()]);
    cancelCheckout.mockRejectedValue(new EmsApiError('Unable to cancel checkout.', 500));

    const wrapper = await mountPage();

    await wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('Cancel Pending Payment'))!
      .trigger('click');
    await wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('Yes, Cancel Payment'))!
      .trigger('click');
    await flushPromises();

    expect(cancelCheckout).toHaveBeenCalled();
    expect(pendingRemove).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('Complete Payment');
  });

  it('keeps Remove from list local-only and does not call cancelCheckout', async () => {
    const item = localPending();
    pendingList.mockReturnValue([item]);
    getMyTickets.mockResolvedValue([]);

    const wrapper = await mountPage();

    await wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('Remove from list'))!
      .trigger('click');

    expect(wrapper.text()).toContain('does not cancel a booking');

    await wrapper
      .findAll('button')
      .find((btn) => btn.text() === 'Remove from list' && btn.classes().includes('bg-primary'))!
      .trigger('click');

    expect(cancelCheckout).not.toHaveBeenCalled();
    expect(hidePending).toHaveBeenCalledWith('local-event');
    expect(pendingRemove).toHaveBeenCalledWith('local-event');
  });

  it('cancels device-only pending checkout via cancelCheckout then clears local entry', async () => {
    const item = localPending();
    pendingList.mockReturnValue([item]);
    getMyTickets.mockResolvedValue([]);

    const wrapper = await mountPage();

    await wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('Cancel Pending Payment'))!
      .trigger('click');
    await wrapper
      .findAll('button')
      .find((btn) => btn.text().includes('Yes, Cancel Payment'))!
      .trigger('click');
    await flushPromises();

    expect(cancelCheckout).toHaveBeenCalledWith('local-event', 'guest@example.com', 'local-order-uuid');
    expect(pendingRemove).toHaveBeenCalledWith('local-event');
  });
});
