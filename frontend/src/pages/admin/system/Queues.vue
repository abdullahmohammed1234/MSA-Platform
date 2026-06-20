<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useSystemStore } from '@/stores/system';
import { useToastStore } from '@/components/feedback/toast';
import { useAuthorization } from '@/composables/auth/useAuthorization';
import { systemService } from '@/services/system';

const store = useSystemStore();
const toast = useToastStore();
const { can } = useAuthorization();

// Tab state
const activeTab = ref<'overview' | 'logs' | 'failed' | 'scheduler' | 'reports'>('overview');

// Logs filters & pagination
const logQueue = ref('');
const logStatus = ref('');
const logSearch = ref('');
const logsPage = ref(1);
const logsPerPage = ref(10);

// Failed pagination
const failedPage = ref(1);

// Reports pagination
const reportsPage = ref(1);

// Manual report selection
const selectedReportPeriod = ref<'daily' | 'weekly' | 'monthly'>('daily');

// Failed job exception modal/expand state
const expandedException = ref<Record<string, boolean>>({});

onMounted(async () => {
  await loadDashboardData();
});

const loadDashboardData = async () => {
  try {
    isLoadingData.value = true;
    if (activeTab.value === 'overview') {
      await store.fetchQueues();
    } else if (activeTab.value === 'logs') {
      await fetchLogs();
    } else if (activeTab.value === 'failed') {
      await store.fetchFailedJobs({ page: failedPage.value, per_page: 10 });
    } else if (activeTab.value === 'scheduler') {
      await store.fetchScheduler();
    } else if (activeTab.value === 'reports') {
      await store.fetchReports({ page: reportsPage.value, per_page: 10 });
    }
  } catch (err) {
    toast.error('Failed to load system queues data.');
  } finally {
    isLoadingData.value = false;
  }
};

const isLoadingData = ref(false);

// Auto-refresh timer for overview
const refreshTimer = ref<ReturnType<typeof setInterval> | null>(null);
const autoRefresh = ref(true);

const stopAutoRefresh = () => {
  if (refreshTimer.value) {
    clearInterval(refreshTimer.value);
    refreshTimer.value = null;
  }
};

const startAutoRefresh = () => {
  stopAutoRefresh();
  refreshTimer.value = setInterval(async () => {
    if (activeTab.value === 'overview') {
      await store.fetchQueues();
    }
  }, 10000); // 10 seconds
};

watch(autoRefresh, (newVal) => {
  if (newVal && activeTab.value === 'overview') {
    startAutoRefresh();
  } else {
    stopAutoRefresh();
  }
}, { immediate: true });

watch(activeTab, async (newTab) => {
  stopAutoRefresh();
  await loadDashboardData();
  if (newTab === 'overview' && autoRefresh.value) {
    startAutoRefresh();
  }
});

// Fetch logs with filters
const fetchLogs = async () => {
  await store.fetchJobLogs({
    queue: logQueue.value,
    status: logStatus.value,
    job_name: logSearch.value,
    page: logsPage.value,
    per_page: logsPerPage.value
  });
};

// Apply log filters
const applyLogFilters = async () => {
  logsPage.value = 1;
  await fetchLogs();
};

const resetLogFilters = async () => {
  logQueue.value = '';
  logStatus.value = '';
  logSearch.value = '';
  logsPage.value = 1;
  await fetchLogs();
};

// Queue clear action
const handleClearQueue = async (queueName: string) => {
  if (confirm(`Are you sure you want to clear all PENDING jobs from the '${queueName}' queue?`)) {
    try {
      await store.clearQueue(queueName);
      toast.success(`Queue '${queueName}' cleared successfully.`);
    } catch (e: any) {
      toast.error(e.response?.data?.message || `Failed to clear queue '${queueName}'.`);
    }
  }
};

// Failed job actions
const handleRetryJob = async (uuid: string) => {
  try {
    await store.retryJob(uuid);
    toast.success('Job dispatched back to processing queue.');
  } catch (e: any) {
    toast.error(e.response?.data?.message || 'Failed to retry job.');
  }
};

const handleRetryAll = async () => {
  if (confirm('Are you sure you want to retry ALL failed jobs?')) {
    try {
      await store.retryAllJobs();
      toast.success('All failed jobs queued for retry.');
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Failed to retry all jobs.');
    }
  }
};

