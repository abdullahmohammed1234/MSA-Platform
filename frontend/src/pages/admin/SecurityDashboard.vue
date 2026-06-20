<script setup lang="ts">
import { ref, onMounted, computed, onBeforeUnmount } from 'vue';
import { securityService, type SecurityDashboardData } from '@/services/admin/security.service';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();
const isLoading = ref(true);
const dashboardData = ref<SecurityDashboardData | null>(null);
const autoRefresh = ref(true);
const refreshInterval = ref<any>(null);

// Fetch dashboard data from backend
const loadData = async (silent = false) => {
  if (!silent) isLoading.value = true;
  try {
    dashboardData.value = await securityService.getDashboardData();
  } catch (err: any) {
    toast.error('Failed to load security dashboard data.');
    console.error(err);
  } finally {
    isLoading.value = false;
  }
};

// Auto refresh control
const toggleAutoRefresh = () => {
  if (autoRefresh.value) {
    startInterval();
  } else {
    clearInterval(refreshInterval.value);
  }
};

const startInterval = () => {
  clearInterval(refreshInterval.value);
  refreshInterval.value = setInterval(() => {
    loadData(true);
  }, 10000); // every 10 seconds
};

onMounted(() => {
  loadData();
  if (autoRefresh.value) {
    startInterval();
  }
});

onBeforeUnmount(() => {
  clearInterval(refreshInterval.value);
});

// Helper for severity colors
const getSeverityBadgeClass = (severity: string) => {
  switch (severity?.toLowerCase()) {
    case 'critical':
      return 'bg-accent-red/100/20 text-accent-red border-rose-500/30';
    case 'high':
      return 'bg-accent-gold/20 text-amber-400 border-amber-500/30';
    case 'medium':
      return 'bg-primary/50/20 text-primary border-primary/20';
    default:
      return 'bg-neutral-background/40 text-neutral-muted border-neutral-ivory';
  }
};

