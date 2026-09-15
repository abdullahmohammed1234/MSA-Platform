import client from '@/services/api';

export interface UserSummary {
  id: number;
  name: string;
  email: string;
}

export interface OperationalAlert {
  id: number;
  uuid: string;
  fingerprint: string;
  category: 'ems' | 'donations' | 'store' | 'mlibms' | 'communications' | 'volunteering' | 'platform';
  severity: 'critical' | 'high' | 'medium' | 'low';
  status: 'open' | 'acknowledged' | 'resolved' | 'dismissed';
  title: string;
  description: string;
  source_type: string;
  source_id: string;
  rule_key: string;
  action_url?: string | null;
  metadata?: Record<string, any>;
  first_detected_at: string;
  last_detected_at: string;
  acknowledged_at?: string | null;
  acknowledged_by?: number | null;
  resolved_at?: string | null;
  resolved_by?: number | null;
  dismissed_at?: string | null;
  dismissed_by?: number | null;
  resolution_reason?: string | null;
  created_at: string;
  updated_at: string;
  acknowledged_by_user?: UserSummary | null;
  resolved_by_user?: UserSummary | null;
  dismissed_by_user?: UserSummary | null;
}

export interface OperationsSummary {
  total_active: number;
  by_status: {
    open: number;
    acknowledged: number;
    resolved: number;
    dismissed: number;
  };
  by_severity: {
    critical: number;
    high: number;
    medium: number;
    low: number;
  };
  by_category: Record<string, number>;
}

export interface OperationalAlertsParams {
  status?: string;
  category?: string;
  severity?: string;
  search?: string;
  page?: number;
  per_page?: number;
}

