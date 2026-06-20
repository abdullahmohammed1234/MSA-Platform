import { defineStore } from 'pinia';
import { ref } from 'vue';
import { 
  systemService, 
  type QueueStatus, 
  type QueueMetrics, 
  type JobLog, 
  type FailedJob, 
  type AnalyticsReport, 
  type ScheduledTask 
} from '@/services/system';

export const useSystemStore = defineStore('system', () => {
  const queues = ref<QueueStatus[]>([]);
  const metrics = ref<QueueMetrics | null>(null);
  const jobLogs = ref<JobLog[]>([]);
  const jobLogsTotal = ref<number>(0);
  const failedJobs = ref<FailedJob[]>([]);
  const failedJobsTotal = ref<number>(0);
  const reports = ref<AnalyticsReport[]>([]);
  const reportsTotal = ref<number>(0);
  const schedulerTasks = ref<ScheduledTask[]>([]);
  
  const isLoading = ref<boolean>(false);
  const isActionLoading = ref<boolean>(false);

  /**
   * Fetch queues overview and health metrics.
   */
  const fetchQueues = async () => {
    isLoading.value = true;
    try {
      const data = await systemService.getQueues();
      queues.value = data.queues;
      metrics.value = data.metrics;
    } catch (error) {
      console.error('Failed to fetch queue status:', error);
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Fetch job execution logs.
   */
  const fetchJobLogs = async (params?: { queue?: string; status?: string; job_name?: string; page?: number; per_page?: number }) => {
    isLoading.value = true;
    try {
      const data = await systemService.getJobLogs(params);
      jobLogs.value = data.logs.data;
      jobLogsTotal.value = data.logs.total;
    } catch (error) {
      console.error('Failed to fetch job logs:', error);
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Fetch failed jobs.
   */
  const fetchFailedJobs = async (params?: { page?: number; per_page?: number }) => {
    isLoading.value = true;
    try {
      const data = await systemService.getFailedJobs(params);
      failedJobs.value = data.failed_jobs.data;
      failedJobsTotal.value = data.failed_jobs.total;
    } catch (error) {
      console.error('Failed to fetch failed jobs:', error);
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Retry a failed job.
   */
  const retryJob = async (uuid: string) => {
    isActionLoading.value = true;
    try {
      await systemService.retryJob(uuid);
      // Refresh queues and failed lists
      await Promise.all([fetchQueues(), fetchFailedJobs()]);
    } catch (error) {
      console.error('Failed to retry job:', error);
      throw error;
    } finally {
      isActionLoading.value = false;
    }
  };

  /**
   * Retry all failed jobs.
   */
  const retryAllJobs = async () => {
    isActionLoading.value = true;
    try {
      await systemService.retryAllJobs();
      await Promise.all([fetchQueues(), fetchFailedJobs()]);
    } catch (error) {
      console.error('Failed to retry all jobs:', error);
      throw error;
    } finally {
      isActionLoading.value = false;
    }
  };

  /**
   * Delete a failed job.
   */
  const deleteFailedJob = async (uuid: string) => {
    isActionLoading.value = true;
    try {
      await systemService.deleteFailedJob(uuid);
      await Promise.all([fetchQueues(), fetchFailedJobs()]);
    } catch (error) {
      console.error('Failed to delete failed job:', error);
      throw error;
    } finally {
      isActionLoading.value = false;
    }
  };

  /**
   * Delete all failed jobs.
   */
  const deleteAllFailedJobs = async () => {
    isActionLoading.value = true;
    try {
      await systemService.deleteAllFailedJobs();
      await Promise.all([fetchQueues(), fetchFailedJobs()]);
    } catch (error) {
      console.error('Failed to delete all failed jobs:', error);
      throw error;
    } finally {
      isActionLoading.value = false;
    }
  };

  /**
   * Clear a queue partition.
   */
  const clearQueue = async (queue: string) => {
    isActionLoading.value = true;
    try {
      await systemService.clearQueue(queue);
      await fetchQueues();
    } catch (error) {
      console.error(`Failed to clear queue ${queue}:`, error);
      throw error;
    } finally {
      isActionLoading.value = false;
    }
  };

  /**
   * Fetch generated reports.
   */
  const fetchReports = async (params?: { page?: number; per_page?: number }) => {
    isLoading.value = true;
    try {
      const data = await systemService.getReports(params);
      reports.value = data.reports.data;
      reportsTotal.value = data.reports.total;
    } catch (error) {
      console.error('Failed to fetch reports:', error);
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Manually request a report generation.
   */
  const generateReport = async (period: 'daily' | 'weekly' | 'monthly') => {
    isActionLoading.value = true;
    try {
      await systemService.generateReport(period);
      await fetchReports();
    } catch (error) {
      console.error('Failed to generate report:', error);
      throw error;
    } finally {
      isActionLoading.value = false;
    }
  };

  /**
   * Delete a report.
   */
  const deleteReport = async (uuid: string) => {
    isActionLoading.value = true;
    try {
      await systemService.deleteReport(uuid);
      await fetchReports();
    } catch (error) {
      console.error('Failed to delete report:', error);
      throw error;
    } finally {
      isActionLoading.value = false;
    }
  };

  /**
   * Fetch scheduled tasks.
   */
  const fetchScheduler = async () => {
    isLoading.value = true;
    try {
      const data = await systemService.getScheduler();
      schedulerTasks.value = data.tasks;
    } catch (error) {
      console.error('Failed to fetch scheduler tasks:', error);
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Run a scheduled task immediately.
   */
  const runScheduledTask = async (id: number) => {
    isActionLoading.value = true;
    try {
      await systemService.runScheduledTask(id);
      await Promise.all([fetchQueues(), fetchScheduler()]);
    } catch (error) {
      console.error('Failed to run scheduled task:', error);
      throw error;
    } finally {
      isActionLoading.value = false;
    }
  };

  const performanceStats = ref<any>(null);

  const fetchPerformanceStats = async () => {
    isLoading.value = true;
    try {
      performanceStats.value = await systemService.getPerformanceStats();
    } catch (error) {
      console.error('Failed to fetch performance stats:', error);
    } finally {
      isLoading.value = false;
    }
  };

  return {
    queues,
    metrics,
    jobLogs,
    jobLogsTotal,
    failedJobs,
    failedJobsTotal,
    reports,
    reportsTotal,
    schedulerTasks,
    performanceStats,
    isLoading,
    isActionLoading,
    fetchQueues,
    fetchJobLogs,
    fetchFailedJobs,
    retryJob,
    retryAllJobs,
    deleteFailedJob,
    deleteAllFailedJobs,
    clearQueue,
    fetchReports,
    generateReport,
    deleteReport,
    fetchScheduler,
    runScheduledTask,
    fetchPerformanceStats,
  };
});
