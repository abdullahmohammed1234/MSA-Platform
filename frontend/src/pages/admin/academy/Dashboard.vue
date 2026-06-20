<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-display font-semibold text-primary">LMS Admin Dashboard</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Monitor academy learning analytics, student metrics, and coordinate mentorship.
        </p>
      </div>
      <button
        type="button"
        @click="fetchAnalytics"
        :disabled="loading"
        class="px-4 py-2 text-sm font-semibold rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background text-primary transition disabled:opacity-60"
      >
        {{ loading ? 'Refreshing…' : 'Refresh Data' }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading && !loaded" class="flex justify-center py-20">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <div v-else-if="error" class="p-4 rounded-xl bg-accent-red/10 border border-accent-red/20 text-secondary text-sm">
      {{ error }}
    </div>

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div
          v-for="card in metrics"
          :key="card.title"
          class="relative overflow-hidden rounded-2xl bg-white border border-neutral-ivory p-6 flex flex-col justify-between hover:border-neutral-gray transition duration-300 shadow-soft"
        >
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold text-neutral-muted uppercase tracking-[0.15em]">{{ card.title }}</span>
            <div :class="`p-2.5 rounded-xl ${card.iconBg} text-white text-lg`">
              {{ card.icon }}
            </div>
          </div>
          <div class="mt-4 flex items-baseline gap-2">
            <span class="text-3xl font-display font-semibold text-primary">{{ card.value }}</span>
            <span v-if="card.change" class="text-xs font-semibold text-neutral-muted">
              {{ card.change }}
            </span>
          </div>
          <div class="absolute -right-6 -bottom-6 opacity-[0.04] text-primary text-8xl">
            {{ card.icon }}
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="rounded-2xl bg-white border border-neutral-ivory p-6 shadow-soft">
        <h2 class="text-lg font-display font-semibold text-primary mb-4">Quick Administrator Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <router-link
            to="/admin/academy/courses/create"
            class="flex flex-col items-center justify-center p-4 rounded-xl bg-neutral-background hover:bg-primary/5 border border-neutral-ivory hover:border-secondary transition text-center group"
          >
            <span class="p-2 bg-secondary/10 text-secondary rounded-lg group-hover:scale-110 transition duration-300">➕</span>
            <span class="text-sm font-semibold mt-3 text-primary group-hover:text-secondary">Create Course</span>
          </router-link>
          <router-link
            to="/admin/academy/quizzes"
            class="flex flex-col items-center justify-center p-4 rounded-xl bg-neutral-background hover:bg-primary/5 border border-neutral-ivory hover:border-primary transition text-center group"
          >
            <span class="p-2 bg-primary/10 text-primary rounded-lg group-hover:scale-110 transition duration-300">❓</span>
            <span class="text-sm font-semibold mt-3 text-primary group-hover:text-primary-light">Create Quiz</span>
          </router-link>
          <router-link
            to="/admin/academy/assignments"
            class="flex flex-col items-center justify-center p-4 rounded-xl bg-neutral-background hover:bg-primary/5 border border-neutral-ivory hover:border-primary transition text-center group"
          >
            <span class="p-2 bg-primary/10 text-primary rounded-lg group-hover:scale-110 transition duration-300">🤝</span>
            <span class="text-sm font-semibold mt-3 text-primary group-hover:text-primary-light">Assign Mentor</span>
          </router-link>
          <router-link
            to="/admin/academy/progress"
            class="flex flex-col items-center justify-center p-4 rounded-xl bg-neutral-background hover:bg-primary/5 border border-neutral-ivory hover:border-secondary transition text-center group"
          >
            <span class="p-2 bg-secondary/10 text-secondary rounded-lg group-hover:scale-110 transition duration-300">📊</span>
            <span class="text-sm font-semibold mt-3 text-primary group-hover:text-secondary">View Progress</span>
          </router-link>
        </div>
      </div>

      <!-- Main Grid: Recent Activity & Pending Tasks -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Activity -->
        <div class="lg:col-span-2 rounded-2xl bg-white border border-neutral-ivory p-6 flex flex-col justify-between shadow-soft">
          <div>
            <h2 class="text-lg font-display font-semibold text-primary mb-4">Recent Academy Activity</h2>
            <div v-if="activity.length === 0" class="text-sm text-neutral-muted py-8 text-center">
              No recent academy activity yet.
            </div>
            <div v-else class="space-y-4">
              <div
                v-for="act in activity"
                :key="act.id"
                class="flex gap-4 p-3 rounded-xl bg-neutral-background/60 border border-neutral-ivory hover:border-neutral-gray transition"
              >
                <div class="text-xl">{{ act.badge }}</div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-neutral-black">{{ act.text }}</p>
                  <p class="text-xs text-neutral-muted mt-1">{{ act.time }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pending Reviews & Alerts -->
        <div class="space-y-8">
          <!-- System Alerts -->
          <div class="rounded-2xl bg-white border border-neutral-ivory p-6 shadow-soft">
            <h2 class="text-lg font-display font-semibold text-primary mb-3 flex items-center gap-2">
              <span>⚠️</span> System Alerts
            </h2>
            <div class="space-y-3">
              <div
                v-if="alerts && alerts.open_reports > 0"
                class="p-3 rounded-xl bg-accent-gold/20 border border-accent-gold/40 text-primary text-xs"
              >
                <span class="font-bold">Moderation:</span> {{ alerts.open_reports }} open discussion report{{ alerts.open_reports === 1 ? '' : 's' }} need review.
              </div>
              <div v-else class="p-3 rounded-xl bg-secondary/10 border border-secondary/20 text-secondary text-xs">
                <span class="font-bold">Moderation:</span> No open discussion reports.
              </div>
              <div class="p-3 rounded-xl bg-primary/5 border border-primary/15 text-primary text-xs">
                <span class="font-bold">Certificates:</span> {{ alerts?.certificates_issued ?? 0 }} certificate{{ (alerts?.certificates_issued ?? 0) === 1 ? '' : 's' }} issued in total.
              </div>
            </div>
          </div>

          <!-- Mentor Workload Stats -->
          <div class="rounded-2xl bg-white border border-neutral-ivory p-6 shadow-soft">
            <h2 class="text-lg font-display font-semibold text-primary mb-3">Workload Summary</h2>
            <div class="space-y-4 text-xs text-neutral-muted">
              <div>
                <div class="flex justify-between mb-1">
                  <span>Avg. Student Load per Mentor</span>
                  <span class="font-semibold text-neutral-black">
                    {{ workload?.avg_students_per_mentor ?? 0 }} / {{ workload?.mentor_capacity ?? 15 }}
                  </span>
                </div>
                <div class="w-full bg-neutral-ivory rounded-full h-1.5">
                  <div
                    class="bg-primary h-1.5 rounded-full"
                    :style="{ width: `${Math.min(100, ((workload?.avg_students_per_mentor ?? 0) / (workload?.mentor_capacity ?? 15)) * 100)}%` }"
                  ></div>
                </div>
              </div>
              <div>
                <div class="flex justify-between mb-1">
                  <span>Quiz Pass Rate (Aggregate)</span>
                  <span class="font-semibold text-neutral-black">{{ workload?.quiz_pass_rate ?? 0 }}%</span>
                </div>
                <div class="w-full bg-neutral-ivory rounded-full h-1.5">
                  <div
                    class="bg-secondary h-1.5 rounded-full"
                    :style="{ width: `${workload?.quiz_pass_rate ?? 0}%` }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import client from '@/services/api/client';

const loading = ref(false);
const loaded = ref(false);
const error = ref<string | null>(null);
const alerts = ref<{ open_reports: number; certificates_issued: number } | null>(null);
const workload = ref<{ avg_students_per_mentor: number; mentor_capacity: number; quiz_pass_rate: number } | null>(null);

const metrics = ref([
  { title: 'Total Courses', value: '0', icon: '📚', iconBg: 'bg-primary', change: '', changeType: 'up' },
  { title: 'Active Students', value: '0', icon: '🎓', iconBg: 'bg-secondary', change: '', changeType: 'up' },
  { title: 'Certificates Issued', value: '0', icon: '🏆', iconBg: 'bg-accent-gold text-primary', change: '', changeType: 'up' },
  { title: 'Completion Rate', value: '0%', icon: '📈', iconBg: 'bg-primary-light', change: '', changeType: 'up' },
]);

const activity = ref<Array<{ id: string; badge: string; text: string; time: string }>>([]);

const fetchAnalytics = async () => {
  loading.value = true;
  error.value = null;

  try {
    const response = await client.get('/admin/academy/analytics');
    if (response.data.success) {
      const summary = response.data.summary;

      metrics.value[0].value = String(summary.courses_count || 0);
      metrics.value[1].value = String(summary.enrolled_users_count || 0);
      metrics.value[2].value = String(summary.certificates_issued ?? summary.enrollments?.completed ?? 0);

      const completedEnrollments = summary.enrollments?.completed || 0;
      const activeEnrollments = summary.enrollments?.active || 0;
      const total = activeEnrollments + completedEnrollments;
      const rate = total > 0 ? Math.round((completedEnrollments / total) * 100) : 0;
      metrics.value[3].value = `${rate}%`;
      metrics.value[3].change = `${activeEnrollments} active enrollments`;

      activity.value = response.data.recent_activity || [];
      alerts.value = response.data.alerts || null;
      workload.value = response.data.workload || null;
      loaded.value = true;
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load live analytics data.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchAnalytics();
});
</script>