export interface PaginatedAlertsResponse {
  data: OperationalAlert[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export interface OperationalActionGovernance {
  allowed: boolean;
  risk: 'low' | 'medium' | 'high' | 'critical';
  requires_confirmation: boolean;
  requires_approval: boolean;
  approval_status: 'none' | 'pending' | 'approved' | 'rejected' | 'expired';
  approval_uuid: string | null;
  cooldown_remaining_seconds: number;
  repeated_failures_count: number;
  blocked_reason: string | null;
}

export interface OperationalRemediationAction {
  key: string;
  name: string;
  description: string;
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  cooldown_seconds: number;
  max_failure_threshold: number;
  requires_approval: boolean;
  required_permission: string;
  requires_confirmation: boolean;
  is_reversible: boolean;
  precondition: {
    valid: boolean;
    reason: string | null;
  };
  governance?: OperationalActionGovernance;
}

export interface OperationalActionExecution {
  id: number;
  uuid: string;
  alert_id: number;
  action_key: string;
  requested_by: number;
  status: 'requested' | 'running' | 'completed' | 'failed' | 'cancelled';
  before_snapshot?: Record<string, any> | null;
  after_snapshot?: Record<string, any> | null;
  summary?: string | null;
  error_code?: string | null;
  error_message?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  created_at: string;
  updated_at: string;
  alert?: OperationalAlert;
  requester?: UserSummary;
}

export interface OperationalActionApproval {
  id: number;
  uuid: string;
  operational_alert_id: number;
  action_key: string;
  status: 'pending' | 'approved' | 'rejected' | 'executed' | 'expired';
  requested_by: number;
  approved_by?: number | null;
  rejected_by?: number | null;
  request_reason?: string | null;
  decision_reason?: string | null;
  requested_at: string;
  decided_at?: string | null;
  expires_at: string;
  created_at: string;
  updated_at: string;
  alert?: OperationalAlert;
  requested_by_user?: UserSummary | null;
  approved_by_user?: UserSummary | null;
  rejected_by_user?: UserSummary | null;
}

export interface ActionEffectivenessMetric {
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

export interface AutomationHealthSummary {
  total_executions: number;
  completed_executions: number;
  failed_executions: number;
  execution_success_rate: number;
  problem_resolved_count: number;
  problem_resolution_effectiveness_rate: number;
  blocked_executions_count: number;
  pending_approvals_count: number;
  action_breakdown: ActionEffectivenessMetric[];
}

export interface PaginatedExecutionsResponse {
  data: OperationalActionExecution[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export interface PaginatedApprovalsResponse {
  data: OperationalActionApproval[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export const operationsService = {
  async getAlerts(params?: OperationalAlertsParams): Promise<{ alerts: PaginatedAlertsResponse }> {
    const res = await client.get('/admin/operations', { params });
    return res.data;
  },

  async getSummary(): Promise<{ summary: OperationsSummary }> {
    const res = await client.get('/admin/operations/summary');
    return res.data;
  },

  async runDetection(): Promise<{ message: string; report: any }> {
    const res = await client.post('/admin/operations/detect');
    return res.data;
  },

  async getAlert(id: number): Promise<{ alert: OperationalAlert }> {
    const res = await client.get(`/admin/operations/alerts/${id}`);
    return res.data;
  },

  async acknowledgeAlert(id: number): Promise<{ alert: OperationalAlert }> {
    const res = await client.post(`/admin/operations/alerts/${id}/acknowledge`);
    return res.data;
  },

  async resolveAlert(id: number, reason?: string): Promise<{ alert: OperationalAlert }> {
    const res = await client.post(`/admin/operations/alerts/${id}/resolve`, { reason });
    return res.data;
  },

  async dismissAlert(id: number, reason?: string): Promise<{ alert: OperationalAlert }> {
    const res = await client.post(`/admin/operations/alerts/${id}/dismiss`, { reason });
    return res.data;
  },

  async reopenAlert(id: number): Promise<{ alert: OperationalAlert }> {
    const res = await client.post(`/admin/operations/alerts/${id}/reopen`);
    return res.data;
  },

  async getActionsForAlert(alertId: number): Promise<{ actions: OperationalRemediationAction[] }> {
    const res = await client.get(`/admin/operations/alerts/${alertId}/actions`);
    return res.data;
  },

  async executeAction(alertId: number, actionKey: string): Promise<{ success: boolean; message: string; execution: OperationalActionExecution }> {
    const res = await client.post(`/admin/operations/alerts/${alertId}/actions/${actionKey}`);
    return res.data;
  },

  async getExecutions(params?: { alert_id?: number; action_key?: string; status?: string; page?: number; per_page?: number }): Promise<{ executions: PaginatedExecutionsResponse }> {
    const res = await client.get('/admin/operations/executions', { params });
    return res.data;
  },

  async getExecution(uuid: string): Promise<{ execution: OperationalActionExecution }> {
    const res = await client.get(`/admin/operations/executions/${uuid}`);
    return res.data;
  },

  async getGovernanceHealth(): Promise<{ metrics: AutomationHealthSummary }> {
    const res = await client.get('/admin/operations/governance/health');
    return res.data;
  },

  async getApprovals(params?: { status?: string; alert_id?: number; action_key?: string; page?: number; per_page?: number }): Promise<{ approvals: PaginatedApprovalsResponse }> {
    const res = await client.get('/admin/operations/approvals', { params });
    return res.data;
  },

  async requestApproval(alertId: number, actionKey: string, reason?: string): Promise<{ success: boolean; message: string; approval: OperationalActionApproval }> {
    const res = await client.post(`/admin/operations/alerts/${alertId}/actions/${actionKey}/request-approval`, { reason });
    return res.data;
  },

  async approveRequest(uuid: string, reason?: string): Promise<{ success: boolean; message: string; approval: OperationalActionApproval }> {
    const res = await client.post(`/admin/operations/approvals/${uuid}/approve`, { reason });
    return res.data;
  },

  async rejectRequest(uuid: string, reason?: string): Promise<{ success: boolean; message: string; approval: OperationalActionApproval }> {
    const res = await client.post(`/admin/operations/approvals/${uuid}/reject`, { reason });
    return res.data;
  },
};
