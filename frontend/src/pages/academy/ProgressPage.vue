<script setup lang="ts">
import { onMounted, computed, ref } from 'vue';
import { useCoursesStore } from '@/stores/academy/courses';
import { useProgressStore } from '@/stores/academy/progress';
import { useDashboardStore } from '@/stores/academy/dashboard';
import { quizzesService } from '@/services/academy/quizzesService';
import Badge from '@/components/data-display/badge/Badge.vue';
import LmsProgressBar from '@/components/data-display/progress/LmsProgressBar.vue';
import Breadcrumbs from '@/components/navigation/breadcrumbs/Breadcrumbs.vue';

const coursesStore = useCoursesStore();
const progressStore = useProgressStore();
const dashboardStore = useDashboardStore();
const quizAttempts = ref<Array<{
  id: number;
  quizTitle: string;
  courseTitle: string;
  score: number;
  passed: boolean;
  submitted_at?: string;
  created_at?: string;
}>>([]);

onMounted(async () => {
  await coursesStore.fetchCourses();
  await dashboardStore.fetchDashboardData();
  try {
    const attempts = await quizzesService.getAttempts();
    quizAttempts.value = attempts.map((attempt) => ({
      id: attempt.id,
      quizTitle: attempt.quiz?.title || `Quiz #${attempt.quiz_id}`,
      courseTitle: attempt.quiz?.course_title || 'Course',
      score: attempt.score,
      passed: attempt.passed,
      submitted_at: attempt.submitted_at || undefined,
      created_at: attempt.created_at,
    }));
  } catch (err) {
    console.error('Failed to load quiz attempts', err);
  }
});

const enrolledCoursesDetails = computed(() => {
  return coursesStore.courses.filter(c => {
    return progressStore.courseProgress[c.id] !== undefined;
  }).map(c => {
    const progress = progressStore.courseProgress[c.id] || 0;
    const completedLessonsCount = progressStore.completedLessonsForCourse(c.id);
    const totalLessonsCount = (c.modules || []).reduce(
      (sum, mod) => sum + (mod.lessons?.length || 0),
      0
    );

    return {
      ...c,
      progress,
      completedLessonsCount,
      totalLessonsCount
    };
  });
});

const quizAttemptsHistory = computed(() => quizAttempts.value);

// Learning Path simulation: Dawah Specialist Program (needs 3 courses)
const pathProgress = computed(() => {
  const totalCourses = 3;
  let completedCount = 0;
  
  coursesStore.courses.forEach(c => {
    if ((progressStore.courseProgress[c.id] || 0) >= 100) {
      completedCount++;
    }
  });

  return Math.round((completedCount / totalCourses) * 100);
});

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
};
</script>

