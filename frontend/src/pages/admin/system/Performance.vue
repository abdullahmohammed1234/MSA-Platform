<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useSystemStore } from '@/stores/system';
import { useToastStore } from '@/components/feedback/toast';

const store = useSystemStore();
const toast = useToastStore();

const isLoadingData = ref(false);

const loadStats = async () => {
  isLoadingData.value = true;
  try {
    await store.fetchPerformanceStats();
  } catch (err) {
    toast.error('Failed to load performance metrics.');
  } finally {
    isLoadingData.value = false;
  }
};

onMounted(async () => {
  await loadStats();
});

// Cache variables
const stats = computed(() => store.performanceStats);

// Sparkline SVG Coordinates math for API Latency
const latencyPoints = computed(() => {
  if (!stats.value || !stats.value.recent_metrics || stats.value.recent_metrics.length === 0) {
    return [];
  }
  const data = stats.value.recent_metrics;
  const maxVal = Math.max(...data.map((d: any) => d.duration_ms), 100);
  const w = 600;
  const h = 180;
  const padding = 20;

  return data.map((d: any, i: number) => {
    const x = padding + (i / (data.length - 1)) * (w - 2 * padding);
    const y = h - padding - (d.duration_ms / maxVal) * (h - 2 * padding);
    return { x, y, duration: d.duration_ms, url: d.url };
  });
});

const latencyLinePath = computed(() => {
  const pts = latencyPoints.value;
  if (!pts || pts.length === 0) return '';
  return `M ${pts.map((p: any) => `${p.x},${p.y}`).join(' L ')}`;
});

const latencyAreaPath = computed(() => {
  const pts = latencyPoints.value;
  if (!pts || pts.length === 0) return '';
  const w = 600;
  const h = 180;
  const padding = 20;
  return `M ${pts[0].x},${pts[0].y} L ${pts.map((p: any) => `${p.x},${p.y}`).join(' L ')} L ${w - padding},${h - padding} L ${pts[0].x},${h - padding} Z`;
});
</script>

