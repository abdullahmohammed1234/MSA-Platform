import { defineStore } from 'pinia';
import { ref } from 'vue';
import client from '@/services/api/client';

export const useAnalyticsStore = defineStore('analytics', () => {
  const startDate = ref<string>(
    new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
  );
  const endDate = ref<string>(new Date().toISOString().split('T')[0]);

  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  const overview = ref<any>(null);
  const website = ref<any>(null);
  const academy = ref<any>(null);
  const events = ref<any>(null);
  const reports = ref<any[]>([]);

  const setDates = (start: string, end: string) => {
    startDate.value = start;
    endDate.value = end;
  };

  const getParams = () => {
    return {
      start_date: startDate.value,
      end_date: endDate.value,
    };
  };

  const fetchOverview = async () => {
    loading.value = true;
    error.value = null;
    try {
      const res = await client.get('/analytics/overview', { params: getParams() });
      if (res.data.success) {
        overview.value = res.data;
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch overview metrics';
    } finally {
      loading.value = false;
    }
  };

  const fetchWebsite = async () => {
    loading.value = true;
    error.value = null;
    try {
      const res = await client.get('/analytics/website', { params: getParams() });
      if (res.data.success) {
        website.value = res.data;
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch website metrics';
    } finally {
      loading.value = false;
    }
  };

  const fetchAcademy = async () => {
    loading.value = true;
    error.value = null;
    try {
      const res = await client.get('/analytics/academy', { params: getParams() });
      if (res.data.success) {
        academy.value = res.data;
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch academy metrics';
    } finally {
      loading.value = false;
    }
  };

  const fetchEvents = async () => {
    loading.value = true;
    error.value = null;
    try {
      const res = await client.get('/analytics/events', { params: getParams() });
      if (res.data.success) {
        events.value = res.data;
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch events metrics';
    } finally {
      loading.value = false;
    }
  };

  const fetchReports = async () => {
    loading.value = true;
    error.value = null;
    try {
      const res = await client.get('/analytics/reports');
      if (res.data.success) {
        reports.value = res.data.reports.data || [];
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch generated reports';
    } finally {
      loading.value = false;
    }
  };

  const exportData = async (format: 'csv' | 'pdf', type: 'website' | 'academy' | 'events' | 'overview') => {
    try {
      const res = await client.get('/analytics/export', {
        params: { ...getParams(), format, type },
        responseType: 'blob',
      });
      
      const blob = new Blob([res.data], {
        type: format === 'pdf' ? 'application/pdf' : 'text/csv',
      });
      
      const link = document.createElement('a');
      link.href = window.URL.createObjectURL(blob);
      link.download = `analytics_${type}_${new Date().toISOString().split('T')[0]}.${format}`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (err: any) {
      console.error('Export failed:', err);
    }
  };

  return {
    startDate,
    endDate,
    loading,
    error,
    overview,
    website,
    academy,
    events,
    reports,
    setDates,
    fetchOverview,
    fetchWebsite,
    fetchAcademy,
    fetchEvents,
    fetchReports,
    exportData,
  };
});
