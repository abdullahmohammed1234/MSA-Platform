import api from '@/services/api';

export interface ScoreDriver {
  category: string;
  deduction: number;
  reason: string;
}

export interface GovernanceReadiness {
  score: number;
  level: 'EXCELLENT' | 'GOOD' | 'NEEDS_ATTENTION' | 'CRITICAL';
  drivers: ScoreDriver[];
}

export interface AuditUser {
  id: number;
  name: string;
  email: string;
}

export interface AuditLogItem {
  id: number;
  user_id: number | null;
  application: string | null;
  action: string;
  severity: 'info' | 'warning' | 'critical';
  target_type: string | null;
  target_id: number | null;
  description: string | null;
  payload: Record<string, any> | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  user?: AuditUser;
}

export interface ChangeAccountabilityItem {
  id: number;
  who: {
    id: number | null;
    name: string;
    email: string;
  };
  what: {
    action: string;
    description: string;
    severity: string;
  };
  when: {
    timestamp: string;
    human: string;
  };
  where: {
    domain: string;
    target_type: string | null;
    target_id: number | null;
    ip_address: string | null;
  };
  why: string;
  result: string;
  payload: Record<string, any>;
}

export interface IntegrityCheckItem {
  domain: string;
  check_key: string;
  status: 'PASSED' | 'WARNING' | 'FAILED';
  issue_count: number;
  summary: string;
}

export interface IntegrityScanResult {
  status: 'PASSED' | 'WARNING' | 'FAILED';
  scanned_at: string;
  total_checks: number;
  passed_count: number;
  warning_count: number;
  failed_count: number;
  checks: IntegrityCheckItem[];
}

export interface ContinuityProbe {
  status: 'HEALTHY' | 'DEGRADED' | 'FAILED' | 'UNKNOWN' | 'NOT_VERIFIED';
  name: string;
  details: string;
  last_checked_at: string;
  verification_state?: string;
  recommendation?: string;
  failed_jobs_count?: number;
  last_heartbeat_at?: string | null;
}

export interface ContinuityReadiness {
  overall_status: string;
  evaluated_at: string;
  probes: {
    database: ContinuityProbe;
    cache: ContinuityProbe;
    queue: ContinuityProbe;
    scheduler: ContinuityProbe;
    backup_verification: ContinuityProbe;
  };
  continuity_summary: string;
}

export interface IncidentTimelineNode {
  id: string;
  timestamp: string;
  event_type: string;
  severity: string;
  title: string;
  summary: string;
  actor: string;
  relationship_label: 'directly_related' | 'same_alert' | 'same_entity' | 'temporal_correlation';
  metadata: Record<string, any>;
}

export interface IncidentTimelineData {
  status: string;
  alert?: {
    id: number;
    uuid: string;
    title: string;
    status: string;
    severity: string;
    category: string;
    rule_key: string;
    first_detected_at: string;
    last_detected_at: string;
  };
  nodes_count: number;
  nodes: IncidentTimelineNode[];
}

export interface GovernanceOverviewData {
  period: string;
  generated_at: string;
  governance_score: number;
  score_level: 'EXCELLENT' | 'GOOD' | 'NEEDS_ATTENTION' | 'CRITICAL';
  score_drivers: ScoreDriver[];
  audit_activity: {
    recent_logs_count: number;
    recent_logs: AuditLogItem[];
  };
  change_accountability: ChangeAccountabilityItem[];
  integrity_status: IntegrityScanResult;
  continuity_readiness: ContinuityReadiness;
  governance_backlog: {
    pending_approvals_count: number;
    stale_approvals_count: number;
  };
  automation_reliability: Record<string, any>;
}

export const governanceService = {
  /**
   * Fetch aggregated Governance overview data.
   */
  async getGovernanceOverview(period: string = '7d'): Promise<GovernanceOverviewData> {
    const response = await api.get<{ success: boolean; data: GovernanceOverviewData }>('/admin/governance', {
      params: { period },
    });
    return response.data.data;
  },

  /**
   * Search audit logs with server-side pagination and filters.
   */
  async searchAuditLogs(params?: Record<string, any>): Promise<{
    data: AuditLogItem[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
  }> {
    const response = await api.get('/admin/governance/audit', { params });
    return response.data;
  },

  /**
   * Get audit log record by ID.
   */
  async getAuditLogShow(id: number): Promise<AuditLogItem> {
    const response = await api.get<{ success: boolean; data: AuditLogItem }>(`/admin/governance/audit/${id}`);
    return response.data.data;
  },

  /**
   * Reconstruct incident timeline for an alert.
   */
  async getIncidentTimeline(identifier: string): Promise<IncidentTimelineData> {
    const response = await api.get<{ success: boolean; data: IncidentTimelineData }>(
      `/admin/governance/incidents/${identifier}/timeline`
    );
    return response.data.data;
  },

  /**
   * Fetch domain data integrity diagnostic results.
   */
  async getIntegrityStatus(domain?: string): Promise<IntegrityScanResult> {
    const response = await api.get<{ success: boolean; data: IntegrityScanResult }>('/admin/governance/integrity', {
      params: { domain },
    });
    return response.data.data;
  },

  /**
   * Fetch operational continuity & recovery readiness status.
   */
  async getContinuityReadiness(): Promise<ContinuityReadiness> {
    const response = await api.get<{ success: boolean; data: ContinuityReadiness }>('/admin/governance/continuity');
    return response.data.data;
  },

  /**
   * Export governance report in JSON or CSV.
   */
  getReportExportUrl(period: string = '30d', format: 'csv' | 'json' = 'csv'): string {
    return `/admin/governance/report?period=${period}&format=${format}`;
  },
};