<template>
  <div class="space-y-8 pb-16">
    <Breadcrumbs :items="[{ id: 'progress', label: 'Progress' }]" />
    <!-- Header banner -->
    <div>
      <h1 class="text-3xl font-display font-bold text-primary tracking-tight">Academic Progress</h1>
      <p class="text-neutral-muted text-sm mt-1 font-light">
        A detailed summary of your course progression, assessment grades, and milestones.
      </p>
    </div>

    <!-- Core learning path progress -->
    <div class="bg-white border border-neutral-ivory p-6 sm:p-8 rounded-3xl shadow-soft space-y-4">
      <div>
        <span class="text-[9px] font-mono font-bold uppercase tracking-wider text-neutral-muted">Learning Path</span>
        <h2 class="text-xl font-display font-semibold text-primary">Dawah Specialist Program</h2>
        <p class="text-xs text-neutral-muted mt-1 font-light">
          Complete "Introduction to Dawah", "Islamic Aqeedah", and "Comparative Religion" to graduate.
        </p>
      </div>

      <div>
        <div class="flex justify-between items-center text-[10px] mb-1 font-semibold text-neutral-muted font-mono">
          <span>Path Completion</span>
          <span class="text-primary font-bold">{{ pathProgress }}%</span>
        </div>
        <LmsProgressBar :value="pathProgress" color="bg-primary" />
      </div>

      <!-- Syllabus checklists -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-neutral-ivory/50">
        <div
          v-for="c in coursesStore.courses"
          :key="c.id"
          class="flex items-center gap-3 p-3 bg-neutral-background/60 rounded-2xl border border-neutral-ivory/70 text-xs font-semibold"
        >
          <div
            :class="[
              'h-5 w-5 rounded-full flex items-center justify-center flex-shrink-0',
              (progressStore.courseProgress[c.id] || 0) >= 100
                ? 'bg-success text-white'
                : progressStore.courseProgress[c.id] !== undefined
                  ? 'border-2 border-primary text-primary'
                  : 'bg-neutral-ivory text-neutral-muted'
            ]"
          >
            <svg v-if="(progressStore.courseProgress[c.id] || 0) >= 100" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
            <span v-else-if="progressStore.courseProgress[c.id] !== undefined" class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse"></span>
            <span v-else class="h-1.5 w-1.5 rounded-full bg-neutral-muted"></span>
          </div>
          <span class="truncate text-neutral-black/85">{{ c.title }}</span>
        </div>
      </div>
    </div>

    <!-- Enrolled Courses Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Enrolled programs list -->
      <div class="lg:col-span-2 space-y-6">
        <h2 class="text-xl font-display font-semibold text-primary">Enrolled Courses</h2>
        
        <div class="space-y-4">
          <div
            v-for="c in enrolledCoursesDetails"
            :key="c.id"
            class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-4"
          >
            <div class="flex justify-between items-start gap-4">
              <div>
                <h3 class="font-display font-semibold text-primary text-base leading-snug">{{ c.title }}</h3>
                <span class="text-[10px] text-neutral-muted font-mono mt-0.5 inline-block">
                  Lessons: {{ c.completedLessonsCount }} completed of {{ c.totalLessonsCount }}
                </span>
              </div>
              
              <Badge :variant="c.progress >= 100 ? 'success' : 'primary'" size="sm">
                {{ c.progress >= 100 ? 'Completed' : 'Active' }}
              </Badge>
            </div>

            <div>
              <div class="flex justify-between items-center text-[9px] mb-1 font-semibold text-neutral-muted font-mono">
                <span>Course Progress</span>
                <span class="text-primary font-bold">{{ c.progress }}%</span>
              </div>
              <LmsProgressBar :value="c.progress" color="bg-secondary" />
            </div>

            <div class="flex justify-end pt-2 border-t border-neutral-ivory/30">
              <router-link
                :to="`/academy/courses/${c.slug}`"
                class="text-xs font-semibold text-primary hover:underline flex items-center gap-1"
              >
                Go to Syllabus
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
              </router-link>
            </div>
          </div>

          <div v-if="enrolledCoursesDetails.length === 0" class="bg-white border border-neutral-ivory rounded-3xl p-12 text-center shadow-soft">
            <svg class="mx-auto h-12 w-12 text-neutral-gray/60 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <h3 class="text-base font-display font-semibold text-primary">No Active Courses Enrolled</h3>
            <p class="text-neutral-muted text-xs mt-1 max-w-sm mx-auto font-light leading-relaxed">
              Browse our catalog of programs and enroll in one to start tracking your achievements.
            </p>
            <router-link to="/academy/courses" class="mt-4 inline-block bg-primary text-white font-bold text-xs px-5 py-2 rounded-xl">
              Browse Courses
            </router-link>
          </div>
        </div>
      </div>

      <!-- Quiz score histories -->
      <div class="space-y-6">
        <h2 class="text-xl font-display font-semibold text-primary">Quiz Performance</h2>

        <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-4">
          <div class="divide-y divide-neutral-ivory/50">
            <div
              v-for="att in quizAttemptsHistory"
              :key="att.id"
              class="py-3 text-xs"
            >
              <div class="flex justify-between items-start gap-4">
                <div class="min-w-0">
                  <h4 class="font-semibold text-neutral-black truncate leading-tight">{{ att.quizTitle }}</h4>
                  <span class="text-[9px] text-neutral-muted mt-0.5 block truncate">{{ att.courseTitle }}</span>
                </div>
                
                <div class="text-right flex-shrink-0">
                  <span class="font-bold font-mono text-primary block text-sm">{{ att.score }}%</span>
                  <Badge :variant="att.passed ? 'success' : 'error'" size="sm" class="mt-1">
                    {{ att.passed ? 'Passed' : 'Failed' }}
                  </Badge>
                </div>
              </div>
              <span class="text-[8px] font-mono text-neutral-muted/70 block mt-2">
                Date: {{ formatDate(att.submitted_at || att.created_at || '') }}
              </span>
            </div>

            <div v-if="quizAttemptsHistory.length === 0" class="text-center py-8 text-xs text-neutral-muted italic font-light">
              No quiz attempts logged.
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>
