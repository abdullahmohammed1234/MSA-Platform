import client from '@/services/api';
import type { Progress } from '@/types/academy';

export const lessonsService = {
  async completeLesson(courseId: number, lessonId: number): Promise<{
    success: boolean;
    message: string;
    completion_percentage: number;
    course_completed: boolean;
    progress: Progress;
  }> {
    const response = await client.post(`/academy/courses/${courseId}/lessons/${lessonId}/complete`);
    const data = response.data;

    if (!data?.success) {
      throw new Error(data?.message || 'Failed to complete lesson.');
    }

    return data;
  },
};
