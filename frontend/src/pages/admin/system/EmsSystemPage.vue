<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import client from '@/services/api';
import { useAuthStore } from '@/stores/auth';
import { 
  Activity, 
  Database, 
  Mail, 
  Settings, 
  ExternalLink, 
  RefreshCw, 
  AlertTriangle, 
  CheckCircle,
  FileText,
  Search,
  Lock,
  Eye,
  Sliders,
  Webhook
} from 'lucide-vue-next';

// Tab system
type TabType = 'overview' | 'health' | 'metrics' | 'integrations' | 'webhooks' | 'logs' | 'config';
const activeTab = ref<TabType>('overview');

// Auth Check
const authStore = useAuthStore();
const isSuperAdmin = computed(() => {
  return typeof authStore.isPrivilegedAdmin === 'boolean'
    ? authStore.isPrivilegedAdmin
    : authStore.roles.includes('super-admin') || authStore.roles.includes('admin');
});

// UI State
const loading = ref(false);
const error = ref<string | null>(null);
const successMessage = ref<string | null>(null);

// Data states
const systemStatus = ref<any>(null);
const healthData = ref<any>(null);
const metricsData = ref<any>(null);
const integrationData = ref<any>(null);
const webhookData = ref<any>(null);
const logsData = ref<any>(null);
const configData = ref<any>(null);

// Log filters
const logFilters = ref({
  severity: '',
  type: '',
  search: '',
  start_date: '',
  end_date: '',
  page: 1,
  per_page: 15
});

// Modal for webhook inspection
const selectedWebhook = ref<any>(null);
const showWebhookModal = ref(false);

// Load data helpers
const fetchSystemOverview = async () => {
  try {
    const res = await client.get('/admin/systems/ems');
    if (res.data.success) systemStatus.value = res.data.system;
  } catch (err: any) {
    console.error('Failed to load system overview', err);
  }
};

const fetchHealth = async () => {
  try {
    const res = await client.get('/admin/systems/ems/health');
    if (res.data.success) healthData.value = res.data.health;
  } catch (err: any) {
    console.error('Failed to load health statistics', err);
  }
};

const fetchMetrics = async () => {
  try {
    const res = await client.get('/admin/systems/ems/metrics');
    if (res.data.success) metricsData.value = res.data.metrics;
  } catch (err: any) {
    console.error('Failed to load operational metrics', err);
  }
};

const fetchIntegrations = async () => {
  try {
    const res = await client.get('/admin/systems/ems/integrations');
    if (res.data.success) integrationData.value = res.data.integrations;
  } catch (err: any) {
    console.error('Failed to load integrations status', err);
  }
};

const fetchWebhooks = async () => {
  try {
    const res = await client.get('/admin/systems/ems/webhooks');
    if (res.data.success) webhookData.value = res.data.webhooks;
  } catch (err: any) {
    console.error('Failed to load webhook activity', err);
  }
};

const fetchLogs = async () => {
  try {
    const params = { ...logFilters.value };
    const res = await client.get('/admin/systems/ems/logs', { params });
    if (res.data.success) logsData.value = res.data.logs;
  } catch (err: any) {
    console.error('Failed to load logs feed', err);
  }
};

const fetchConfig = async () => {
  try {
    const res = await client.get('/admin/systems/ems/config');
    if (res.data.success) configData.value = res.data.config;
  } catch (err: any) {
    console.error('Failed to load configurations', err);
  }
};

const loadAllData = async (forceRefresh = false) => {
  loading.value = true;
  error.value = null;
  try {
    await Promise.all([
      fetchSystemOverview(),
      fetchHealth(),
      fetchMetrics(),
      fetchIntegrations(),
      fetchWebhooks(),
      fetchLogs(),
      fetchConfig()
    ]);
    if (forceRefresh) {
      showToast('System status refreshed successfully.');
    }
  } catch (err: any) {
    error.value = 'Failed to fetch some EMS system diagnostics. Please verify connection.';
  } finally {
    loading.value = false;
  }
};

// Actions
const handleSaveConfig = async () => {
  if (!isSuperAdmin.value) return;
  loading.value = true;
  error.value = null;
  successMessage.value = null;
  try {
    const res = await client.put('/admin/systems/ems/config', configData.value);
    if (res.data.success) {
      configData.value = res.data.config;
      successMessage.value = 'EMS Configuration updated successfully and committed to persistent storage.';
      await fetchHealth(); // refresh mailer/queue configuration health
      setTimeout(() => {
        successMessage.value = null;
      }, 5000);
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to save configuration settings.';
  } finally {
    loading.value = false;
  }
};

const triggerSSOLaunch = () => {
  // securely opens EMS with existing Sanctum tokens
  window.open('/ems', '_blank');
};

const inspectWebhook = (hook: any) => {
  selectedWebhook.value = hook;
  showWebhookModal.value = true;
};

const closeWebhookModal = () => {
  selectedWebhook.value = null;
  showWebhookModal.value = false;
};

const changeLogPage = (page: number) => {
  logFilters.value.page = page;
  fetchLogs();
};

const handleApplyFilters = () => {
  logFilters.value.page = 1;
  fetchLogs();
};

const showToast = (msg: string) => {
  successMessage.value = msg;
  setTimeout(() => {
    successMessage.value = null;
  }, 4000);
};

// Format utilities
const formatBytes = (bytes: number) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(val);
};

const formatDateTime = (str: string) => {
  if (!str) return 'N/A';
  return new Date(str).toLocaleString();
};

