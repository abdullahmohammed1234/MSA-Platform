<template>
  <div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Platform Operations & Intelligence</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Central intelligence control plane for SFU MSA Platform (9 Applications & 4 Core Services).
        </p>
      </div>

      <div class="flex items-center gap-3 self-start sm:self-auto">
        <button
          type="button"
          @click="triggerSnapshot"
          :disabled="snapshotting"
          class="px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-soft transition disabled:opacity-60 cursor-pointer"
        >
          {{ snapshotting ? 'Probing…' : 'Probe Platform Now' }}
        </button>
        <button
          type="button"
          @click="loadDashboard"
          :disabled="loading"
          class="px-4 py-2 text-sm font-semibold rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background transition disabled:opacity-60 cursor-pointer"
        >
          {{ loading ? 'Refreshing…' : 'Refresh Telemetry' }}
        </button>
      </div>
    </div>

    <!-- Error Banner -->
    <div v-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
      {{ error }}
    </div>

    <!-- Loading Indicator -->
    <div v-if="loading && !metrics" class="flex justify-center py-16">
      <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
    </div>

    <template v-else-if="metrics">
      <!-- Overall Health Status Banner -->
      <div
        :class="getOverallBannerClass(metrics?.overall_health?.status || 'healthy')"
        class="p-5 rounded-2xl border flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-soft"
      >
        <div class="flex items-center gap-4">
          <div class="h-4 w-4 rounded-full animate-pulse" :class="getOverallPulseClass(metrics?.overall_health?.status || 'healthy')"></div>
          <div>
            <span class="text-xs font-bold uppercase tracking-widest block opacity-75">Platform Operational Status</span>
            <h2 class="text-2xl font-display font-bold uppercase tracking-wide">
              {{ metrics?.overall_health?.status || 'healthy' }}
            </h2>
          </div>
        </div>

        <div class="flex items-center gap-4 text-xs font-mono">
          <span>Applications: <strong>{{ metrics?.overall_health?.healthy_apps ?? 9 }}/{{ metrics?.overall_health?.total_apps ?? 9 }} Healthy</strong></span>
          <span class="opacity-40">|</span>
          <span>Last Updated: <strong>{{ metrics?.generated_at ? new Date(metrics.generated_at).toLocaleTimeString() : 'Just now' }}</strong></span>
        </div>
      </div>

      <!-- High-Level Telemetry Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
        <!-- Apps Health -->
        <div class="bg-white border border-neutral-ivory p-5 rounded-xl shadow-soft flex items-center justify-between">
          <div>
            <h3 class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">9 Applications</h3>
            <p class="text-2xl font-display font-semibold text-primary">
              {{ metrics?.overall_health?.healthy_apps ?? 9 }} / {{ metrics?.overall_health?.total_apps ?? 9 }}
            </p>
            <span class="text-xs text-emerald-600 font-medium">Operational</span>
          </div>
          <router-link to="/admin/operations/health" class="text-xs font-bold text-primary hover:underline">
            History →
          </router-link>
        </div>

        <!-- Active Alerts -->
        <div class="bg-white border border-neutral-ivory p-5 rounded-xl shadow-soft flex items-center justify-between">
          <div>
            <h3 class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">Active Alerts</h3>
            <p class="text-2xl font-display font-semibold" :class="(metrics?.active_alerts_count ?? 0) > 0 ? 'text-amber-600' : 'text-primary'">
              {{ metrics?.active_alerts_count ?? 0 }}
            </p>
            <span class="text-xs text-neutral-muted">Operational Breaches</span>
          </div>
          <router-link to="/admin/operations/alerts" class="text-xs font-bold text-primary hover:underline">
            Manage →
          </router-link>
        </div>

        <!-- Failed Queue Jobs -->
        <div class="bg-white border border-neutral-ivory p-5 rounded-xl shadow-soft flex items-center justify-between">
          <div>
            <h3 class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">Failed Jobs</h3>
            <p class="text-2xl font-display font-semibold" :class="(metrics?.failed_jobs_count ?? 0) > 0 ? 'text-red-600' : 'text-primary'">
              {{ metrics?.failed_jobs_count ?? 0 }}
            </p>
            <span class="text-xs text-neutral-muted">Database Queue</span>
          </div>
          <button
            v-if="(metrics?.failed_jobs_count ?? 0) > 0"
            @click="showFlushModal = true"
            class="text-xs font-bold text-red-600 hover:underline cursor-pointer"
          >
            Flush All
          </button>
        </div>

        <!-- Unified Audit Activity -->
        <div class="bg-white border border-neutral-ivory p-5 rounded-xl shadow-soft flex items-center justify-between">
          <div>
            <h3 class="text-[10px] font-bold uppercase tracking-[0.15em] text-neutral-muted mb-1">Audit Stream</h3>
            <p class="text-2xl font-display font-semibold text-primary">
              {{ metrics?.recent_audits?.length ?? 0 }}
            </p>
            <span class="text-xs text-neutral-muted">Recent Actions</span>
          </div>
          <router-link to="/admin/operations/audit" class="text-xs font-bold text-primary hover:underline">
            Search →
          </router-link>
        </div>
      </div>

      <!-- Cross-System Aggregations Dashboard -->
      <div class="bg-white border border-neutral-ivory rounded-xl p-6 shadow-soft space-y-4">
        <h2 class="text-lg font-display font-semibold text-primary">Cross-System Business Intelligence</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
          <div class="p-4 rounded-xl bg-neutral-background/60 border border-neutral-ivory text-center">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted block">EMS Events</span>
            <span class="text-xl font-bold font-display text-neutral-black">{{ metrics?.cross_system_summary?.events_count ?? 0 }}</span>
          </div>
          <div class="p-4 rounded-xl bg-neutral-background/60 border border-neutral-ivory text-center">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted block">Store Orders</span>
            <span class="text-xl font-bold font-display text-neutral-black">{{ metrics?.cross_system_summary?.store_orders_count ?? 0 }}</span>
          </div>
          <div class="p-4 rounded-xl bg-neutral-background/60 border border-neutral-ivory text-center">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted block">DMS Donations</span>
            <span class="text-xl font-bold font-display text-neutral-black">{{ metrics?.cross_system_summary?.donations_count ?? 0 }}</span>
          </div>
          <div class="p-4 rounded-xl bg-neutral-background/60 border border-neutral-ivory text-center">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted block">SPMS Sponsors</span>
            <span class="text-xl font-bold font-display text-neutral-black">{{ metrics?.cross_system_summary?.sponsorships_count ?? 0 }}</span>
          </div>
          <div class="p-4 rounded-xl bg-neutral-background/60 border border-neutral-ivory text-center">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted block">MLibMS Loans</span>
            <span class="text-xl font-bold font-display text-neutral-black">{{ metrics?.cross_system_summary?.library_loans_count ?? 0 }}</span>
          </div>
          <div class="p-4 rounded-xl bg-neutral-background/60 border border-neutral-ivory text-center">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted block">DAMS Learners</span>
            <span class="text-xl font-bold font-display text-neutral-black">{{ metrics?.cross_system_summary?.academy_learners_count ?? 0 }}</span>
          </div>
        </div>
      </div>

      <!-- Live 9 Applications & 4 Services Status Grid -->
      <div class="bg-white border border-neutral-ivory rounded-xl p-6 shadow-soft space-y-6">
        <h2 class="text-lg font-display font-semibold text-primary">Applications & Core Services Live Telemetry</h2>

        <div class="space-y-4">
          <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-muted">9 Applications Probe Status</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <div
              v-for="(appData, appKey) in (metrics?.apps || {})"
              :key="appKey"
              class="p-4 rounded-xl border border-neutral-ivory bg-white shadow-xs flex items-center justify-between"
            >
              <div>
                <span class="font-bold text-sm font-mono text-neutral-black uppercase">{{ appKey }}</span>
                <span class="block text-xs text-neutral-muted font-mono">{{ appData?.probe_ms ?? 0 }}ms latency</span>
              </div>
              <span :class="getBadgeClass(appData?.status)" class="px-2.5 py-1 text-xs font-bold uppercase rounded-full border">
                {{ appData?.status || 'unknown' }}
              </span>
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-muted">4 Core Platform Services Probes</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div
              v-for="(srvData, srvKey) in (metrics?.services || {})"
              :key="srvKey"
              class="p-4 rounded-xl border border-neutral-ivory bg-white shadow-xs flex items-center justify-between"
            >
              <div>
                <span class="font-bold text-sm font-mono text-neutral-black uppercase">{{ srvKey }}</span>
                <span class="block text-xs text-neutral-muted font-mono">{{ srvData?.probe_ms ?? 0 }}ms probe</span>
              </div>
              <span :class="getBadgeClass(srvData?.status)" class="px-2.5 py-1 text-xs font-bold uppercase rounded-full border">
                {{ srvData?.status || 'unknown' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Failed Jobs Control Drawer -->
      <div v-if="(metrics?.failed_jobs_samples?.length ?? 0) > 0" class="bg-white border border-neutral-ivory rounded-xl p-6 shadow-soft space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-display font-semibold text-red-600">Failed Queue Jobs Control</h2>
          <button
            @click="showFlushModal = true"
            class="px-3 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-soft cursor-pointer"
          >
            Flush All Failed Jobs
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="bg-neutral-background text-xs font-bold uppercase tracking-wider text-neutral-muted">
                <th class="py-2 px-3">Job ID</th>
                <th class="py-2 px-3">Queue</th>
                <th class="py-2 px-3">Failed At</th>
                <th class="py-2 px-3">Exception</th>
                <th class="py-2 px-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory">
              <tr v-for="job in (metrics?.failed_jobs_samples || [])" :key="job?.id" class="hover:bg-neutral-background/30">
                <td class="py-2 px-3 font-mono font-bold">#{{ job?.id }}</td>
                <td class="py-2 px-3 font-mono text-xs">{{ job?.connection }} / {{ job?.queue }}</td>
                <td class="py-2 px-3 font-mono text-xs text-neutral-muted">{{ job?.failed_at ? new Date(job.failed_at).toLocaleString() : '—' }}</td>
                <td class="py-2 px-3 text-xs text-red-700 truncate max-w-xs">{{ job?.exception_summary }}</td>
                <td class="py-2 px-3 text-right">
                  <button
                    v-if="job?.id"
                    @click="retryJob(job.id)"
                    class="px-2 py-1 text-xs font-bold text-primary bg-emerald-50 hover:bg-emerald-100 rounded border border-emerald-200 cursor-pointer"
                  >
                    Retry Job
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Platform Audit Logs Stream -->
      <div class="bg-white border border-neutral-ivory rounded-xl p-6 shadow-soft space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-display font-semibold text-primary">Recent Platform Audit Stream</h2>
          <router-link to="/admin/operations/audit" class="text-xs font-bold text-primary hover:underline">
            View All Audit Logs →
          </router-link>
        </div>

        <div v-if="!metrics?.recent_audits || metrics.recent_audits.length === 0" class="text-sm text-neutral-muted py-6 text-center">
          No audit logs recorded yet.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="log in (metrics?.recent_audits || [])"
            :key="log?.id"
            class="flex items-center justify-between p-3 rounded-xl border border-neutral-ivory bg-neutral-background/40 text-xs"
          >
            <div class="flex items-center gap-3">
              <span class="font-mono px-2 py-0.5 rounded bg-white border border-neutral-ivory font-bold uppercase">
                {{ log?.application || 'platform' }}
              </span>
              <span class="font-bold text-neutral-black">{{ log?.action }}</span>
              <span v-if="log?.user" class="text-neutral-muted">by {{ log.user.name || (log.user.first_name ? log.user.first_name + ' ' + (log.user.last_name || '') : 'User #' + log.user.id) }}</span>
            </div>

            <span class="font-mono text-neutral-muted">
              {{ log?.created_at ? new Date(log.created_at).toLocaleTimeString() : '—' }}
            </span>
          </div>
        </div>
      </div>
    </template>

    <!-- Destructive Action Safeguard Modal: Flush Failed Jobs -->
    <div v-if="showFlushModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-black/50 backdrop-blur-xs">
      <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-4">
        <div class="flex items-center gap-3 text-red-600">
          <svg class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <h3 class="text-lg font-bold">Destructive Action Warning</h3>
        </div>

        <p class="text-sm text-neutral-muted">
          Flushing failed jobs is a <strong>destructive, non-reversible operational command</strong>. All failed jobs will be permanently deleted from the queue database.
        </p>

        <p class="text-xs font-mono bg-red-50 text-red-800 p-3 rounded-lg border border-red-200">
          This operation will be logged to the security audit trail with CRITICAL severity.
        </p>

        <div class="flex justify-end gap-3 pt-2">
          <button
            @click="showFlushModal = false"
            class="px-4 py-2 text-xs font-bold text-neutral-muted hover:text-neutral-black bg-neutral-background rounded-xl cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="confirmFlushFailed"
            :disabled="operating"
            class="px-4 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-soft cursor-pointer disabled:opacity-50"
          >
            {{ operating ? 'Flushing…' : 'I Understand, Flush Failed Jobs' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { platformOperationsService, type PlatformIntelligenceMetrics } from '@/services/platformOperationsService';

const loading = ref(false);
const snapshotting = ref(false);
const operating = ref(false);
const error = ref<string | null>(null);
const metrics = ref<PlatformIntelligenceMetrics | null>(null);

const showFlushModal = ref(false);

const loadDashboard = async () => {
  loading.value = true;
  error.value = null;

  try {
    metrics.value = await platformOperationsService.getIntelligenceMetrics();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load platform operations data.';
  } finally {
    loading.value = false;
  }
};

const triggerSnapshot = async () => {
  snapshotting.value = true;
  try {
    await platformOperationsService.triggerHealthSnapshot();
    await loadDashboard();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to probe platform.';
  } finally {
    snapshotting.value = false;
  }
};

const retryJob = async (jobId: number) => {
  operating.value = true;
  try {
    await platformOperationsService.retryFailedJob(jobId);
    await loadDashboard();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to retry job.';
  } finally {
    operating.value = false;
  }
};

const confirmFlushFailed = async () => {
  operating.value = true;
  try {
    await platformOperationsService.flushFailedJobs(true);
    showFlushModal.value = false;
    await loadDashboard();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to flush failed jobs.';
  } finally {
    operating.value = false;
  }
};

const getBadgeClass = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'healthy':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    case 'degraded':
      return 'bg-amber-100 text-amber-800 border-amber-200';
    case 'down':
    case 'unhealthy':
      return 'bg-red-100 text-red-800 border-red-200';
    default:
      return 'bg-neutral-100 text-neutral-800 border-neutral-200';
  }
};

const getOverallBannerClass = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'healthy':
      return 'bg-emerald-900 text-white border-emerald-700';
    case 'degraded':
      return 'bg-amber-900 text-white border-amber-700';
    case 'down':
      return 'bg-red-900 text-white border-red-700';
    default:
      return 'bg-neutral-900 text-white border-neutral-700';
  }
};

const getOverallPulseClass = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'healthy':
      return 'bg-emerald-400';
    case 'degraded':
      return 'bg-amber-400';
    case 'down':
      return 'bg-red-400';
    default:
      return 'bg-neutral-400';
  }
};

onMounted(() => {
  loadDashboard();
});
</script>
