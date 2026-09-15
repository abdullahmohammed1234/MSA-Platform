import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import OperationalAlertModal from '@/components/operations/OperationalAlertModal.vue';
import { operationsService, type OperationalAlert } from '@/services/operationsService';

vi.mock('@/services/operationsService', async (importOriginal) => {
  const mod = await importOriginal<typeof import('@/services/operationsService')>();
  return {
    ...mod,
    operationsService: {
      ...mod.operationsService,
      getActionsForAlert: vi.fn().mockResolvedValue({
        actions: [
          {
            key: 'ems.issue_missing_ticket',
            name: 'Issue Missing Ticket',
            description: 'Generate ticket for confirmed paid registration',
            required_permission: 'platform.operations.execute',
            requires_confirmation: true,
            is_reversible: false,
            precondition: {
              valid: true,
              reason: null,
            },
            governance: {
              risk_level: 'medium',
              requires_approval: false,
              cooldown_seconds: 300,
              max_failure_threshold: 3,
              allowed: true,
              block_reason: null,
              pending_approval: null,
            },
          },
        ],
      }),
      executeAction: vi.fn().mockResolvedValue({
        success: true,
        message: 'Ticket issued successfully.',
        execution: {
          id: 1,
          uuid: 'exec-uuid-1',
          alert_id: 42,
          action_key: 'ems.issue_missing_ticket',
          requested_by: 5,
          status: 'completed',
          summary: 'Ticket issued successfully.',
          before_snapshot: { ticket_count: 0 },
          after_snapshot: { ticket_count: 1 },
          created_at: '2026-09-18T00:00:00Z',
          updated_at: '2026-09-18T00:00:00Z',
        },
      }),
    },
  };
});

describe('Phase 25 Automated Remediation Frontend Integration', () => {
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

  it('fetches and renders recommended remediation actions in OperationalAlertModal', async () => {
    const wrapper = mount(OperationalAlertModal, {
      props: {
        alert: mockAlert,
        isOpen: true,
      },
    });

    // Wait for async getActionsForAlert call
    await new Promise((r) => setTimeout(r, 50));
    await wrapper.vm.$nextTick();

    expect(operationsService.getActionsForAlert).toHaveBeenCalledWith(42);
    expect(wrapper.text()).toContain('Governed Remediation Actions');
    expect(wrapper.text()).toContain('Issue Missing Ticket');
    expect(wrapper.text()).toContain('Allowed');
  });
});