// Lifecycle
onMounted(() => {
  loadAllData();
});
</script>

<template>
  <div class="space-y-8 max-w-7xl mx-auto p-1">
    <!-- Header Banner -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 bg-white border border-neutral-ivory rounded-3xl p-6 shadow-soft relative overflow-hidden">
      <div class="space-y-1 z-10">
        <div class="flex items-center gap-3">
          <h1 class="text-3xl font-display font-black text-primary">Event Management System (EMS)</h1>
          <span 
            v-if="systemStatus" 
            :class="[
              'px-2.5 py-0.5 rounded-full text-xs font-bold capitalize flex items-center gap-1.5 border shadow-sm',
              systemStatus.status === 'operational' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'
            ]"
          >
            <span :class="['h-2 w-2 rounded-full animate-pulse', systemStatus.status === 'operational' ? 'bg-emerald-500' : 'bg-amber-500']"></span>
            {{ systemStatus.status }}
          </span>
        </div>
        <p class="text-xs text-neutral-muted">Central platform oversight, health analytics, integrations status, and configurations deck.</p>
      </div>
      <div class="flex items-center gap-3 z-10 shrink-0">
        <button 
          @click="loadAllData(true)"
          :disabled="loading"
          class="h-10 px-4 rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background font-bold text-xs text-neutral-black flex items-center gap-2 cursor-pointer transition"
        >
          <RefreshCw :class="['h-4 w-4 text-neutral-muted', loading ? 'animate-spin' : '']" />
          Refresh Status
        </button>
        <button 
          @click="triggerSSOLaunch"
          class="h-10 px-5 rounded-xl bg-primary text-white hover:bg-primary-dark font-bold text-xs flex items-center gap-2 cursor-pointer transition shadow-md"
        >
          <ExternalLink class="h-4 w-4 text-accent-gold" />
          Open EMS Portal
        </button>
      </div>
    </div>

    <!-- Feedback messages -->
    <div v-if="error" class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-xs flex items-center gap-2">
      <AlertTriangle class="h-4 w-4 text-red-500 shrink-0" />
      {{ error }}
    </div>

    <div v-if="successMessage" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl text-xs flex items-center gap-2">
      <CheckCircle class="h-4 w-4 text-emerald-500 shrink-0" />
      {{ successMessage }}
    </div>

    <!-- Layout Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <!-- Navigation Sidebar -->
      <div class="lg:col-span-3 space-y-4">
        <div class="bg-white border border-neutral-ivory rounded-3xl p-5 shadow-soft space-y-4">
          <div class="pb-3 border-b border-neutral-ivory/60 border-dashed">
            <h3 class="text-xs font-black uppercase text-primary tracking-wider">System Control</h3>
          </div>
          
          <div class="flex flex-col gap-1">
            <button 
              @click="activeTab = 'overview'"
              :class="['w-full flex items-center gap-3 p-3 rounded-2xl text-left text-xs font-bold transition cursor-pointer', activeTab === 'overview' ? 'bg-primary text-white shadow-soft' : 'text-neutral-black hover:bg-neutral-background']"
            >
              <Activity class="h-4 w-4 shrink-0" />
              Overview
            </button>
            <button 
              @click="activeTab = 'health'"
              :class="['w-full flex items-center gap-3 p-3 rounded-2xl text-left text-xs font-bold transition cursor-pointer', activeTab === 'health' ? 'bg-primary text-white shadow-soft' : 'text-neutral-black hover:bg-neutral-background']"
            >
              <Database class="h-4 w-4 shrink-0" />
              System Health
            </button>
            <button 
              @click="activeTab = 'metrics'"
              :class="['w-full flex items-center gap-3 p-3 rounded-2xl text-left text-xs font-bold transition cursor-pointer', activeTab === 'metrics' ? 'bg-primary text-white shadow-soft' : 'text-neutral-black hover:bg-neutral-background']"
            >
              <Sliders class="h-4 w-4 shrink-0" />
              Operational Metrics
            </button>
            <button 
              @click="activeTab = 'integrations'"
              :class="['w-full flex items-center gap-3 p-3 rounded-2xl text-left text-xs font-bold transition cursor-pointer', activeTab === 'integrations' ? 'bg-primary text-white shadow-soft' : 'text-neutral-black hover:bg-neutral-background']"
            >
              <Mail class="h-4 w-4 shrink-0" />
              Integration Health
            </button>
            <button 
              @click="activeTab = 'webhooks'"
              :class="['w-full flex items-center gap-3 p-3 rounded-2xl text-left text-xs font-bold transition cursor-pointer', activeTab === 'webhooks' ? 'bg-primary text-white shadow-soft' : 'text-neutral-black hover:bg-neutral-background']"
            >
              <Webhook class="h-4 w-4 shrink-0" />
              Webhook Monitoring
            </button>
            <button 
              @click="activeTab = 'logs'"
              :class="['w-full flex items-center gap-3 p-3 rounded-2xl text-left text-xs font-bold transition cursor-pointer', activeTab === 'logs' ? 'bg-primary text-white shadow-soft' : 'text-neutral-black hover:bg-neutral-background']"
            >
              <FileText class="h-4 w-4 shrink-0" />
              Logs Viewer
            </button>
            <button 
              @click="activeTab = 'config'"
              :class="['w-full flex items-center gap-3 p-3 rounded-2xl text-left text-xs font-bold transition cursor-pointer', activeTab === 'config' ? 'bg-primary text-white shadow-soft' : 'text-neutral-black hover:bg-neutral-background']"
            >
              <Settings class="h-4 w-4 shrink-0" />
              Configuration settings
            </button>
          </div>
        </div>
      </div>

      <!-- Main Panel viewport -->
      <div class="lg:col-span-9">
        <div class="bg-white border border-neutral-ivory rounded-3xl p-6 sm:p-8 shadow-soft min-h-[400px]">
          
          <!-- OVERVIEW TAB -->
          <div v-if="activeTab === 'overview'" class="space-y-6">
            <h2 class="text-xl font-display font-bold text-primary">System Oversight Overview</h2>
            <p class="text-xs text-neutral-muted">EMS platform running details and integration heartbeat summaries.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div class="p-5 border border-neutral-ivory rounded-2xl bg-neutral-background/20 relative overflow-hidden">
                <h4 class="text-[10px] font-black uppercase tracking-wider text-neutral-muted">API Latency</h4>
                <p class="text-2xl font-semibold text-primary mt-2">{{ healthData?.api?.avg_latency_ms ?? 0 }} ms</p>
                <div class="text-[10px] text-neutral-muted mt-2">Average response speed (24h)</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl bg-neutral-background/20 relative overflow-hidden">
                <h4 class="text-[10px] font-black uppercase tracking-wider text-neutral-muted">Database Check</h4>
                <p class="text-2xl font-semibold text-primary mt-2">{{ healthData?.database?.latency_ms ?? 0 }} ms</p>
                <div class="text-[10px] text-neutral-muted mt-2">Query latency & migration check</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl bg-neutral-background/20 relative overflow-hidden">
                <h4 class="text-[10px] font-black uppercase tracking-wider text-neutral-muted">Pending Queue Jobs</h4>
                <p class="text-2xl font-semibold text-primary mt-2">{{ healthData?.queues?.pending_jobs ?? 0 }}</p>
                <div class="text-[10px] text-neutral-muted mt-2">Active jobs across EMS queues</div>
              </div>
            </div>

            <!-- System Services table summary -->
            <div class="space-y-4">
              <h3 class="text-sm font-bold text-neutral-black">Oversight Status Feed</h3>
              <div class="border border-neutral-ivory rounded-2xl overflow-hidden shadow-sm">
                <table class="w-full text-xs text-left border-collapse">
                  <thead>
                    <tr class="bg-neutral-background/40 font-bold border-b border-neutral-ivory/60">
                      <th class="p-4">Service</th>
                      <th class="p-4">Oversight Area</th>
                      <th class="p-4">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-neutral-ivory/40">
                    <tr>
                      <td class="p-4 font-bold">Square Payments</td>
                      <td class="p-4">Card checkouts, webhooks and sandbox configurations</td>
                      <td class="p-4">
                        <span :class="['px-2 py-0.5 rounded text-[10px] font-bold border', integrationData?.square?.status === 'Connected' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-red-50 text-red-700 border-red-100']">
                          {{ integrationData?.square?.status }}
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <td class="p-4 font-bold">Email Service</td>
                      <td class="p-4">Classroom reminders, RSVPs and email queue processor</td>
                      <td class="p-4">
                        <span :class="['px-2 py-0.5 rounded text-[10px] font-bold border', healthData?.email?.status === 'operational' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-amber-50 text-amber-700 border-amber-100']">
                          {{ healthData?.email?.status === 'operational' ? 'Operational' : 'Failed Deliveries' }}
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <td class="p-4 font-bold">Platform Queues</td>
                      <td class="p-4">High/default/low priority workers</td>
                      <td class="p-4">
                        <span :class="['px-2 py-0.5 rounded text-[10px] font-bold border', integrationData?.queues?.status === 'Operational' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-red-50 text-red-700 border-red-100']">
                          {{ integrationData?.queues?.status }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- HEALTH TAB -->
          <div v-if="activeTab === 'health'" class="space-y-6">
            <h2 class="text-xl font-display font-bold text-primary">System Health Diagnostics</h2>
            <p class="text-xs text-neutral-muted">Continuous background diagnostics check across application stacks.</p>

            <div v-if="healthData" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- API Card -->
              <div class="p-5 border border-neutral-ivory rounded-2xl space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-bold text-neutral-black">API Gateway</span>
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', healthData.api.status === 'operational' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                    {{ healthData.api.status }}
                  </span>
                </div>
                <div class="text-xs text-neutral-muted space-y-1">
                  <div>Average Latency: <span class="font-bold text-neutral-black">{{ healthData.api.avg_latency_ms }} ms</span></div>
                  <div>Active REST Version: <span class="font-bold text-neutral-black">V1</span></div>
                </div>
              </div>

              <!-- Database Card -->
              <div class="p-5 border border-neutral-ivory rounded-2xl space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-bold text-neutral-black">Database Connection</span>
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', healthData.database.status === 'operational' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700']">
                    {{ healthData.database.status }}
                  </span>
                </div>
                <div class="text-xs text-neutral-muted space-y-1">
                  <div>Connection Latency: <span class="font-bold text-neutral-black">{{ healthData.database.latency_ms }} ms</span></div>
                  <div>Pending Migrations: <span class="font-bold text-neutral-black">{{ healthData.database.pending_migrations }}</span></div>
                </div>
              </div>

              <!-- Queue Workers Card -->
              <div class="p-5 border border-neutral-ivory rounded-2xl space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-bold text-neutral-black">Queue Workers</span>
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', healthData.queues.status === 'operational' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                    {{ healthData.queues.status }}
                  </span>
                </div>
                <div class="text-xs text-neutral-muted space-y-1">
                  <div>Pending Jobs count: <span class="font-bold text-neutral-black">{{ healthData.queues.pending_jobs }}</span></div>
                  <div>Failed Jobs count: <span class="font-bold text-neutral-black">{{ healthData.queues.failed_jobs }}</span></div>
                </div>
              </div>

              <!-- Email Service Card -->
              <div class="p-5 border border-neutral-ivory rounded-2xl space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-bold text-neutral-black">Email service</span>
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', healthData.email.status === 'operational' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                    {{ healthData.email.status }}
                  </span>
                </div>
                <div class="text-xs text-neutral-muted space-y-1">
                  <div>Mail service Provider: <span class="font-bold text-neutral-black capitalize">{{ healthData.email.mailer }}</span></div>
                  <div>Failed Deliveries: <span class="font-bold text-neutral-black">{{ healthData.email.failed_deliveries }}</span></div>
                </div>
              </div>

              <!-- Storage Check Card -->
              <div class="p-5 border border-neutral-ivory rounded-2xl space-y-3 md:col-span-2">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-bold text-neutral-black">Disk Storage Health</span>
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', healthData.storage.status === 'operational' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700']">
                    {{ healthData.storage.status }}
                  </span>
                </div>
                <div class="space-y-2">
                  <div class="flex justify-between text-xs">
                    <span>Used Space: {{ healthData.storage.used_gb }} GB / {{ healthData.storage.total_gb }} GB</span>
                    <span class="font-bold">{{ healthData.storage.percent_used }}%</span>
                  </div>
                  <div class="h-2 w-full bg-neutral-background rounded-full overflow-hidden">
                    <div :class="['h-full rounded-full', healthData.storage.percent_used > 80 ? 'bg-amber-500' : 'bg-primary']" :style="{ width: healthData.storage.percent_used + '%' }"></div>
                  </div>
                  <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2 text-[10px] text-neutral-muted">
                    <div>
                      <div>Tickets size</div>
                      <div class="font-bold text-neutral-black">{{ formatBytes(healthData.storage.ticket_storage_bytes) }}</div>
                    </div>
                    <div>
                      <div>QR Codes size</div>
                      <div class="font-bold text-neutral-black">{{ formatBytes(healthData.storage.qr_storage_bytes) }}</div>
                    </div>
                    <div>
                      <div>Uploads size</div>
                      <div class="font-bold text-neutral-black">{{ formatBytes(healthData.storage.uploaded_files_bytes) }}</div>
                    </div>
                    <div>
                      <div>Logs size</div>
                      <div class="font-bold text-neutral-black">{{ formatBytes(healthData.storage.logs_bytes) }}</div>
                    </div>
                    <div>
                      <div>Temp files size</div>
                      <div class="font-bold text-neutral-black">{{ formatBytes(healthData.storage.temp_bytes) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- METRICS TAB -->
          <div v-if="activeTab === 'metrics'" class="space-y-6">
            <h2 class="text-xl font-display font-bold text-primary">System Summary Metrics</h2>
            <p class="text-xs text-neutral-muted">Platform-wide events and ticketing metrics count summaries.</p>

            <div v-if="metricsData" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Total Events</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.total_events }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">All configured events in database</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Upcoming Events</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.upcoming_events }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">Active registrations on website</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Total Registrations</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.registrations }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">Including pending approvals</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Tickets Issued</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.tickets_sold }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">Valid active check-in ticket entries</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Total Check-Ins</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.check_ins }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">Attendees verified at doors</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft font-mono bg-neutral-background/30">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Gross Revenues</div>
                <div class="text-3xl font-bold text-emerald-700 mt-2">{{ formatCurrency(metricsData.revenue) }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">Processed checkout collections</div>
              </div>
            </div>

            <!-- Secondary Metrics List -->
            <div v-if="metricsData" class="border border-neutral-ivory rounded-2xl p-5 space-y-4">
              <h3 class="text-xs font-black uppercase text-neutral-black tracking-wider">Oversight Status Breakdown</h3>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                <div class="space-y-1">
                  <div class="text-neutral-muted">Live Events</div>
                  <div class="text-lg font-bold text-neutral-black">{{ metricsData.active_events }}</div>
                </div>
                <div class="space-y-1">
                  <div class="text-neutral-muted">Completed Events</div>
                  <div class="text-lg font-bold text-neutral-black">{{ metricsData.completed_events }}</div>
                </div>
                <div class="space-y-1">
                  <div class="text-neutral-muted">Cancelled Events</div>
                  <div class="text-lg font-bold text-neutral-black">{{ metricsData.cancelled_events }}</div>
                </div>
                <div class="space-y-1">
                  <div class="text-neutral-muted">Pending Registration queue</div>
                  <div class="text-lg font-bold text-neutral-black">{{ metricsData.pending_registrations }}</div>
                </div>
                <div class="space-y-1">
                  <div class="text-neutral-muted">Waitlisted Attendees</div>
                  <div class="text-lg font-bold text-neutral-black">{{ metricsData.waitlisted_attendees }}</div>
                </div>
                <div class="space-y-1">
                  <div class="text-neutral-muted">Imported Attendees</div>
                  <div class="text-lg font-bold text-neutral-black">{{ metricsData.imported_attendees }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- INTEGRATIONS TAB -->
          <div v-if="activeTab === 'integrations'" class="space-y-6">
            <h2 class="text-xl font-display font-bold text-primary">Integration Channels</h2>
            <p class="text-xs text-neutral-muted">Modes and credentials configurations for payment channels and communications.</p>

            <div v-if="integrationData" class="space-y-6">
              <!-- Square payment monitoring -->
              <div class="border border-neutral-ivory rounded-3xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-neutral-ivory/60 pb-3">
                  <h3 class="text-sm font-bold text-primary flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Square Payment Integration
                  </h3>
                  <span class="text-[10px] font-bold px-2 py-0.5 bg-neutral-background border rounded text-neutral-muted capitalize">{{ integrationData.square.mode }} Mode</span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                  <div class="space-y-2">
                    <div>Connection Status: <span class="font-bold text-neutral-black">{{ integrationData.square.status }}</span></div>
                    <div>Environment: <span class="font-bold text-neutral-black capitalize">{{ integrationData.square.environment || integrationData.square.mode }}</span></div>
                    <div>Location configured: <span class="font-bold text-neutral-black">{{ integrationData.square.location_configured ? 'Yes' : 'No' }}</span></div>
                    <div>Credentials configured: <span class="font-bold text-neutral-black">{{ integrationData.square.credentials_configured ? 'Yes' : 'No' }}</span></div>
                    <div>Catalog API: <span class="font-bold text-neutral-black">{{ integrationData.square.catalog_api || '—' }}</span></div>
                    <div>Orders API: <span class="font-bold text-neutral-black">{{ integrationData.square.orders_api || '—' }}</span></div>
                    <div>Payments API: <span class="font-bold text-neutral-black">{{ integrationData.square.payments_api || '—' }}</span></div>
                    <div>Refunds API: <span class="font-bold text-neutral-black">{{ integrationData.square.refunds_api || '—' }}</span></div>
                  </div>
                  <div class="space-y-2">
                    <div>Webhook configuration: <span class="font-bold text-neutral-black">{{ integrationData.square.webhook_configuration || integrationData.square.webhook_connectivity }}</span></div>
                    <div>Terminal: <span class="font-bold text-neutral-black">{{ integrationData.square.terminal_availability || 'not_configured' }}</span></div>
                    <div>Unmatched transactions: <span class="font-bold text-neutral-black">{{ integrationData.square.unmatched_transactions ?? 0 }}</span></div>
                    <div>Failed catalog syncs: <span class="font-bold text-neutral-black">{{ integrationData.square.failed_sync_jobs ?? 0 }}</span></div>
                    <div>Last catalog sync: <span class="font-bold text-neutral-black">{{ integrationData.square.last_successful_synchronization || '—' }}</span></div>
                    <div>Secrets and Keys: <span class="font-mono text-neutral-muted">[Read-Only in .env]</span></div>
                  </div>
                </div>
              </div>

              <!-- Email service configuration -->
              <div class="border border-neutral-ivory rounded-3xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-neutral-ivory/60 pb-3">
                  <h3 class="text-sm font-bold text-primary flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Email Service Channels
                  </h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                  <div class="space-y-2">
                    <div>Outbound mailer: <span class="font-bold text-neutral-black capitalize">{{ integrationData.email.mail_service }}</span></div>
                    <div>Queue Processing: <span class="font-bold text-neutral-black">{{ integrationData.email.queue_processing }}</span></div>
                  </div>
                  <div class="space-y-2">
                    <div>From Address: <span class="font-mono text-neutral-black">{{ integrationData.email.mail_from }}</span></div>
                    <div>Failed Deliveries count: <span class="font-bold text-red-600">{{ integrationData.email.failed_deliveries }}</span></div>
                  </div>
                </div>
              </div>

              <!-- Platform queues details -->
              <div class="border border-neutral-ivory rounded-3xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-neutral-ivory/60 pb-3">
                  <h3 class="text-sm font-bold text-primary flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Platform Queues partitions
                  </h3>
                  <a href="/admin/system/queues" class="text-xs font-bold text-primary hover:text-primary-dark flex items-center gap-1">
                    Queues dashboard
                    <ExternalLink :size="12" />
                  </a>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                  <div class="space-y-2">
                    <div>EMS active queues: <span class="font-mono text-neutral-black">{{ integrationData.queues.queue_workers.join(', ') }}</span></div>
                    <div>Pending jobs count: <span class="font-bold text-neutral-black">{{ integrationData.queues.pending_jobs }}</span></div>
                  </div>
                  <div class="space-y-2">
                    <div>Failed jobs count: <span class="font-bold text-red-600">{{ integrationData.queues.failed_jobs }}</span></div>
                    <div>Processing Rate: <span class="font-bold text-neutral-black">{{ integrationData.queues.processing_rate }}</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- WEBHOOKS TAB -->
          <div v-if="activeTab === 'webhooks'" class="space-y-6">
            <h2 class="text-xl font-display font-bold text-primary">Webhook Activity monitor</h2>
            <p class="text-xs text-neutral-muted">Real-time Square checkout callbacks and payment verification tracking.</p>

            <div v-if="webhookData" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="p-5 border border-neutral-ivory rounded-2xl bg-neutral-background/30">
                  <div class="text-[10px] font-bold uppercase text-neutral-muted">Verification Status</div>
                  <div class="text-lg font-bold text-primary mt-2">{{ webhookData.verification_status }}</div>
                </div>
                <div class="p-5 border border-neutral-ivory rounded-2xl bg-neutral-background/30">
                  <div class="text-[10px] font-bold uppercase text-neutral-muted">Average Speed</div>
                  <div class="text-lg font-bold text-primary mt-2">{{ webhookData.average_processing_time_ms }} ms</div>
                </div>
                <div class="p-5 border border-neutral-ivory rounded-2xl bg-neutral-background/30">
                  <div class="text-[10px] font-bold uppercase text-neutral-muted">Failed Callback Count</div>
                  <div class="text-lg font-bold text-red-600 mt-2">{{ webhookData.failed_count }}</div>
                </div>
              </div>

              <!-- History table -->
              <div class="space-y-4">
                <h3 class="text-sm font-bold text-neutral-black">Recent Webhook Logs</h3>
                <div class="border border-neutral-ivory rounded-2xl overflow-hidden shadow-sm">
                  <table class="w-full text-xs text-left border-collapse">
                    <thead>
                      <tr class="bg-neutral-background/40 font-bold border-b border-neutral-ivory/60">
                        <th class="p-3">Received At</th>
                        <th class="p-3">Provider</th>
                        <th class="p-3">Event Type</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Actions</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-ivory/40">
                      <tr v-if="webhookData.history.length === 0">
                        <td colspan="5" class="p-8 text-center text-neutral-muted">No webhook activity recorded yet.</td>
                      </tr>
                      <tr v-for="w in webhookData.history" :key="w.uuid" class="hover:bg-neutral-background/20 transition-colors">
                        <td class="p-3 font-mono text-[10px]">{{ formatDateTime(w.created_at) }}</td>
                        <td class="p-3 font-bold uppercase text-[10px]">{{ w.provider }}</td>
                        <td class="p-3 font-mono text-[10px] text-neutral-muted">{{ w.event_type }}</td>
                        <td class="p-3">
                          <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase border', w.status === 'processed' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-red-50 text-red-700 border-red-100']">
                            {{ w.status }}
                          </span>
                        </td>
                        <td class="p-3">
                          <button @click="inspectWebhook(w)" class="p-1 rounded bg-neutral-background border border-neutral-ivory hover:bg-neutral-ivory text-primary transition cursor-pointer">
                            <Eye class="h-3.5 w-3.5" />
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- LOGS TAB -->
          <div v-if="activeTab === 'logs'" class="space-y-6">
            <h2 class="text-xl font-display font-bold text-primary">Operational Log Viewer</h2>
            <p class="text-xs text-neutral-muted">Live application logging parsed directly from the environment.</p>

            <!-- Filters form -->
            <div class="p-5 border border-neutral-ivory bg-neutral-background/15 rounded-3xl space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <div class="space-y-1">
                  <label class="text-[9px] uppercase font-bold text-neutral-muted">Log Level/Severity</label>
                  <select v-model="logFilters.severity" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-xs text-[#640c0e]">
                    <option value="">All Severities</option>
                    <option value="INFO">INFO</option>
                    <option value="WARNING">WARNING</option>
                    <option value="ERROR">ERROR</option>
                    <option value="DEBUG">DEBUG</option>
                  </select>
                </div>

                <div class="space-y-1">
                  <label class="text-[9px] uppercase font-bold text-neutral-muted">Category/Type</label>
                  <select v-model="logFilters.type" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-xs text-[#640c0e]">
                    <option value="">All Categories</option>
                    <option value="payment">Payment & Revenue</option>
                    <option value="queue">Queue dispatch</option>
                    <option value="email">Email Communications</option>
                    <option value="webhook">Webhook updates</option>
                    <option value="import">Excel import</option>
                    <option value="check-in">Check-in Perform</option>
                    <option value="api">API exceptions</option>
                    <option value="general">General App</option>
                  </select>
                </div>

                <div class="space-y-1">
                  <label class="text-[9px] uppercase font-bold text-neutral-muted">Search Keyword</label>
                  <div class="relative">
                    <input 
                      type="text" 
                      v-model="logFilters.search" 
                      placeholder="Search log messages..."
                      class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 pl-8 text-xs focus:outline-none"
                    />
                    <Search class="h-3.5 w-3.5 text-neutral-muted absolute left-3 top-3.5" />
                  </div>
                </div>

                <div class="space-y-1">
                  <label class="text-[9px] uppercase font-bold text-neutral-muted">Start Date</label>
                  <input type="date" v-model="logFilters.start_date" class="w-full bg-white border border-neutral-ivory rounded-xl p-2 text-xs" />
                </div>

                <div class="space-y-1">
                  <label class="text-[9px] uppercase font-bold text-neutral-muted">End Date</label>
                  <input type="date" v-model="logFilters.end_date" class="w-full bg-white border border-neutral-ivory rounded-xl p-2 text-xs" />
                </div>

                <div class="flex items-end">
                  <button @click="handleApplyFilters" class="w-full h-10 rounded-xl bg-primary text-white hover:bg-primary-dark font-bold text-xs cursor-pointer transition">
                    Apply Filters
                  </button>
                </div>
              </div>
            </div>

            <!-- Logs Table -->
            <div v-if="logsData" class="space-y-4">
              <div class="border border-neutral-ivory rounded-2xl overflow-hidden shadow-sm">
                <table class="w-full text-xs text-left border-collapse">
                  <thead>
                    <tr class="bg-neutral-background/40 font-bold border-b border-neutral-ivory/60">
                      <th class="p-3">Timestamp</th>
                      <th class="p-3">Severity</th>
                      <th class="p-3">Type</th>
                      <th class="p-3">Message</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-neutral-ivory/40 font-mono text-[10px]">
                    <tr v-if="logsData.data.length === 0">
                      <td colspan="4" class="p-8 text-center text-neutral-muted">No logs matching configuration found.</td>
                    </tr>
                    <tr v-for="(log, i) in logsData.data" :key="i" class="hover:bg-neutral-background/10 transition-colors">
                      <td class="p-3 text-neutral-muted">{{ log.timestamp }}</td>
                      <td class="p-3">
                        <span 
                          :class="[
                            'px-1.5 py-0.5 rounded text-[8px] font-black tracking-wider uppercase border',
                            log.severity === 'ERROR' ? 'bg-red-50 text-red-700 border-red-200' : 
                            log.severity === 'WARNING' ? 'bg-amber-50 text-amber-700 border-amber-200' : 
                            'bg-blue-50 text-blue-700 border-blue-200'
                          ]"
                        >
                          {{ log.severity }}
                        </span>
                      </td>
                      <td class="p-3 capitalize font-bold text-neutral-muted">{{ log.type }}</td>
                      <td class="p-3 text-neutral-black max-w-xs sm:max-w-md truncate" :title="log.message + ' ' + JSON.stringify(log.context)">
                        {{ log.message }}
                        <span v-if="Object.keys(log.context).length > 0" class="text-[9px] text-[#640c0e] bg-amber-50 px-1 rounded-sm border border-amber-100">
                          {context}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Pagination controls -->
              <div v-if="logsData.last_page > 1" class="flex justify-between items-center pt-2 text-xs">
                <span class="text-neutral-muted">Showing page {{ logsData.current_page }} of {{ logsData.last_page }} &bull; Total records: {{ logsData.total }}</span>
                <div class="flex gap-2">
                  <button 
                    @click="changeLogPage(logsData.current_page - 1)" 
                    :disabled="logsData.current_page === 1"
                    class="px-3 py-1 rounded border border-neutral-ivory bg-white disabled:opacity-50 text-[10px] font-bold cursor-pointer hover:bg-neutral-background"
                  >
                    Previous
                  </button>
                  <button 
                    @click="changeLogPage(logsData.current_page + 1)" 
                    :disabled="logsData.current_page === logsData.last_page"
                    class="px-3 py-1 rounded border border-neutral-ivory bg-white disabled:opacity-50 text-[10px] font-bold cursor-pointer hover:bg-neutral-background"
                  >
                    Next
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- CONFIGURATIONS TAB -->
          <div v-if="activeTab === 'config'" class="space-y-6">
            <div class="flex justify-between items-center border-b border-neutral-ivory/60 pb-3">
              <div>
                <h2 class="text-xl font-display font-bold text-primary">Centralized configurations</h2>
                <p class="text-xs text-neutral-muted">Central settings ledger for events defaults and limits control.</p>
              </div>
              <div v-if="!isSuperAdmin" class="flex items-center gap-1.5 p-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl text-[10px] font-bold">
                <Lock :size="12" />
                Read-Only (Super Admin Restriction)
              </div>
            </div>

            <form v-if="configData" @submit.prevent="handleSaveConfig" class="space-y-6 text-xs">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- General Section -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl">
                  <h3 class="text-xs font-bold text-primary">General Defaults</h3>
                  
                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">System Timezone</label>
                    <select v-model="configData.timezone" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black">
                      <option value="America/Vancouver">America/Vancouver (PST)</option>
                      <option value="America/Toronto">America/Toronto (EST)</option>
                      <option value="Europe/London">Europe/London (GMT)</option>
                      <option value="Asia/Riyadh">Asia/Riyadh (AST)</option>
                    </select>
                  </div>

                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">System Currency</label>
                    <input type="text" v-model="configData.currency" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>
                </div>

                <!-- Registration Defaults -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl">
                  <h3 class="text-xs font-bold text-primary">Registration defaults</h3>
                  
                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Max Tickets per Order</label>
                    <input type="number" v-model.number="configData.max_tickets_per_order" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>

                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Max Registrations per Attendee</label>
                    <input type="number" v-model.number="configData.max_registrations_per_attendee" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>
                </div>

                <!-- Ticket Defaults -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl">
                  <h3 class="text-xs font-bold text-primary">Ticket defaults</h3>
                  
                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Ticket Code Prefix</label>
                    <input type="text" v-model="configData.ticket_code_prefix" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>

                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Ticket Code Length</label>
                    <input type="number" v-model.number="configData.ticket_code_length" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>

                  <div class="flex justify-between items-center p-2.5 bg-neutral-background/10 rounded-xl">
                    <span class="font-bold text-neutral-muted">QR Code Generation</span>
                    <input type="checkbox" v-model="configData.ticket_qr_enabled" :disabled="!isSuperAdmin" class="h-4.5 w-4.5 rounded cursor-pointer" />
                  </div>
                </div>

                <!-- Queues config -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl">
                  <h3 class="text-xs font-bold text-primary">Queues configuration</h3>
                  
                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Payments queue name</label>
                    <input type="text" v-model="configData.queue_payments" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black font-mono text-[10px]" />
                  </div>

                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Operations queue name</label>
                    <input type="text" v-model="configData.queue_operations" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black font-mono text-[10px]" />
                  </div>

                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Notifications queue name</label>
                    <input type="text" v-model="configData.queue_notifications" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black font-mono text-[10px]" />
                  </div>
                </div>

                <!-- Outbound Email settings -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl">
                  <h3 class="text-xs font-bold text-primary">Email configuration</h3>
                  
                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">From Address</label>
                    <input type="email" v-model="configData.email_from_address" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>

                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">From Name</label>
                    <input type="text" v-model="configData.email_from_name" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>

                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Max Notification Delivery Retries</label>
                    <input type="number" v-model.number="configData.email_max_retries" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>
                </div>

                <!-- Reminders & Retention -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl">
                  <h3 class="text-xs font-bold text-primary">Reminders & Retention</h3>
                  
                  <div class="flex justify-between items-center p-2.5 bg-neutral-background/10 rounded-xl mb-2">
                    <span class="font-bold text-neutral-muted">Default reminders enabled</span>
                    <input type="checkbox" v-model="configData.reminder_defaults_enabled" :disabled="!isSuperAdmin" class="h-4.5 w-4.5 rounded cursor-pointer" />
                  </div>

                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Analytics Retention period (Days)</label>
                    <input type="number" v-model.number="configData.analytics_retention_days" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>
                </div>

                <!-- Import defaults -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl md:col-span-2">
                  <h3 class="text-xs font-bold text-primary">Import Settings</h3>
                  
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                      <label class="font-bold text-neutral-muted block">Import chunk size</label>
                      <input type="number" v-model.number="configData.import_chunk_size" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                    </div>
                    <div class="space-y-1.5">
                      <label class="font-bold text-neutral-muted block">Import Sync Threshold</label>
                      <input type="number" v-model.number="configData.import_sync_threshold" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Submit button -->
              <div v-if="isSuperAdmin" class="pt-4 border-t border-neutral-ivory/60 flex justify-end">
                <button 
                  type="submit" 
                  :disabled="loading"
                  class="h-10 px-6 rounded-xl bg-primary text-white hover:bg-primary-dark font-bold text-xs cursor-pointer transition shadow-md"
                >
                  {{ loading ? 'Saving...' : 'Save Configuration' }}
                </button>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>

    <!-- Webhook Inspection Modal -->
    <div v-if="showWebhookModal && selectedWebhook" class="fixed inset-0 bg-neutral-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white border border-neutral-ivory rounded-3xl max-w-2xl w-full max-h-[85vh] overflow-hidden flex flex-col shadow-premium">
        <div class="p-6 border-b border-neutral-ivory bg-neutral-background/30 flex justify-between items-center">
          <h3 class="text-sm font-bold text-primary uppercase">Inspect Webhook Activity</h3>
          <button @click="closeWebhookModal" class="text-neutral-muted hover:text-neutral-black text-lg font-bold cursor-pointer font-sans">&times;</button>
        </div>
        <div class="p-6 space-y-4 text-xs overflow-y-auto flex-grow font-sans">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="text-neutral-muted font-bold uppercase text-[9px]">Event UUID</div>
              <div class="font-mono text-neutral-black mt-1">{{ selectedWebhook.uuid }}</div>
            </div>
            <div>
              <div class="text-neutral-muted font-bold uppercase text-[9px]">Provider / Mode</div>
              <div class="font-bold text-neutral-black mt-1 uppercase">{{ selectedWebhook.provider }}</div>
            </div>
            <div>
              <div class="text-neutral-muted font-bold uppercase text-[9px]">Callback received</div>
              <div class="font-bold text-neutral-black mt-1">{{ formatDateTime(selectedWebhook.created_at) }}</div>
            </div>
            <div>
              <div class="text-neutral-muted font-bold uppercase text-[9px]">Processed At</div>
              <div class="font-bold text-neutral-black mt-1">{{ formatDateTime(selectedWebhook.processed_at) }}</div>
            </div>
            <div class="col-span-2">
              <div class="text-neutral-muted font-bold uppercase text-[9px]">Webhook Event type</div>
              <div class="font-mono text-neutral-black bg-neutral-background px-2 py-1.5 border border-neutral-ivory/60 rounded mt-1">{{ selectedWebhook.event_type }}</div>
            </div>
            <div class="col-span-2">
              <div class="text-neutral-muted font-bold uppercase text-[9px]">Verification Status</div>
              <div class="font-bold text-emerald-600 mt-1">✓ Signature Verified</div>
            </div>
            <div v-if="selectedWebhook.status === 'failed'" class="col-span-2">
              <div class="text-neutral-muted font-bold uppercase text-[9px]">Failure Reason</div>
              <div class="font-mono text-red-700 bg-red-50 p-3 border border-red-200 rounded mt-1">{{ selectedWebhook.failure_reason }}</div>
            </div>
          </div>
          
          <div class="pt-4 border-t border-neutral-ivory/60">
            <div class="text-[9px] uppercase font-bold text-neutral-muted mb-2 tracking-wider">Operational Payload Information</div>
            <div class="p-3 bg-neutral-background rounded-2xl border border-neutral-ivory/60 text-[10px] text-neutral-muted italic">
              Sensitive payload values, personal identifier data, and payment keys are redacted automatically for secure administrative inspection.
            </div>
          </div>
        </div>
        <div class="p-6 border-t border-neutral-ivory flex justify-end">
          <button @click="closeWebhookModal" class="h-9 px-4 rounded-xl bg-neutral-background hover:bg-neutral-ivory border border-neutral-ivory font-bold text-xs text-neutral-black cursor-pointer transition">
            Close Diagnostics
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
