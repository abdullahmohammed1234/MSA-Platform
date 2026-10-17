<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { connectAdminService } from '@/services/admin/connectAdminService';
import type { ConnectHealth, ConnectMetrics, ConnectSystemInfo } from '@/services/admin/connectAdminService';
import {
  Smartphone,
  RefreshCw,
  Server,
  Radio,
  Database,
  SlidersVertical,
  Calendar,
  Megaphone,
  History,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  Users,
  MessageSquare,
  HelpCircle,
} from 'lucide-vue-next';

const isLoading = ref(true);
const isRefreshing = ref(false);
const error = ref<string | null>(null);

const systemInfo = ref<ConnectSystemInfo | null>(null);
const health = ref<ConnectHealth | null>(null);
const metrics = ref<ConnectMetrics | null>(null);

const fetchData = async () => {
  try {
    error.value = null;
    const [sys, hlth, mtr] = await Promise.all([
      connectAdminService.getSystemInfo(),
      connectAdminService.getHealth(),
      connectAdminService.getMetrics(),
    ]);
    systemInfo.value = sys;
    health.value = hlth;
    metrics.value = mtr;
  } catch (err: any) {
    error.value = err?.response?.data?.message || err?.message || 'Failed to load MSA Connect Management status.';
  } finally {
    isLoading.value = false;
    isRefreshing.value = false;
  }
};

const handleRefresh = async () => {
  isRefreshing.value = true;
  await fetchData();
};

