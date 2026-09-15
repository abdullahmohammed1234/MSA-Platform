import api from './api'

export interface ReadinessCheck {
  domain: string
  key: string
  title: string
  status: 'PASSED' | 'WARNING' | 'FAILED' | 'UNKNOWN'
  details: string
  remediation?: string | null
}

export interface DeploymentReadiness {
  readiness_status: 'READY' | 'READY_WITH_WARNINGS' | 'NOT_READY' | 'UNKNOWN'
  summary: {
    total_checks: number
    passed: number
    warning: number
    failed: number
    unknown: number
  }
  checks: ReadinessCheck[]
  evaluated_at: string
}

export interface ReleaseMetadata {
  application_version: string
  api_version: string
  frontend_build: string
  release_identifier: string
  deployment_detected_at: string
  deployment_detection_method: string
  hosting_environment: string
}

export interface EnvironmentInventory {
  environment: string
  debug_mode: boolean
  laravel_version: string
  php_version: string
  database_driver: string
  database_version: string
  cache_driver: string
  queue_driver: string
  filesystem_driver: string
  mail_transport: string
  timezone: string
  locale: string
  enabled_capabilities: Record<string, boolean>
  configured_services: Record<string, string>
}

export interface MigrationStatus {
  status: 'UP_TO_DATE' | 'PENDING_MIGRATIONS' | 'UNKNOWN'
  is_up_to_date: boolean
  applied_count: number
  pending_count: number
  latest_applied?: string | null
  pending_migrations: string[]
  details: string
  last_checked_at: string
}

export interface DependencyServiceItem {
  name: string
  category: string
  status: 'HEALTHY' | 'DEGRADED' | 'UNAVAILABLE' | 'NOT_CONFIGURED' | 'NOT_VERIFIED' | 'UNKNOWN'
  driver: string
  details: string
  last_checked_at: string
  limitations?: string | null
  failed_jobs?: number
  last_heartbeat?: string | null
}

export interface DependencyHealth {
  overall_status: string
  checked_at: string
  services: Record<string, DependencyServiceItem>
}

export interface ScheduledTask {
  command: string
  description: string
  expression: string
  status: 'DEFINED' | 'EXPECTED' | 'VERIFIED' | 'UNKNOWN'
}

export interface SchedulerQueueInventory {
  scheduler: {
    driver: string
    status: 'VERIFIED' | 'OBSERVED' | 'NOT_VERIFIED' | 'UNKNOWN'
    last_heartbeat?: string | null
    minutes_since_heartbeat?: number | null
    defined_tasks_count: number
    tasks: ScheduledTask[]
    execution_semantics: Record<string, string>
  }
  queues: {
    driver: string
    verification_state: string
    configured_queues: string[]
    backlog_count: number
    failed_jobs_count: number
    worker_expectation: string
  }
  evaluated_at: string
}

export interface DriftFinding {
  rule_key: string
  category: string
  severity: 'critical' | 'warning' | 'info'
  detected: boolean
  explanation: string
  remediation: string
  verification_source: string
}

export interface ConfigurationDrift {
  status: 'NO_DRIFT' | 'MINOR_DRIFT' | 'WARNING_DRIFT' | 'CRITICAL_DRIFT'
  total_findings: number
  critical_count: number
  warning_count: number
  info_count: number
  findings: DriftFinding[]
  evaluated_at: string
}

export interface LifecycleOverviewData {
  generated_at: string
  readiness: DeploymentReadiness
  release: ReleaseMetadata
  environment: EnvironmentInventory
  migrations: MigrationStatus
  dependencies: DependencyHealth
  scheduler_queue: SchedulerQueueInventory
  drift: ConfigurationDrift
}

export const lifecycleService = {
  async getLifecycleOverview(): Promise<LifecycleOverviewData> {
    const response = await api.get('/admin/lifecycle')
    return response.data.data
  },

  async getEnvironmentInventory(): Promise<{ environment: EnvironmentInventory; release: ReleaseMetadata }> {
    const response = await api.get('/admin/lifecycle/environment')
    return response.data.data
  },

  async getReleaseMetadata(): Promise<ReleaseMetadata> {
    const response = await api.get('/admin/lifecycle/release')
    return response.data.data
  },

  async getDependencyHealth(): Promise<DependencyHealth> {
    const response = await api.get('/admin/lifecycle/dependencies')
    return response.data.data
  },

  async getMigrationStatus(): Promise<MigrationStatus> {
    const response = await api.get('/admin/lifecycle/migrations')
    return response.data.data
  },

  async getSchedulerQueueInventory(): Promise<SchedulerQueueInventory> {
    const response = await api.get('/admin/lifecycle/scheduler')
    return response.data.data
  },

  async getConfigurationDrift(): Promise<ConfigurationDrift> {
    const response = await api.get('/admin/lifecycle/drift')
    return response.data.data
  },

  async getDeploymentReadiness(): Promise<DeploymentReadiness> {
    const response = await api.get('/admin/lifecycle/readiness')
    return response.data.data
  },
}
