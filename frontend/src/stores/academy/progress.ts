import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { lessonsService } from '@/services/academy/lessonsService';
import { useGamificationStore } from '@/stores/gamification';
import type { Progress } from '@/types/academy';

export const useProgressStore = defineStore('academy-progress', () => {
  const completedLessons = ref<string[]>([]);
  const courseProgress = ref<Record<number, number>>({});
  const loading = ref<boolean>(false);

  const isLessonCompleted = computed(() => (courseId: number, lessonId: number): boolean => {
    return completedLessons.value.includes(`${courseId}-${lessonId}`);
  });

  const completedLessonsForCourse = (courseId: number): number => {
    return completedLessons.value.filter((key) => key.startsWith(`${courseId}-`)).length;
  };

  const syncProgress = (courseId: number, progressRecords: Progress[]) => {
    progressRecords.forEach((rec) => {
      if (rec.completed) {
        const key = `${courseId}-${rec.lesson_id}`;
        if (!completedLessons.value.includes(key)) {
          completedLessons.value.push(key);
        }
      }
    });

    if (progressRecords.length > 0) {
      const maxPct = Math.max(...progressRecords.map((r) => r.completion_percentage));
      courseProgress.value[courseId] = maxPct;
    } else if (courseProgress.value[courseId] === undefined) {
      courseProgress.value[courseId] = 0;
    }
  };

  const markEnrolled = (courseId: number) => {
    if (courseProgress.value[courseId] === undefined) {
      courseProgress.value[courseId] = 0;
    }
  };

  const completeLesson = async (courseId: number, lessonId: number, lessonTitle?: string) => {
    loading.value = true;
    try {
      const res = await lessonsService.completeLesson(courseId, lessonId);
      const key = `${courseId}-${lessonId}`;
      if (!completedLessons.value.includes(key)) {
        completedLessons.value.push(key);
      }
      courseProgress.value[courseId] = res.completion_percentage;

      if (lessonTitle) {
        useGamificationStore().recordLessonComplete(lessonTitle);
      }

      return res;
    } finally {
      loading.value = false;
    }
  };

  const reset = () => {
    completedLessons.value = [];
    courseProgress.value = {};
  };

  return {
    completedLessons,
    courseProgress,
    loading,
    isLessonCompleted,
    completedLessonsForCourse,
    syncProgress,
    markEnrolled,
    completeLesson,
    reset,
  };
});
