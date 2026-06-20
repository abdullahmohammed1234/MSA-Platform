<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useMentorStore } from '@/stores/academy/mentor';
import Button from '@/components/ui/button/Button.vue';
import { Motion } from '@motionone/vue';

const mentorStore = useMentorStore();

const examTitle = ref('');
const examCourseId = ref('');
const examPassingScore = ref(75);

onMounted(() => {
  if (!mentorStore.isLoaded) {
    mentorStore.fetchState();
  }
});

const handleCreateExam = (e: Event) => {
  e.preventDefault();
  if (!examTitle.value.trim()) return;

  const targetCourse = mentorStore.courses.find(c => String(c.id) === String(examCourseId.value));
  const courseTitle = targetCourse ? targetCourse.title : 'Unassigned Course';

  const newExam = {
    id: Date.now(),
    title: examTitle.value.trim(),
    category: 'Mentor Draft',
    courseTitle,
    passingScore: examPassingScore.value,
    timeLimit: 30,
    totalAttempts: 0,
    uniqueVolunteers: 0,
    averageScore: 0,
    passRate: 0,
    status: 'Draft' as const,
    questionsAnalysis: []
  };

  mentorStore.saveExamDraft(newExam);
  examTitle.value = '';
  examCourseId.value = '';
  examPassingScore.value = 75;
};
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Exam Builder</h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        Track exam performance and draft new assessments for your supervised courses.
      </p>
    </div>

    <div v-if="mentorStore.isLoading" class="flex justify-center items-center py-20">
      <div class="h-8 w-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Exam Cards (Left 2 Columns) -->
      <div class="lg:col-span-2 space-y-6">
        <Motion
          v-for="quiz in mentorStore.quizAnalytics"
          :key="quiz.id"
          :initial="{ opacity: 0, y: 15 }"
          :animate="{ opacity: 1, y: 0 }"
          class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-6"
        >
          <div>
            <h2 class="text-lg font-display font-bold text-primary">{{ quiz.title }}</h2>
            <p class="text-xs text-neutral-muted mt-1">{{ quiz.courseTitle }} &bull; {{ quiz.category }}</p>
          </div>

          <!-- Exam Stats Grid -->
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-6 border-t border-neutral-ivory pt-6">
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Status</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ quiz.status }}</p>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Attempts</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ quiz.totalAttempts }}</p>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Learners</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ quiz.uniqueVolunteers }}</p>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Average</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ quiz.averageScore }}%</p>
            </div>
            <div>
              <p class="text-[9px] font-bold uppercase tracking-wider text-neutral-muted">Pass Rate</p>
              <p class="mt-1 text-sm font-semibold text-neutral-black">{{ quiz.passRate }}%</p>
            </div>
          </div>
        </Motion>
      </div>

      <!-- Exam Drafting Form (Right Column) -->
      <div>
        <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-6 sticky top-24">
          <h2 class="text-lg font-display font-bold text-primary flex items-center gap-2">
            <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create Exam Draft
          </h2>

          <form @submit="handleCreateExam" class="space-y-4">
            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Exam Title</label>
              <input
                v-model="examTitle"
                required
                placeholder="e.g. Intermediate Outreach Quiz"
                class="w-full bg-neutral-ivory/30 border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold"
              />
            </div>

            <div class="space-y-1">
              <label class="text-xs font-bold text-neutral-muted uppercase tracking-wider">Supervised Course</label>
              <select
                v-model="examCourseId"
                required
                class="w-full bg-neutral-ivory/30 border border-neutral-gray/20 rounded-xl py-3 px-4 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 text-neutral-black font-semibold"
              >
                <option value="" disabled selected>Select course</option>
                <option 
                  v-for="course in mentorStore.courses" 
                  :key="course.id" 
                  :value="course.id"
                >
                  {{ course.title }}
                </option>
              </select>
            </div>

            <div class="space-y-2">
              <div class="flex justify-between text-xs">
                <span class="font-bold text-neutral-muted uppercase tracking-wider">Passing Score</span>
                <span class="font-bold text-primary">{{ examPassingScore }}%</span>
              </div>
              <input
                type="range"
                min="50"
                max="100"
                v-model.number="examPassingScore"
                class="w-full accent-primary cursor-pointer"
              />
            </div>

            <Button type="submit" size="lg" class="w-full bg-primary hover:bg-primary-dark text-white rounded-xl">
              Save Exam Draft
            </Button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
