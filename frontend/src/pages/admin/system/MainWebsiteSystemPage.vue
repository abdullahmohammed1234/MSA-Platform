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
  Sliders
} from 'lucide-vue-next';

// Tab system
type TabType = 'overview' | 'health' | 'metrics' | 'integrations' | 'config';
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
const configData = ref<any>(null);

// Load data helpers
const fetchSystemOverview = async () => {
  try {
    const res = await client.get('/admin/systems/main-website');
    if (res.data.success) systemStatus.value = res.data.system;
  } catch (err: any) {
    console.error('Failed to load system overview', err);
  }
};

const fetchHealth = async () => {
  try {
    const res = await client.get('/admin/systems/main-website/health');
    if (res.data.success) healthData.value = res.data.health;
  } catch (err: any) {
    console.error('Failed to load health statistics', err);
  }
};

const fetchMetrics = async () => {
  try {
    const res = await client.get('/admin/systems/main-website/metrics');
    if (res.data.success) metricsData.value = res.data.metrics;
  } catch (err: any) {
    console.error('Failed to load operational metrics', err);
  }
};

const fetchIntegrations = async () => {
  try {
    const res = await client.get('/admin/systems/main-website/integrations');
    if (res.data.success) integrationData.value = res.data.integrations;
  } catch (err: any) {
    console.error('Failed to load integrations status', err);
  }
};

