import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AutomationHealthDashboard from '@/components/operations/AutomationHealthDashboard.vue';
import GovernanceReadinessCard from '@/components/governance/GovernanceReadinessCard.vue';
import ContinuityReadinessPanel from '@/components/governance/ContinuityReadinessPanel.vue';
import IntegrityStatusPanel from '@/components/governance/IntegrityStatusPanel.vue';
import GovernanceDashboardPage from '@/pages/admin/governance/GovernanceDashboardPage.vue';
import { operationsService } from '@/services/operationsService';
import { governanceService } from '@/services/governanceService';

vi.mock('@/services/operationsService', async (importOriginal) => {
  const mod = await importOriginal<typeof import('@/services/operationsService')>();
  return {
    ...mod,
    operationsService: {
      ...mod.operationsService,
      getGovernanceHealth: vi.fn().mockResolvedValue({
        metrics: {
          total_executions: 25,
          completed_executions: 23,
          failed_executions: 2,
          execution_success_rate: 92,
          problem_resolved_count: 22,
          problem_resolution_effectiveness_rate: 88,
          blocked_executions_count: 1,
          pending_approvals_count: 2,
          action_breakdown: [
            {
              action_key: 'volunteers.refresh_pending_backlog',
              name: 'Refresh Volunteer Backlog',
              risk_level: 'low',
              total_executions: 15,
              success_count: 15,
              resolved_count: 14,
              failed_count: 0,
              success_rate: 100,
              resolution_effectiveness_rate: 93.3,
            },
          ],
        },
      }),
      getApprovals: vi.fn().mockResolvedValue({
        data: [],
        total: 0,
      }),
      requestApproval: vi.fn().mockResolvedValue({
        id: 1,
        uuid: 'appr-uuid-1',
        status: 'pending',
      }),
      approveRequest: vi.fn().mockResolvedValue({
        id: 1,
        status: 'approved',
      }),
      rejectRequest: vi.fn().mockResolvedValue({
        id: 1,
        status: 'rejected',
      }),
    },
  };
});

vi.mock('@/services/governanceService', async (importOriginal) => {
  const mod = await importOriginal<typeof import('@/services/governanceService')>();
  const mockOverview = {
    period: '7d',
    generated_at: '2026-09-14T00:00:00Z',
    governance_score: 85,
    score_level: 'GOOD',
    score_drivers: [
      {
        category: 'backup_verification',
        deduction: 10,
        reason: 'Readiness reduced by 10 points because database & asset backup verification status is NOT_VERIFIED.',
      },
    ],
    audit_activity: {
      recent_logs_count: 10,
      recent_logs: [],
    },
    change_accountability: [
      {
        id: 1,
        who: { id: 1, name: 'Super Admin', email: 'admin@sfumsa.ca' },
        what: { action: 'grant_application_access', description: 'Granted access to EMS', severity: 'info' },
        when: { timestamp: '2026-09-14T00:00:00Z', human: '5 minutes ago' },
        where: { domain: 'ems', target_type: 'User', target_id: 2, ip_address: '127.0.0.1' },
        why: 'Reason not recorded',
        result: 'Granted',
        payload: {},
      },
    ],
    integrity_status: {
      status: 'PASSED',
      scanned_at: '2026-09-14T00:00:00Z',
      total_checks: 10,
      passed_count: 10,
      warning_count: 0,
      failed_count: 0,
      checks: [
        {
          domain: 'EMS',
          check_key: 'paid_registrations_without_tickets',
          status: 'PASSED',
          issue_count: 0,
          summary: 'All paid registrations have issued tickets.',
        },
      ],
    },
    continuity_readiness: {
      overall_status: 'HEALTHY',
      evaluated_at: '2026-09-14T00:00:00Z',
      probes: {
        database: { status: 'HEALTHY', name: 'Database Primary Connection', details: 'Active', last_checked_at: '2026-09-14T00:00:00Z' },
        cache: { status: 'HEALTHY', name: 'Cache Store', details: 'Verified', last_checked_at: '2026-09-14T00:00:00Z' },
        queue: { status: 'HEALTHY', name: 'Queue System', details: 'Healthy', last_checked_at: '2026-09-14T00:00:00Z' },
        scheduler: { status: 'HEALTHY', name: 'Scheduler Runner', details: 'Active', last_checked_at: '2026-09-14T00:00:00Z' },
        backup_verification: {
          status: 'NOT_VERIFIED',
          verification_state: 'UNKNOWN',
          name: 'Database & Media Asset Backup Verification',
          details: 'No automated backup verification agent detected in cPanel / shared-hosting environment.',
          recommendation: 'Perform manual backup checks via hosting portal.',
          last_checked_at: '2026-09-14T00:00:00Z',
        },
      },
      continuity_summary: 'Platform infrastructure is operational. Backup verification is NOT_VERIFIED.',
    },
    governance_backlog: {
      pending_approvals_count: 0,
      stale_approvals_count: 0,
    },
    automation_reliability: {},
  };

  return {
    ...mod,
    governanceService: {
      ...mod.governanceService,
      getGovernanceOverview: vi.fn().mockResolvedValue(mockOverview),
      searchAuditLogs: vi.fn().mockResolvedValue({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 20 }),
      getIncidentTimeline: vi.fn().mockResolvedValue({ status: 'success', nodes_count: 0, nodes: [] }),
      getIntegrityStatus: vi.fn().mockResolvedValue(mockOverview.integrity_status),
      getContinuityReadiness: vi.fn().mockResolvedValue(mockOverview.continuity_readiness),
      getReportExportUrl: vi.fn().mockReturnValue('/api/v1/admin/governance/report?period=30d&format=csv'),
    },
  };
});

