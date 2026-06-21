import client from '@/services/api';
import type { QuizAttempt } from '@/types/academy';

interface SubmittedAnswer {
  question_id: number;
  answer: string[];
}

export const quizzesService = {
  async submitQuiz(
    courseId: number,
    quizId: number,
    answers: SubmittedAnswer[]
  ): Promise<{
    success: boolean;
    message: string;
    attempt: QuizAttempt;
    course_completed: boolean;
  }> {
    const response = await client.post(`/academy/courses/${courseId}/quizzes/${quizId}/submit`, {
      answers,
    });
    const data = response.data;

    if (!data?.success) {
      throw new Error(data?.message || 'Failed to submit quiz.');
    }

    return data;
  },

  async getAttempts(quizId?: number): Promise<QuizAttempt[]> {
    const params = quizId ? { quiz_id: quizId } : undefined;
    const response = await client.get('/academy/quiz-attempts', { params });
    const data = response.data;

    if (!data?.success) {
      throw new Error(data?.message || 'Failed to load quiz attempts.');
    }

    return Array.isArray(data.attempts) ? data.attempts : [];
  },
};