const handleDeleteFailedJob = async (uuid: string) => {
  if (confirm('Delete this failed job log permanently?')) {
    try {
      await store.deleteFailedJob(uuid);
      toast.success('Failed job log deleted.');
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Failed to delete failed job log.');
    }
  }
};

const handleDeleteAllFailed = async () => {
  if (confirm('Are you sure you want to permanently delete ALL failed job logs?')) {
    try {
      await store.deleteAllFailedJobs();
      toast.success('All failed job logs deleted.');
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Failed to delete failed jobs.');
    }
  }
};

// Report actions
const handleTriggerReport = async () => {
  try {
    await store.generateReport(selectedReportPeriod.value);
    toast.success(`Manual ${selectedReportPeriod.value} report generation job queued.`);
    activeTab.value = 'reports';
  } catch (e: any) {
    toast.error(e.response?.data?.message || 'Failed to queue report generation.');
  }
};

const handleDeleteReport = async (uuid: string) => {
  if (confirm('Delete this report and its associated PDF permanently?')) {
    try {
      await store.deleteReport(uuid);
      toast.success('Report deleted successfully.');
    } catch (e: any) {
      toast.error(e.response?.data?.message || 'Failed to delete report.');
    }
  }
};

// Scheduler actions
const handleRunTask = async (id: number, desc: string) => {
  try {
    await store.runScheduledTask(id);
    toast.success(`Task triggered successfully: ${desc}`);
  } catch (e: any) {
    toast.error('Error triggering scheduled task.');
  }
};

const getDownloadLink = (uuid: string) => {
  return systemService.getDownloadUrl(uuid);
};

// Toggle stack trace expand
const toggleException = (uuid: string) => {
  expandedException.value[uuid] = !expandedException.value[uuid];
};

// Paginations change hooks
watch(logsPage, async () => {
  await fetchLogs();
});

watch(failedPage, async (newVal) => {
  await store.fetchFailedJobs({ page: newVal, per_page: 10 });
});

watch(reportsPage, async (newVal) => {
  await store.fetchReports({ page: newVal, per_page: 10 });
});

// Sparkline SVG Coordinates math
const throughputPoints = computed(() => {
  if (!store.metrics || !store.metrics.hourly_throughput || store.metrics.hourly_throughput.length === 0) {
    return '';
  }
  const data = store.metrics.hourly_throughput;
  const maxVal = Math.max(...data.map(d => d.total_jobs), 5);
  const w = 600;
  const h = 180;
  const padding = 20;
  const xStep = data.length > 1 ? (w - 2 * padding) / (data.length - 1) : 0;

  return data.map((d, i) => {
    const x = padding + i * xStep;
    const y = h - padding - (d.total_jobs / maxVal) * (h - 2 * padding);
    return `${x},${y}`;
  });
});

const throughputLinePath = computed(() => {
  const pts = throughputPoints.value;
  if (!pts || pts.length === 0) return '';
  return `M ${pts.join(' L ')}`;
});

const throughputAreaPath = computed(() => {
  const pts = throughputPoints.value;
  if (!pts || pts.length === 0) return '';
  const w = 600;
  const h = 180;
  const padding = 20;
  return `M ${pts[0]} L ${pts.join(' L ')} L ${w - padding},${h - padding} L ${padding},${h - padding} Z`;
});
</script>

