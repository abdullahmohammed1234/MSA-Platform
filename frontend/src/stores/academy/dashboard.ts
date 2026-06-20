import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { dashboardService } from '@/services/academy/dashboardService';
import { useProgressStore } from './progress';
import { useBadgesStore } from './badges';
import { useGamificationStore } from '@/stores/gamification';
import type { Course } from '@/types/academy';

export interface DashboardStats {
  coursesEnrolled: number;
  coursesCompleted: number;
  badgesUnlocked: number;
  overallProgress: number;
  totalXp?: number;
  streakDays?: number;
}

export interface ActivityLog {
  id: string | number;
  type: 'lesson_completed' | 'quiz_passed' | 'quiz_failed' | 'enrolled';
  title: string;
  detail: string;
  timestamp: string;
}

export interface ContinueLearning {
  courseId: number;
  courseTitle: string;
  courseSlug: string;
  moduleTitle: string;
  lessonTitle: string;
  lessonId: number;
  progress: number;
}

export const useDashboardStore = defineStore('academy-dashboard', () => {
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);
  const recommendedCourses = ref<Pick<Course, 'id' | 'title' | 'slug' | 'difficulty'>[]>([]);
  const recentActivities = ref<ActivityLog[]>([]);
  const continueLearningData = ref<ContinueLearning | null>(null);
  const serverStats = ref<DashboardStats | null>(null);

  const progressStore = useProgressStore();
  const badgesStore = useBadgesStore();
  const gamification = useGamificationStore();

  const stats = computed((): DashboardStats => {
    if (serverStats.value) {
      return serverStats.value;
    }

    const progressKeys = Object.keys(progressStore.courseProgress);
    const enrolledCount = progressKeys.length;
    let completedCount = 0;
    let totalProgressSum = 0;

    progressKeys.forEach((kStr) => {
      const pct = progressStore.courseProgress[Number(kStr)] || 0;
      if (pct >= 100) completedCount++;
      totalProgressSum += pct;
    });

    return {
      coursesEnrolled: enrolledCount,
      coursesCompleted: completedCount,
      badgesUnlocked: badgesStore.badges.filter((b) => b.unlocked).length,
      overallProgress: enrolledCount > 0 ? Math.round(totalProgressSum / enrolledCount) : 0,
    };
  });

  const continueLearning = computed(() => continueLearningData.value);

  const fetchDashboardData = async () => {
    loading.value = true;
    error.value = null;
    try {
      const payload = await dashboardService.getDashboard();

      serverStats.value = payload.stats;
      recentActivities.value = payload.recentActivities;
      continueLearningData.value = payload.continueLearning;
      recommendedCourses.value = payload.recommendedCourses;

      Object.entries(payload.courseProgress).forEach(([courseId, pct]) => {
        progressStore.courseProgress[Number(courseId)] = pct;
      });

      if (payload.stats.totalXp !== undefined) {
        gamification.totalXp = payload.stats.totalXp;
      }
      if (payload.stats.streakDays !== undefined) {
        gamification.streakDays = payload.stats.streakDays;
      }

      await badgesStore.fetchBadges();
    } catch (err: any) {
      error.value = err.message || 'Failed to fetch dashboard data';
      await badgesStore.fetchBadges();
      recommendedCourses.value = [];
      recentActivities.value = [];
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    stats,
    continueLearning,
    recommendedCourses,
    recentActivities,
    fetchDashboardData,
  };
});
