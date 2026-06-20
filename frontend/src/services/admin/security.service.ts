import client from '@/services/api/client';

export interface SecurityDashboardData {
  metrics: {
    failed_logins_24h: number;
    failed_logins_7d: number;
    rate_violations_24h: number;
    rate_violations_7d: number;
    total_security_events_24h: number;
    total_security_events_7d: number;
    total_audit_logs_24h: number;
    total_audit_logs_7d: number;
  };
  recent_security_events: any[];
  recent_audit_logs: any[];
  system_health: {
    db_status: string;
    failed_jobs: number;
    active_sessions: number;
    app_debug: string;
    app_env: string;
    https_enforced: string;
  };
  chart_data: {
    labels: string[];
    failed_logins: number[];
    rate_violations: number[];
  };
}

export const securityService = {
  /**
   * Fetch security events, audit trail, chart data, and platform health aggregates.
   */
  async getDashboardData(): Promise<SecurityDashboardData> {
    const response = await client.get('/admin/security/dashboard');
    return response.data;
  }
};