<template>
  <div class="space-y-8 pb-12 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-secondary">Background Processing</h1>
        <p class="text-sm text-neutral-muted mt-1">Monitor queues, view job logs, manage scheduled tasks, and retry failed operations.</p>
      </div>

      <div class="flex items-center gap-4 bg-white/60 border border-neutral-ivory p-2.5 rounded-xl backdrop-blur">
        <label class="flex items-center gap-2 text-xs font-semibold text-neutral-muted select-none cursor-pointer">
          <input
            type="checkbox"
            v-model="autoRefresh"
            class="h-4 w-4 rounded border-neutral-ivory text-secondary focus:ring-primary focus:ring-offset-0 bg-neutral-background cursor-pointer"
          />
          Auto-refresh (10s)
        </label>
        <button
          @click="loadDashboardData"
          :disabled="isLoadingData || store.isLoading"
          class="p-1.5 rounded-lg bg-neutral-background hover:bg-neutral-background text-neutral-muted disabled:opacity-50 transition-colors cursor-pointer"
          title="Refresh Data"
        >
          <svg class="h-4 w-4" :class="{ 'animate-spin': isLoadingData || store.isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89M21 24v-5h-.581" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-neutral-ivory flex flex-wrap gap-2">
      <button
        v-for="tab in (['overview', 'logs', 'failed', 'scheduler', 'reports'] as const)"
        :key="tab"
        @click="activeTab = tab"
        :class="[
          activeTab === tab 
            ? 'border-secondary text-secondary font-bold' 
            : 'border-transparent text-neutral-muted hover:text-neutral-black hover:border-neutral-ivory',
          'px-5 py-3 border-b-2 font-medium text-sm transition-all capitalize cursor-pointer'
        ]"
      >
        {{ tab === 'failed' ? `Failed Jobs (${store.failedJobsTotal})` : tab }}
      </button>
    </div>

    <!-- MAIN LOADING INDICATOR -->
    <div v-if="isLoadingData && !store.queues.length" class="flex flex-col items-center justify-center py-20 space-y-4">
      <div class="h-10 w-10 border-4 border-secondary/20 border-t-primary rounded-full animate-spin"></div>
      <p class="text-sm text-neutral-muted">Loading system state...</p>
    </div>

    <div v-else class="space-y-8">
      
      <!-- TAB 1: OVERVIEW -->
      <div v-if="activeTab === 'overview'" class="space-y-8 animate-fade-in">
        <!-- KPI Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-primary/5 rounded-full blur-xl group-hover:bg-primary/10 transition-all"></div>
            <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Total Jobs (24h)</span>
            <div class="text-3xl font-bold text-white mt-2">{{ store.metrics?.total_jobs_24h ?? 0 }}</div>
            <div class="text-xs text-neutral-muted mt-2">Background queue events handled.</div>
          </div>
          
          <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-secondary/5 rounded-full blur-xl group-hover:bg-secondary/10 transition-all"></div>
            <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Success Rate</span>
            <div class="text-3xl font-bold text-secondary mt-2">{{ store.metrics?.success_rate_24h ?? 100 }}%</div>
            <div class="text-xs text-neutral-muted mt-2">Successful job completions.</div>
          </div>

          <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-accent-gold/5 rounded-full blur-xl group-hover:bg-accent-gold/20 transition-all"></div>
            <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Avg Job Duration</span>
            <div class="text-3xl font-bold text-amber-400 mt-2">{{ store.metrics?.avg_duration_24h ?? 0 }}s</div>
            <div class="text-xs text-neutral-muted mt-2">Processing execution speed.</div>
          </div>

          <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
            <div class="absolute -right-6 -top-6 w-20 h-20 bg-accent-red/5 rounded-full blur-xl group-hover:bg-accent-red/10 transition-all"></div>
            <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Failed Jobs (24h)</span>
            <div class="text-3xl font-bold text-accent-red mt-2">{{ store.metrics?.failed_jobs_24h ?? 0 }}</div>
            <div class="text-xs text-neutral-muted mt-2">Jobs written to failed table.</div>
          </div>
        </div>

        <!-- Throughput Chart and Clear Queues Panel -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
          <!-- Throughput Chart (Native SVG) -->
          <div class="lg:col-span-8 bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl shadow-lg">
            <div class="flex justify-between items-center mb-6">
              <h2 class="text-lg font-bold text-neutral-black">Throughput Analysis (Last 24h)</h2>
              <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-neutral-background text-neutral-muted">Hourly Volume</span>
            </div>
            
            <div v-if="!store.metrics?.hourly_throughput?.length" class="flex items-center justify-center h-48 border border-dashed border-neutral-ivory rounded-xl text-neutral-muted text-sm">
              No throughput data recorded. Run some background jobs to display metrics.
            </div>
            
            <div v-else class="relative w-full">
              <svg viewBox="0 0 600 180" class="w-full h-auto overflow-visible">
                <defs>
                  <linearGradient id="chartGlow" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#34d399" stop-opacity="0.25" />
                    <stop offset="100%" stop-color="#34d399" stop-opacity="0.0" />
                  </linearGradient>
                </defs>
                
                <!-- Background Grid Lines -->
                <line x1="20" y1="20" x2="580" y2="20" stroke="#1e293b" stroke-dasharray="4" />
                <line x1="20" y1="65" x2="580" y2="65" stroke="#1e293b" stroke-dasharray="4" />
                <line x1="20" y1="110" x2="580" y2="110" stroke="#1e293b" stroke-dasharray="4" />
                <line x1="20" y1="160" x2="580" y2="160" stroke="#334155" />

                <!-- Filled Area Under Line -->
                <path :d="throughputAreaPath" fill="url(#chartGlow)" />
                
                <!-- Line Path -->
                <path :d="throughputLinePath" fill="none" stroke="#34d399" stroke-width="2.5" stroke-linecap="round" />
                
                <!-- Markers -->
                <circle
                  v-for="(pt, idx) in throughputPoints"
                  :key="idx"
                  :cx="pt.split(',')[0]"
                  :cy="pt.split(',')[1]"
                  r="4"
                  fill="#10b981"
                  stroke="#020617"
                  stroke-width="1.5"
                />
              </svg>
              <!-- Timeline labels -->
              <div class="flex justify-between text-[10px] text-neutral-muted font-mono mt-2 px-4">
                <span>24 hours ago</span>
                <span>12 hours ago</span>
                <span>Just Now</span>
              </div>
            </div>
          </div>

          <!-- Queue Control Panel -->
          <div class="lg:col-span-4 bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl shadow-lg flex flex-col justify-between">
            <div>
              <h2 class="text-lg font-bold text-neutral-black mb-2">Queue Health Actions</h2>
              <p class="text-xs text-neutral-muted mb-6">Manually clear pending partitions. This deletes all unreserved jobs.</p>
              
              <div class="space-y-4">
                <div v-for="q in store.queues" :key="q.name" class="flex items-center justify-between p-3.5 bg-neutral-background/40 border border-neutral-ivory/80 rounded-xl">
                  <div>
                    <h3 class="text-sm font-semibold capitalize text-neutral-black">{{ q.name }} Channel</h3>
                    <p class="text-[10px] text-neutral-muted font-mono mt-0.5">{{ q.pending_count }} pending</p>
                  </div>
                  <button
                    v-if="can('manage_queues')"
                    @click="handleClearQueue(q.name)"
                    :disabled="q.pending_count === 0"
                    class="text-xs px-3 py-1.5 rounded-lg border border-accent-red/20 bg-accent-red/10 text-accent-red hover:bg-accent-red/15 disabled:opacity-35 transition-all cursor-pointer font-medium"
                  >
                    Flush
                  </button>
                </div>
              </div>
            </div>

            <!-- Fast Manual report dispatcher -->
            <div class="border-t border-neutral-ivory mt-6 pt-6">
              <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-muted mb-3">Quick Manual Report Build</h3>
              <div class="flex gap-2">
                <select
                  v-model="selectedReportPeriod"
                  class="bg-neutral-background border border-neutral-ivory rounded-lg p-2 text-xs focus:border-primary focus:outline-none flex-1 text-neutral-muted"
                >
                  <option value="daily">Daily Summary</option>
                  <option value="weekly">Weekly Report</option>
                  <option value="monthly">Monthly Statistics</option>
                </select>
                <button
                  @click="handleTriggerReport"
                  class="bg-secondary hover:bg-secondary text-white text-xs px-4 py-2 rounded-lg font-bold transition-all shadow hover:shadow-soft cursor-pointer"
                >
                  Run
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Queues Breakdown Table -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-5 border-b border-neutral-ivory">
            <h2 class="text-lg font-bold text-neutral-black">Active Queue Pipelines</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-neutral-ivory/80 bg-neutral-background/20 text-xs font-bold uppercase tracking-wider text-neutral-muted font-mono">
                  <th class="px-6 py-4">Queue Name</th>
                  <th class="px-6 py-4">Status</th>
                  <th class="px-6 py-4 text-center">Pending Jobs</th>
                  <th class="px-6 py-4 text-center">Running Jobs</th>
                  <th class="px-6 py-4 text-center">Total Failed</th>
                  <th class="px-6 py-4 text-center">Avg Latency</th>
                  <th class="px-6 py-4 text-center">Failure Rate</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-ivory text-sm">
                <tr v-for="q in store.queues" :key="q.name" class="hover:bg-neutral-background/10 transition-colors">
                  <td class="px-6 py-4 font-mono font-semibold text-neutral-black capitalize">{{ q.name }}</td>
                  <td class="px-6 py-4">
                    <span
                      :class="[
                        q.status === 'active' ? 'bg-secondary/10 text-secondary border-secondary/20' :
                        q.status === 'idle' ? 'bg-accent-gold/20 text-amber-400 border-accent-gold/30' :
                        'bg-neutral-background/40 text-neutral-muted border-neutral-ivory/60',
                        'px-2.5 py-0.5 rounded-full text-xs font-semibold border'
                      ]"
                    >
                      {{ q.status === 'active' ? 'Processing' : q.status === 'idle' ? 'Idle Jobs' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center font-mono font-semibold">{{ q.pending_count }}</td>
                  <td class="px-6 py-4 text-center font-mono font-semibold text-amber-400">{{ q.active_count }}</td>
                  <td class="px-6 py-4 text-center font-mono font-semibold text-accent-red">{{ q.failed_count }}</td>
                  <td class="px-6 py-4 text-center font-mono">
                    {{ store.metrics?.queue_breakdown[q.name]?.avg_duration ?? '0.000' }}s
                  </td>
                  <td class="px-6 py-4 text-center font-mono text-accent-red">
                    {{ store.metrics?.queue_breakdown[q.name]?.failure_rate ?? 0 }}%
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB 2: EXECUTION HISTORY LOGS -->
      <div v-if="activeTab === 'logs'" class="space-y-6 animate-fade-in">
        <!-- Filters Card -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl shadow-lg">
          <form @submit.prevent="applyLogFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1.5">Job Class Name</label>
              <input
                v-model="logSearch"
                placeholder="e.g. SendEmail"
                class="w-full bg-neutral-background border border-neutral-ivory p-2.5 text-sm rounded-lg focus:border-primary focus:outline-none text-neutral-black"
              />
            </div>
            
            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1.5">Queue Priority</label>
              <select
                v-model="logQueue"
                class="w-full bg-neutral-background border border-neutral-ivory p-2.5 text-sm rounded-lg focus:border-primary focus:outline-none text-neutral-black"
              >
                <option value="">All Priorities</option>
                <option value="high">High</option>
                <option value="default">Default</option>
                <option value="low">Low</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1.5">Result Status</label>
              <select
                v-model="logStatus"
                class="w-full bg-neutral-background border border-neutral-ivory p-2.5 text-sm rounded-lg focus:border-primary focus:outline-none text-neutral-black"
              >
                <option value="">All Statuses</option>
                <option value="processing">Processing</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
              </select>
            </div>

            <div class="flex gap-2">
              <button
                type="submit"
                class="flex-1 bg-secondary hover:bg-secondary text-white font-bold p-2.5 rounded-lg text-sm transition-all cursor-pointer"
              >
                Filter
              </button>
              <button
                type="button"
                @click="resetLogFilters"
                class="bg-neutral-background hover:bg-neutral-background text-neutral-muted font-semibold p-2.5 rounded-lg text-sm transition-all cursor-pointer"
              >
                Reset
              </button>
            </div>
          </form>
        </div>

        <!-- Logs Table -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 rounded-2xl shadow-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-neutral-ivory/80 bg-neutral-background/20 text-xs font-bold uppercase tracking-wider text-neutral-muted font-mono">
                  <th class="px-6 py-4">Job Details</th>
                  <th class="px-6 py-4">Queue</th>
                  <th class="px-6 py-4 text-center">Attempts</th>
                  <th class="px-6 py-4 text-center">Status</th>
                  <th class="px-6 py-4 text-center">Started At</th>
                  <th class="px-6 py-4 text-center">Duration</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-ivory text-sm">
                <tr v-if="store.jobLogs.length === 0">
                  <td colspan="6" class="px-6 py-10 text-center text-neutral-muted">
                    No execution logs found matching criteria.
                  </td>
                </tr>
                <tr v-for="log in store.jobLogs" :key="log.id" class="hover:bg-neutral-background/10 transition-colors">
                  <td class="px-6 py-4">
                    <div class="font-semibold text-neutral-black">{{ log.job_name }}</div>
                    <div class="text-[10px] text-neutral-muted font-mono mt-0.5">{{ log.job_uuid }}</div>
                    <!-- Error log sneakpeek -->
                    <div v-if="log.failure_reason" class="text-xs text-accent-red mt-1.5 max-w-lg overflow-hidden text-ellipsis line-clamp-1 border-l border-accent-red/20 pl-2">
                      {{ log.failure_reason }}
                    </div>
                  </td>
                  <td class="px-6 py-4 capitalize font-mono text-xs text-neutral-muted">
                    {{ log.queue }}
                  </td>
                  <td class="px-6 py-4 text-center font-mono text-xs">
                    {{ log.attempts }}
                  </td>
                  <td class="px-6 py-4 text-center">
                    <span
                      :class="[
                        log.status === 'completed' ? 'bg-secondary/10 text-secondary border-secondary/20' :
                        log.status === 'failed' ? 'bg-accent-red/10 text-accent-red border-accent-red/20' :
                        'bg-accent-gold/20 text-amber-400 border-accent-gold/30',
                        'px-2 py-0.5 rounded text-[10px] uppercase font-bold border'
                      ]"
                    >
                      {{ log.status }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center font-mono text-xs text-neutral-muted">
                    {{ log.started_at ? new Date(log.started_at).toLocaleString() : 'Pending' }}
                  </td>
                  <td class="px-6 py-4 text-center font-mono text-neutral-muted">
                    {{ log.duration !== null ? log.duration.toFixed(3) + 's' : '-' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination footer -->
          <div v-if="store.jobLogsTotal > logsPerPage" class="px-6 py-4 border-t border-neutral-ivory flex justify-between items-center bg-neutral-background/20">
            <span class="text-xs text-neutral-muted">
              Showing {{ (logsPage - 1) * logsPerPage + 1 }} to {{ Math.min(logsPage * logsPerPage, store.jobLogsTotal) }} of {{ store.jobLogsTotal }} logs
            </span>
            <div class="flex gap-2">
              <button
                @click="logsPage--"
                :disabled="logsPage === 1"
                class="px-3 py-1.5 rounded bg-neutral-background hover:bg-neutral-background disabled:opacity-40 text-xs font-semibold transition cursor-pointer"
              >
                Previous
              </button>
              <button
                @click="logsPage++"
                :disabled="logsPage * logsPerPage >= store.jobLogsTotal"
                class="px-3 py-1.5 rounded bg-neutral-background hover:bg-neutral-background disabled:opacity-40 text-xs font-semibold transition cursor-pointer"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 3: FAILED JOBS INSPECTOR -->
      <div v-if="activeTab === 'failed'" class="space-y-6 animate-fade-in">
        <!-- Actions Ribbon -->
        <div class="flex justify-between items-center bg-white/40 border border-neutral-ivory/80 p-4 rounded-xl backdrop-blur">
          <span class="text-sm text-neutral-muted">
            There are <strong class="text-accent-red font-bold font-mono">{{ store.failedJobsTotal }}</strong> failed records waiting in store.
          </span>
          <div v-if="can('manage_queues') && store.failedJobs.length > 0" class="flex gap-2">
            <button
              @click="handleRetryAll"
              :disabled="store.isActionLoading"
              class="px-4 py-2 bg-secondary hover:bg-secondary text-white text-xs font-bold rounded-lg transition-all shadow hover:shadow-soft cursor-pointer"
            >
              Retry All
            </button>
            <button
              @click="handleDeleteAllFailed"
              :disabled="store.isActionLoading"
              class="px-4 py-2 bg-accent-red/10 hover:bg-accent-red/15 border border-accent-red/20 text-accent-red text-xs font-bold rounded-lg transition-all cursor-pointer"
            >
              Flush All
            </button>
          </div>
        </div>

        <!-- Failed Jobs Card Stack -->
        <div class="space-y-4">
          <div v-if="store.failedJobs.length === 0" class="bg-white/40 border border-neutral-ivory/80 p-10 rounded-2xl text-center text-neutral-muted">
            No failed jobs present. Infrastructure is running healthy!
          </div>

          <div
            v-for="job in store.failedJobs"
            :key="job.id"
            class="bg-white/40 border border-neutral-ivory/80 rounded-2xl p-6 shadow-md relative group hover:border-accent-red/20 transition-all duration-300"
          >
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-neutral-ivory/50 pb-4">
              <div>
                <h3 class="text-base font-bold text-neutral-black tracking-wide">{{ job.name }}</h3>
                <div class="flex flex-wrap gap-2 items-center mt-1.5 text-xs text-neutral-muted">
                  <span class="font-mono bg-neutral-background px-2 py-0.5 rounded text-[10px] text-accent-red border border-accent-red/10">UUID: {{ job.uuid }}</span>
                  <span class="capitalize font-mono bg-neutral-background px-2 py-0.5 rounded text-[10px]">Queue: {{ job.queue }}</span>
                  <span class="font-mono text-[10px] text-neutral-muted">Failed at: {{ new Date(job.failed_at).toLocaleString() }}</span>
                </div>
              </div>
              
              <div v-if="can('manage_queues')" class="flex gap-2 shrink-0">
                <button
                  @click="handleRetryJob(job.uuid)"
                  :disabled="store.isActionLoading"
                  class="bg-secondary/10 hover:bg-secondary/20 border border-secondary/20 text-secondary text-xs font-bold px-3 py-1.5 rounded-lg transition-all cursor-pointer"
                >
                  Retry
                </button>
                <button
                  @click="handleDeleteFailedJob(job.uuid)"
                  :disabled="store.isActionLoading"
                  class="bg-accent-red/10 hover:bg-accent-red/15 border border-accent-red/20 text-accent-red text-xs font-bold px-3 py-1.5 rounded-lg transition-all cursor-pointer"
                >
                  Forget
                </button>
              </div>
            </div>

            <!-- Exception trace panel -->
            <div class="mt-4">
              <button
                @click="toggleException(job.uuid)"
                class="flex items-center gap-1.5 text-xs font-semibold text-neutral-muted hover:text-accent-red transition-colors cursor-pointer select-none"
              >
                <svg class="h-4.5 w-4.5 transition-transform" :class="{ 'rotate-90': expandedException[job.uuid] }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                View Exception Details
              </button>
              
              <div v-if="expandedException[job.uuid]" class="mt-3 bg-neutral-background p-4 rounded-xl border border-neutral-ivory overflow-x-auto text-[11px] font-mono text-accent-red max-h-[220px] overflow-y-auto leading-relaxed">
                {{ job.exception }}
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination footer -->
        <div v-if="store.failedJobsTotal > 10" class="flex justify-end gap-2 pt-2">
          <button
            @click="failedPage--"
            :disabled="failedPage === 1"
            class="px-3 py-1.5 rounded bg-neutral-background hover:bg-neutral-background disabled:opacity-40 text-xs font-semibold transition cursor-pointer"
          >
            Previous
          </button>
          <button
            @click="failedPage++"
            :disabled="failedPage * 10 >= store.failedJobsTotal"
            class="px-3 py-1.5 rounded bg-neutral-background hover:bg-neutral-background disabled:opacity-40 text-xs font-semibold transition cursor-pointer"
          >
            Next
          </button>
        </div>
      </div>

      <!-- TAB 4: SCHEDULED TASKS -->
      <div v-if="activeTab === 'scheduler'" class="space-y-6 animate-fade-in">
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-5 border-b border-neutral-ivory">
            <h2 class="text-lg font-bold text-neutral-black">Scheduled Platform Operations</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-neutral-ivory/80 bg-neutral-background/20 text-xs font-bold uppercase tracking-wider text-neutral-muted font-mono">
                  <th class="px-6 py-4">Task Description</th>
                  <th class="px-6 py-4">Cron Rule</th>
                  <th class="px-6 py-4">Run Interval</th>
                  <th class="px-6 py-4 text-center">Next Scheduled Run</th>
                  <th class="px-6 py-4 text-center" v-if="can('manage_scheduler')">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-ivory text-sm">
                <tr v-for="task in store.schedulerTasks" :key="task.id" class="hover:bg-neutral-background/10 transition-colors">
                  <td class="px-6 py-4">
                    <div class="font-semibold text-neutral-black">{{ task.description }}</div>
                    <div class="text-[10px] text-neutral-muted font-mono mt-0.5">{{ task.command }}</div>
                  </td>
                  <td class="px-6 py-4 font-mono text-xs text-secondary">
                    {{ task.expression }}
                  </td>
                  <td class="px-6 py-4 text-neutral-muted text-xs">
                    {{ task.interval_description }}
                  </td>
                  <td class="px-6 py-4 text-center font-mono text-xs text-neutral-muted">
                    {{ task.next_run_at }}
                  </td>
                  <td class="px-6 py-4 text-center" v-if="can('manage_scheduler')">
                    <button
                      @click="handleRunTask(task.id, task.description)"
                      :disabled="store.isActionLoading"
                      class="bg-secondary/10 hover:bg-secondary/20 border border-secondary/20 text-secondary text-xs font-semibold px-3 py-1.5 rounded-lg transition-all cursor-pointer"
                    >
                      Trigger Now
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB 5: REPORTS CENTER -->
      <div v-if="activeTab === 'reports'" class="space-y-6 animate-fade-in">
        <!-- Reports Table -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-5 border-b border-neutral-ivory flex justify-between items-center">
            <h2 class="text-lg font-bold text-neutral-black">Generated System Reports</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-neutral-ivory/80 bg-neutral-background/20 text-xs font-bold uppercase tracking-wider text-neutral-muted font-mono">
                  <th class="px-6 py-4">Report Title</th>
                  <th class="px-6 py-4 text-center">Type</th>
                  <th class="px-6 py-4 text-center">Generated At</th>
                  <th class="px-6 py-4 text-center">Author</th>
                  <th class="px-6 py-4 text-center">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-ivory text-sm">
                <tr v-if="store.reports.length === 0">
                  <td colspan="5" class="px-6 py-10 text-center text-neutral-muted">
                    No reports generated yet. Use the Overview panel to run one manually.
                  </td>
                </tr>
                <tr v-for="r in store.reports" :key="r.id" class="hover:bg-neutral-background/10 transition-colors">
                  <td class="px-6 py-4">
                    <div class="font-semibold text-neutral-black">{{ r.title }}</div>
                    <div class="text-[10px] text-neutral-muted font-mono mt-0.5">{{ r.uuid }}</div>
                  </td>
                  <td class="px-6 py-4 text-center capitalize text-xs">
                    <span
                      :class="[
                        r.type === 'daily' ? 'bg-primary/10 text-primary border-primary/15' :
                        r.type === 'weekly' ? 'bg-purple-500/10 text-purple-400 border-purple-500/20' :
                        'bg-accent-gold/20 text-amber-400 border-accent-gold/30',
                        'px-2.5 py-0.5 rounded-full border'
                      ]"
                    >
                      {{ r.type }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center font-mono text-xs text-neutral-muted">
                    {{ new Date(r.generated_at).toLocaleString() }}
                  </td>
                  <td class="px-6 py-4 text-center text-neutral-muted">
                    {{ r.generated_by_user?.name ?? 'System Scheduler' }}
                  </td>
                  <td class="px-6 py-4 text-center">
                    <div class="flex items-center justify-center gap-2">
                      <a
                        :href="getDownloadLink(r.uuid)"
                        download
                        class="bg-secondary hover:bg-secondary text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-all shadow hover:shadow-soft cursor-pointer inline-block"
                      >
                        Download
                      </a>
                      <button
                        v-if="can('manage_queues')"
                        @click="handleDeleteReport(r.uuid)"
                        class="bg-accent-red/10 hover:bg-accent-red/15 border border-accent-red/20 text-accent-red text-xs font-bold px-3 py-1.5 rounded-lg transition-all cursor-pointer"
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination footer -->
          <div v-if="store.reportsTotal > 10" class="px-6 py-4 border-t border-neutral-ivory flex justify-between items-center bg-neutral-background/20">
            <span class="text-xs text-neutral-muted">
              Showing {{ (reportsPage - 1) * 10 + 1 }} to {{ Math.min(reportsPage * 10, store.reportsTotal) }} of {{ store.reportsTotal }} reports
            </span>
            <div class="flex gap-2">
              <button
                @click="reportsPage--"
                :disabled="reportsPage === 1"
                class="px-3 py-1.5 rounded bg-neutral-background hover:bg-neutral-background disabled:opacity-40 text-xs font-semibold transition cursor-pointer"
              >
                Previous
              </button>
              <button
                @click="reportsPage++"
                :disabled="reportsPage * 10 >= store.reportsTotal"
                class="px-3 py-1.5 rounded bg-neutral-background hover:bg-neutral-background disabled:opacity-40 text-xs font-semibold transition cursor-pointer"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
