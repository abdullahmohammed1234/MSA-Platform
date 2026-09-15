import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CommandCenterPage from '@/pages/admin/operations/CommandCenterPage.vue';
import OperationalRiskCard from '@/components/command-center/OperationalRiskCard.vue';
import PlatformStatusOverview from '@/components/command-center/PlatformStatusOverview.vue';
import AttentionRequiredPanel from '@/components/command-center/AttentionRequiredPanel.vue';
import ApplicationHealthMatrix from '@/components/command-center/ApplicationHealthMatrix.vue';
import { commandCenterService } from '@/services/commandCenterService';

vi.mock('@/services/commandCenterService', async (importOriginal) => {
  const mod = await importOriginal<typeof import('@/services/commandCenterService')>();
  return {
    ...mod,
    commandCenterService: {
      ...mod.commandCenterService,
      getCommandCenterData: vi.fn().mockResolvedValue({
        success: true,
        generated_at: '2026-09-14T00:00:00Z',
        period: '30d',
        platform: {
          overall_status: 'degraded',
          critical_alerts_count: 1,
          high_alerts_count: 2,
          pending_approvals_count: 1,
          blocked_automations_count: 1,
          failed_jobs_count: 3,
          scheduler_status: 'operational',
          communication_failures_count: 0,
        },
        risk: {
          score: 45,
          level: 'HIGH',
          color: 'orange',
          contributing_factors: [
            '1 active CRITICAL operational alert(s) (+30 pts)',
            '2 active HIGH operational alert(s) (+30 pts)',
          ],
        },
        attention: [
          {
            id: 'alert-1',
            type: 'alert',
            severity: 'critical',
            source: 'Ems',
            title: 'Critical Ticket Mismatch',
            summary: 'Registration #99999 missing ticket',
            created_at: '2026-09-14T00:00:00Z',
            age_human: '5 minutes ago',
            status: 'open',
            action_name: 'View in Operations',
            action_key: null,
            drilldown_url: '/admin/operations',
          },
        ],
        infrastructure: {
          database: { status: 'operational', message: 'DB connected', driver: 'mysql' },
          storage: { status: 'operational', message: 'Storage active' },
          email: { status: 'operational', message: 'Mail active' },
          queues: { status: 'operational', message: 'Queues active', failed_jobs: 3 },
          scheduler: { status: 'operational', message: 'Scheduler active' },
          communications: { status: 'operational', message: 'Notifications active', failed_count: 0 },
        },
        applications: [
          {
            id: 'ems',
            name: 'Event Management System',
            status: 'operational',
            health_status: 'operational',
            status_reason: 'Reachable',
            access_granted: true,
            open_issues_count: 1,
            launch_url: '/ems',
            admin_path: '/ems/admin',
            last_checked_at: '2026-09-14T00:00:00Z',
          },
          {
            id: 'donations',
            name: 'Donations Management System',
            status: 'operational',
            health_status: 'operational',
            status_reason: 'Reachable',
            access_granted: false,
            open_issues_count: 0,
            launch_url: '/donations/admin',
            admin_path: '/donations/admin',
            last_checked_at: '2026-09-14T00:00:00Z',
          },
        ],
        business: {
          ems: { access_granted: true, status: 'available', total_registrations: 120 },
          donations: { access_granted: false, status: 'restricted', message: 'Access Restricted' },
        },
        automation: {
          status: 'available',
          total_executions: 10,
          completed_executions: 9,
          failed_executions: 1,
          execution_success_rate: 90,
          problem_resolution_effectiveness_rate: 85,
          pending_approvals_count: 1,
          blocked_executions_count: 1,
          action_breakdown: [],
        },
      }),
    },
  };
});

describe('Phase 27 Platform Command Center Frontend Component Tests', () => {
  it('renders OperationalRiskCard with score and contributing factors', () => {
    const wrapper = mount(OperationalRiskCard, {
      props: {
        risk: {
          score: 45,
          level: 'HIGH',
          color: 'orange',
          contributing_factors: ['1 active CRITICAL alert (+30 pts)'],
        },
      },
    });

    expect(wrapper.text()).toContain('Platform Operational Risk Score');
    expect(wrapper.text()).toContain('45 pts');
    expect(wrapper.text()).toContain('HIGH RISK');
    expect(wrapper.text()).toContain('1 active CRITICAL alert (+30 pts)');
  });

  it('renders PlatformStatusOverview with status KPIs', () => {
    const wrapper = mount(PlatformStatusOverview, {
      props: {
        status: {
          overall_status: 'degraded',
          critical_alerts_count: 1,
          high_alerts_count: 2,
          pending_approvals_count: 1,
          blocked_automations_count: 1,
          failed_jobs_count: 3,
          scheduler_status: 'operational',
          communication_failures_count: 0,
        },
      },
    });

    expect(wrapper.text()).toContain('degraded');
    expect(wrapper.text()).toContain('Critical Alerts');
    expect(wrapper.text()).toContain('1');
    expect(wrapper.text()).toContain('High Alerts');
    expect(wrapper.text()).toContain('2');
  });

  it('renders AttentionRequiredPanel with priority items and drilldowns', () => {
    const wrapper = mount(AttentionRequiredPanel, {
      props: {
        items: [
          {
            id: 'alert-1',
            type: 'alert',
            severity: 'critical',
            source: 'Ems',
            title: 'Critical Ticket Mismatch',
            summary: 'Registration #99999 missing ticket',
            created_at: '2026-09-14T00:00:00Z',
            age_human: '5 minutes ago',
            status: 'open',
            action_name: 'View in Operations',
            action_key: null,
            drilldown_url: '/admin/operations',
          },
        ],
      },
    });

    expect(wrapper.text()).toContain('Immediate Attention Required');
    expect(wrapper.text()).toContain('Critical Ticket Mismatch');
    expect(wrapper.text()).toContain('critical');
  });

  it('renders ApplicationHealthMatrix with system statuses and access badges', () => {
    const wrapper = mount(ApplicationHealthMatrix, {
      props: {
        applications: [
          {
            id: 'ems',
            name: 'Event Management System',
            status: 'operational',
            health_status: 'operational',
            status_reason: 'Reachable',
            access_granted: true,
            open_issues_count: 1,
            launch_url: '/ems',
            admin_path: '/ems/admin',
            last_checked_at: '2026-09-14T00:00:00Z',
          },
          {
            id: 'donations',
            name: 'Donations Management System',
            status: 'operational',
            health_status: 'operational',
            status_reason: 'Reachable',
            access_granted: false,
            open_issues_count: 0,
            launch_url: '/donations/admin',
            admin_path: '/donations/admin',
            last_checked_at: '2026-09-14T00:00:00Z',
          },
        ],
      },
    });

    expect(wrapper.text()).toContain('Application Health Matrix');
    expect(wrapper.text()).toContain('Event Management System');
    expect(wrapper.text()).toContain('Granted');
    expect(wrapper.text()).toContain('Restricted');
  });

  it('mounts CommandCenterPage and calls commandCenterService', async () => {
    const wrapper = mount(CommandCenterPage);

    await new Promise((r) => setTimeout(r, 50));
    await wrapper.vm.$nextTick();

    expect(commandCenterService.getCommandCenterData).toHaveBeenCalled();
    expect(wrapper.text()).toContain('Platform Command Center');
  });
});
