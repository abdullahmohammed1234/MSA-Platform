<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { commandCenterService, type CommandCenterData } from '@/services/commandCenterService';
import OperationalRiskCard from '@/components/command-center/OperationalRiskCard.vue';
import PlatformStatusOverview from '@/components/command-center/PlatformStatusOverview.vue';
import AttentionRequiredPanel from '@/components/command-center/AttentionRequiredPanel.vue';
import PlatformHealthPanel from '@/components/command-center/PlatformHealthPanel.vue';
import ApplicationHealthMatrix from '@/components/command-center/ApplicationHealthMatrix.vue';
import BusinessOperationsSnapshot from '@/components/command-center/BusinessOperationsSnapshot.vue';
import AutomationOperationsSnapshot from '@/components/command-center/AutomationOperationsSnapshot.vue';

const loading = ref(true);
const refreshing = ref(false);
const error = ref<string | null>(null);

const period = ref('30d');
const data = ref<CommandCenterData | null>(null);

const periods = [
  { value: 'today', label: 'Today' },
  { value: '7d', label: 'Last 7 Days' },
  { value: '30d', label: 'Last 30 Days' },
  { value: '90d', label: 'Last 90 Days' },
  { value: 'this_year', label: 'This Year' },
];

const loadData = async (isManualRefresh = false) => {
  if (isManualRefresh) {
    refreshing.value = true;
  } else {
    loading.value = true;
  }
  error.value = null;

  try {
    const res = await commandCenterService.getCommandCenterData({
      period: period.value,
    });
    data.value = res;
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load Platform Command Center telemetry.';
  } finally {
    loading.value = false;
    refreshing.value = false;
  }
};

watch(period, () => {
  loadData();
});

onMounted(() => {
  loadData();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Header Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-xl border border-neutral-ivory shadow-sm">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-xl sm:text-2xl font-extrabold text-neutral-black tracking-tight">
            Platform Command Center
          </h1>
          <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 bg-primary/10 text-primary rounded-full">
            Executive Operations
          </span>
        </div>
        <p class="text-xs text-neutral-muted mt-1">
          Unified operational cockpit aggregating cross-platform health, immediate attention items, domain intelligence, and automation governance.
        </p>
      </div>

      <div class="flex items-center gap-3 self-start sm:self-center">
        <!-- Period Selector -->
        <select
          v-model="period"
          class="px-3 py-2 text-xs font-bold bg-neutral-background border border-neutral-ivory rounded-lg text-neutral-black focus:outline-none focus:border-primary min-h-[44px] cursor-pointer"
        >
          <option v-for="p in periods" :key="p.value" :value="p.value">
            {{ p.label }}
          </option>
        </select>

        <!-- Refresh Button -->
        <button
          @click="loadData(true)"
          :disabled="refreshing"
          class="px-4 py-2 text-xs font-bold text-white bg-primary hover:bg-primary-dark rounded-lg transition-colors min-h-[44px] cursor-pointer flex items-center gap-2 disabled:opacity-50"
        >
          <svg
            :class="['h-4 w-4', { 'animate-spin': refreshing }]"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span>{{ refreshing ? 'Refreshing...' : 'Refresh' }}</span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="py-16 text-center bg-white rounded-xl border border-neutral-ivory">
      <svg class="mx-auto h-8 w-8 text-primary animate-spin mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
      </svg>
      <p class="text-xs font-bold text-neutral-black">Aggregating Cross-Platform Telemetry...</p>
      <p class="text-[11px] text-neutral-muted">Probing database, queue workers, domain intelligence, and governance metrics.</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-6 bg-red-50 rounded-xl border border-red-200 text-red-800">
      <div class="flex items-center gap-2 font-bold text-sm mb-1">
        <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>Telemetry Aggregation Error</span>
      </div>
      <p class="text-xs">{{ error }}</p>
      <button @click="loadData()" class="mt-3 px-3 py-1.5 text-xs font-bold bg-red-600 text-white rounded-lg hover:bg-red-700">
        Retry Load
      </button>
    </div>

    <!-- Telemetry Content -->
    <div v-else-if="data" class="space-y-6">
      <!-- Status Overview Cards -->
      <PlatformStatusOverview :status="data.platform" />

      <!-- Operational Risk Card & Attention Required Stream -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <OperationalRiskCard :risk="data.risk" class="lg:col-span-1" />
        <AttentionRequiredPanel :items="data.attention" class="lg:col-span-2" />
      </div>

      <!-- Infrastructure Health Probes -->
      <PlatformHealthPanel :infra="data.infrastructure" />

      <!-- Application Health Matrix -->
      <ApplicationHealthMatrix :applications="data.applications" />

      <!-- Business Operations Snapshot -->
      <BusinessOperationsSnapshot :business="data.business" />

      <!-- Automation Governance Snapshot -->
      <AutomationOperationsSnapshot :automation="data.automation" />
    </div>
  </div>
</template>
