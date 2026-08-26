export type SystemStatus = 'operational' | 'degraded' | 'unavailable' | 'unknown'

export interface SystemErrorSummary {
  severity: 'error' | 'warning' | string
  message: string
  timestamp?: string
}

export interface SystemCheck {
  status?: SystemStatus
  message?: string
  label?: string
  metrics?: Record<string, unknown>
}

export interface SystemDependencyDetail {
  id: string
  label: string
  status: SystemStatus
  message?: string | null
}

export interface QueuePartition {
  name: string
  pending: number
  active: number
  failed: number
  status: string
}

export interface SystemApplication {
  id: string
  name: string
  description: string
  type: 'application'
  status: SystemStatus
  health_status: SystemStatus
  status_reason?: string | null
  connection_status: Record<string, SystemStatus>
  dependency_details?: SystemDependencyDetail[]
  version: string
  url: string | null
  launch_url?: string
  admin_path: string | null
  console_path?: string | null
  dependencies: string[]
  owns?: string[]
  does_not_own?: string[]
  notes?: string | null
  last_checked_at: string
  checks?: Record<string, SystemCheck>
  errors: SystemErrorSummary[]
  recent_incidents: unknown[]
  registered?: boolean
  configured?: boolean
}

export interface PlatformService {
  id: string
  name: string
  description: string
  type: 'platform_service'
  status: SystemStatus
  health_status: SystemStatus
  status_reason?: string | null
  admin_path: string | null
  detail_path?: string | null
  required_permission?: string | null
  metrics?: Record<string, unknown>
  checks?: Array<{ id?: string; label: string; status: SystemStatus; message?: string | null }>
  partitions?: QueuePartition[]
  last_checked_at: string
  message?: string | null
  errors: SystemErrorSummary[]
}

export interface SecurityCenterEntry {
  id: string
  name: string
  description: string
  type: 'security'
  status: SystemStatus
  health_status: SystemStatus
  status_reason?: string | null
  admin_path: string | null
  required_permission?: string | null
  last_checked_at: string
  message?: string | null
  errors: SystemErrorSummary[]
}

export interface SystemsOverview {
  success: boolean
  checked_at: string
  summary: {
    applications_total: number
    applications_by_status: Record<SystemStatus, number>
    platform_services_total: number
    platform_services_by_status: Record<SystemStatus, number>
    security_status: SystemStatus
  }
  applications: SystemApplication[]
  platform_services: PlatformService[]
  security: SecurityCenterEntry
  platform: {
    version: string
    build_id: string | null
    commit_sha: string | null
  }
  incidents_supported: boolean
  recent_incidents: unknown[]
}
