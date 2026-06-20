import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface GamificationToast {
  id: string;
  type: 'badge' | 'milestone' | 'streak' | 'lesson' | 'quiz';
  title: string;
  subtitle: string;
  xpBonus?: number;
}

export interface CelebrationState {
  title: string;
  subtitle: string;
  xpBonus?: number;
}

export const useGamificationStore = defineStore('gamification', () => {
  const streakDays = ref(0);
  const totalXp = ref(0);
  const toasts = ref<GamificationToast[]>([]);
  const celebration = ref<CelebrationState | null>(null);

  const pushToast = (toast: Omit<GamificationToast, 'id'>) => {
    const entry: GamificationToast = {
      ...toast,
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    };
    toasts.value.unshift(entry);
    if (toast.xpBonus) {
      totalXp.value += toast.xpBonus;
    }
    window.setTimeout(() => dismissToast(entry.id), 5000);
  };

  const dismissToast = (id: string) => {
    toasts.value = toasts.value.filter((item) => item.id !== id);
  };

  const showCelebration = (payload: CelebrationState) => {
    celebration.value = payload;
  };

  const clearCelebration = () => {
    celebration.value = null;
  };

  const recordLessonComplete = (lessonTitle: string) => {
    streakDays.value += 1;
    showCelebration({
      title: 'Lesson Complete',
      subtitle: `You finished "${lessonTitle}". Keep your learning streak alive.`,
      xpBonus: 25,
    });
    pushToast({
      type: 'lesson',
      title: 'Lesson completed',
      subtitle: lessonTitle,
      xpBonus: 25,
    });
  };

  const recordQuizPass = (quizTitle: string, score: number) => {
    showCelebration({
      title: 'Assessment Passed',
      subtitle: `You scored ${score}% on "${quizTitle}".`,
      xpBonus: 100,
    });
    pushToast({
      type: 'quiz',
      title: 'Quiz passed',
      subtitle: `${quizTitle} · ${score}%`,
      xpBonus: 100,
    });
  };

  return {
    streakDays,
    totalXp,
    toasts,
    celebration,
    pushToast,
    dismissToast,
    showCelebration,
    clearCelebration,
    recordLessonComplete,
    recordQuizPass,
  };
});
