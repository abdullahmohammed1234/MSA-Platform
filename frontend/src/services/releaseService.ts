import api from './api'

export interface PlatformChange {
  id: number
  uuid: string
  release_id: number | null
  change_identifier: string
  category:
    | 'backend_code'
    | 'frontend_code'
    | 'migration'
    | 'configuration'
    | 'rbac'
    | 'application_access'
    | 'scheduled_job'
    | 'queue'
    | 'integration'
    | 'operational_rule'
    | 'remediation_action'
    | 'lifecycle_control'
  title: string
  description?: string | null
  reason?: string | null
  environment: string
  affected_applications: string[]
  affected_services: string[]
  affected_database_areas?: string[]
  impact_level: 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL'
  requires_migration: boolean
  requires_rbac_update: boolean
  requires_config_change: boolean
  requires_queue_flush: boolean
  is_breaking: boolean
  validation_status: 'PENDING' | 'VALIDATED' | 'FAILED' | 'REJECTED'
  author_user_id: number
  author?: { name: string; email: string }
  created_at: string
}

export interface PlatformRelease {
  id: number
  uuid: string
  release_identifier: string
  application_version: string
  api_version: string
  frontend_build: string
  environment: string
  status:
    | 'PLANNED'
    | 'APPROVED'
    | 'DEPLOYING'
    | 'DEPLOYED'
    | 'VERIFYING'
    | 'SUCCESSFUL'
    | 'SUCCESSFUL_WITH_WARNINGS'
    | 'FAILED'
    | 'CANCELLED'
    | 'ROLLED_BACK'
    | 'NOT_VERIFIED'
  is_active: boolean
  released_at?: string | null
  release_notes?: string | null
  created_by: number
  approved_by?: number | null
  approved_at?: string | null
  previous_release_id?: number | null
  affected_applications: string[]
  rollback_readiness: 'ROLLBACK_READY' | 'ROLLBACK_MANUAL' | 'ROLLBACK_NOT_VERIFIED' | 'ROLLBACK_UNAVAILABLE'
  post_release_verification_status?: string | null
  creator?: { name: string; email: string }
  approver?: { name: string; email: string }
  changes?: PlatformChange[]
  created_at?: string
}

export interface ImpactAnalysis {
  overall_impact_level: 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL'
  affected_applications: string[]
  affected_services: string[]
  requires_migration: boolean
  requires_rbac_update: boolean
  requires_config_change: boolean
  requires_queue_flush: boolean
  has_breaking_changes: boolean
  risk_factors: string[]
}

export interface VerificationProbe {
  name: string
  status: 'PASSED' | 'WARNING' | 'FAILED' | 'NOT_VERIFIED'
  details: string
}

export interface VerificationResult {
  release_identifier: string
  verification_status: 'SUCCESSFUL' | 'SUCCESSFUL_WITH_WARNINGS' | 'FAILED' | 'NOT_VERIFIED'
  verified_at: string
  probes: Record<string, VerificationProbe>
  summary: {
    total_probes: number
    passed: number
    warning: number
    failed: number
    not_verified: number
  }
}

export interface RollbackReadiness {
  release_identifier: string
  rollback_readiness: 'ROLLBACK_READY' | 'ROLLBACK_MANUAL' | 'ROLLBACK_NOT_VERIFIED' | 'ROLLBACK_UNAVAILABLE'
  previous_release_identifier: string
  migration_rollback_compatible: boolean
  backup_verification_state: string
  blockers: string[]
  manual_recovery_steps: string[]
  evaluated_at: string
}

export interface TimelineEvent {
  timestamp: string
  event_type: string
  title: string
  description: string
  actor: string
  relationship: 'directly_related' | 'same_change' | 'same_entity' | 'temporal_correlation'
}

export interface TimelineResult {
  release_identifier: string
  total_events: number
  timeline: TimelineEvent[]
  correlation_semantics: Record<string, string>
}

export interface ComparisonResult {
  release_a: { identifier: string; version: string; status: string; changes_count: number }
  release_b: { identifier: string; version: string; status: string; changes_count: number }
  comparison: { version_diff: string; status_diff: string; changes_diff_count: number }
  evaluated_at: string
}

export const releaseService = {
  async getReleases(params?: Record<string, any>) {
    const res = await api.get('/admin/releases', { params })
    return res.data
  },

  async getCurrentRelease() {
    const res = await api.get('/admin/releases/current')
    return res.data.data
  },

  async getRelease(identifier: string) {
    const res = await api.get(`/admin/releases/${identifier}`)
    return res.data.data
  },

  async createRelease(payload: {
    application_version: string
    release_identifier?: string
    api_version?: string
    frontend_build?: string
    environment?: string
    release_notes?: string
    affected_applications?: string[]
  }) {
    const res = await api.post('/admin/releases', payload)
    return res.data.data
  },

  async approveRelease(identifier: string, release_notes?: string) {
    const res = await api.post(`/admin/releases/${identifier}/approve`, { release_notes })
    return res.data
  },

  async rejectRelease(identifier: string) {
    const res = await api.post(`/admin/releases/${identifier}/reject`)
    return res.data
  },

  async getReleaseImpact(identifier: string) {
    const res = await api.get(`/admin/releases/${identifier}/impact`)
    return res.data.data
  },

  async getReleaseVerification(identifier: string) {
    const res = await api.get(`/admin/releases/${identifier}/verification`)
    return res.data.data
  },

  async runReleaseVerification(identifier: string) {
    const res = await api.post(`/admin/releases/${identifier}/verify`)
    return res.data.data
  },

  async getReleaseTimeline(identifier: string) {
    const res = await api.get(`/admin/releases/${identifier}/timeline`)
    return res.data.data
  },

  async getReleaseRollback(identifier: string) {
    const res = await api.get(`/admin/releases/${identifier}/rollback`)
    return res.data.data
  },

  async compareReleases(releaseA: string, releaseB: string) {
    const res = await api.get('/admin/releases/compare', {
      params: { release_a: releaseA, release_b: releaseB }
    })
    return res.data.data
  },

  async getChanges(params?: Record<string, any>) {
    const res = await api.get('/admin/changes', { params })
    return res.data
  },

  async getChange(identifier: string) {
    const res = await api.get(`/admin/changes/${identifier}`)
    return res.data.data
  },

  async createChange(payload: {
    category: string
    title: string
    release_id?: number
    description?: string
    reason?: string
    affected_applications?: string[]
    affected_services?: string[]
    impact_level?: string
  }) {
    const res = await api.post('/admin/changes', payload)
    return res.data.data
  }
}

export default releaseService
