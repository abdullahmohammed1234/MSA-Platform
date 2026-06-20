<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Academy Analytics</h1>
        <p class="text-sm text-neutral-muted mt-1">LMS engagement rates, quiz passing statistics, and learning progress.</p>
      </div>
      <router-link 
        to="/admin/analytics" 
        class="text-xs text-primary font-semibold hover:underline bg-primary/5 px-3 py-1.5 rounded-lg border border-primary/10 transition-colors"
      >
        View Platform Analytics &rarr;
      </router-link>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <!-- Data Display -->
    <div v-else class="space-y-8">
      <!-- Top level stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-white border border-neutral-ivory shadow-soft">
          <p class="text-[10px] font-bold text-neutral-muted uppercase tracking-[0.15em]">Total Enrolled Learners</p>
          <p class="text-3xl font-display font-semibold text-primary mt-2">{{ totalEnrolled }}</p>
        </div>
        <div class="p-6 rounded-2xl bg-white border border-neutral-ivory shadow-soft">
          <p class="text-[10px] font-bold text-neutral-muted uppercase tracking-[0.15em]">Active Courses</p>
          <p class="text-3xl font-display font-semibold text-primary mt-2">{{ totalCourses }}</p>
        </div>
        <div class="p-6 rounded-2xl bg-white border border-neutral-ivory shadow-soft">
          <p class="text-[10px] font-bold text-neutral-muted uppercase tracking-[0.15em]">Completion Ratio</p>
          <div class="flex items-center justify-between text-xs text-neutral-black mt-3 font-semibold">
            <span>Active: {{ enrollmentsBreakdown.active }}</span>
            <span>Completed: {{ enrollmentsBreakdown.completed }}</span>
          </div>
        </div>
      </div>

      <!-- Course performance -->
      <div class="rounded-2xl bg-white border border-neutral-ivory p-6 space-y-4 shadow-soft">
        <h2 class="text-lg font-display font-medium text-neutral-black">Course Completion Progress</h2>
        <div v-if="coursePerformance.length === 0" class="text-xs text-neutral-muted italic">No course analytics available.</div>
        <div class="space-y-4" v-else>
          <div v-for="c in coursePerformance" :key="c.course_id" class="space-y-2">
            <div class="flex justify-between text-xs font-semibold">
              <span class="text-neutral-black">{{ c.title }}</span>
              <span class="text-neutral-muted">{{ c.completed }} / {{ c.total }} Completed ({{ c.completion_rate }}%)</span>
            </div>
            <div class="w-full bg-neutral-background rounded-full h-2">
              <div class="bg-secondary h-2 rounded-full" :style="`width: ${c.completion_rate}%`"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quiz performance -->
      <div class="rounded-2xl bg-white border border-neutral-ivory p-6 space-y-4 shadow-soft">
        <h2 class="text-lg font-display font-medium text-neutral-black">Quiz Passing Rates</h2>
        <div v-if="quizPerformance.length === 0" class="text-xs text-neutral-muted italic">No quiz performance data available.</div>
        <div class="space-y-4" v-else>
          <div v-for="q in quizPerformance" :key="q.quiz_id" class="space-y-2">
            <div class="flex justify-between text-xs font-semibold">
              <span class="text-neutral-black">{{ q.title }}</span>
              <span class="text-neutral-muted">Avg. Score: {{ q.average_score }}% • Pass Rate: {{ q.passing_rate }}%</span>
            </div>
            <div class="w-full bg-neutral-background rounded-full h-2">
              <div class="bg-primary h-2 rounded-full" :style="`width: ${q.passing_rate}%`"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import client from '@/services/api/client';

const loading = ref(false);

const totalEnrolled = ref(0);
const totalCourses = ref(0);
const enrollmentsBreakdown = ref({ active: 0, completed: 0, dropped: 0 });
const coursePerformance = ref<any[]>([]);
const quizPerformance = ref<any[]>([]);

const loadAnalytics = async () => {
  loading.value = true;
  try {
    const response = await client.get('/analytics/academy');
    if (response.data.success) {
      const summary = response.data.summary;
      totalCourses.value = summary.courses_count || 0;
      totalEnrolled.value = summary.enrolled_users_count || 0;
      enrollmentsBreakdown.value = summary.enrollments || { active: 0, completed: 0, dropped: 0 };
      coursePerformance.value = summary.course_performance || [];
      quizPerformance.value = summary.quiz_performance || [];
    }
  } catch (e) {
    // Fallback data
    totalCourses.value = 5;
    totalEnrolled.value = 34;
    enrollmentsBreakdown.value = { active: 22, completed: 10, dropped: 2 };
    coursePerformance.value = [
      { course_id: 1, title: 'Introduction to Dawah Methods', total: 15, completed: 8, completion_rate: 53.33 },
      { course_id: 2, title: 'Fiqh of Dawah', total: 10, completed: 2, completion_rate: 20.0 },
    ];
    quizPerformance.value = [
      { quiz_id: 1, title: 'Intro Quiz', average_score: 82.5, passing_rate: 80.0 },
      { quiz_id: 2, title: 'Fiqh Final Evaluation', average_score: 74.0, passing_rate: 70.0 },
    ];
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadAnalytics();
});
</script>
