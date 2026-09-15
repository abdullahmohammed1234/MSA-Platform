import client from '@/services/api';

export interface IntelligencePeriodParams {
  period?: 'today' | '7d' | '30d' | '90d' | 'this_year' | 'custom';
  start_date?: string;
  end_date?: string;
}

export interface KpiMetric {
  current: number;
  previous: number;
  total?: number;
  pct_change: number;
}

export interface PlatformIntelligenceData {
  overall_health: {
    status: 'healthy' | 'degraded';
    total_apps: number;
    healthy_apps: number;
    unhealthy_apps: number;
  };
  platform: {
    total_users: number;
    privileged_admins: number;
  };
  failed_jobs_count: number;
  active_alerts_count: number;
  domains: {
    ems: any;
    donations: any;
    store: any;
    mlibms: any;
    communications: any;
    volunteers: any;
    feedback: any;
  };
  generated_at: string;
}

export const platformIntelligenceService = {
  async getOverview(params?: IntelligencePeriodParams): Promise<PlatformIntelligenceData> {
    const res = await client.get('/admin/intelligence', { params });
    return res.data.data;
  },

  async getEms(params?: IntelligencePeriodParams): Promise<any> {
    const res = await client.get('/admin/intelligence/ems', { params });
    return res.data.data;
  },

  async getDonations(params?: IntelligencePeriodParams): Promise<any> {
    const res = await client.get('/admin/intelligence/donations', { params });
    return res.data.data;
  },

  async getStore(params?: IntelligencePeriodParams): Promise<any> {
    const res = await client.get('/admin/intelligence/store', { params });
    return res.data.data;
  },

  async getMlibms(params?: IntelligencePeriodParams): Promise<any> {
    const res = await client.get('/admin/intelligence/mlibms', { params });
    return res.data.data;
  },

  async getCommunications(params?: IntelligencePeriodParams): Promise<any> {
    const res = await client.get('/admin/intelligence/communications', { params });
    return res.data.data;
  },

  async getVolunteers(params?: IntelligencePeriodParams): Promise<any> {
    const res = await client.get('/admin/intelligence/volunteers', { params });
    return res.data.data;
  },

  async getFeedback(params?: IntelligencePeriodParams): Promise<any> {
    const res = await client.get('/admin/intelligence/feedback', { params });
    return res.data.data;
  },
};
