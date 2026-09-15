import api from '@/services/api';

export interface PlatformStatus {
  overall_status: 'healthy' | 'attention_required' | 'degraded' | 'critical';
  critical_alerts_count: number;
  high_alerts_count: number;
  pending_approvals_count: number;
  blocked_automations_count: number;
  failed_jobs_count: number;
  scheduler_status: string;
  communication_failures_count: number;
}

export interface OperationalRisk {
  score: number;
  level: 'LOW' | 'MEDIUM' | 'HIGH' | 'CRITICAL';
  color: string;
  contributing_factors: string[];
}

export interface AttentionItem {
  id: string;
  type: 'alert' | 'pending_approval' | 'governance_blocked';
  severity: 'critical' | 'high' | 'medium' | 'low';
  source: string;
  title: string;
  summary: string;
  created_at: string;
  age_human: string;
  status: string;
  action_name: string;
  action_key: string | null;
  drilldown_url: string;
}

export interface ComponentHealthStatus {
  status: string;
  message?: string;
  driver?: string;
  failed_jobs?: number;
  failed_count?: number;
}

export interface InfrastructureHealth {
  database: ComponentHealthStatus;
  storage: ComponentHealthStatus;
  email: ComponentHealthStatus;
  queues: ComponentHealthStatus;
  scheduler: ComponentHealthStatus;
  communications: ComponentHealthStatus;
}

export interface ApplicationMatrixItem {
  id: string;
  name: string;
  status: string;
  health_status: string;
  status_reason: string;
  access_granted: boolean;
  open_issues_count: number;
  launch_url: string;
  admin_path: string | null;
  last_checked_at: string;
}

export interface DomainTelemetry {
  access_granted: boolean;
  status: string;
  message?: string;
  [key: string]: any;
}

export interface BusinessSnapshot {
  ems?: DomainTelemetry;
  donations?: DomainTelemetry;
  store?: DomainTelemetry;
  mlibms?: DomainTelemetry;
  communications?: DomainTelemetry;
  volunteers?: DomainTelemetry;
  feedback?: DomainTelemetry;
}

export interface ActionBreakdown {
  action_key: string;
  name: string;
  risk_level: string;
  total_executions: number;
  success_count: number;
  resolved_count: number;
  failed_count: number;
  success_rate: number;
  resolution_effectiveness_rate: number;
}

export interface AutomationSnapshot {
  status: string;
  message?: string;
  total_executions: number;
  completed_executions: number;
  failed_executions: number;
  execution_success_rate: number;
  problem_resolution_effectiveness_rate: number;
  pending_approvals_count: number;
  blocked_executions_count: number;
  action_breakdown: ActionBreakdown[];
}

export interface CommandCenterData {
  success: boolean;
  generated_at: string;
  period: string;
  platform: PlatformStatus;
  risk: OperationalRisk;
  attention: AttentionItem[];
  infrastructure: InfrastructureHealth;
  applications: ApplicationMatrixItem[];
  business: BusinessSnapshot;
  automation: AutomationSnapshot;
}

export const commandCenterService = {
  /**
   * Fetch aggregated Command Center executive telemetry.
   */
  async getCommandCenterData(params?: {
    period?: string;
    start_date?: string;
    end_date?: string;
  }): Promise<CommandCenterData> {
    const response = await api.get<CommandCenterData>('/admin/command-center', {
      params,
    });
    return response.data;
  },
};
