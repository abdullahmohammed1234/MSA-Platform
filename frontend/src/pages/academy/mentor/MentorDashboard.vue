<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { useMentorStore } from '@/stores/academy/mentor';
import { Motion } from '@motionone/vue';

const mentorStore = useMentorStore();

onMounted(() => {
  if (!mentorStore.isLoaded) {
    mentorStore.fetchState();
  }
});

const pendingSubmissionsCount = computed(() => {
  return mentorStore.submissions.filter(s => s.status === 'needs_review').length;
});

const examAverage = computed(() => {
  const exams = mentorStore.quizAnalytics;
  if (!exams.length) return 0;
  const total = exams.reduce((sum, exam) => sum + exam.averageScore, 0);
  return Math.round(total / exams.length);
});
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Mentor Studio</h1>
        <p class="text-neutral-muted text-sm mt-1 font-light">
          Supervise your assigned courses, coach learners, correct submissions, and check exams.
        </p>
      </div>
      <div class="bg-primary/10 border border-primary/20 rounded-full px-4 py-1.5 flex items-center gap-2">
        <span class="h-2.5 w-2.5 rounded-full bg-primary animate-pulse"></span>
        <span class="text-xs font-semibold text-primary uppercase tracking-widest font-mono">Mentor Session</span>
      </div>
    </div>

    <div v-if="mentorStore.isLoading" class="flex justify-center items-center py-20">
      <div class="h-8 w-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else class="space-y-6">
      <!-- Stat Cards -->
      <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <!-- Stat Card 1 -->
        <Motion
          :initial="{ opacity: 0, y: 15 }"
          :animate="{ opacity: 1, y: 0 }"
          class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft flex items-center justify-between gap-4"
        >
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Supervised Courses</p>
            <p class="mt-2 text-2xl font-black text-primary">{{ mentorStore.courses.length }}</p>
          </div>
          <div class="rounded-2xl bg-primary/10 p-3 text-primary">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
          </div>
        </Motion>

        <!-- Stat Card 2 -->
        <Motion
          :initial="{ opacity: 0, y: 15 }"
          :animate="{ opacity: 1, y: 0 }"
          :transition="{ delay: 0.1 }"
          class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft flex items-center justify-between gap-4"
        >
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Open Corrections</p>
            <p class="mt-2 text-2xl font-black text-primary">{{ pendingSubmissionsCount }}</p>
          </div>
          <div class="rounded-2xl bg-primary/10 p-3 text-primary">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
          </div>
        </Motion>

        <!-- Stat Card 3 -->
        <Motion
          :initial="{ opacity: 0, y: 15 }"
          :animate="{ opacity: 1, y: 0 }"
          :transition="{ delay: 0.2 }"
          class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft flex items-center justify-between gap-4"
        >
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Assigned Learners</p>
            <p class="mt-2 text-2xl font-black text-primary">{{ mentorStore.volunteers.length }}</p>
          </div>
          <div class="rounded-2xl bg-primary/10 p-3 text-primary">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
        </Motion>

        <!-- Stat Card 4 -->
        <Motion
          :initial="{ opacity: 0, y: 15 }"
          :animate="{ opacity: 1, y: 0 }"
          :transition="{ delay: 0.3 }"
          class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft flex items-center justify-between gap-4"
        >
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Exam Average</p>
            <p class="mt-2 text-2xl font-black text-primary">{{ examAverage }}%</p>
          </div>
          <div class="rounded-2xl bg-primary/10 p-3 text-primary">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        </Motion>
      </div>

      <!-- Two-Column Priority Board & SNAPSHOT -->
      <div class="grid gap-6 xl:grid-cols-2 mt-8">
        <!-- Priorities Board -->
        <div class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-6">
          <h2 class="text-xl font-display font-bold text-primary">Today’s Mentor Priorities</h2>
          <div class="space-y-4">
            <div class="flex items-center gap-3.5 rounded-2xl border border-neutral-ivory bg-neutral-background p-4">
              <svg class="h-5 w-5 text-secondary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="text-sm font-semibold text-neutral-black">
                {{ pendingSubmissionsCount }} learner submissions need correction
              </span>
            </div>
            
            <div class="flex items-center gap-3.5 rounded-2xl border border-neutral-ivory bg-neutral-background p-4">
              <svg class="h-5 w-5 text-secondary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="text-sm font-semibold text-neutral-black">
                0 certificate portfolios need mentor endorsement
              </span>
            </div>

            <div class="flex items-center gap-3.5 rounded-2xl border border-neutral-ivory bg-neutral-background p-4">
              <svg class="h-5 w-5 text-secondary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="text-sm font-semibold text-neutral-black">
                0 discussions flagged for moderation review
              </span>
            </div>
          </div>
        </div>

        <!-- Course Snapshot -->
        <div class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-6">
          <h2 class="text-xl font-display font-bold text-primary">Course Health Snapshot</h2>
          <div class="space-y-4">
            <div 
              v-for="course in mentorStore.courses" 
              :key="course.id" 
              class="rounded-2xl border border-neutral-ivory p-4 flex justify-between items-center"
            >
              <div>
                <p class="text-sm font-bold text-neutral-black">{{ course.title }}</p>
                <p class="text-xs text-neutral-muted mt-0.5">{{ course.enrollmentCount }} learners enrolled</p>
              </div>
              <span class="text-xs font-bold px-3 py-1 bg-green-500/10 border border-green-500/20 text-green-700 rounded-full">
                {{ course.completionRate }}% complete
              </span>
            </div>
            
            <p v-if="!mentorStore.courses.length" class="text-center text-sm text-neutral-muted py-8">
              Assigned courses will appear here.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
