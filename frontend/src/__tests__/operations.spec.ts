import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import OperationalAlertModal from '@/components/operations/OperationalAlertModal.vue';
import type { OperationalAlert } from '@/services/operationsService';

vi.mock('@/services/operationsService', async (importOriginal) => {
  const mod = await importOriginal<typeof import('@/services/operationsService')>();
  return {
    ...mod,
    operationsService: {
      ...mod.operationsService,
      getActionsForAlert: vi.fn().mockResolvedValue({ actions: [] }),
    },
  };
});

describe('Phase 24 Operations Center Frontend Components', () => {
  const mockAlert: OperationalAlert = {
    id: 42,
    uuid: 'test-uuid-42',
    fingerprint: 'md5hash',
    category: 'ems',
    severity: 'high',
    status: 'open',
    title: 'Ticket Issuance Missing for Paid Registration #REG-99',
    description: 'Registration #REG-99 has settled payment via square_pos but no ticket was issued.',
    source_type: 'Registration',
    source_id: '99',
    rule_key: 'ems_reconciled_square_ticket_missing',
    action_url: '/ems/events/evt-uuid-1',
    metadata: { payment_method: 'square_pos' },
    first_detected_at: '2026-09-17T00:00:00Z',
    last_detected_at: '2026-09-17T00:00:00Z',
    created_at: '2026-09-17T00:00:00Z',
    updated_at: '2026-09-17T00:00:00Z',
  };

  it('renders OperationalAlertModal with alert details and controls', () => {
    const wrapper = mount(OperationalAlertModal, {
      props: {
        alert: mockAlert,
        isOpen: true,
      },
    });

    expect(wrapper.text()).toContain('Ticket Issuance Missing for Paid Registration #REG-99');
    expect(wrapper.text()).toContain('ems_reconciled_square_ticket_missing');
    expect(wrapper.text()).toContain('high');
    expect(wrapper.text()).toContain('open');
    expect(wrapper.text()).toContain('Acknowledge');
    expect(wrapper.text()).toContain('Resolve Alert');
    expect(wrapper.text()).toContain('Dismiss');
  });

  it('emits acknowledge event when Acknowledge button is clicked', async () => {
    const wrapper = mount(OperationalAlertModal, {
      props: {
        alert: mockAlert,
        isOpen: true,
      },
    });

    const ackBtn = wrapper.findAll('button').find(b => b.text() === 'Acknowledge');
    expect(ackBtn).toBeDefined();

    await ackBtn?.trigger('click');
    expect(wrapper.emitted('acknowledge')).toBeTruthy();
    expect(wrapper.emitted('acknowledge')![0]).toEqual([42]);
  });
});
