import { defineStore } from 'pinia';
import { ref } from 'vue';
import { mentorService } from '@/services/academy/mentorService';
import type { MentorCourse, MentorSubmission, MentorLearner, MentorExam } from '@/services/academy/mentorService';

export const useMentorStore = defineStore('mentor', () => {
  const courses = ref<MentorCourse[]>([]);
  const submissions = ref<MentorSubmission[]>([]);
  const volunteers = ref<MentorLearner[]>([]);
  const quizAnalytics = ref<MentorExam[]>([]);
  const certifications = ref<any[]>([]);
  const discussions = ref<any[]>([]);
  
  const isLoading = ref(false);
  const isLoaded = ref(false);
  const error = ref<string | null>(null);

  const fetchState = async () => {
    isLoading.value = true;
    error.value = null;
    try {
      const data = await mentorService.getDashboardState();
      courses.value = data.courses;
      submissions.value = data.submissions;
      volunteers.value = data.volunteers;
      quizAnalytics.value = data.quizAnalytics;
      certifications.value = data.certifications;
      discussions.value = data.discussions;
      isLoaded.value = true;
    } catch (err: any) {
      error.value = err.message || 'Failed to load mentor workspace.';
    } finally {
      isLoading.value = false;
    }
  };

  const gradeSubmission = async (submissionId: string | number, score: number) => {
    await mentorService.gradeSubmission(submissionId, score);
    submissions.value = submissions.value.map((sub) =>
      sub.id === submissionId ? { ...sub, score, status: 'graded' } : sub
    );
  };

  const saveCourseDraft = (course: MentorCourse) => {
    const exists = courses.value.some((c) => c.id === course.id);
    if (exists) {
      courses.value = courses.value.map((c) => (c.id === course.id ? course : c));
    } else {
      courses.value = [course, ...courses.value];
    }
  };

  const saveExamDraft = (exam: MentorExam) => {
    const exists = quizAnalytics.value.some((e) => e.id === exam.id);
    if (exists) {
      quizAnalytics.value = quizAnalytics.value.map((e) => (e.id === exam.id ? exam : e));
    } else {
      quizAnalytics.value = [exam, ...quizAnalytics.value];
    }
  };

  return {
    courses,
    submissions,
    volunteers,
    quizAnalytics,
    certifications,
    discussions,
    isLoading,
    isLoaded,
    error,
    fetchState,
    gradeSubmission,
    saveCourseDraft,
    saveExamDraft
  };
});