<template>
  <div class="space-y-8 pb-12 text-neutral-black">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">System Performance</h1>
        <p class="text-sm text-neutral-muted mt-1">Real-time application performance metrics, cache hit efficiency, slow query reports, and server statistics.</p>
      </div>

      <div class="flex items-center gap-4 bg-white/60 border border-neutral-ivory p-2.5 rounded-xl backdrop-blur">
        <button
          @click="loadStats"
          :disabled="isLoadingData || store.isLoading"
          class="flex items-center gap-2 px-4 py-2 rounded-lg bg-neutral-background hover:bg-neutral-background text-neutral-muted disabled:opacity-50 transition-colors cursor-pointer text-xs font-semibold"
          title="Refresh Data"
        >
          <svg class="h-4 w-4" :class="{ 'animate-spin': isLoadingData || store.isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89M21 24v-5h-.581" />
          </svg>
          Refresh Statistics
        </button>
      </div>
    </div>

    <!-- MAIN LOADING INDICATOR -->
    <div v-if="isLoadingData && !stats" class="flex flex-col items-center justify-center py-20 space-y-4">
      <div class="h-10 w-10 border-4 border-primary/15 border-t-primary rounded-full animate-spin"></div>
      <p class="text-sm text-neutral-muted">Profiling system metrics...</p>
    </div>

    <div v-else-if="!stats" class="text-center py-20 text-neutral-muted bg-white/40 rounded-2xl border border-neutral-ivory">
      No performance data collected. Try visiting some pages on the platform first.
    </div>

    <div v-else class="space-y-8 animate-fade-in">
      <!-- KPI Metrics Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Avg Response Duration -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
          <div class="absolute -right-6 -top-6 w-20 h-20 bg-primary/5 rounded-full blur-xl group-hover:bg-primary/10 transition-all"></div>
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Avg Latency</span>
          <div class="text-3xl font-bold text-white mt-2">{{ stats.averages?.duration_ms ?? 0 }} ms</div>
          <div class="text-xs text-neutral-muted mt-2">Server response duration.</div>
        </div>

        <!-- Cache Hit Rate -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
          <div class="absolute -right-6 -top-6 w-20 h-20 bg-secondary/5 rounded-full blur-xl group-hover:bg-secondary/10 transition-all"></div>
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Cache Efficiency</span>
          <div class="text-3xl font-bold text-secondary mt-2">{{ stats.cache?.hit_rate ?? 0 }}%</div>
          <div class="text-xs text-neutral-muted mt-2">Cache hit vs miss ratio (24h).</div>
        </div>

        <!-- DB Query Stats -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
          <div class="absolute -right-6 -top-6 w-20 h-20 bg-accent-gold/5 rounded-full blur-xl group-hover:bg-accent-gold/20 transition-all"></div>
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Database Load</span>
          <div class="text-3xl font-bold text-amber-400 mt-2">{{ stats.averages?.db_queries ?? 0 }} Q</div>
          <div class="text-xs text-neutral-muted mt-2">Avg: {{ stats.averages?.db_queries_time_ms ?? 0 }} ms query time.</div>
        </div>

        <!-- Peak Memory -->
        <div class="bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl relative overflow-hidden group shadow-lg">
          <div class="absolute -right-6 -top-6 w-20 h-20 bg-accent-red/5 rounded-full blur-xl group-hover:bg-accent-red/10 transition-all"></div>
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Memory Footprint</span>
          <div class="text-3xl font-bold text-accent-red mt-2">{{ stats.averages?.memory_mb ?? 0 }} MB</div>
          <div class="text-xs text-neutral-muted mt-2">Average Peak Memory usage.</div>
        </div>
      </div>

      <!-- Latency Trend Sparkline -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-8 bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl shadow-lg">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-bold text-neutral-black font-display">API Latency Trend (Last 50 requests)</h2>
            <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-neutral-background text-neutral-muted">HTTP Latency Profile</span>
          </div>

          <div v-if="!stats.recent_metrics?.length" class="flex items-center justify-center h-48 border border-dashed border-neutral-ivory rounded-xl text-neutral-muted text-sm">
            No profile requests recorded yet.
          </div>

          <div v-else class="relative w-full">
            <svg viewBox="0 0 600 180" class="w-full h-auto overflow-visible">
              <defs>
                <linearGradient id="latencyGlow" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="#6366f1" stop-opacity="0.25" />
                  <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0" />
                </linearGradient>
              </defs>
              
              <!-- Background Grid Lines -->
              <line x1="20" y1="20" x2="580" y2="20" stroke="#1e293b" stroke-dasharray="4" />
              <line x1="20" y1="65" x2="580" y2="65" stroke="#1e293b" stroke-dasharray="4" />
              <line x1="20" y1="110" x2="580" y2="110" stroke="#1e293b" stroke-dasharray="4" />
              <line x1="20" y1="160" x2="580" y2="160" stroke="#334155" />

              <!-- Filled Area Under Line -->
              <path :d="latencyAreaPath" fill="url(#latencyGlow)" />
              
              <!-- Line Path -->
              <path :d="latencyLinePath" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" />
              
              <!-- Markers with tooltips (styled via standard svg elements) -->
              <circle
                v-for="(pt, idx) in latencyPoints"
                :key="idx"
                :cx="pt.x"
                :cy="pt.y"
                r="3"
                fill="#818cf8"
                stroke="#0f172a"
                stroke-width="1.5"
                class="transition-all hover:r-5 cursor-pointer"
              >
                <title>{{ pt.url }} - {{ pt.duration }}ms</title>
              </circle>
            </svg>
            <div class="flex justify-between text-[10px] text-neutral-muted font-mono mt-2 px-4">
              <span>Oldest Request</span>
              <span>Timeline</span>
              <span>Latest Request</span>
            </div>
          </div>
        </div>

        <!-- Caching Metrics Panel -->
        <div class="lg:col-span-4 bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl shadow-lg flex flex-col justify-between">
          <div>
            <h2 class="text-lg font-bold text-neutral-black mb-2 font-display">Cache Statistics</h2>
            <p class="text-xs text-neutral-muted mb-6">Optimization ratio and memory caching counts over the last 24 hours.</p>
            
            <div class="space-y-4">
              <div class="flex justify-between p-3.5 bg-neutral-background/40 border border-neutral-ivory/85 rounded-xl">
                <div>
                  <h3 class="text-xs font-semibold text-neutral-muted">Cached Hits Count</h3>
                  <p class="text-[10px] text-neutral-muted font-mono mt-0.5">High efficiency memory reads</p>
                </div>
                <div class="text-right font-mono font-bold text-secondary text-sm">
                  {{ stats.cache?.cached_requests_count ?? 0 }}
                </div>
              </div>

              <div class="flex justify-between p-3.5 bg-neutral-background/40 border border-neutral-ivory/85 rounded-xl">
                <div>
                  <h3 class="text-xs font-semibold text-neutral-muted">Total API Hits (24h)</h3>
                  <p class="text-[10px] text-neutral-muted font-mono mt-0.5">Total measured volume</p>
                </div>
                <div class="text-right font-mono font-bold text-primary text-sm">
                  {{ stats.cache?.total_requests_24h ?? 0 }}
                </div>
              </div>

              <div class="flex justify-between p-3.5 bg-neutral-background/40 border border-neutral-ivory/85 rounded-xl">
                <div>
                  <h3 class="text-xs font-semibold text-neutral-muted">Queue Processing Delay</h3>
                  <p class="text-[10px] text-neutral-muted font-mono mt-0.5">Job average wait time</p>
                </div>
                <div class="text-right font-mono font-bold text-amber-400 text-sm">
                  {{ stats.queues?.avg_duration_s ?? 0 }} s
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-neutral-ivory mt-6 pt-6">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-muted mb-3">Queue Backlog</h3>
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-neutral-background/60 p-3 rounded-xl border border-neutral-ivory text-center">
                <span class="text-[10px] text-neutral-muted font-bold uppercase">Pending</span>
                <div class="text-lg font-bold text-white font-mono mt-1">{{ stats.queues?.pending ?? 0 }}</div>
              </div>
              <div class="bg-neutral-background/60 p-3 rounded-xl border border-neutral-ivory text-center">
                <span class="text-[10px] text-neutral-muted font-bold uppercase">Failed</span>
                <div class="text-lg font-bold text-accent-red font-mono mt-1">{{ stats.queues?.failed ?? 0 }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Slow Queries / Requests Table & Server Config -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Slow Queries -->
        <div class="lg:col-span-8 bg-white/40 backdrop-blur-md border border-neutral-ivory/80 rounded-2xl shadow-lg overflow-hidden">
          <div class="px-6 py-5 border-b border-neutral-ivory">
            <h2 class="text-lg font-bold text-neutral-black font-display">Bottlenecks & Slow API Requests (>300ms)</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b border-neutral-ivory/80 bg-neutral-background/20 text-xs font-bold uppercase tracking-wider text-neutral-muted font-mono">
                  <th class="px-6 py-4">URL / Endpoint</th>
                  <th class="px-6 py-4 text-center">Method</th>
                  <th class="px-6 py-4 text-center">Duration</th>
                  <th class="px-6 py-4 text-center">Queries</th>
                  <th class="px-6 py-4 text-center">Query Time</th>
                  <th class="px-6 py-4 text-center">Recorded At</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-ivory text-sm">
                <tr v-if="!stats.slow_requests?.length">
                  <td colspan="6" class="px-6 py-10 text-center text-neutral-muted">
                    No bottleneck requests recorded. Everything is performing under thresholds!
                  </td>
                </tr>
                <tr v-for="req in stats.slow_requests" :key="req.created_at" class="hover:bg-neutral-background/10 transition-colors">
                  <td class="px-6 py-4 font-mono text-xs max-w-xs truncate text-primary-light">{{ req.url }}</td>
                  <td class="px-6 py-4 text-center font-bold text-xs text-neutral-muted">{{ req.method }}</td>
                  <td class="px-6 py-4 text-center font-mono font-bold text-accent-red">{{ req.duration_ms }} ms</td>
                  <td class="px-6 py-4 text-center font-mono text-amber-400">{{ req.db_queries_count }}</td>
                  <td class="px-6 py-4 text-center font-mono">{{ req.db_queries_time_ms }} ms</td>
                  <td class="px-6 py-4 text-center text-xs text-neutral-muted">{{ req.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Server Config parameters -->
        <div class="lg:col-span-4 bg-white/40 backdrop-blur-md border border-neutral-ivory/80 p-6 rounded-2xl shadow-lg flex flex-col justify-between">
          <div>
            <h2 class="text-lg font-bold text-neutral-black mb-4 font-display">System Environment</h2>
            
            <div class="space-y-4">
              <div class="flex justify-between py-2.5 border-b border-neutral-ivory/50">
                <span class="text-xs text-neutral-muted">PHP Version</span>
                <span class="text-xs font-mono font-bold text-neutral-black">{{ stats.system?.php_version }}</span>
              </div>
              <div class="flex justify-between py-2.5 border-b border-neutral-ivory/50">
                <span class="text-xs text-neutral-muted">Laravel Version</span>
                <span class="text-xs font-mono font-bold text-neutral-black">{{ stats.system?.laravel_version }}</span>
              </div>
              <div class="flex justify-between py-2.5 border-b border-neutral-ivory/50">
                <span class="text-xs text-neutral-muted">OS Platform</span>
                <span class="text-xs font-mono font-bold text-neutral-black">{{ stats.system?.os }}</span>
              </div>
              <div class="flex justify-between py-2.5 border-b border-neutral-ivory/50">
                <span class="text-xs text-neutral-muted">DB Driver</span>
                <span class="text-xs font-mono font-bold text-neutral-black capitalize">{{ stats.system?.db_driver }}</span>
              </div>
              <div class="flex justify-between py-2.5">
                <span class="text-xs text-neutral-muted">Database Size</span>
                <span class="text-xs font-mono font-bold text-neutral-black">{{ stats.system?.db_size }}</span>
              </div>
            </div>
          </div>

          <div class="bg-primary/5 border border-primary/10 p-4 rounded-xl text-center text-[10px] text-neutral-muted leading-relaxed mt-6">
            Database index optimization and memory profiling middlewares are active. Old profiling sessions are pruned automatically after 7 days.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
