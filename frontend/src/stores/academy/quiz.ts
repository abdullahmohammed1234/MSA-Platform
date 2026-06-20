import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { quizzesService } from '@/services/academy/quizzesService';
import { useGamificationStore } from '@/stores/gamification';
import type { Quiz, QuizAttempt } from '@/types/academy';

export const useQuizStore = defineStore('academy-quiz', () => {
  const activeQuiz = ref<Quiz | null>(null);
  const selectedAnswers = ref<Record<number, string[]>>({});
  const currentQuestionIndex = ref<number>(0);
  const timeRemaining = ref<number | null>(null); // in seconds
  const currentAttempt = ref<QuizAttempt | null>(null);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);
  
  let timerInterval: any = null;

  const questionsCount = computed(() => activeQuiz.value?.questions?.length || 0);

  const startQuiz = (quiz: Quiz) => {
    activeQuiz.value = quiz;
    selectedAnswers.value = {};
    currentQuestionIndex.value = 0;
    currentAttempt.value = null;
    error.value = null;
    
    // Clear any existing timer
    if (timerInterval) {
      clearInterval(timerInterval);
    }

    if (quiz.time_limit) {
      timeRemaining.value = quiz.time_limit * 60;
      timerInterval = setInterval(() => {
        if (timeRemaining.value !== null && timeRemaining.value > 0) {
          timeRemaining.value--;
        } else {
          clearInterval(timerInterval);
          submitQuiz(quiz.course_id);
        }
      }, 1000);
    } else {
      timeRemaining.value = null;
    }
  };

  const setAnswer = (questionId: number, answer: string[]) => {
    selectedAnswers.value[questionId] = answer;
  };

  const getAnswer = (questionId: number): string[] => {
    return selectedAnswers.value[questionId] || [];
  };

  const nextQuestion = () => {
    if (currentQuestionIndex.value < questionsCount.value - 1) {
      currentQuestionIndex.value++;
    }
  };

  const prevQuestion = () => {
    if (currentQuestionIndex.value > 0) {
      currentQuestionIndex.value--;
    }
  };

  const submitQuiz = async (courseId: number): Promise<QuizAttempt> => {
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }

    if (!activeQuiz.value) {
      throw new Error('No active quiz to submit');
    }

    loading.value = true;
    error.value = null;

    try {
      // Map local answers format to API format
      const formattedAnswers = Object.entries(selectedAnswers.value).map(([qId, ans]) => ({
        question_id: Number(qId),
        answer: ans
      }));

      const res = await quizzesService.submitQuiz(courseId, activeQuiz.value.id, formattedAnswers);
      
      if (res.success) {
        currentAttempt.value = res.attempt;
        if (res.attempt.passed && activeQuiz.value?.title) {
          useGamificationStore().recordQuizPass(activeQuiz.value.title, res.attempt.score);
        }
        return res.attempt;
      } else {
        throw new Error(res.message || 'Quiz submission failed');
      }
    } catch (err: any) {
      error.value = err.message || 'An error occurred during submission';
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const cleanQuizState = () => {
    activeQuiz.value = null;
    selectedAnswers.value = {};
    currentQuestionIndex.value = 0;
    timeRemaining.value = null;
    currentAttempt.value = null;
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
  };

  return {
    activeQuiz,
    selectedAnswers,
    currentQuestionIndex,
    timeRemaining,
    currentAttempt,
    loading,
    error,
    questionsCount,
    startQuiz,
    setAnswer,
    getAnswer,
    nextQuestion,
    prevQuestion,
    submitQuiz,
    cleanQuizState
  };
});
