<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { BookOpen, CheckCircle, Clock, GraduationCap, LineChart } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';
import { useProgressStore } from '@/stores/academy/progress';
import { coursesService } from '@/services/academy/coursesService';
import { quizzesService } from '@/services/academy/quizzesService';
import type { Course } from '@/types/academy';
import Breadcrumbs from '@/components/navigation/breadcrumbs/Breadcrumbs.vue';

interface Attempt {
  id: string;
  quizId: string;
  score: number;
  passed: boolean;
  submittedAt: string;
  quiz?: {
    title: string;
  };
}

const authStore = useAuthStore();
const progressStore = useProgressStore();

const courses = ref<Course[]>([]);
const attempts = ref<Attempt[]>([]);
const loadingAttempts = ref(true);

const user = computed(() => authStore.user);

const initials = (name: string) => {
  return name
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
};

const stats = computed(() => {
  // Enrolled courses from progressStore.courseProgress keys
  const enrolledIds = Object.keys(progressStore.courseProgress).map(Number);
  const enrolledCourses = enrolledIds.length;
  
  // Completed courses count (where progress = 100)
  const completedCourses = enrolledIds.filter(
    (id) => (progressStore.courseProgress[id] || 0) >= 100
  ).length;

  const completedLessons = progressStore.completedLessons.length;

  // Quiz average
  const quizAverage = attempts.value.length
    ? Math.round(attempts.value.reduce((sum, item) => sum + item.score, 0) / attempts.value.length)
    : 0;

  return {
    enrolledCourses,
    completedLessons,
    completedCourses,
    quizAverage
  };
});

const enrolledCoursesList = computed(() => {
  const enrolledIds = Object.keys(progressStore.courseProgress).map(Number);
  return courses.value.filter((course) => enrolledIds.includes(course.id));
});

onMounted(async () => {
  try {
    courses.value = await coursesService.getCourses();
  } catch (err) {
    console.error('Failed to load courses', err);
  }

  try {
    const apiAttempts = await quizzesService.getAttempts();
    attempts.value = apiAttempts.map((item) => ({
      id: String(item.id),
      quizId: String(item.quiz_id),
      score: item.score,
      passed: item.passed,
      submittedAt: item.submitted_at || item.started_at,
      quiz: item.quiz ? { title: item.quiz.title } : undefined,
    }));
  } catch (err) {
    console.error('Failed to load quiz attempts', err);
  } finally {
    loadingAttempts.value = false;
  }
});
</script>

<template>
  <div v-if="user" class="space-y-8 max-w-7xl mx-auto font-sans text-left">
    <Breadcrumbs :items="[{ id: 'profile', label: 'Profile' }]" />
    <!-- User Profile Header Card -->
    <div class="rounded-3xl border border-neutral-ivory bg-white p-8 shadow-premium">
      <div class="flex flex-col md:flex-row md:items-center gap-6">
        <div class="h-24 w-24 rounded-full bg-primary flex items-center justify-center text-white text-3xl font-black ring-4 ring-primary/10 shrink-0">
          {{ initials(user.name) }}
        </div>
        <div class="space-y-2 flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-3xl font-display font-black text-primary truncate">{{ user.name }}</h2>
            <span class="rounded-xl border border-primary/10 bg-primary/5 px-3 py-1 text-[10px] font-black uppercase text-primary tracking-wider font-mono">
              {{ user.roles?.[0] || 'volunteer' }}
            </span>
          </div>
          <p class="text-sm font-semibold text-neutral-muted">{{ user.email }}</p>
          <p class="max-w-2xl text-xs text-neutral-muted leading-relaxed font-light">
            SFU Muslim Students Association Learning Portal · Intention sanctuary verified.
          </p>
        </div>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div
        v-for="item in [
          { label: 'Enrolled Courses', value: stats.enrolledCourses, icon: BookOpen },
          { label: 'Completed Lessons', value: stats.completedLessons, icon: CheckCircle },
          { label: 'Completed Courses', value: stats.completedCourses, icon: GraduationCap },
          { label: 'Quiz Average', value: `${stats.quizAverage}%`, icon: LineChart },
        ]"
        :key="item.label"
        class="rounded-2xl border border-neutral-ivory bg-[#fffdfa] p-6 shadow-soft hover:shadow-premium transition-all duration-300"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="space-y-1">
            <p class="text-[10px] font-bold uppercase tracking-widest text-neutral-muted font-mono leading-none">{{ item.label }}</p>
            <p class="text-3xl font-black text-primary mt-2">{{ item.value }}</p>
          </div>
          <div class="rounded-xl bg-white p-3 text-primary border border-neutral-ivory shadow-soft">
            <component :is="item.icon" class="h-5 w-5" />
          </div>
        </div>
      </div>
    </div>

    <!-- Details Columns -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Live Enrollments -->
      <div class="rounded-3xl border border-neutral-ivory bg-white p-6 shadow-premium space-y-5">
        <h3 class="text-lg font-display font-bold text-primary flex items-center gap-2 border-b border-neutral-ivory/50 pb-3">
          <BookOpen class="h-5 w-5" />
          Live Enrollments
        </h3>
        
        <div class="space-y-4">
          <p v-if="enrolledCoursesList.length === 0" class="text-xs font-semibold text-neutral-muted italic">
            No course enrollments have been recorded yet.
          </p>
          <div
            v-else
            v-for="course in enrolledCoursesList"
            :key="course.id"
            class="rounded-2xl border border-neutral-ivory bg-[#fffdfa]/50 p-4 hover:border-primary transition-colors flex items-center justify-between"
          >
            <div class="space-y-0.5">
              <p class="text-xs font-bold text-neutral-black">{{ course.title }}</p>
              <p class="text-[10px] text-neutral-muted font-mono uppercase">{{ course.difficulty }} • {{ course.estimated_duration ? `${course.estimated_duration} mins` : 'Self-paced' }}</p>
            </div>
            <span class="text-xs font-mono font-bold text-primary bg-primary/5 px-2.5 py-1 rounded-lg">
              {{ progressStore.courseProgress[course.id] || 0 }}%
            </span>
          </div>
        </div>
      </div>

      <!-- Recent Quiz Activity -->
      <div class="rounded-3xl border border-neutral-ivory bg-white p-6 shadow-premium space-y-5">
        <h3 class="text-lg font-display font-bold text-primary flex items-center gap-2 border-b border-neutral-ivory/50 pb-3">
          <Clock class="h-5 w-5" />
          Recent Quiz Activity
        </h3>
        
        <div class="space-y-4">
          <p v-if="attempts.length === 0" class="text-xs font-semibold text-neutral-muted italic">
            No quiz attempts have been recorded yet.
          </p>
          <div
            v-else
            v-for="attempt in attempts.slice(0, 8)"
            :key="attempt.id"
            class="flex items-center justify-between rounded-2xl border border-neutral-ivory bg-[#fffdfa]/50 p-4"
          >
            <div class="space-y-0.5">
              <p class="text-xs font-bold text-neutral-black">{{ attempt.quiz?.title || attempt.quizId }}</p>
              <p class="text-[10px] text-neutral-muted font-mono">
                {{ new Date(attempt.submittedAt).toLocaleString() }}
              </p>
            </div>
            <span
              class="text-xs font-black font-mono px-2.5 py-1 rounded-lg border"
              :class="attempt.passed ? 'text-green-650 bg-green-50 border-green-100' : 'text-red-650 bg-red-50 border-red-100'"
            >
              {{ attempt.score }}%
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
