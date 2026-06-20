<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { Motion } from '@motionone/vue';
import { useDashboardStore } from '@/stores/academy/dashboard';
import { useProgressStore } from '@/stores/academy/progress';
import { useAuthStore } from '@/stores/auth';
import CourseCard from '@/components/academy/CourseCard.vue';
import AnimatedCounter from '@/components/shared/AnimatedCounter.vue';
import StreakTracker from '@/components/gamification/StreakTracker.vue';
import { buttonHover } from '@/design-system/animations/hover';

const router = useRouter();
const dashboardStore = useDashboardStore();
const progressStore = useProgressStore();
const authStore = useAuthStore();

const userName = computed(() => authStore.user?.name || 'Scholar Student');

onMounted(async () => {
  await dashboardStore.fetchDashboardData();
});

const handleResume = (_slug: string, lessonId: number) => {
  // Find which course matches this slug to navigate
  const courseId = dashboardStore.continueLearning?.courseId || 1;
  router.push(`/academy/courses/${courseId}/lessons/${lessonId}`);
};

const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header Greeting -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-bold text-primary tracking-tight">
          Welcome back, {{ userName }}
        </h1>
        <p class="text-neutral-muted text-sm mt-1 font-light">
          "He who treads a path in search of knowledge, Allah will make easy for him the path to Paradise."
        </p>
      </div>
      <div class="bg-primary/5 border border-primary/15 rounded-full px-4 py-1.5 flex items-center gap-2">
        <span class="h-2.5 w-2.5 rounded-full bg-accent-gold animate-pulse"></span>
        <span class="text-xs font-semibold text-primary uppercase tracking-widest font-mono">Academy Active</span>
      </div>
    </div>

    <!-- Main Grid Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Left 2 Columns: Stats & Learning -->
      <div class="lg:col-span-2 space-y-8">
        
        <!-- Continue Learning Panel -->
        <div v-if="dashboardStore.continueLearning" class="bg-gradient-to-r from-primary to-primary-dark rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden shadow-premium">
          <div class="absolute right-0 bottom-0 opacity-10 pattern-islamic w-48 h-48 pointer-events-none"></div>
          
          <div class="relative z-10 flex flex-col justify-between h-full space-y-6">
            <div>
              <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-accent-gold font-mono">
                Continue Learning
              </span>
              <h2 class="text-2xl font-display font-semibold text-white mt-1">
                {{ dashboardStore.continueLearning.courseTitle }}
              </h2>
              <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-white/80">
                <span class="font-medium text-white">{{ dashboardStore.continueLearning.moduleTitle }}</span>
                <span class="opacity-40">•</span>
                <span>Next up: {{ dashboardStore.continueLearning.lessonTitle }}</span>
              </div>
            </div>
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-4 border-t border-white/10">
              <div class="w-full sm:w-1/2">
                <div class="flex justify-between items-center text-xs text-white/70 mb-1">
                  <span>Course Completed</span>
                  <span class="font-semibold text-white">{{ dashboardStore.continueLearning.progress }}%</span>
                </div>
                <div class="h-2 bg-white/20 rounded-full overflow-hidden">
                  <div class="h-full bg-accent-gold rounded-full" :style="{ width: `${dashboardStore.continueLearning.progress}%` }"></div>
                </div>
              </div>
              
              <Motion
                :hover="buttonHover.hover"
                :press="buttonHover.tap"
                :transition="buttonHover.transition"
                as="button"
                class="bg-accent-gold hover:bg-accent-gold/90 text-primary font-bold text-sm px-6 py-2.5 rounded-xl flex items-center gap-2 cursor-pointer shadow-soft"
                @click="handleResume(dashboardStore.continueLearning.courseSlug, dashboardStore.continueLearning.lessonId)"
              >
                Resume Lesson
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                </svg>
              </Motion>
            </div>
          </div>
        </div>

        <!-- 4-Stat Reusable Metrics Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
          <!-- Courses Enrolled -->
          <div class="bg-white border border-neutral-ivory p-5 rounded-2xl shadow-soft">
            <h3 class="text-[9px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1 font-mono">Enrolled</h3>
            <div class="text-3xl font-display font-semibold text-primary">
              <AnimatedCounter :value="dashboardStore.stats.coursesEnrolled" />
            </div>
          </div>

          <!-- Courses Completed -->
          <div class="bg-white border border-neutral-ivory p-5 rounded-2xl shadow-soft">
            <h3 class="text-[9px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1 font-mono">Completed</h3>
            <div class="text-3xl font-display font-semibold text-primary">
              <AnimatedCounter :value="dashboardStore.stats.coursesCompleted" />
            </div>
          </div>

          <!-- Badges Earned -->
          <div class="bg-white border border-neutral-ivory p-5 rounded-2xl shadow-soft">
            <h3 class="text-[9px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1 font-mono">Badges</h3>
            <div class="text-3xl font-display font-semibold text-primary">
              <AnimatedCounter :value="dashboardStore.stats.badgesUnlocked" />
            </div>
          </div>

          <!-- Overall Progress -->
          <div class="bg-white border border-neutral-ivory p-5 rounded-2xl shadow-soft">
            <h3 class="text-[9px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1 font-mono">Avg Progress</h3>
            <div class="text-3xl font-display font-semibold text-primary">
              <AnimatedCounter :value="dashboardStore.stats.overallProgress" />%
            </div>
          </div>
        </div>

        <!-- Recommended Courses Section -->
        <div class="space-y-4">
          <h2 class="text-xl font-display font-semibold text-primary">
            Recommended Programs
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="course in dashboardStore.recommendedCourses" :key="course.id">
              <CourseCard
                :course="course"
                :enrolled="progressStore.courseProgress[course.id] !== undefined"
                :progress="progressStore.courseProgress[course.id] || 0"
              />
            </div>
            <div v-if="dashboardStore.recommendedCourses.length === 0" class="col-span-2 py-8 text-center text-neutral-muted font-light">
              Loading recommendations...
            </div>
          </div>
        </div>

      </div>

      <!-- Right 1 Column: Upcoming Sessions & Recent Activities -->
      <div class="space-y-8">
        <StreakTracker />
        
        <!-- Upcoming Training / Calendar Sessions -->
        <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-4">
          <div class="flex items-center gap-2 border-b border-neutral-ivory pb-3">
            <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <h2 class="text-lg font-display font-semibold text-primary">Upcoming Training</h2>
          </div>
          
          <div class="space-y-4">
            <div class="flex gap-4 items-start">
              <div class="bg-neutral-ivory/60 border border-neutral-ivory text-primary p-2.5 rounded-2xl flex flex-col items-center justify-center font-mono w-14">
                <span class="text-xs uppercase font-bold text-neutral-muted">Jun</span>
                <span class="text-lg font-bold">12</span>
              </div>
              <div class="min-w-0">
                <h4 class="text-sm font-semibold text-neutral-black leading-snug">Public Dawah Bootcamp</h4>
                <p class="text-xs text-neutral-muted mt-0.5">Vancity Center Room 104 • 6:00 PM</p>
              </div>
            </div>

            <div class="flex gap-4 items-start">
              <div class="bg-neutral-ivory/60 border border-neutral-ivory text-primary p-2.5 rounded-2xl flex flex-col items-center justify-center font-mono w-14">
                <span class="text-xs uppercase font-bold text-neutral-muted">Jun</span>
                <span class="text-lg font-bold">19</span>
              </div>
              <div class="min-w-0">
                <h4 class="text-sm font-semibold text-neutral-black leading-snug">Qur'an Translation Webinar</h4>
                <p class="text-xs text-neutral-muted mt-0.5">Online via Teams • 7:30 PM</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="bg-white border border-neutral-ivory p-6 rounded-3xl shadow-soft space-y-4">
          <div class="flex items-center gap-2 border-b border-neutral-ivory pb-3">
            <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <h2 class="text-lg font-display font-semibold text-primary">Recent Activity</h2>
          </div>

          <div class="space-y-4">
            <div
              v-for="act in dashboardStore.recentActivities"
              :key="act.id"
              class="relative pl-6 pb-2"
            >
              <!-- Timeline node line -->
              <div class="absolute left-1.5 top-2 bottom-0 w-0.5 bg-neutral-ivory"></div>
              
              <!-- Indicator circle -->
              <div class="absolute left-0.5 top-1.5 h-2 w-2 rounded-full bg-primary"></div>
              
              <div>
                <h4 class="text-sm font-semibold text-neutral-black leading-snug">{{ act.title }}</h4>
                <p class="text-xs text-neutral-muted mt-0.5 leading-relaxed">{{ act.detail }}</p>
                <span class="text-[9px] font-mono text-neutral-muted/70 block mt-1">
                  {{ formatDate(act.timestamp) }}
                </span>
              </div>
            </div>
            
            <div v-if="dashboardStore.recentActivities.length === 0" class="text-center py-6 text-xs text-neutral-muted italic">
              No recent activity found.
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>
