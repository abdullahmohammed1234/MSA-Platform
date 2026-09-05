<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { platformOperationsService, type PlatformHealthHistoryItem } from '@/services/platformOperationsService';

const loading = ref(false);
const snapshotting = ref(false);
const error = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const history = ref<PlatformHealthHistoryItem[]>([]);
const expandedSnapshot = ref<Record<number, boolean>>({});

const fetchHistory = async () => {
  loading.value = true;
  error.value = null;
  try {
    const res = await platformOperationsService.getHealthHistory({ limit: 30 });
    history.value = res.data;
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to fetch platform health history.';
  } finally {
    loading.value = false;
  }
};

const triggerSnapshot = async () => {
  snapshotting.value = true;
  error.value = null;
  successMessage.value = null;
  try {
    const res = await platformOperationsService.triggerHealthSnapshot();
    successMessage.value = `Health snapshot recorded. Status: ${res.data.overall_status} (${res.data.response_time_ms}ms)`;
    fetchHistory();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to record health snapshot.';
  } finally {
    snapshotting.value = false;
  }
};

const toggleExpand = (id: number) => {
  expandedSnapshot.value[id] = !expandedSnapshot.value[id];
};

const getStatusBadge = (status: string) => {
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

onMounted(() => {
  fetchHistory();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Platform Health History & Probes</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Historical system telemetry, active probes, and system status snapshots.
        </p>
      </div>
      <div class="flex items-center gap-3 self-start sm:self-auto">
        <button
          @click="triggerSnapshot"
          :disabled="snapshotting"
          class="px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-primary-hover rounded-xl shadow-soft transition disabled:opacity-60 cursor-pointer"
        >
          {{ snapshotting ? 'Probing…' : 'Trigger Snapshot Now' }}
        </button>
        <button
          @click="fetchHistory"
          :disabled="loading"
          class="px-4 py-2 text-sm font-semibold rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background transition disabled:opacity-60 cursor-pointer"
        >
          Refresh
        </button>
      </div>
    </div>

    <!-- Messages -->
    <div v-if="successMessage" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-medium">
      {{ successMessage }}
    </div>
    <div v-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
      {{ error }}
    </div>

    <!-- History Timeline Table -->
    <div class="bg-white border border-neutral-ivory rounded-xl shadow-soft overflow-hidden">
      <div v-if="loading && history.length === 0" class="flex justify-center py-16">
        <div class="h-10 w-10 border-4 border-neutral-ivory border-t-primary rounded-full animate-spin"></div>
      </div>

      <div v-else-if="history.length === 0" class="p-8 text-center text-neutral-muted">
        No health snapshot history available. Click "Trigger Snapshot Now" to record one.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse">
          <thead>
            <tr class="bg-neutral-background/60 border-b border-neutral-ivory text-xs font-bold uppercase tracking-wider text-neutral-muted">
              <th class="py-3 px-4">Recorded At</th>
              <th class="py-3 px-4">Overall Status</th>
              <th class="py-3 px-4">Probe Latency</th>
              <th class="py-3 px-4">Memory</th>
              <th class="py-3 px-4">CPU Load</th>
              <th class="py-3 px-4 text-right">Details</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory/60">
            <template v-for="snap in history" :key="snap.id">
              <tr class="hover:bg-neutral-background/30 transition">
                <td class="py-3 px-4 font-mono text-xs text-neutral-black font-semibold whitespace-nowrap">
                  {{ new Date(snap.recorded_at).toLocaleString() }}
                </td>
                <td class="py-3 px-4">
                  <span :class="getStatusBadge(snap.overall_status)" class="px-2.5 py-0.5 text-xs font-bold uppercase rounded-full border">
                    {{ snap.overall_status }}
                  </span>
                </td>
                <td class="py-3 px-4 font-mono text-xs font-semibold text-primary">
                  {{ snap.response_time_ms }} ms
                </td>
                <td class="py-3 px-4 font-mono text-xs text-neutral-muted">
                  {{ snap.memory_mb }} MB
                </td>
                <td class="py-3 px-4 font-mono text-xs text-neutral-muted">
                  {{ snap.cpu_load }}
                </td>
                <td class="py-3 px-4 text-right">
                  <button
                    @click="toggleExpand(snap.id)"
                    class="px-2.5 py-1 text-xs font-semibold rounded border border-neutral-ivory bg-white hover:bg-neutral-background transition cursor-pointer"
                  >
                    {{ expandedSnapshot[snap.id] ? 'Hide Probes' : 'View Probes' }}
                  </button>
                </td>
              </tr>
              <!-- Expanded Probes Breakdown Drawer -->
              <tr v-if="expandedSnapshot[snap.id]" class="bg-neutral-background/40 border-b border-neutral-ivory">
                <td colspan="6" class="p-5 space-y-4">
                  <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-muted mb-2">9 Application Probes</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                      <div
                        v-for="(appData, appKey) in (snap?.apps_health || {})"
                        :key="appKey"
                        class="p-3 bg-white border border-neutral-ivory rounded-lg flex items-center justify-between"
                      >
                        <div>
                          <span class="font-bold text-xs uppercase font-mono text-neutral-black">{{ appKey }}</span>
                          <span class="block text-[11px] text-neutral-muted font-mono">{{ appData?.probe_ms ?? 0 }}ms</span>
                        </div>
                        <span :class="getStatusBadge(appData?.status || 'unknown')" class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-md border">
                          {{ appData?.status || 'unknown' }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-muted mb-2">4 Platform Services Probes</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                      <div
                        v-for="(srvData, srvKey) in (snap?.services_health || {})"
                        :key="srvKey"
                        class="p-3 bg-white border border-neutral-ivory rounded-lg flex items-center justify-between"
                      >
                        <div>
                          <span class="font-bold text-xs uppercase font-mono text-neutral-black">{{ srvKey }}</span>
                          <span class="block text-[11px] text-neutral-muted font-mono">{{ srvData?.probe_ms ?? 0 }}ms</span>
                        </div>
                        <span :class="getStatusBadge(srvData?.status || 'unknown')" class="px-2 py-0.5 text-[10px] font-bold uppercase rounded-md border">
                          {{ srvData?.status || 'unknown' }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
