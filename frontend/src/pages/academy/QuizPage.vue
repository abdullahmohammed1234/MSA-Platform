<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Motion } from '@motionone/vue';
import { useCoursesStore } from '@/stores/academy/courses';
import { useQuizStore } from '@/stores/academy/quiz';
import { useToastStore } from '@/components/feedback/toast';
import QuestionRenderer from '@/components/academy/QuestionRenderer.vue';
import { buttonHover } from '@/design-system/animations/hover';
import { academyAnalytics } from '@/utils/analytics';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const coursesStore = useCoursesStore();
const quizStore = useQuizStore();

const courseId = computed(() => Number(route.params.courseId));
const quizId = computed(() => Number(route.params.quizId));

const quizStarted = ref(false);

const targetQuiz = computed(() =>
  coursesStore.currentCourse?.quizzes?.find((q) => q.id === quizId.value) || null
);

onMounted(async () => {
  await coursesStore.fetchCourseDetails(courseId.value);
  quizStore.cleanQuizState();

  if (!targetQuiz.value) {
    toast.error('Quiz not found.');
    router.push(`/academy/courses/${courseId.value}`);
  }
});

const formattedTime = computed(() => {
  if (quizStore.timeRemaining === null) return '';
  const mins = Math.floor(quizStore.timeRemaining / 60);
  const secs = quizStore.timeRemaining % 60;
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const isQuestionAnswered = (idx: number): boolean => {
  if (!targetQuiz.value?.questions) return false;
  const qId = targetQuiz.value.questions[idx].id;
  const ans = quizStore.selectedAnswers[qId];
  return ans && ans.length > 0 && ans[0] !== '';
};

const startAssessment = () => {
  if (!targetQuiz.value) return;
  quizStore.startQuiz(targetQuiz.value);
  quizStarted.value = true;
};

const handleAnswerUpdate = (val: string[]) => {
  const question = targetQuiz.value?.questions?.[quizStore.currentQuestionIndex];
  if (question) {
    quizStore.setAnswer(question.id, val);
  }
};

const activeQuestion = computed(() => {
  if (!targetQuiz.value?.questions) return null;
  return targetQuiz.value.questions[quizStore.currentQuestionIndex];
});

const handleSubmit = async () => {
  if (Object.keys(quizStore.selectedAnswers).length < quizStore.questionsCount) {
    const confirmSubmit = confirm('You have unanswered questions. Are you sure you want to submit?');
    if (!confirmSubmit) return;
  }

  try {
    const attempt = await quizStore.submitQuiz(courseId.value);

    academyAnalytics.trackQuizAttempt(courseId.value, quizId.value, attempt.score, attempt.passed);
    if (attempt.passed) {
      academyAnalytics.trackCourseCompletion(courseId.value, coursesStore.currentCourse?.title || '');
    }

    toast.success(attempt.passed ? 'Quiz passed!' : 'Quiz submitted.');
    router.push(`/academy/courses/${courseId.value}/quizzes/${quizId.value}/results`);
  } catch (err: any) {
    toast.error(err.message || 'Failed to submit quiz');
  }
};

const selectQuestionIndex = (idx: number) => {
  quizStore.currentQuestionIndex = idx;
};
</script>

<template>
  <div v-if="!coursesStore.currentCourse || !targetQuiz" class="py-20 text-center text-neutral-muted">
    Loading quiz assessments...
  </div>

  <div v-else class="max-w-3xl mx-auto space-y-8 pb-16">
    <router-link
      :to="`/academy/courses/${coursesStore.currentCourse.slug}`"
      class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
    >
      Cancel & Return to Course
    </router-link>

    <div v-if="!quizStarted" class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-6">
      <div>
        <span class="text-[10px] font-mono font-bold uppercase tracking-widest text-neutral-muted">Course Assessment</span>
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-primary mt-1">{{ targetQuiz.title }}</h1>
        <p class="text-neutral-muted text-sm mt-3 leading-relaxed font-light">
          {{ targetQuiz.description || 'Complete this grading assessment to demonstrate your understanding of the course syllabus and qualify for certificates.' }}
        </p>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-neutral-ivory/50 text-center">
        <div class="bg-neutral-background/60 p-4 border border-neutral-ivory rounded-2xl">
          <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted font-mono">Questions</p>
          <p class="text-xl font-display font-bold text-primary mt-1">{{ targetQuiz.questions?.length || 0 }}</p>
        </div>
        <div class="bg-neutral-background/60 p-4 border border-neutral-ivory rounded-2xl">
          <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted font-mono">Passing Grade</p>
          <p class="text-xl font-display font-bold text-primary mt-1">{{ targetQuiz.passing_score }}%</p>
        </div>
        <div class="bg-neutral-background/60 p-4 border border-neutral-ivory rounded-2xl">
          <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted font-mono">Time Limit</p>
          <p class="text-xl font-display font-bold text-primary mt-1">
            {{ targetQuiz.time_limit ? `${targetQuiz.time_limit}m` : 'Unlimited' }}
          </p>
        </div>
        <div class="bg-neutral-background/60 p-4 border border-neutral-ivory rounded-2xl">
          <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted font-mono">Attempts</p>
          <p class="text-xl font-display font-bold text-primary mt-1">
            {{ targetQuiz.attempt_limit || 'Unlimited' }}
          </p>
        </div>
      </div>

      <div class="pt-6 border-t border-neutral-ivory/50 flex justify-end">
        <Motion
          :hover="buttonHover.hover"
          :press="buttonHover.tap"
          :transition="buttonHover.transition"
          as="button"
          class="bg-primary hover:bg-primary/95 text-white font-bold px-8 py-3 rounded-2xl text-sm cursor-pointer shadow-soft"
          @click="startAssessment"
        >
          Start Assessment
        </Motion>
      </div>
    </div>

    <div v-else-if="activeQuestion" class="space-y-6">
      <div class="bg-white border border-neutral-ivory p-5 rounded-3xl shadow-soft flex items-center justify-between gap-4">
        <div>
          <h2 class="text-sm font-semibold text-primary truncate max-w-xs sm:max-w-md">{{ targetQuiz.title }}</h2>
          <p class="text-[10px] text-neutral-muted mt-0.5">
            Question {{ quizStore.currentQuestionIndex + 1 }} of {{ quizStore.questionsCount }}
          </p>
        </div>

        <div
          v-if="quizStore.timeRemaining !== null"
          class="flex items-center gap-2 bg-secondary/10 border border-secondary/15 text-secondary px-3 py-1.5 rounded-full font-mono text-xs font-bold"
        >
          {{ formattedTime }}
        </div>
      </div>

      <div class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft">
        <QuestionRenderer
          :question="activeQuestion"
          :modelValue="quizStore.getAnswer(activeQuestion.id)"
          @update:modelValue="handleAnswerUpdate"
        />

        <div class="flex items-center justify-between pt-8 mt-8 border-t border-neutral-ivory/50">
          <button
            type="button"
            :disabled="quizStore.currentQuestionIndex === 0"
            class="px-4 py-2 border border-neutral-ivory hover:bg-neutral-background/50 rounded-xl text-xs font-semibold text-primary disabled:opacity-50"
            @click="quizStore.prevQuestion()"
          >
            Previous
          </button>

          <div class="hidden sm:flex items-center gap-1.5">
            <button
              v-for="(_, idx) in quizStore.questionsCount"
              :key="idx"
              type="button"
              class="h-7 w-7 rounded-full text-[10px] font-mono font-bold flex items-center justify-center border transition-all"
              :class="idx === quizStore.currentQuestionIndex
                ? 'border-primary bg-primary text-white'
                : isQuestionAnswered(idx)
                  ? 'border-neutral-gray bg-neutral-ivory/50 text-primary'
                  : 'border-neutral-ivory bg-white text-neutral-muted'"
              @click="selectQuestionIndex(idx)"
            >
              {{ idx + 1 }}
            </button>
          </div>

          <Motion
            v-if="quizStore.currentQuestionIndex < quizStore.questionsCount - 1"
            :hover="buttonHover.hover"
            :press="buttonHover.tap"
            :transition="buttonHover.transition"
            as="button"
            class="bg-primary text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-soft"
            @click="quizStore.nextQuestion()"
          >
            Next Question
          </Motion>

          <Motion
            v-else
            :hover="buttonHover.hover"
            :press="buttonHover.tap"
            :transition="buttonHover.transition"
            as="button"
            class="bg-secondary text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-soft disabled:opacity-60"
            :disabled="quizStore.loading"
            @click="handleSubmit"
          >
            {{ quizStore.loading ? 'Submitting...' : 'Submit Assessment' }}
          </Motion>
        </div>
      </div>
    </div>
  </div>
</template>
