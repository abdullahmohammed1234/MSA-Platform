import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DeploymentReadinessCard from '@/components/lifecycle/DeploymentReadinessCard.vue'
import EnvironmentInventoryCard from '@/components/lifecycle/EnvironmentInventoryCard.vue'
import DependencyHealthPanel from '@/components/lifecycle/DependencyHealthPanel.vue'
import MigrationReadinessPanel from '@/components/lifecycle/MigrationReadinessPanel.vue'
import SchedulerQueuePanel from '@/components/lifecycle/SchedulerQueuePanel.vue'
import ConfigurationDriftPanel from '@/components/lifecycle/ConfigurationDriftPanel.vue'
import PlatformLifecyclePage from '@/pages/admin/lifecycle/PlatformLifecyclePage.vue'
import { lifecycleService } from '@/services/lifecycleService'

vi.mock('@/services/lifecycleService', () => ({
  lifecycleService: {
    getLifecycleOverview: vi.fn().mockResolvedValue({
      generated_at: '2026-09-13T22:00:00Z',
      readiness: {
        readiness_status: 'READY',
        summary: { total_checks: 6, passed: 6, warning: 0, failed: 0, unknown: 0 },
        checks: [
          { domain: 'Security', key: 'app_key', title: 'APP_KEY Present', status: 'PASSED', details: 'Encryption key set' },
          { domain: 'Database', key: 'db_conn', title: 'Database Connected', status: 'PASSED', details: 'MySQL connected' },
        ],
        evaluated_at: '2026-09-13T22:00:00Z',
      },
      release: {
        application_version: '1.29.0',
        api_version: 'v1',
        frontend_build: '2026.09.13-phase29',
        release_identifier: 'v1.29.0-prod',
        deployment_detected_at: '2026-09-13T20:00:00Z',
        deployment_detection_method: 'composer_manifest_mtime',
        hosting_environment: 'cPanel / Shared Hosting',
      },
      environment: {
        environment: 'production',
        debug_mode: false,
        laravel_version: '10.48.0',
        php_version: '8.2.12',
        database_driver: 'mysql',
        database_version: '8.0.35',
        cache_driver: 'file',
        queue_driver: 'database',
        filesystem_driver: 'local',
        mail_transport: 'smtp',
        timezone: 'UTC',
        locale: 'en',
        enabled_capabilities: { ems: true, store: true },
        configured_services: { database: 'CONFIGURED' },
      },
      migrations: {
        status: 'UP_TO_DATE',
        is_up_to_date: true,
        applied_count: 85,
        pending_count: 0,
        latest_applied: '2026_09_13_create_operations_table.php',
        pending_migrations: [],
        details: 'All migrations current.',
        last_checked_at: '2026-09-13T22:00:00Z',
      },
      dependencies: {
        overall_status: 'HEALTHY',
        checked_at: '2026-09-13T22:00:00Z',
        services: {
          database: { name: 'MySQL Database', category: 'database', status: 'HEALTHY', driver: 'mysql', details: 'Active', last_checked_at: '2026-09-13T22:00:00Z' },
          scheduler: { name: 'Laravel Scheduler', category: 'scheduler', status: 'NOT_VERIFIED', driver: 'cron', details: 'No heartbeat', last_checked_at: '2026-09-13T22:00:00Z' },
        },
      },
      scheduler_queue: {
        scheduler: {
          driver: 'cPanel Cron',
          status: 'VERIFIED',
          defined_tasks_count: 2,
          tasks: [
            { command: 'operations:detect', description: 'Run detection', expression: '*/5 * * * *', status: 'VERIFIED' },
          ],
          execution_semantics: {},
        },
        queues: {
          driver: 'database',
          verification_state: 'CONFIGURED',
          configured_queues: ['default', 'notifications'],
          backlog_count: 0,
          failed_jobs_count: 0,
          worker_expectation: 'Processed via artisan queue:work',
        },
        evaluated_at: '2026-09-13T22:00:00Z',
      },
      drift: {
        status: 'NO_DRIFT',
        total_findings: 0,
        critical_count: 0,
        warning_count: 0,
        info_count: 0,
        findings: [],
        evaluated_at: '2026-09-13T22:00:00Z',
      },
    }),
  },
}))

