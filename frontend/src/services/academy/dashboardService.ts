import client from '@/services/api/client';
import type { Course } from '@/types/academy';
import type { ActivityLog, DashboardStats } from '@/stores/academy/dashboard';

export interface DashboardPayload {
  stats: DashboardStats & { totalXp: number; streakDays: number };
  courseProgress: Record<number, number>;
  recentActivities: ActivityLog[];
  continueLearning: {
    courseId: number;
    courseTitle: string;
    courseSlug: string;
    moduleTitle: string;
    lessonTitle: string;
    lessonId: number;
    progress: number;
  } | null;
  recommendedCourses: Pick<Course, 'id' | 'title' | 'slug' | 'difficulty'>[];
}

export const dashboardService = {
  async getDashboard(): Promise<DashboardPayload> {
    const response = await client.get('/academy/dashboard');
    const data = response.data;
    if (!data?.success) {
      throw new Error(data?.message || 'Failed to load dashboard.');
    }
    return data;
  },
};