describe('Phase 26 Operational Governance Frontend Integration', () => {
  it('renders AutomationHealthDashboard with governance metrics and effectiveness rates', async () => {
    const wrapper = mount(AutomationHealthDashboard);

    await new Promise((r) => setTimeout(r, 50));
    await wrapper.vm.$nextTick();

    expect(operationsService.getGovernanceHealth).toHaveBeenCalled();
    expect(wrapper.text()).toContain('Automation Intelligence & Governance Health');
    expect(wrapper.text()).toContain('92%');
    expect(wrapper.text()).toContain('88%');
    expect(wrapper.text()).toContain('25');
  });
});

describe('Phase 28 Governance & Continuity Frontend Suite', () => {
  it('renders GovernanceReadinessCard with score, GOOD level, and score drivers', () => {
    const wrapper = mount(GovernanceReadinessCard, {
      props: {
        score: 85,
        level: 'GOOD',
        drivers: [
          {
            category: 'backup_verification',
            deduction: 10,
            reason: 'Readiness reduced by 10 points because database & asset backup verification status is NOT_VERIFIED.',
          },
        ],
      },
    });

    expect(wrapper.text()).toContain('85');
    expect(wrapper.text()).toContain('GOOD');
    expect(wrapper.text()).toContain('NOT_VERIFIED');
  });

  it('renders ContinuityReadinessPanel with NOT_VERIFIED backup status badge', () => {
    const wrapper = mount(ContinuityReadinessPanel, {
      props: {
        readiness: {
          overall_status: 'HEALTHY',
          evaluated_at: '2026-09-14T00:00:00Z',
          probes: {
            database: { status: 'HEALTHY', name: 'Database Primary Connection', details: 'Active', last_checked_at: '2026-09-14T00:00:00Z' },
            cache: { status: 'HEALTHY', name: 'Cache Store', details: 'Verified', last_checked_at: '2026-09-14T00:00:00Z' },
            queue: { status: 'HEALTHY', name: 'Queue System', details: 'Healthy', last_checked_at: '2026-09-14T00:00:00Z' },
            scheduler: { status: 'HEALTHY', name: 'Scheduler Runner', details: 'Active', last_checked_at: '2026-09-14T00:00:00Z' },
            backup_verification: {
              status: 'NOT_VERIFIED',
              verification_state: 'UNKNOWN',
              name: 'Database & Media Asset Backup Verification',
              details: 'No automated backup verification agent detected in cPanel / shared-hosting environment.',
              recommendation: 'Perform manual backup checks via hosting portal.',
              last_checked_at: '2026-09-14T00:00:00Z',
            },
          },
          continuity_summary: 'Platform infrastructure is operational. Backup verification is NOT_VERIFIED.',
        },
      },
    });

    expect(wrapper.text()).toContain('Continuity & Recovery Readiness');
    expect(wrapper.text()).toContain('NOT_VERIFIED');
    expect(wrapper.text()).toContain('No automated backup verification agent detected');
  });

  it('renders IntegrityStatusPanel with domain diagnostic checks', () => {
    const wrapper = mount(IntegrityStatusPanel, {
      props: {
        result: {
          status: 'PASSED',
          scanned_at: '2026-09-14T00:00:00Z',
          total_checks: 10,
          passed_count: 10,
          warning_count: 0,
          failed_count: 0,
          checks: [
            {
              domain: 'EMS',
              check_key: 'paid_registrations_without_tickets',
              status: 'PASSED',
              issue_count: 0,
              summary: 'All paid registrations have issued tickets.',
            },
          ],
        },
      },
    });

    expect(wrapper.text()).toContain('Data Integrity Diagnostics');
    expect(wrapper.text()).toContain('PASSED');
    expect(wrapper.text()).toContain('paid_registrations_without_tickets');
  });

  it('mounts GovernanceDashboardPage and calls governanceService.getGovernanceOverview', async () => {
    const wrapper = mount(GovernanceDashboardPage, {
      global: {
        stubs: ['router-link'],
      },
    });

    await new Promise((r) => setTimeout(r, 50));
    await wrapper.vm.$nextTick();

    expect(governanceService.getGovernanceOverview).toHaveBeenCalled();
    expect(wrapper.text()).toContain('Governance & Operational Continuity');
    expect(wrapper.text()).toContain('85');
  });
});