// Helper to format timestamps
const formatTime = (timestamp: string) => {
  if (!timestamp) return '-';
  const date = new Date(timestamp);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const formatDate = (timestamp: string) => {
  if (!timestamp) return '-';
  const date = new Date(timestamp);
  return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

// Dynamic chart max value
const chartMaxVal = computed(() => {
  if (!dashboardData.value?.chart_data) return 5;
  const logins = dashboardData.value.chart_data.failed_logins;
  const rateLimit = dashboardData.value.chart_data.rate_violations;
  return Math.max(...logins, ...rateLimit, 5);
});

// Failed Logins coordinates
const failedLoginsPoints = computed(() => {
  if (!dashboardData.value?.chart_data?.failed_logins?.length) return '';
  const data = dashboardData.value.chart_data.failed_logins;
  const max = chartMaxVal.value;
  const w = 600;
  const h = 160;
  const padding = 20;

  return data.map((val, i) => {
    const x = padding + (i / (data.length - 1)) * (w - 2 * padding);
    const y = h - padding - (val / max) * (h - 2 * padding);
    return `${x},${y}`;
  }).join(' ');
});

const failedLoginsLinePath = computed(() => {
  const pts = failedLoginsPoints.value;
  if (!pts) return '';
  return `M ${pts.split(' ').join(' L ')}`;
});

const failedLoginsAreaPath = computed(() => {
  const pts = failedLoginsPoints.value;
  if (!pts) return '';
  const w = 600;
  const h = 160;
  const padding = 20;
  const ptsArr = pts.split(' ');
  return `M ${ptsArr[0]} L ${ptsArr.join(' L ')} L ${w - padding},${h - padding} L ${padding},${h - padding} Z`;
});

// Rate Violations coordinates
const rateViolationsPoints = computed(() => {
  if (!dashboardData.value?.chart_data?.rate_violations?.length) return '';
  const data = dashboardData.value.chart_data.rate_violations;
  const max = chartMaxVal.value;
  const w = 600;
  const h = 160;
  const padding = 20;

  return data.map((val, i) => {
    const x = padding + (i / (data.length - 1)) * (w - 2 * padding);
    const y = h - padding - (val / max) * (h - 2 * padding);
    return `${x},${y}`;
  }).join(' ');
});

const rateViolationsLinePath = computed(() => {
  const pts = rateViolationsPoints.value;
  if (!pts) return '';
  return `M ${pts.split(' ').join(' L ')}`;
});

const rateViolationsAreaPath = computed(() => {
  const pts = rateViolationsPoints.value;
  if (!pts) return '';
  const w = 600;
  const h = 160;
  const padding = 20;
  const ptsArr = pts.split(' ');
  return `M ${ptsArr[0]} L ${ptsArr.join(' L ')} L ${w - padding},${h - padding} L ${padding},${h - padding} Z`;
});
</script>

<template>
  <div class="space-y-8 pb-12 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-accent-red flex items-center gap-3">
          <svg class="h-8 w-8 text-accent-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
          Security Center
        </h1>
        <p class="text-sm text-neutral-muted mt-1">Audit platform logs, rate limits, session details, and track security event anomalies.</p>
      </div>

      <div class="flex items-center gap-4 bg-white/60 border border-neutral-ivory p-2.5 rounded-xl backdrop-blur">
        <label class="flex items-center gap-2 text-xs font-semibold text-neutral-muted select-none cursor-pointer">
          <input
            type="checkbox"
            v-model="autoRefresh"
            @change="toggleAutoRefresh"
            class="h-4 w-4 rounded border-neutral-ivory text-rose-500 focus:ring-rose-500 focus:ring-offset-0 bg-neutral-background cursor-pointer"
          />
          Live Monitoring (10s)
        </label>
        <button
          @click="loadData(false)"
          :disabled="isLoading"
          class="p-1.5 rounded-lg bg-neutral-background hover:bg-neutral-background text-neutral-muted disabled:opacity-50 transition-colors cursor-pointer"
        >
          <svg class="h-4 w-4" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89M21 24v-5h-.581" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Main Loading Spinner -->
    <div v-if="isLoading && !dashboardData" class="flex flex-col items-center justify-center py-20 space-y-4">
      <div class="h-10 w-10 border-4 border-accent-red/20 border-t-rose-500 rounded-full animate-spin"></div>
      <p class="text-sm text-neutral-muted">Loading security parameters...</p>
    </div>

    <div v-else-if="dashboardData" class="space-y-8">
      
      <!-- Metrics Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Failed Logins (24h / 7d)</span>
          <div class="text-3xl font-bold text-accent-red mt-2">
            {{ dashboardData.metrics.failed_logins_24h }}
            <span class="text-base text-neutral-muted font-medium">/ {{ dashboardData.metrics.failed_logins_7d }}</span>
          </div>
          <p class="text-xs text-neutral-muted mt-2">Bad credentials or auth attempts.</p>
        </div>

        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Rate Limit Exceeded (24h)</span>
          <div class="text-3xl font-bold text-amber-400 mt-2">
            {{ dashboardData.metrics.rate_violations_24h }}
            <span class="text-base text-neutral-muted font-medium">/ {{ dashboardData.metrics.rate_violations_7d }}</span>
          </div>
          <p class="text-xs text-neutral-muted mt-2">API rate limiter triggers logged.</p>
        </div>

        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Security Events (24h)</span>
          <div class="text-3xl font-bold text-primary mt-2">
            {{ dashboardData.metrics.total_security_events_24h }}
            <span class="text-base text-neutral-muted font-medium">/ {{ dashboardData.metrics.total_security_events_7d }}</span>
          </div>
          <p class="text-xs text-neutral-muted mt-2">Suspicious requests & warnings.</p>
        </div>

        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Admin Audit Trail (24h)</span>
          <div class="text-3xl font-bold text-secondary mt-2">
            {{ dashboardData.metrics.total_audit_logs_24h }}
            <span class="text-base text-neutral-muted font-medium">/ {{ dashboardData.metrics.total_audit_logs_7d }}</span>
          </div>
          <p class="text-xs text-neutral-muted mt-2">System modifications registered.</p>
        </div>
      </div>

      <!-- Incident Volume Chart & System Health -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- SVG Line Chart -->
        <div class="lg:col-span-8 bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl shadow-lg">
          <div class="flex justify-between items-center mb-6">
            <div>
              <h2 class="text-lg font-bold text-neutral-black">Incident Volume Analysis</h2>
              <p class="text-xs text-neutral-muted mt-0.5">Timeline comparing login blockages vs. rate limit triggers</p>
            </div>
            <div class="flex items-center gap-4 text-xs">
              <span class="flex items-center gap-1.5 text-accent-red">
                <span class="h-2 w-2 rounded-full bg-accent-red/100"></span> Failed Logins
              </span>
              <span class="flex items-center gap-1.5 text-amber-400">
                <span class="h-2 w-2 rounded-full bg-accent-gold"></span> Rate Limits
              </span>
            </div>
          </div>

          <div v-if="!dashboardData.chart_data.labels.length" class="flex items-center justify-center h-48 border border-dashed border-neutral-ivory rounded-xl text-neutral-muted text-sm">
            No incident logs to chart.
          </div>
          <div v-else class="relative w-full">
            <svg viewBox="0 0 600 160" class="w-full h-auto overflow-visible">
              <defs>
                <linearGradient id="roseGlow" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.15" />
                  <stop offset="100%" stop-color="#f43f5e" stop-opacity="0.0" />
                </linearGradient>
                <linearGradient id="amberGlow" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.15" />
                  <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.0" />
                </linearGradient>
              </defs>
              
              <!-- Background grid lines -->
              <line x1="20" y1="20" x2="580" y2="20" stroke="#1e293b" stroke-dasharray="4" />
              <line x1="20" y1="55" x2="580" y2="55" stroke="#1e293b" stroke-dasharray="4" />
              <line x1="20" y1="90" x2="580" y2="90" stroke="#1e293b" stroke-dasharray="4" />
              <line x1="20" y1="140" x2="580" y2="140" stroke="#334155" />

              <!-- Failed Logins Area and Line -->
              <path v-if="failedLoginsAreaPath" :d="failedLoginsAreaPath" fill="url(#roseGlow)" />
              <path v-if="failedLoginsLinePath" :d="failedLoginsLinePath" fill="none" stroke="#f43f5e" stroke-width="2.5" stroke-linecap="round" />

              <!-- Rate Violations Area and Line -->
              <path v-if="rateViolationsAreaPath" :d="rateViolationsAreaPath" fill="url(#amberGlow)" />
              <path v-if="rateViolationsLinePath" :d="rateViolationsLinePath" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" />
            </svg>
            <div class="flex justify-between text-[10px] text-neutral-muted font-mono mt-3 px-4">
              <span v-for="label in dashboardData.chart_data.labels" :key="label">{{ label }}</span>
            </div>
          </div>
        </div>

        <!-- System Health Status Card -->
        <div class="lg:col-span-4 bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl shadow-lg flex flex-col justify-between">
          <div>
            <h2 class="text-lg font-bold text-neutral-black mb-1">Platform Security State</h2>
            <p class="text-xs text-neutral-muted mb-6">Core system parameters validation</p>

            <div class="space-y-4">
              <div class="flex justify-between items-center pb-3 border-b border-neutral-ivory/60">
                <span class="text-xs text-neutral-muted">Database Status</span>
                <span :class="[dashboardData.system_health.db_status === 'Healthy' ? 'text-secondary' : 'text-accent-red', 'text-xs font-bold font-mono']">
                  ● {{ dashboardData.system_health.db_status }}
                </span>
              </div>

              <div class="flex justify-between items-center pb-3 border-b border-neutral-ivory/60">
                <span class="text-xs text-neutral-muted">Failed Queue Jobs</span>
                <span :class="[dashboardData.system_health.failed_jobs === 0 ? 'text-secondary' : 'text-accent-red', 'text-xs font-bold font-mono']">
                  {{ dashboardData.system_health.failed_jobs }} failed
                </span>
              </div>

              <div class="flex justify-between items-center pb-3 border-b border-neutral-ivory/60">
                <span class="text-xs text-neutral-muted">Active User Sessions</span>
                <span class="text-xs font-bold text-neutral-black font-mono">
                  {{ dashboardData.system_health.active_sessions }} active
                </span>
              </div>

              <div class="flex justify-between items-center pb-3 border-b border-neutral-ivory/60">
                <span class="text-xs text-neutral-muted">HSTS Headers Enforced</span>
                <span class="text-xs font-bold text-secondary font-mono">
                  {{ dashboardData.system_health.https_enforced }}
                </span>
              </div>

              <div class="flex justify-between items-center">
                <span class="text-xs text-neutral-muted">Application Environment</span>
                <span class="text-xs font-bold text-primary font-mono capitalize">
                  {{ dashboardData.system_health.app_env }}
                </span>
              </div>
            </div>
          </div>

          <div class="bg-neutral-background/50 border border-neutral-ivory/80 rounded-xl p-3.5 mt-6 flex gap-3 items-center">
            <div :class="[dashboardData.system_health.app_debug.includes('Warning') ? 'bg-accent-red/10 text-accent-red' : 'bg-secondary/10 text-secondary', 'p-2 rounded-lg shrink-0']">
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <div>
              <h3 class="text-xs font-bold text-neutral-black">APP_DEBUG Parameter</h3>
              <p class="text-[10px] text-neutral-muted mt-0.5">{{ dashboardData.system_health.app_debug }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Timelines / Feeds Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Security Incidents Feed -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 rounded-2xl shadow-lg p-6 flex flex-col justify-between">
          <div>
            <h3 class="text-lg font-bold text-neutral-black mb-1 flex items-center gap-2.5">
              <span class="h-2 w-2 rounded-full bg-accent-red/100 animate-ping"></span>
              Security Incidents Alert Feed
            </h3>
            <p class="text-xs text-neutral-muted mb-6">Unauthorized access, login failures, and rate limit violations.</p>

            <div class="space-y-4 max-h-[420px] overflow-y-auto pr-1">
              <div v-if="!dashboardData.recent_security_events.length" class="text-neutral-muted text-center py-10 text-sm border border-dashed border-neutral-ivory rounded-xl">
                No security alerts registered.
              </div>

              <div
                v-for="event in dashboardData.recent_security_events"
                :key="event.id"
                class="p-4 bg-neutral-background/40 border border-neutral-ivory/60 rounded-xl hover:border-neutral-ivory transition-colors"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <span :class="[getSeverityBadgeClass(event.severity), 'px-2 py-0.5 text-[9px] font-mono border rounded uppercase font-bold']">
                      {{ event.severity }}
                    </span>
                    <h4 class="text-sm font-semibold text-neutral-black capitalize mt-2">{{ event.event_type.replace('_', ' ') }}</h4>
                  </div>
                  <span class="text-[10px] text-neutral-muted font-mono">
                    {{ formatDate(event.created_at) }} {{ formatTime(event.created_at) }}
                  </span>
                </div>
                <p class="text-xs text-neutral-muted mt-2">{{ event.details }}</p>
                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-[10px] text-neutral-muted font-mono mt-3 border-t border-neutral-ivory pt-2">
                  <span>IP: {{ event.ip_address }}</span>
                  <span v-if="event.user">User: {{ event.user.name }}</span>
                  <span>Method: {{ event.method }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Administrative Audit Logs Feed -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 rounded-2xl shadow-lg p-6 flex flex-col justify-between">
          <div>
            <h3 class="text-lg font-bold text-neutral-black mb-1 flex items-center gap-2.5">
              <span class="h-2 w-2 rounded-full bg-secondary"></span>
              Administrative Audit Trail
            </h3>
            <p class="text-xs text-neutral-muted mb-6">Chronological logs of administrative changes and system mappings.</p>

            <div class="space-y-4 max-h-[420px] overflow-y-auto pr-1">
              <div v-if="!dashboardData.recent_audit_logs.length" class="text-neutral-muted text-center py-10 text-sm border border-dashed border-neutral-ivory rounded-xl">
                No administrative audit logs found.
              </div>

              <div
                v-for="log in dashboardData.recent_audit_logs"
                :key="log.id"
                class="p-4 bg-neutral-background/40 border border-neutral-ivory/60 rounded-xl hover:border-neutral-ivory transition-colors"
              >
                <div class="flex justify-between items-start">
                  <div>
                    <span class="px-2 py-0.5 text-[9px] font-mono border border-secondary/20 bg-secondary/10 text-secondary rounded uppercase font-bold">
                      Audit
                    </span>
                    <h4 class="text-sm font-semibold text-neutral-black capitalize mt-2">{{ log.action.replace('_', ' ') }}</h4>
                  </div>
                  <span class="text-[10px] text-neutral-muted font-mono">
                    {{ formatDate(log.created_at) }} {{ formatTime(log.created_at) }}
                  </span>
                </div>
                <p class="text-xs text-neutral-muted mt-2">{{ log.description }}</p>
                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-[10px] text-neutral-muted font-mono mt-3 border-t border-neutral-ivory pt-2">
                  <span>IP: {{ log.ip_address || 'unknown' }}</span>
                  <span v-if="log.user">By: {{ log.user.name }}</span>
                  <span v-if="log.target_type">Target: {{ log.target_type.split('\\').pop() }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>
