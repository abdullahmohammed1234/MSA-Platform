import api from '@/services/api';

export interface PlatformHealthHistoryItem {
  id: number;
  recorded_at: string;
  apps_health: Record<string, { status: string; probe_ms: number; last_check: string; details?: any }>;
  services_health: Record<string, { status: string; probe_ms: number; last_check: string; details?: any }>;
  overall_status: string;
  response_time_ms: number;
  memory_mb: number;
  cpu_load: number;
}

export interface PlatformAlertItem {
  id: number;
  alert_key: string;
  app_key: string;
  severity: 'info' | 'warning' | 'critical';
  status: 'active' | 'acknowledged' | 'resolved';
  title: string;
  message: string;
  context: any;
  acknowledged_at: string | null;
  acknowledged_by: any | null;
  resolved_at: string | null;
  resolved_by: any | null;
  created_at: string;
  updated_at: string;
}

export interface PlatformAuditLogItem {
  id: number;
  user_id: number | null;
  application: string | null;
  severity: string | null;
  action: string;
  entity_type: string | null;
  entity_id: string | null;
  old_values: any;
  new_values: any;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  user?: {
    id: number;
    name?: string;
    first_name?: string;
    last_name?: string;
    email?: string;
  } | null;
}

export interface PlatformIntelligenceMetrics {
  generated_at: string;
  overall_health: {
    status: string;
    total_apps: number;
    healthy_apps: number;
    unhealthy_apps: number;
    stale_apps: number;
  };
  apps: Record<string, any>;
  services: Record<string, any>;
  cross_system_summary: {
    events_count: number;
    store_orders_count: number;
    donations_count: number;
    sponsorships_count: number;
    library_loans_count: number;
    academy_learners_count: number;
  };
  active_alerts_count: number;
  failed_jobs_count: number;
  failed_jobs_samples: Array<{
    id: number;
    connection: string;
    queue: string;
    failed_at: string;
    exception_summary: string;
  }>;
  recent_audits: PlatformAuditLogItem[];
  recent_alerts: PlatformAlertItem[];
}

export const platformOperationsService = {
  async getIntelligenceMetrics(): Promise<PlatformIntelligenceMetrics> {
    const res = await api.get('/admin/platform/intelligence/metrics');
    return res.data.metrics || res.data.data || res.data;
  },

  async getHealthHistory(params?: { app_key?: string; limit?: number }) {
    const res = await api.get('/admin/platform/health/history', { params });
    return res.data;
  },

  async triggerHealthSnapshot() {
    const res = await api.post('/admin/platform/health/snapshot');
    return res.data;
  },

  async getAuditLogs(params?: {
    application?: string;
    severity?: string;
    action?: string;
    search?: string;
    user_id?: number;
    page?: number;
    per_page?: number;
  }) {
    const res = await api.get('/admin/platform/audit', { params });
    return res.data;
  },

  async getAlerts(params?: {
    status?: string;
    severity?: string;
    app_key?: string;
    page?: number;
  }) {
    const res = await api.get('/admin/platform/alerts', { params });
    return res.data;
  },

  async acknowledgeAlert(id: number, notes?: string) {
    const res = await api.post(`/admin/platform/alerts/${id}/acknowledge`, { notes });
    return res.data;
  },

  async resolveAlert(id: number, notes?: string) {
    const res = await api.post(`/admin/platform/alerts/${id}/resolve`, { notes });
    return res.data;
  },

  async retryFailedJob(job_id: number) {
    const res = await api.post('/admin/platform/operations/retry-job', { job_id });
    return res.data;
  },

  async flushFailedJobs(confirm: boolean) {
    const res = await api.post('/admin/platform/operations/flush-failed', { confirm });
    return res.data;
  },

  async runTask(task: string) {
    const res = await api.post('/admin/platform/operations/run-task', { task });
    return res.data;
  },
};
