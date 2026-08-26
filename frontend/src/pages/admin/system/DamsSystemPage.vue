<script setup lang="ts">
import { ref, onMounted } from 'vue';
import client from '@/services/api';
import { ExternalLink, RefreshCw, CheckCircle } from 'lucide-vue-next';

const loading = ref(false);
const systemStatus = ref<any>(null);
const healthData = ref<any>(null);
const metricsData = ref<any>(null);

const loadAll = async () => {
  loading.value = true;
  try {
    const [sys, health, metrics] = await Promise.all([
      client.get('/admin/systems/dams'),
      client.get('/admin/systems/dams/health'),
      client.get('/admin/systems/dams/metrics'),
    ]);
    if (sys.data.success) systemStatus.value = sys.data.system;
    if (health.data.success) healthData.value = health.data.health;
    if (metrics.data.success) metricsData.value = metrics.data.metrics;
  } catch (err) {
    console.error('Failed to load DAMS system status', err);
  } finally {
    loading.value = false;
  }
};

onMounted(loadAll);
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Dawah Academy Management System</h1>
        <p class="text-sm text-neutral-muted mt-1">
          DAMS application registry — Academy administration is separate from MSA Admin and from the learner Academy.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory px-3 py-2 text-sm font-semibold hover:bg-neutral-background"
          @click="loadAll"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
        <router-link
          to="/dams"
          class="inline-flex items-center gap-2 rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-white hover:opacity-90"
        >
          Open DAMS
          <ExternalLink class="h-4 w-4" />
        </router-link>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Status</p>
        <p class="mt-2 flex items-center gap-2 text-lg font-semibold text-neutral-black">
          <CheckCircle class="h-5 w-5 text-secondary" />
          {{ systemStatus?.status || '—' }}
        </p>
        <p class="mt-1 text-xs text-neutral-muted">{{ systemStatus?.name }}</p>
      </div>
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Health</p>
        <p class="mt-2 text-lg font-semibold text-neutral-black capitalize">{{ healthData?.health_status || healthData?.status || '—' }}</p>
        <p class="mt-1 text-xs text-neutral-muted">{{ healthData?.checked_at || '' }}</p>
      </div>
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Frontend</p>
        <p class="mt-2 text-sm font-semibold text-primary break-all">{{ systemStatus?.frontend_url || '/dams' }}</p>
      </div>
    </div>

    <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
      <h2 class="text-sm font-bold text-neutral-black">Academy metrics</h2>
      <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
        <div><dt class="text-neutral-muted">Courses</dt><dd class="font-semibold">{{ metricsData?.courses ?? '—' }}</dd></div>
        <div><dt class="text-neutral-muted">Published courses</dt><dd class="font-semibold">{{ metricsData?.courses_published ?? '—' }}</dd></div>
        <div><dt class="text-neutral-muted">Quizzes</dt><dd class="font-semibold">{{ metricsData?.quizzes ?? '—' }}</dd></div>
        <div><dt class="text-neutral-muted">Enrollments</dt><dd class="font-semibold">{{ metricsData?.enrollments ?? '—' }}</dd></div>
      </dl>
    </div>

    <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
      <h2 class="text-sm font-bold text-neutral-black">Owns (operations)</h2>
      <ul class="mt-3 flex flex-wrap gap-2">
        <li
          v-for="item in systemStatus?.owns_operations || []"
          :key="item"
          class="rounded-full bg-neutral-background px-3 py-1 text-xs font-semibold text-neutral-black"
        >
          {{ item }}
        </li>
      </ul>
      <p class="mt-4 text-xs text-neutral-muted">
        API contract remains <code class="text-primary">/api/v1/admin/academy/*</code>.
        Learner experience remains at <code class="text-primary">/academy</code>.
      </p>
    </div>
  </div>
</template>
