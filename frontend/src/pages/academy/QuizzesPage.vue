<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { FileQuestion, Clock, Award, ArrowRight, CheckCircle, AlertCircle } from 'lucide-vue-next';
import { useCoursesStore } from '@/stores/academy/courses';
import { useProgressStore } from '@/stores/academy/progress';
import { coursesService } from '@/services/academy/coursesService';
import type { Course, Quiz } from '@/types/academy';

interface QuizRow extends Quiz {
  courseTitle: string;
  courseId: number;
}

const router = useRouter();
const coursesStore = useCoursesStore();
const progressStore = useProgressStore();

const loading = ref(true);
const error = ref<string | null>(null);
const enrolledCourses = ref<Course[]>([]);

onMounted(async () => {
  loading.value = true;
  error.value = null;

  try {
    await coursesStore.fetchCourses();
    const details = await Promise.all(
      coursesStore.courses.map((course) => coursesService.getCourseDetails(course.slug))
    );

    enrolledCourses.value = details
      .filter((entry) => entry.enrollment)
      .map((entry) => {
        progressStore.syncProgress(entry.course.id, entry.progress);
        return entry.course;
      });
  } catch (err: any) {
    error.value = err.message || 'Failed to load quizzes.';
  } finally {
    loading.value = false;
  }
});

const quizzes = computed((): QuizRow[] => {
  const rows: QuizRow[] = [];

  enrolledCourses.value.forEach((course) => {
    (course.quizzes || []).forEach((quiz) => {
      rows.push({
        ...quiz,
        courseTitle: course.title,
        courseId: course.id,
      });
    });
  });

  return rows;
});

const courseCompleted = (courseId: number) => (progressStore.courseProgress[courseId] || 0) >= 100;

const openQuiz = (quiz: QuizRow) => {
  router.push(`/academy/courses/${quiz.courseId}/quizzes/${quiz.id}`);
};
</script>

<template>
  <div class="space-y-8 pb-16">
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Knowledge Assessments</h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        Certify your expertise in dawah methodology, dialogue ethics, and worldview analysis.
      </p>
    </div>

    <div class="rounded-3xl border border-amber-500/10 bg-amber-500/5 p-6 text-sm text-neutral-black/70">
      Quizzes unlock after completing the required lessons in each enrolled course.
    </div>

    <div v-if="loading" class="py-16 text-center text-neutral-muted">Loading quizzes...</div>
    <div v-else-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
      {{ error }}
    </div>
    <div
      v-else-if="quizzes.length === 0"
      class="rounded-3xl border border-neutral-ivory bg-white p-10 text-center shadow-soft"
    >
      <FileQuestion class="mx-auto h-10 w-10 text-neutral-muted" />
      <h2 class="mt-4 text-lg font-semibold text-primary">No assessments available</h2>
      <p class="mt-2 text-sm text-neutral-muted">Enroll in a course to access its knowledge assessments.</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4">
      <article
        v-for="quiz in quizzes"
        :key="`${quiz.courseId}-${quiz.id}`"
        class="rounded-3xl border border-neutral-ivory bg-white p-6 shadow-soft"
      >
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div class="space-y-2">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-neutral-muted">{{ quiz.courseTitle }}</p>
            <h2 class="text-xl font-display font-semibold text-primary">{{ quiz.title }}</h2>
            <p v-if="quiz.description" class="text-sm text-neutral-muted">{{ quiz.description }}</p>
            <div class="flex flex-wrap items-center gap-4 text-xs text-neutral-muted">
              <span class="inline-flex items-center gap-1.5">
                <Clock class="h-3.5 w-3.5" />
                {{ quiz.time_limit || 15 }} min
              </span>
              <span class="inline-flex items-center gap-1.5">
                <Award class="h-3.5 w-3.5" />
                Pass: {{ quiz.passing_score || 75 }}%
              </span>
              <span
                v-if="courseCompleted(quiz.courseId)"
                class="inline-flex items-center gap-1.5 text-emerald-700"
              >
                <CheckCircle class="h-3.5 w-3.5" />
                Course completed
              </span>
              <span v-else class="inline-flex items-center gap-1.5">
                <AlertCircle class="h-3.5 w-3.5" />
                In progress
              </span>
            </div>
          </div>

          <button
            type="button"
            class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2.5 text-xs font-bold uppercase tracking-wider text-white"
            @click="openQuiz(quiz)"
          >
            Start Quiz
            <ArrowRight class="h-4 w-4" />
          </button>
        </div>
      </article>
    </div>
  </div>
</template>
