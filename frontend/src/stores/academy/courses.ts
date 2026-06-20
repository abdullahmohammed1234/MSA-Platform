import { defineStore } from 'pinia';
import { ref } from 'vue';
import { coursesService } from '@/services/academy/coursesService';
import { useProgressStore } from './progress';
import type { Course, Enrollment } from '@/types/academy';

export const useCoursesStore = defineStore('academy-courses', () => {
  const courses = ref<Course[]>([]);
  const currentCourse = ref<Course | null>(null);
  const currentEnrollment = ref<Enrollment | null>(null);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  const progressStore = useProgressStore();

  const lastFetched = ref<number | null>(null);
  const courseDetailsCache = ref<Record<string, { data: any; fetchedAt: number }>>({});
  const cacheDuration = 5 * 60 * 1000; // 5 minutes cache

  const fetchCourses = async (filters?: { difficulty?: string; search?: string }, force = false) => {
    const now = Date.now();
    const hasFilters = filters && (filters.difficulty || filters.search);
    if (!force && lastFetched.value && (now - lastFetched.value < cacheDuration) && !hasFilters && courses.value.length > 0) {
      return;
    }

    loading.value = true;
    error.value = null;
    try {
      courses.value = await coursesService.getCourses(filters);
      if (!hasFilters) {
        lastFetched.value = now;
      }
    } catch (err: any) {
      error.value = err.message || 'Failed to fetch courses';
    } finally {
      loading.value = false;
    }
  };

  const fetchCourseDetails = async (idOrSlug: string | number, force = false) => {
    const key = String(idOrSlug);
    const now = Date.now();
    if (!force && courseDetailsCache.value[key] && (now - courseDetailsCache.value[key].fetchedAt < cacheDuration)) {
      const cached = courseDetailsCache.value[key].data;
      currentCourse.value = cached.course;
      currentEnrollment.value = cached.enrollment;
      if (cached.progress) {
        progressStore.syncProgress(cached.course.id, cached.progress);
      }
      return;
    }

    loading.value = true;
    error.value = null;
    try {
      const data = await coursesService.getCourseDetails(idOrSlug);
      currentCourse.value = data.course;
      currentEnrollment.value = data.enrollment;

      const courseIndex = courses.value.findIndex((c) => c.id === data.course.id);
      if (courseIndex >= 0) {
        courses.value[courseIndex] = { ...courses.value[courseIndex], ...data.course };
      }

      if (data.progress) {
        progressStore.syncProgress(data.course.id, data.progress);
      }

      courseDetailsCache.value[key] = {
        data,
        fetchedAt: now
      };
    } catch (err: any) {
      error.value = err.message || 'Failed to fetch course details';
    } finally {
      loading.value = false;
    }
  };

  const enrollInCourse = async (courseId: number) => {
    loading.value = true;
    error.value = null;
    try {
      const enrollment = await coursesService.enroll(courseId);
      currentEnrollment.value = enrollment;
      
      // Invalidate caches
      lastFetched.value = null;
      courseDetailsCache.value = {};
      
      progressStore.markEnrolled(courseId);
      
      // Refresh course list/details
      if (currentCourse.value && currentCourse.value.id === courseId) {
        await fetchCourseDetails(currentCourse.value.slug, true);
      }
      return enrollment;
    } catch (err: any) {
      error.value = err.message || 'Enrollment failed';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  return {
    courses,
    currentCourse,
    currentEnrollment,
    loading,
    error,
    fetchCourses,
    fetchCourseDetails,
    enrollInCourse
  };
});
