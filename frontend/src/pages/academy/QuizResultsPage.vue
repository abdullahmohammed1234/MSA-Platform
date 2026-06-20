<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCoursesStore } from '@/stores/academy/courses';
import { useQuizStore } from '@/stores/academy/quiz';
import { useToastStore } from '@/components/feedback/toast';
import LmsCircularProgress from '@/components/data-display/progress/LmsCircularProgress.vue';
import Badge from '@/components/data-display/badge/Badge.vue';
import QuizReview from '@/components/lms/QuizReview.vue';
import type { QuizAttempt } from '@/types/academy';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const coursesStore = useCoursesStore();
const quizStore = useQuizStore();

const courseId = computed(() => Number(route.params.courseId));
const quizId = computed(() => Number(route.params.quizId));

const attempt = ref<QuizAttempt | null>(null);
const pastAttempts = ref<QuizAttempt[]>([]);

onMounted(async () => {
  await coursesStore.fetchCourseDetails(courseId.value);

  if (quizStore.currentAttempt) {
    attempt.value = quizStore.currentAttempt;
    pastAttempts.value = [quizStore.currentAttempt];
  }

  if (!attempt.value) {
    toast.error('No quiz attempts found.');
    router.push(`/academy/courses/${courseId.value}`);
  }
});

const quiz = computed(() => {
  return coursesStore.currentCourse?.quizzes?.find(q => q.id === quizId.value);
});

const correctAnswersCount = computed(() => {
  if (!attempt.value?.answers) return 0;
  return attempt.value.answers.filter(a => a.is_correct).length;
});

const totalQuestionsCount = computed(() => {
  return quiz.value?.questions?.length || 4;
});

const isRetakeAllowed = computed(() => {
  if (!quiz.value) return false;
  if (!quiz.value.attempt_limit) return true; // unlimited
  return pastAttempts.value.length < quiz.value.attempt_limit;
});

const handleRetake = () => {
  if (!quiz.value) return;
  quizStore.cleanQuizState();
  router.push(`/academy/courses/${courseId.value}/quizzes/${quizId.value}`);
};

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};
</script>

<template>
  <div v-if="!coursesStore.currentCourse || !attempt" class="py-20 text-center text-neutral-muted">
    Loading attempt results...
  </div>
  
  <div v-else class="max-w-3xl mx-auto space-y-8 pb-16">
    <!-- Header results card -->
    <div class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-6">
      <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        
        <!-- Score Circular Progress & Status -->
        <div class="flex items-center gap-6">
          <LmsCircularProgress
            :value="attempt.score"
            :size="90"
            :strokeWidth="7"
            :color="attempt.passed ? 'text-success' : 'text-secondary'"
          />
          <div>
            <span class="text-[10px] font-mono font-bold uppercase tracking-wider text-neutral-muted">
              Quiz Result
            </span>
            <h1 :class="['text-2xl font-display font-bold mt-0.5', attempt.passed ? 'text-success' : 'text-secondary']">
              {{ attempt.passed ? 'Assessment Passed' : 'Assessment Failed' }}
            </h1>
            <p class="text-xs text-neutral-muted mt-1 leading-relaxed max-w-xs font-light">
              {{ attempt.passed ? 'Congratulations! You met the passing requirements for this program.' : 'You did not meet the passing grade. Review your notes and try again!' }}
            </p>
          </div>
        </div>

        <div class="flex flex-wrap gap-3">
          <router-link
            v-if="attempt.passed"
            to="/academy/badges"
            class="bg-accent-gold hover:bg-accent-gold/90 text-primary font-bold text-xs px-5 py-2.5 rounded-xl shadow-soft flex items-center gap-1.5"
          >
            View Unlocked Badges
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v5m-3-3.04h6m-3-9.96A4.98 4.98 0 008 12c0 2.5 2 4.5 4 4.5s4-2 4-4.5a4.98 4.98 0 00-4-4.96zM12 2a5 5 0 015 5c0 2.5-3 5-5 5S7 9.5 7 7a5 5 0 015-5z" />
            </svg>
          </router-link>

          <button
            v-if="!attempt.passed && isRetakeAllowed"
            @click="handleRetake"
            class="bg-primary hover:bg-primary/95 text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-soft cursor-pointer flex items-center gap-1.5"
          >
            Retake Quiz
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8.89M9 11l3 3L22 4" />
            </svg>
          </button>
        </div>

      </div>

      <!-- Quick Metrics Summary -->
      <div class="grid grid-cols-3 gap-4 pt-6 border-t border-neutral-ivory/50 text-center font-mono">
        <div>
          <span class="text-[9px] text-neutral-muted font-bold uppercase tracking-wider block">Score</span>
          <span class="text-lg font-bold text-primary">{{ attempt.score }}%</span>
        </div>
        <div>
          <span class="text-[9px] text-neutral-muted font-bold uppercase tracking-wider block">Accuracy</span>
          <span class="text-lg font-bold text-primary">{{ correctAnswersCount }} / {{ totalQuestionsCount }}</span>
        </div>
        <div>
          <span class="text-[9px] text-neutral-muted font-bold uppercase tracking-wider block">Passing score</span>
          <span class="text-lg font-bold text-primary">{{ quiz?.passing_score || 75 }}%</span>
        </div>
      </div>
    </div>

    <QuizReview v-if="attempt.answers?.length" :quiz="quiz" :attempt="attempt" />

    <!-- Attempt History Section -->
    <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-4">
      <h3 class="text-base font-display font-semibold text-primary border-b border-neutral-ivory pb-3">Attempt History</h3>
      <div class="divide-y divide-neutral-ivory/50">
        <div
          v-for="(past, idx) in pastAttempts"
          :key="past.id"
          class="flex items-center justify-between py-3 text-xs"
        >
          <div class="space-y-0.5">
            <span class="font-bold text-neutral-black">Attempt {{ pastAttempts.length - idx }}</span>
            <span class="text-[10px] text-neutral-muted block">{{ formatDate(past.submitted_at || past.created_at) }}</span>
          </div>

          <div class="flex items-center gap-3">
            <Badge :variant="past.passed ? 'success' : 'outline'" size="sm">
              {{ past.passed ? 'Pass' : 'Fail' }}
            </Badge>
            <span class="font-bold font-mono text-primary w-10 text-right">{{ past.score }}%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Return link -->
    <div class="flex justify-center pt-4">
      <router-link
        :to="`/academy/courses/${coursesStore.currentCourse.slug}`"
        class="text-sm font-semibold text-primary hover:underline flex items-center gap-1"
      >
        Return to Course Dashboard
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
        </svg>
      </router-link>
    </div>
  </div>
</template>
