import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CurrentReleasePanel from '@/components/release/CurrentReleasePanel.vue'
import ReleaseChangesPanel from '@/components/release/ReleaseChangesPanel.vue'
import ChangeImpactPanel from '@/components/release/ChangeImpactPanel.vue'
import PostReleaseVerificationPanel from '@/components/release/PostReleaseVerificationPanel.vue'
import ReleaseTimelinePanel from '@/components/release/ReleaseTimelinePanel.vue'
import RollbackReadinessPanel from '@/components/release/RollbackReadinessPanel.vue'
import ReleaseComparisonModal from '@/components/release/ReleaseComparisonModal.vue'
import ReleaseApprovalModal from '@/components/release/ReleaseApprovalModal.vue'
import ReleaseManagementPage from '@/pages/admin/release/ReleaseManagementPage.vue'
import { releaseService } from '@/services/releaseService'

vi.mock('@/services/releaseService', () => ({
  releaseService: {
    getCurrentRelease: vi.fn().mockResolvedValue({
      id: 1,
      release_identifier: 'v1.30.0-prod',
      application_version: '1.30.0',
      api_version: 'v1',
      frontend_build: 'build-20260914-001',
      environment: 'production',
      status: 'SUCCESSFUL',
      is_active: true,
      released_at: '2026-09-14T00:00:00Z',
      rollback_readiness: 'ROLLBACK_MANUAL',
      post_release_verification_status: 'SUCCESSFUL',
      affected_applications: ['platform', 'ems']
    }),
    getReleases: vi.fn().mockResolvedValue({
      data: [
        { id: 1, release_identifier: 'v1.30.0-prod', application_version: '1.30.0', status: 'SUCCESSFUL' },
        { id: 2, release_identifier: 'v1.29.0-prod', application_version: '1.29.0', status: 'SUCCESSFUL' }
      ]
    }),
    getChanges: vi.fn().mockResolvedValue({
      data: [
        {
          id: 1,
          change_identifier: 'CHG-1001',
          category: 'backend_code',
          title: 'Update Release Engine',
          description: 'Added change impact analysis',
          impact_level: 'MEDIUM',
          requires_migration: false,
          is_breaking: false,
          affected_applications: ['admin_portal']
        },
        {
          id: 2,
          change_identifier: 'CHG-1002',
          category: 'migration',
          title: 'Database Schema Change',
          description: 'Created platform_releases table',
          impact_level: 'HIGH',
          requires_migration: true,
          is_breaking: true,
          affected_applications: ['database']
        }
      ]
    }),
    getReleaseImpact: vi.fn().mockResolvedValue({
      overall_impact_level: 'HIGH',
      affected_applications: ['platform', 'database'],
      affected_services: ['database', 'cache'],
      requires_migration: true,
      requires_rbac_update: false,
      requires_queue_flush: false,
      has_breaking_changes: true,
      risk_factors: ['Release contains database schema migrations']
    }),
    getReleaseVerification: vi.fn().mockResolvedValue({
      release_identifier: 'v1.30.0-prod',
      verification_status: 'SUCCESSFUL',
      verified_at: '2026-09-14T00:00:00Z',
      probes: {
        database: { name: 'Database Probes', status: 'PASSED', details: 'Healthy' },
        cache: { name: 'Cache Probes', status: 'PASSED', details: 'Healthy' }
      },
      summary: { total_probes: 2, passed: 2, warning: 0, failed: 0, not_verified: 0 }
    }),
    runReleaseVerification: vi.fn().mockResolvedValue({
      release_identifier: 'v1.30.0-prod',
      verification_status: 'SUCCESSFUL',
      verified_at: '2026-09-14T00:05:00Z',
      probes: {
        database: { name: 'Database Probes', status: 'PASSED', details: 'Healthy' }
      },
      summary: { total_probes: 1, passed: 1, warning: 0, failed: 0, not_verified: 0 }
    }),
    getReleaseTimeline: vi.fn().mockResolvedValue({
      release_identifier: 'v1.30.0-prod',
      total_events: 2,
      timeline: [
        {
          timestamp: '2026-09-14T00:00:00Z',
          event_type: 'release_created',
          title: 'Release Created',
          description: 'Release registered',
          actor: 'System Admin',
          relationship: 'directly_related'
        }
      ],
      correlation_semantics: {}
    }),
    getReleaseRollback: vi.fn().mockResolvedValue({
      release_identifier: 'v1.30.0-prod',
      rollback_readiness: 'ROLLBACK_MANUAL',
      previous_release_identifier: 'v1.29.0-prod',
      migration_rollback_compatible: false,
      backup_verification_state: 'NOT_VERIFIED',
      blockers: ['Contains database migration'],
      manual_recovery_steps: ['Revert git workspace', 'Clear caches']
    }),
    compareReleases: vi.fn().mockResolvedValue({
      release_a: { identifier: 'v1.29.0-prod', version: '1.29.0', status: 'SUCCESSFUL', changes_count: 5 },
      release_b: { identifier: 'v1.30.0-prod', version: '1.30.0', status: 'SUCCESSFUL', changes_count: 8 },
      comparison: { version_diff: 'CHANGED', status_diff: 'UNCHANGED', changes_diff_count: 3 },
      evaluated_at: '2026-09-14T00:00:00Z'
    }),
    approveRelease: vi.fn().mockResolvedValue({ success: true })
  }
}))

