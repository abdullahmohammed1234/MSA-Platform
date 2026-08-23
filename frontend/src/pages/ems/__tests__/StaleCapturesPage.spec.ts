import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { defineComponent, h } from 'vue';
import type { StaleCapture } from '@/types/ems/staleCaptures';

const list = vi.fn();
const get = vi.fn();
const refund = vi.fn();
const resolveWithoutRefund = vi.fn();

const canRefundPayments = vi.fn(() => true);

vi.mock('lucide-vue-next', () => {
  const icon = (name: string) =>
    defineComponent({
      name,
      setup: (_, { attrs }) => () => h('svg', { 'data-icon': name, ...attrs }),
    });
  return {
    AlertTriangle: icon('AlertTriangle'),
    Search: icon('Search'),
  };
});

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({
    params: { paymentUuid: 'pay-uuid', squarePaymentId: 'sq_stale_pay_1' },
  }),
}));

vi.mock('@/composables/ems/useEmsPermissions', () => ({
  useEmsPermissions: () => ({ canRefundPayments: { value: canRefundPayments() } }),
}));

vi.mock('@/composables/ems/useEmsApiError', () => ({
  useEmsApiError: () => ({
    fieldError: () => null,
    generalError: { value: null },
    handle: (error: unknown) => ({
      message: error instanceof Error ? error.message : 'Request failed',
    }),
    clear: vi.fn(),
  }),
}));

vi.mock('@/components/feedback/toast', () => ({
  useToastStore: () => ({ success: vi.fn(), error: vi.fn() }),
}));

vi.mock('@/services/ems/staleCapturesService', () => ({
  staleCapturesService: {
    list: (...args: unknown[]) => list(...args),
    get: (...args: unknown[]) => get(...args),
    refund: (...args: unknown[]) => refund(...args),
    resolveWithoutRefund: (...args: unknown[]) => resolveWithoutRefund(...args),
  },
}));

const sampleCapture = (): StaleCapture => ({
  payment_uuid: 'pay-uuid',
  payment_status: 'cancelled',
  payment_status_label: 'Cancelled',
  order_uuid: 'ord-uuid',
  order_reference: 'ORD-1',
  registration_uuid: 'reg-uuid',
  registration_status: 'cancelled',
  registration_status_label: 'Cancelled',
  attendee_name: 'Sara Ahmed',
  attendee_email: 'sara@example.com',
  event_uuid: 'evt-uuid',
  event_name: 'Community Dinner',
  event_missing: false,
  checkout_amount: 15,
  currency: 'CAD',
  square_payment_id: 'sq_stale_pay_1',
  square_order_id: 'sq_order_1',
  reported_at: '2026-08-23T12:00:00Z',
  source: 'webhook',
  webhook_event_id: 'evt_1',
  buyer_cancelled_at: '2026-08-23T11:55:00Z',
  ticket_count: 0,
  resolution_status: 'unresolved',
  resolved_at: null,
  resolved_by_user_id: null,
  resolved_by_name: null,
  resolution_reason: null,
  square_refund_uuid: null,
  amount_refunded: null,
  remaining_refundable_amount: null,
});

describe('StaleCapturesPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    list.mockReset();
    canRefundPayments.mockReturnValue(true);
  });

  it('renders stale capture list rows', async () => {
    list.mockResolvedValue({ items: [sampleCapture()], total: 1 });
    const StaleCapturesPage = (await import('@/pages/ems/StaleCapturesPage.vue')).default;
    const wrapper = mount(StaleCapturesPage);
    await flushPromises();

    expect(wrapper.text()).toMatch(/stale capture — buyer cancelled/i);
    expect(wrapper.text()).toContain('Sara Ahmed');
    expect(wrapper.text()).toContain('sq_stale_pay_1');
  });
});

describe('StaleCaptureDetailPage', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    get.mockReset();
    refund.mockReset();
    resolveWithoutRefund.mockReset();
    canRefundPayments.mockReturnValue(true);
    vi.stubGlobal('confirm', vi.fn(() => true));
  });

  it('shows unresolved actions', async () => {
    get.mockResolvedValue(sampleCapture());
    const StaleCaptureDetailPage = (await import('@/pages/ems/StaleCaptureDetailPage.vue')).default;
    const wrapper = mount(StaleCaptureDetailPage);
    await flushPromises();

    expect(wrapper.text()).toContain('Refund Capture');
    expect(wrapper.text()).toContain('Resolve Without Refund');
  });

  it('requires a reason before resolve submission', async () => {
    get.mockResolvedValue(sampleCapture());
    const StaleCaptureDetailPage = (await import('@/pages/ems/StaleCaptureDetailPage.vue')).default;
    const wrapper = mount(StaleCaptureDetailPage);
    await flushPromises();

    await wrapper.findAll('button').find((b) => b.text().includes('Resolve Without Refund'))!.trigger('click');
    const confirmButton = wrapper
      .findAll('button')
      .find((button) => button.text().includes('Confirm Resolution'));
    expect(confirmButton?.attributes('disabled')).toBeDefined();
  });

  it('submits resolve without refund with reason', async () => {
    get.mockResolvedValue(sampleCapture());
    resolveWithoutRefund.mockResolvedValue({
      ...sampleCapture(),
      resolution_status: 'resolved_no_refund',
      resolution_reason: 'Handled offline.',
    });

    const StaleCaptureDetailPage = (await import('@/pages/ems/StaleCaptureDetailPage.vue')).default;
    const wrapper = mount(StaleCaptureDetailPage);
    await flushPromises();

    await wrapper.findAll('button').find((b) => b.text().includes('Resolve Without Refund'))!.trigger('click');
    const textarea = wrapper.find('textarea');
    await textarea.setValue('Handled offline.');
    await wrapper.findAll('button').find((b) => b.text().includes('Confirm Resolution'))!.trigger('click');
    await flushPromises();

    expect(resolveWithoutRefund).toHaveBeenCalledWith('pay-uuid', 'sq_stale_pay_1', {
      reason: 'Handled offline.',
    });
  });

  it('disables actions for already resolved capture', async () => {
    get.mockResolvedValue({
      ...sampleCapture(),
      resolution_status: 'resolved_no_refund',
      resolution_reason: 'Done.',
    });

    const StaleCaptureDetailPage = (await import('@/pages/ems/StaleCaptureDetailPage.vue')).default;
    const wrapper = mount(StaleCaptureDetailPage);
    await flushPromises();

    expect(wrapper.text()).not.toContain('Refund Capture');
    expect(wrapper.text()).toContain('Done.');
  });

  it('handles API failure', async () => {
    get.mockRejectedValue(new Error('Forbidden'));
    const StaleCaptureDetailPage = (await import('@/pages/ems/StaleCaptureDetailPage.vue')).default;
    const wrapper = mount(StaleCaptureDetailPage);
    await flushPromises();

    expect(wrapper.text()).toContain('Forbidden');
  });
});