describe('Phase 29 — Lifecycle & Environment Components', () => {
  it('renders DeploymentReadinessCard with status badge and summary counters', () => {
    const wrapper = mount(DeploymentReadinessCard, {
      props: {
        readiness: {
          readiness_status: 'READY',
          summary: { total_checks: 5, passed: 5, warning: 0, failed: 0, unknown: 0 },
          checks: [
            { domain: 'Security', key: 'app_key', title: 'APP_KEY Present', status: 'PASSED', details: 'Encryption key configured' },
          ],
          evaluated_at: '2026-09-13T22:00:00Z',
        },
      },
    })

    expect(wrapper.text()).toContain('Deployment Readiness Evaluation')
    expect(wrapper.text()).toContain('READY')
    expect(wrapper.text()).toContain('APP_KEY Present')
  })

  it('renders EnvironmentInventoryCard with release details and framework versions', () => {
    const wrapper = mount(EnvironmentInventoryCard, {
      props: {
        environment: {
          environment: 'production',
          debug_mode: false,
          laravel_version: '10.48.0',
          php_version: '8.2.12',
          database_driver: 'mysql',
          database_version: '8.0.35',
          cache_driver: 'file',
          queue_driver: 'database',
          filesystem_driver: 'local',
          mail_transport: 'smtp',
          timezone: 'UTC',
          locale: 'en',
          enabled_capabilities: {},
          configured_services: {},
        },
        release: {
          application_version: '1.29.0',
          api_version: 'v1',
          frontend_build: '2026.09.13-phase29',
          release_identifier: 'v1.29.0-prod',
          deployment_detected_at: '2026-09-13T20:00:00Z',
          deployment_detection_method: 'composer_manifest_mtime',
          hosting_environment: 'cPanel / Shared Hosting',
        },
      },
    })

    expect(wrapper.text()).toContain('Environment & Release Inventory')
    expect(wrapper.text()).toContain('10.48.0')
    expect(wrapper.text()).toContain('8.2.12')
    expect(wrapper.text()).toContain('v1.29.0-prod')
    expect(wrapper.text()).toContain('100% Secrets Redacted')
  })

  it('renders DependencyHealthPanel with service status cards', () => {
    const wrapper = mount(DependencyHealthPanel, {
      props: {
        dependencies: {
          overall_status: 'HEALTHY',
          checked_at: '2026-09-13T22:00:00Z',
          services: {
            database: { name: 'MySQL Database', category: 'database', status: 'HEALTHY', driver: 'mysql', details: 'Connected', last_checked_at: '2026-09-13T22:00:00Z' },
          },
        },
      },
    })

    expect(wrapper.text()).toContain('Dependency & Service Inventory')
    expect(wrapper.text()).toContain('MySQL Database')
    expect(wrapper.text()).toContain('HEALTHY')
  })

  it('renders MigrationReadinessPanel with applied and pending counts', () => {
    const wrapper = mount(MigrationReadinessPanel, {
      props: {
        migrations: {
          status: 'UP_TO_DATE',
          is_up_to_date: true,
          applied_count: 90,
          pending_count: 0,
          latest_applied: '2026_09_13_create_operations_table.php',
          pending_migrations: [],
          details: 'All database migrations are current.',
          last_checked_at: '2026-09-13T22:00:00Z',
        },
      },
    })

    expect(wrapper.text()).toContain('Migration Readiness & Schema Status')
    expect(wrapper.text()).toContain('90')
    expect(wrapper.text()).toContain('UP_TO_DATE')
  })

  it('renders ConfigurationDriftPanel empty state when no drift', () => {
    const wrapper = mount(ConfigurationDriftPanel, {
      props: {
        drift: {
          status: 'NO_DRIFT',
          total_findings: 0,
          critical_count: 0,
          warning_count: 0,
          info_count: 0,
          findings: [],
          evaluated_at: '2026-09-13T22:00:00Z',
        },
      },
    })

    expect(wrapper.text()).toContain('No Configuration Drift Detected')
  })

  it('renders PlatformLifecyclePage dashboard correctly', async () => {
    const wrapper = mount(PlatformLifecyclePage)
    await wrapper.vm.$nextTick()
    await new Promise((resolve) => setTimeout(resolve, 50))

    expect(lifecycleService.getLifecycleOverview).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Platform Lifecycle & Environment Center')
  })
})