describe('Phase 30 — Release & Change Management Components', () => {
  it('renders CurrentReleasePanel with active release information', () => {
    const wrapper = mount(CurrentReleasePanel, {
      props: {
        release: {
          release_identifier: 'v1.30.0-prod',
          application_version: '1.30.0',
          frontend_build: 'build-20260914-001',
          status: 'SUCCESSFUL',
          rollback_readiness: 'ROLLBACK_MANUAL',
          post_release_verification_status: 'SUCCESSFUL'
        }
      }
    })

    expect(wrapper.text()).toContain('Active Production Release')
    expect(wrapper.text()).toContain('v1.30.0-prod')
    expect(wrapper.text()).toContain('1.30.0')
    expect(wrapper.text()).toContain('ROLLBACK_MANUAL')
  })

  it('renders ReleaseChangesPanel and filters changes', async () => {
    const changes = [
      {
        id: 1,
        uuid: 'uuid-1',
        release_id: 1,
        change_identifier: 'CHG-1001',
        category: 'backend_code' as const,
        title: 'Update Release Engine',
        description: 'Added change impact analysis',
        environment: 'production',
        affected_applications: ['admin_portal'],
        affected_services: ['releases'],
        impact_level: 'MEDIUM' as const,
        requires_migration: false,
        requires_rbac_update: false,
        requires_config_change: false,
        requires_queue_flush: false,
        is_breaking: false,
        validation_status: 'VALIDATED' as const,
        author_user_id: 1,
        created_at: '2026-09-14T00:00:00Z'
      }
    ]

    const wrapper = mount(ReleaseChangesPanel, {
      props: { changes }
    })

    expect(wrapper.text()).toContain('Operational Changes Registry')
    expect(wrapper.text()).toContain('Update Release Engine')
    expect(wrapper.text()).toContain('CHG-1001')
  })

  it('renders ChangeImpactPanel with matrix breakdown', () => {
    const impact = {
      overall_impact_level: 'HIGH' as const,
      affected_applications: ['platform', 'database'],
      affected_services: ['database', 'cache'],
      requires_migration: true,
      requires_rbac_update: false,
      requires_queue_flush: false,
      has_breaking_changes: true,
      risk_factors: ['Database schema migration']
    }

    const wrapper = mount(ChangeImpactPanel, {
      props: { impact }
    })

    expect(wrapper.text()).toContain('Deterministic Impact Analysis')
    expect(wrapper.text()).toContain('HIGH IMPACT')
    expect(wrapper.text()).toContain('Database schema migration')
  })

  it('renders PostReleaseVerificationPanel probes', () => {
    const verification = {
      release_identifier: 'v1.30.0-prod',
      verification_status: 'SUCCESSFUL' as const,
      verified_at: '2026-09-14T00:00:00Z',
      probes: {
        database: { name: 'Database Probes', status: 'PASSED' as const, details: 'Healthy' }
      },
      summary: { total_probes: 1, passed: 1, warning: 0, failed: 0, not_verified: 0 }
    }

    const wrapper = mount(PostReleaseVerificationPanel, {
      props: { verification }
    })

    expect(wrapper.text()).toContain('Post-Release Health Verification')
    expect(wrapper.text()).toContain('Database Probes')
    expect(wrapper.text()).toContain('PASSED')
  })

  it('renders ReleaseTimelinePanel with correlation tags', () => {
    const timeline = [
      {
        timestamp: '2026-09-14T00:00:00Z',
        event_type: 'release_created',
        title: 'Release Created',
        description: 'Release registered with application version 1.30.0',
        actor: 'System Admin',
        relationship: 'directly_related' as const
      }
    ]

    const wrapper = mount(ReleaseTimelinePanel, {
      props: { timeline }
    })

    expect(wrapper.text()).toContain('Unified Release Timeline & Incident Correlation')
    expect(wrapper.text()).toContain('Release Created')
    expect(wrapper.text()).toContain('directly_related')
  })

  it('renders RollbackReadinessPanel with recovery steps', () => {
    const rollback = {
      release_identifier: 'v1.30.0-prod',
      rollback_readiness: 'ROLLBACK_MANUAL' as const,
      previous_release_identifier: 'v1.29.0-prod',
      migration_rollback_compatible: false,
      backup_verification_state: 'NOT_VERIFIED',
      blockers: ['Schema migration present'],
      manual_recovery_steps: ['Revert git workspace', 'Clear caches']
    }

    const wrapper = mount(RollbackReadinessPanel, {
      props: { rollback }
    })

    expect(wrapper.text()).toContain('Truthful Rollback Readiness')
    expect(wrapper.text()).toContain('ROLLBACK_MANUAL')
    expect(wrapper.text()).toContain('Revert git workspace')
  })

  it('renders ReleaseComparisonModal when open', () => {
    const comparison = {
      release_a: { identifier: 'v1.29.0-prod', version: '1.29.0', status: 'SUCCESSFUL', changes_count: 5 },
      release_b: { identifier: 'v1.30.0-prod', version: '1.30.0', status: 'SUCCESSFUL', changes_count: 8 },
      comparison: { version_diff: 'CHANGED', status_diff: 'UNCHANGED', changes_diff_count: 3 },
      evaluated_at: '2026-09-14T00:00:00Z'
    }

    const wrapper = mount(ReleaseComparisonModal, {
      props: { isOpen: true, comparison }
    })

    expect(wrapper.text()).toContain('Release Comparison Matrix')
    expect(wrapper.text()).toContain('v1.29.0-prod')
    expect(wrapper.text()).toContain('v1.30.0-prod')
  })

  it('renders ReleaseApprovalModal and emits approval event', async () => {
    const wrapper = mount(ReleaseApprovalModal, {
      props: { isOpen: true, releaseIdentifier: 'v1.31.0-rc1' }
    })

    expect(wrapper.text()).toContain('Release Approval Control')
    expect(wrapper.text()).toContain('v1.31.0-rc1')

    await wrapper.find('button.bg-emerald-600').trigger('click')
    expect(wrapper.emitted('approve')).toBeTruthy()
  })

  it('renders ReleaseManagementPage end-to-end', async () => {
    const wrapper = mount(ReleaseManagementPage)
    // Wait for async fetchData
    await new Promise((r) => setTimeout(r, 50))
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Platform Change Management & Release Intelligence')
    expect(wrapper.text()).toContain('v1.30.0-prod')
  })
})
