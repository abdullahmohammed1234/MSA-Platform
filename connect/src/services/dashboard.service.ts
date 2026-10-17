import apiClient from './api';
import type { DashboardData } from '@/types/dashboard';

export const dashboardService = {
  /**
   * Fetch authenticated user aggregated dashboard data.
   */
  async getDashboard(): Promise<DashboardData> {
    const response = await apiClient.get<DashboardData>('/dashboard');
    return response.data;
  },
};

export default dashboardService;
