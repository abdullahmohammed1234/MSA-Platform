import client from '@/services/api/client';
import { parseApiResponse } from '@/services/api/parseResponse';
import type { Course, Enrollment, Progress, LearningPath } from '@/types/academy';

export const coursesService = {
  async getCourses(filters?: { difficulty?: string; search?: string }): Promise<Course[]> {
    const response = await client.get('/academy/courses', { params: filters });
    return parseApiResponse<Course[]>(response, 'courses');
  },

  async getCourseDetails(idOrSlug: string | number): Promise<{
    course: Course;
    enrollment: Enrollment | null;
    progress: Progress[];
  }> {
    const response = await client.get(`/academy/courses/${idOrSlug}`);
    const data = response.data;

    if (!data?.success) {
      throw new Error(data?.message || 'Failed to load course details.');
    }

    return {
      course: data.course,
      enrollment: data.enrollment ?? null,
      progress: data.progress ?? [],
    };
  },

  async enroll(courseId: number): Promise<Enrollment> {
    const response = await client.post(`/academy/courses/${courseId}/enroll`);
    return parseApiResponse<Enrollment>(response, 'enrollment');
  },

  async getLearningPaths(): Promise<LearningPath[]> {
    const response = await client.get('/academy/learning-paths');
    return parseApiResponse<LearningPath[]>(response, 'learning_paths');
  },
};
