<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-3xl font-display font-medium text-primary">Admin Overview</h1>
      <button
        type="button"
        @click="loadDashboard"
        :disabled="loading"
        class="px-4 py-2 text-sm font-semibold rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background transition disabled:opacity-60"
      >
        {{ loading ? 'Refreshing…' : 'Refresh' }}
      </button>
    </div>

    <div v-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
      {{ error }}
    </div>

    <div v-if="loading && !stats" class="flex justify-center py-16">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <template v-else-if="stats">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white border border-neutral-ivory p-6 rounded-xl shadow-soft">
          <h3 class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">Total Users</h3>
          <p class="text-3xl font-display font-semibold text-primary">{{ stats.total_users }}</p>
        </div>
        <div class="bg-white border border-neutral-ivory p-6 rounded-xl shadow-soft">
          <h3 class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">Active Students</h3>
          <p class="text-3xl font-display font-semibold text-primary">{{ stats.active_students }}</p>
        </div>
        <div class="bg-white border border-neutral-ivory p-6 rounded-xl shadow-soft">
          <h3 class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">Courses Published</h3>
          <p class="text-3xl font-display font-semibold text-primary">{{ stats.published_courses }}</p>
        </div>
        <div class="bg-white border border-neutral-ivory p-6 rounded-xl shadow-soft">
          <h3 class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">Open Reports</h3>
          <p class="text-3xl font-display font-semibold text-primary">{{ stats.pending_inquiries }}</p>
        </div>
      </div>

      <div class="bg-white border border-neutral-ivory rounded-xl shadow-soft p-6">
        <h2 class="text-lg font-display font-semibold text-primary mb-4">Recent Platform Activity</h2>
        <div v-if="recentActivity.length === 0" class="text-sm text-neutral-muted py-8 text-center">
          No recent activity yet. Enrollments, certificates, and quiz attempts will appear here.
        </div>
        <div v-else class="space-y-3">
          <div
            v-for="item in recentActivity"
            :key="item.id"
            class="flex gap-4 p-3 rounded-xl border border-neutral-ivory/80 bg-neutral-background/40"
          >
            <div class="text-xl">{{ item.badge }}</div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-neutral-black">{{ item.text }}</p>
              <p class="text-xs text-neutral-muted mt-1">{{ item.time }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import client from '@/services/api';

interface DashboardStats {
  total_users: number;
  active_students: number;
  published_courses: number;
  pending_inquiries: number;
}

interface ActivityItem {
  id: string;
  badge: string;
  text: string;
  time: string;
}

const loading = ref(false);
const error = ref<string | null>(null);
const stats = ref<DashboardStats | null>(null);
const recentActivity = ref<ActivityItem[]>([]);

const loadDashboard = async () => {
  loading.value = true;
  error.value = null;

  try {
    const response = await client.get('/admin/dashboard');
    if (response.data.success) {
      stats.value = response.data.stats;
      recentActivity.value = response.data.recent_activity || [];
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load admin dashboard data.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadDashboard();
});
</script>