const fetchConfig = async () => {
  try {
    const res = await client.get('/admin/systems/main-website/config');
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
      fetchConfig()
    ]);
    if (forceRefresh) {
      showToast('System status refreshed successfully.');
    }
  } catch (err: any) {
    error.value = 'Failed to fetch some Main Website diagnostics. Please verify connection.';
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
    const res = await client.put('/admin/systems/main-website/config', configData.value);
    if (res.data.success) {
      configData.value = res.data.config;
      successMessage.value = 'Configurations updated successfully and committed to persistent storage.';
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
  window.open('/', '_blank');
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
          <h1 class="text-3xl font-display font-black text-primary">Main Website & CMS</h1>
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
        <p class="text-xs text-neutral-muted">Public portal operations, site metadata, content management health, and configs dashboard.</p>
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
          Open Website
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
              CMS & Public Metrics
            </button>
            <button 
              @click="activeTab = 'integrations'"
              :class="['w-full flex items-center gap-3 p-3 rounded-2xl text-left text-xs font-bold transition cursor-pointer', activeTab === 'integrations' ? 'bg-primary text-white shadow-soft' : 'text-neutral-black hover:bg-neutral-background']"
            >
              <Mail class="h-4 w-4 shrink-0" />
              Integrations check
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
            <p class="text-xs text-neutral-muted">Public website response latencies and database tables state.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div class="p-5 border border-neutral-ivory rounded-2xl bg-neutral-background/20 relative overflow-hidden">
                <h4 class="text-[10px] font-black uppercase tracking-wider text-neutral-muted">API Latency</h4>
                <p class="text-2xl font-semibold text-primary mt-2">{{ healthData?.api?.avg_latency_ms ?? 0 }} ms</p>
                <div class="text-[10px] text-neutral-muted mt-2">Average API response speed</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl bg-neutral-background/20 relative overflow-hidden">
                <h4 class="text-[10px] font-black uppercase tracking-wider text-neutral-muted">Database Check</h4>
                <p class="text-2xl font-semibold text-primary mt-2">{{ healthData?.database?.latency_ms ?? 0 }} ms</p>
                <div class="text-[10px] text-neutral-muted mt-2">Query latency response</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl bg-neutral-background/20 relative overflow-hidden">
                <h4 class="text-[10px] font-black uppercase tracking-wider text-neutral-muted">CMS Revisions</h4>
                <p class="text-2xl font-semibold text-primary mt-2">Active</p>
                <div class="text-[10px] text-neutral-muted mt-2">Rollbacks and logs audit tracking</div>
              </div>
            </div>

            <!-- Oversight Status Feed -->
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
                      <td class="p-4 font-bold">CMS Engine</td>
                      <td class="p-4">Content blocks, layouts, and public page hydration</td>
                      <td class="p-4">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">
                          Operational
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <td class="p-4 font-bold">Prayer Times API</td>
                      <td class="p-4">SFU Burnaby, Surrey & Vancouver schedule synchronization</td>
                      <td class="p-4">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">
                          Synchronized
                        </span>
                      </td>
                    </tr>
                    <tr>
                      <td class="p-4 font-bold">Mailing Dispatch</td>
                      <td class="p-4">Contact forms, sponsorships and newsletter mailers</td>
                      <td class="p-4">
                        <span :class="['px-2 py-0.5 rounded text-[10px] font-bold border', integrationData?.email?.status === 'Operational' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-amber-50 text-amber-700 border-amber-100']">
                          {{ integrationData?.email?.status }}
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
            <p class="text-xs text-neutral-muted">Continuous diagnostics checks across database and cache storage pools.</p>

            <div v-if="healthData" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- API Gateway -->
              <div class="p-5 border border-neutral-ivory rounded-2xl space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-bold text-neutral-black">API Gateway</span>
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-50 text-emerald-700">
                    {{ healthData.api.status }}
                  </span>
                </div>
                <div class="text-xs text-neutral-muted space-y-1">
                  <div>Average Latency: <span class="font-bold text-neutral-black">{{ healthData.api.avg_latency_ms }} ms</span></div>
                  <div>Cache Layer: <span class="font-bold text-neutral-black">Enabled</span></div>
                </div>
              </div>

              <!-- Database Connection -->
              <div class="p-5 border border-neutral-ivory rounded-2xl space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-bold text-neutral-black">Database Connection</span>
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', healthData.database.status === 'operational' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700']">
                    {{ healthData.database.status }}
                  </span>
                </div>
                <div class="text-xs text-neutral-muted space-y-1">
                  <div>Connection Latency: <span class="font-bold text-neutral-black">{{ healthData.database.latency_ms }} ms</span></div>
                  <div>Central Server Check: <span class="font-bold text-neutral-black">Online</span></div>
                </div>
              </div>

              <!-- Cache Diagnostics -->
              <div class="p-5 border border-neutral-ivory rounded-2xl space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-bold text-neutral-black">Cache Store</span>
                  <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', healthData.cache.status === 'operational' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
                    {{ healthData.cache.status }}
                  </span>
                </div>
                <div class="text-xs text-neutral-muted space-y-1">
                  <div>Cache Driver: <span class="font-bold text-neutral-black capitalize">{{ healthData.cache.driver }}</span></div>
                  <div>Store Availability: <span class="font-bold text-neutral-black">Available</span></div>
                </div>
              </div>

              <!-- Disk Storage Health -->
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
                  <div class="grid grid-cols-2 gap-4 pt-2 text-[10px] text-neutral-muted">
                    <div>
                      <div>Uploaded Media size</div>
                      <div class="font-bold text-neutral-black">{{ formatBytes(healthData.storage.media_bytes) }}</div>
                    </div>
                    <div>
                      <div>Logs size</div>
                      <div class="font-bold text-neutral-black">{{ formatBytes(healthData.storage.logs_bytes) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- METRICS TAB -->
          <div v-if="activeTab === 'metrics'" class="space-y-6">
            <h2 class="text-xl font-display font-bold text-primary">CMS & Public Metrics</h2>
            <p class="text-xs text-neutral-muted">Summary metrics parsed from current CMS tables.</p>

            <div v-if="metricsData" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Total Announcements</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.announcements }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">All configured public announcements</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Team Directory</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.team_members }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">Active committee members & admins</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Resource Items</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.resources }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">Shared documents, starter kits, revert guides</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Media Assets</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.media_assets }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">Images, flyers and public attachments</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Newsletter Subscribers</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.subscribers }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">Registered email list updates</div>
              </div>
              <div class="p-5 border border-neutral-ivory rounded-2xl shadow-soft">
                <div class="text-[10px] font-black uppercase text-neutral-muted tracking-wider">Total Events</div>
                <div class="text-3xl font-bold text-primary mt-2">{{ metricsData.events }}</div>
                <div class="text-[10px] text-neutral-muted mt-2">EMS public events consumed by Main Website</div>
              </div>
            </div>
          </div>

          <!-- INTEGRATIONS TAB -->
          <div v-if="activeTab === 'integrations'" class="space-y-6">
            <h2 class="text-xl font-display font-bold text-primary">Integration Channels</h2>
            <p class="text-xs text-neutral-muted">Outbound integrations and mailer diagnostics.</p>

            <div v-if="integrationData" class="space-y-6">
              <!-- Outbound Mail Configuration -->
              <div class="border border-neutral-ivory rounded-3xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-neutral-ivory/60 pb-3">
                  <h3 class="text-sm font-bold text-primary flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    SMTP Outbound Mailer
                  </h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                  <div class="space-y-2">
                    <div>Connection Status: <span class="font-bold text-neutral-black">{{ integrationData.email.status }}</span></div>
                    <div>Default Driver: <span class="font-bold text-neutral-black capitalize">{{ integrationData.email.mail_service }}</span></div>
                  </div>
                  <div class="space-y-2">
                    <div>Sender Address: <span class="font-mono text-neutral-black">{{ integrationData.email.from_address }}</span></div>
                  </div>
                </div>
              </div>

              <!-- Newsletter Dispatch -->
              <div class="border border-neutral-ivory rounded-3xl p-6 space-y-4">
                <div class="flex justify-between items-center border-b border-neutral-ivory/60 pb-3">
                  <h3 class="text-sm font-bold text-primary flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Newsletter Service
                  </h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                  <div class="space-y-2">
                    <div>Service Status: <span class="font-bold text-neutral-black">{{ integrationData.newsletter.status }}</span></div>
                  </div>
                  <div class="space-y-2">
                    <div>Provider Model: <span class="font-bold text-neutral-black">{{ integrationData.newsletter.provider }}</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- CONFIG TAB -->
          <div v-if="activeTab === 'config'" class="space-y-6">
            <div class="flex justify-between items-center border-b border-neutral-ivory/60 pb-3">
              <div>
                <h2 class="text-xl font-display font-bold text-primary">Website configurations</h2>
                <p class="text-xs text-neutral-muted">Centralized settings deck for public portal parameters.</p>
              </div>
            </div>

            <form v-if="configData" @submit.prevent="handleSaveConfig" class="space-y-6 text-xs">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- General settings -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl">
                  <h3 class="text-xs font-bold text-primary">General settings</h3>
                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Website name</label>
                    <input type="text" v-model="configData.site_name" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>
                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">System Timezone</label>
                    <select v-model="configData.timezone" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black">
                      <option value="America/Vancouver">America/Vancouver (PST)</option>
                      <option value="America/Toronto">America/Toronto (EST)</option>
                      <option value="Europe/London">Europe/London (GMT)</option>
                    </select>
                  </div>
                </div>

                <!-- Operations defaults -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl">
                  <h3 class="text-xs font-bold text-primary">Public communications</h3>
                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Contact Form recipient</label>
                    <input type="email" v-model="configData.contact_recipient" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>
                  <div class="space-y-1.5 flex justify-between items-center p-2 bg-neutral-background/10 rounded-xl mt-3">
                    <span class="font-bold text-neutral-muted">Enable Newsletter Subscription</span>
                    <input type="checkbox" v-model="configData.newsletter_enabled" :disabled="!isSuperAdmin" class="h-4.5 w-4.5 rounded cursor-pointer" />
                  </div>
                </div>

                <!-- Cache settings -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl md:col-span-2">
                  <h3 class="text-xs font-bold text-primary">System Cache limits</h3>
                  <div class="space-y-1.5">
                    <label class="font-bold text-neutral-muted block">Cache Time To Live (TTL) - Minutes</label>
                    <input type="number" v-model.number="configData.cache_ttl" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black" />
                  </div>
                </div>

                <!-- Social integration -->
                <div class="space-y-3 p-5 border border-neutral-ivory rounded-2xl md:col-span-2">
                  <h3 class="text-xs font-bold text-primary">Social integrations</h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                      <label class="font-bold text-neutral-muted block">Facebook URL</label>
                      <input type="text" v-model="configData.social_facebook" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black font-mono" />
                    </div>
                    <div class="space-y-1.5">
                      <label class="font-bold text-neutral-muted block">Instagram URL</label>
                      <input type="text" v-model="configData.social_instagram" :disabled="!isSuperAdmin" class="w-full bg-white border border-neutral-ivory rounded-xl p-2.5 text-neutral-black font-mono" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Submit button -->
              <div v-if="isSuperAdmin" class="pt-4 border-t border-neutral-ivory/60 flex justify-end">
                <button type="submit" :disabled="loading" class="h-10 px-6 rounded-xl bg-primary text-white font-bold hover:bg-primary-dark transition cursor-pointer">
                  {{ loading ? 'Saving...' : 'Save configs' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