onMounted(() => {
  void fetchData();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header Banner -->
    <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-start gap-4">
        <div class="h-12 w-12 rounded-2xl bg-[#640c0e]/10 text-[#640c0e] flex items-center justify-center shrink-0">
          <Smartphone class="w-6 h-6" />
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-xl font-bold text-neutral-900">MSA Connect Management</h1>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-[#640c0e]/10 text-[#640c0e]">
              v{{ systemInfo?.version || '1.0.0' }}
            </span>
          </div>
          <p class="text-xs text-neutral-500 mt-1 max-w-2xl leading-relaxed">
            Administrative system for configuring and managing the member-facing MSA Connect application, member device notifications, settings, and integrations.
          </p>
        </div>
      </div>
      <div class="flex items-center gap-2 self-start md:self-center">
        <button
          type="button"
          @click="handleRefresh"
          :disabled="isRefreshing"
          class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-[#ebe8de] text-xs font-semibold text-neutral-700 hover:bg-[#faf8f5] transition-colors cursor-pointer"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isRefreshing }" />
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-2xl p-4 text-xs text-red-700 flex items-center gap-3">
      <XCircle class="w-5 h-5 shrink-0 text-red-500" />
      <div class="flex-1">{{ error }}</div>
      <button @click="handleRefresh" class="font-bold underline cursor-pointer">Retry</button>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-4 gap-4 animate-pulse">
      <div v-for="i in 4" :key="i" class="h-28 bg-white rounded-2xl border border-[#ebe8de]"></div>
    </div>

    <template v-else>
      <!-- Operational Diagnostics -->
      <section class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-[#ebe8de] pb-3">
          <div class="flex items-center gap-2">
            <Server class="w-4 h-4 text-[#640c0e]" />
            <h2 class="text-sm font-bold text-neutral-900">System Health & Diagnostics</h2>
          </div>
          <div class="flex items-center gap-2 text-xs">
            <span class="text-neutral-400">Overall Status:</span>
            <span
              v-if="health?.status === 'operational'"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
            >
              <CheckCircle2 class="w-3.5 h-3.5" /> Operational
            </span>
            <span
              v-else-if="health?.status === 'degraded'"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200"
            >
              <AlertTriangle class="w-3.5 h-3.5" /> Degraded
            </span>
            <span
              v-else
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200"
            >
              <XCircle class="w-3.5 h-3.5" /> Unavailable
            </span>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-1">
          <!-- Database -->
          <div class="bg-[#faf8f5] rounded-xl p-3.5 border border-[#ebe8de]">
            <div class="flex items-center justify-between text-xs font-medium text-neutral-500 mb-1.5">
              <span>Primary Database</span>
              <Database class="w-3.5 h-3.5 text-neutral-400" />
            </div>
            <div class="text-sm font-bold text-neutral-900 capitalize">{{ health?.database.status || 'Unknown' }}</div>
            <div class="text-[11px] text-neutral-500 mt-1 font-mono">
              Latency: {{ health?.database.latency_ms ?? 0 }}ms
            </div>
          </div>

          <!-- Web Push -->
          <div class="bg-[#faf8f5] rounded-xl p-3.5 border border-[#ebe8de]">
            <div class="flex items-center justify-between text-xs font-medium text-neutral-500 mb-1.5">
              <span>Web Push Delivery</span>
              <Radio class="w-3.5 h-3.5 text-neutral-400" />
            </div>
            <div class="text-sm font-bold text-neutral-900 capitalize">{{ health?.web_push.status || 'Unknown' }}</div>
            <div class="text-[11px] text-neutral-500 mt-1">
              VAPID: {{ health?.web_push.vapid_configured ? 'Configured' : 'Missing' }} · {{ health?.web_push.delivery_mode }}
            </div>
          </div>

          <!-- Cache -->
          <div class="bg-[#faf8f5] rounded-xl p-3.5 border border-[#ebe8de]">
            <div class="flex items-center justify-between text-xs font-medium text-neutral-500 mb-1.5">
              <span>Cache Layer</span>
              <Server class="w-3.5 h-3.5 text-neutral-400" />
            </div>
            <div class="text-sm font-bold text-neutral-900 capitalize">{{ health?.cache.status || 'Unknown' }}</div>
            <div class="text-[11px] text-neutral-500 mt-1 font-mono">
              Driver: {{ health?.cache.driver || 'default' }}
            </div>
          </div>

          <!-- Device Store -->
          <div class="bg-[#faf8f5] rounded-xl p-3.5 border border-[#ebe8de]">
            <div class="flex items-center justify-between text-xs font-medium text-neutral-500 mb-1.5">
              <span>Device Subscriptions</span>
              <Smartphone class="w-3.5 h-3.5 text-neutral-400" />
            </div>
            <div class="text-sm font-bold text-neutral-900 capitalize">{{ health?.devices_store.status || 'Unknown' }}</div>
            <div class="text-[11px] text-neutral-500 mt-1 font-mono">
              Total Records: {{ health?.devices_store.total_registered_devices ?? 0 }}
            </div>
          </div>
        </div>
      </section>

      <!-- Live Metrics Overview -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-xs">
          <div class="flex items-center justify-between text-xs font-medium text-neutral-500 mb-2">
            <span>Registered Devices</span>
            <Smartphone class="w-4 h-4 text-[#640c0e]" />
          </div>
          <div class="text-2xl font-bold text-neutral-900">{{ metrics?.registered_devices ?? 0 }}</div>
          <div class="text-[11px] text-emerald-600 mt-1 font-medium">
            {{ metrics?.active_devices ?? 0 }} actively subscribed
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-xs">
          <div class="flex items-center justify-between text-xs font-medium text-neutral-500 mb-2">
            <span>Active Device Users</span>
            <Users class="w-4 h-4 text-[#640c0e]" />
          </div>
          <div class="text-2xl font-bold text-neutral-900">{{ metrics?.unique_device_users ?? 0 }}</div>
          <div class="text-[11px] text-neutral-500 mt-1">
            Distinct members with push active
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-xs">
          <div class="flex items-center justify-between text-xs font-medium text-neutral-500 mb-2">
            <span>Direct Messaging</span>
            <MessageSquare class="w-4 h-4 text-[#640c0e]" />
          </div>
          <div class="text-2xl font-bold text-neutral-900">{{ metrics?.total_conversations ?? 0 }}</div>
          <div class="text-[11px] text-neutral-500 mt-1">
            {{ metrics?.total_messages ?? 0 }} total messages recorded
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-xs">
          <div class="flex items-center justify-between text-xs font-medium text-neutral-500 mb-2">
            <span>Support Inquiries</span>
            <HelpCircle class="w-4 h-4 text-[#640c0e]" />
          </div>
          <div class="text-2xl font-bold text-neutral-900">{{ metrics?.support_inquiries ?? 0 }}</div>
          <div class="text-[11px] mt-1 font-medium" :class="(metrics?.open_inquiries ?? 0) > 0 ? 'text-amber-600' : 'text-neutral-500'">
            {{ metrics?.open_inquiries ?? 0 }} open inquiries awaiting response
          </div>
        </div>
      </section>

      <!-- Management Capability Areas -->
      <section class="space-y-3">
        <h2 class="text-xs font-bold uppercase tracking-wider text-neutral-500">Management Capabilities</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Configuration -->
          <router-link
            to="/connect/admin/config"
            class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-xs hover:border-[#640c0e]/40 hover:shadow-sm transition-all group block"
          >
            <div class="h-10 w-10 rounded-xl bg-[#faf8f5] text-[#640c0e] flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
              <SlidersVertical class="w-5 h-5" />
            </div>
            <h3 class="text-sm font-bold text-neutral-900 group-hover:text-[#640c0e] transition-colors">Configuration & Controls</h3>
            <p class="text-xs text-neutral-500 mt-1 leading-relaxed">
              Feature publication flags, Web Push timeout & device bounds, and maintenance mode controls.
            </p>
          </router-link>

          <!-- EMS Events -->
          <router-link
            to="/connect/admin/events"
            class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-xs hover:border-[#640c0e]/40 hover:shadow-sm transition-all group block"
          >
            <div class="h-10 w-10 rounded-xl bg-[#faf8f5] text-[#640c0e] flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
              <Calendar class="w-5 h-5" />
            </div>
            <h3 class="text-sm font-bold text-neutral-900 group-hover:text-[#640c0e] transition-colors">EMS Event Integration</h3>
            <p class="text-xs text-neutral-500 mt-1 leading-relaxed">
              Inspect authoritative EMS event visibility without duplicating event records or ticket workflows.
            </p>
          </router-link>

          <!-- Communications -->
          <router-link
            to="/connect/admin/communications"
            class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-xs hover:border-[#640c0e]/40 hover:shadow-sm transition-all group block"
          >
            <div class="h-10 w-10 rounded-xl bg-[#faf8f5] text-[#640c0e] flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
              <Megaphone class="w-5 h-5" />
            </div>
            <h3 class="text-sm font-bold text-neutral-900 group-hover:text-[#640c0e] transition-colors">Communications</h3>
            <p class="text-xs text-neutral-500 mt-1 leading-relaxed">
              Inspect authoritative CMS bulletins and announcement integration delivered to Connect.
            </p>
          </router-link>

          <!-- Audit -->
          <router-link
            to="/connect/admin/audit"
            class="bg-white rounded-2xl border border-[#ebe8de] p-5 shadow-xs hover:border-[#640c0e]/40 hover:shadow-sm transition-all group block"
          >
            <div class="h-10 w-10 rounded-xl bg-[#faf8f5] text-[#640c0e] flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
              <History class="w-5 h-5" />
            </div>
            <h3 class="text-sm font-bold text-neutral-900 group-hover:text-[#640c0e] transition-colors">Audit Trail</h3>
            <p class="text-xs text-neutral-500 mt-1 leading-relaxed">
              Immutable audit history of all administrative configuration and operational changes.
            </p>
          </router-link>
        </div>
      </section>

      <!-- System Ownership Contract -->
      <section class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-xs">
        <h2 class="text-xs font-bold uppercase tracking-wider text-neutral-500 mb-3">Architectural Ownership Contract</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
          <div class="p-3.5 rounded-xl bg-emerald-50/50 border border-emerald-100">
            <div class="font-bold text-emerald-800 mb-1">MSA Connect Management Owns:</div>
            <ul class="list-disc list-inside space-y-1 text-emerald-700">
              <li v-for="own in systemInfo?.owns || []" :key="own">
                <span class="font-mono">{{ own }}</span>
              </li>
            </ul>
          </div>
          <div class="p-3.5 rounded-xl bg-neutral-50 border border-neutral-200/80">
            <div class="font-bold text-neutral-800 mb-1">Authoritatively Owned Elsewhere:</div>
            <ul class="list-disc list-inside space-y-1 text-neutral-600">
              <li v-for="notOwn in systemInfo?.does_not_own || []" :key="notOwn">
                <span class="font-mono">{{ notOwn }}</span>
              </li>
            </ul>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
