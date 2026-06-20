import client from './api/client';

export interface QueueStatus {
  name: string;
  pending_count: number;
  active_count: number;
  failed_count: number;
  status: 'active' | 'idle' | 'empty';
}

export interface QueueMetrics {
  total_jobs_24h: number;
  completed_jobs_24h: number;
  failed_jobs_24h: number;
  avg_duration_24h: number;
  success_rate_24h: number;
  queue_breakdown: Record<string, {
    total_processed: number;
    failed_count: number;
    avg_duration: number;
    failure_rate: number;
  }>;
  hourly_throughput: Array<{
    hour: string;
    total_jobs: number;
    failed_jobs: number;
  }>;
}

export interface JobLog {
  id: number;
  job_uuid: string;
  job_name: string;
  queue: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  started_at: string | null;
  completed_at: string | null;
  duration: number | null;
  failure_reason: string | null;
  attempts: number;
  payload: any;
  created_at: string;
}

export interface FailedJob {
  id: number;
  uuid: string;
  connection: string;
  queue: string;
  name: string;
  exception: string;
  failed_at: string;
}

export interface AnalyticsReport {
  id: number;
  uuid: string;
  title: string;
  type: 'daily' | 'weekly' | 'monthly';
  filters: any;
  generated_by: number | null;
  generated_at: string;
  file_path: string;
  generated_by_user?: {
    id: number;
    name: string;
  };
}

export interface ScheduledTask {
  id: number;
  command: string;
  expression: string;
  description: string;
  timezone: string;
  next_run_at: string;
  interval_description: string;
}

export const systemService = {
  getQueues() {
    return client.get('/admin/system/queues').then(res => res.data);
  },

  getJobLogs(params?: { queue?: string; status?: string; job_name?: string; page?: number; per_page?: number }) {
    return client.get('/admin/system/jobs', { params }).then(res => res.data);
  },

  getFailedJobs(params?: { page?: number; per_page?: number }) {
    return client.get('/admin/system/jobs/failed', { params }).then(res => res.data);
  },

  retryJob(uuid: string) {
    return client.post(`/admin/system/jobs/failed/${uuid}/retry`).then(res => res.data);
  },

  retryAllJobs() {
    return client.post('/admin/system/jobs/failed/retry-all').then(res => res.data);
  },

  deleteFailedJob(uuid: string) {
    return client.delete(`/admin/system/jobs/failed/${uuid}`).then(res => res.data);
  },

  deleteAllFailedJobs() {
    return client.delete('/admin/system/jobs/failed').then(res => res.data);
  },

  clearQueue(queue: string) {
    return client.post('/admin/system/queues/clear', { queue }).then(res => res.data);
  },

  getReports(params?: { page?: number; per_page?: number }) {
    return client.get('/admin/system/reports', { params }).then(res => res.data);
  },

  generateReport(period: 'daily' | 'weekly' | 'monthly') {
    return client.post('/admin/system/reports/generate', { period }).then(res => res.data);
  },

  deleteReport(uuid: string) {
    return client.delete(`/admin/system/reports/${uuid}`).then(res => res.data);
  },

  getScheduler() {
    return client.get('/admin/system/scheduler').then(res => res.data);
  },

  runScheduledTask(id: number) {
    return client.post('/admin/system/scheduler/run', { id }).then(res => res.data);
  },

  getPerformanceStats() {
    return client.get('/admin/system/performance').then(res => res.data);
  },

  getDownloadUrl(uuid: string) {
    const baseURL = (import.meta.env.VITE_API_URL as string) || 'http://localhost:8000/api/v1';
    const token = localStorage.getItem('auth_token');
    return `${baseURL}/admin/system/reports/${uuid}/download?token=${token}`;
  }
};
