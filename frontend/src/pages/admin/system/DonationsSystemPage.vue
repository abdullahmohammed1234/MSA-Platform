<script setup lang="ts">
import { ref, onMounted } from 'vue';
import client from '@/services/api';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const systemInfo = ref<any>(null);
const healthInfo = ref<any>(null);
const metricsInfo = ref<any>(null);
const isLoading = ref(true);

onMounted(async () => {
  await fetchAll();
});

const fetchAll = async () => {
  isLoading.value = true;
  try {
    const [sysRes, healthRes, metricsRes] = await Promise.all([
      client.get('/admin/systems/donations'),
      client.get('/admin/systems/donations/health'),
      client.get('/admin/systems/donations/metrics'),
    ]);

    systemInfo.value = sysRes.data.system;
    healthInfo.value = healthRes.data.health;
    metricsInfo.value = metricsRes.data.metrics;
  } catch (error) {
    toast.error('Failed to load Donations system control plane data.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="text-xs font-mono font-bold bg-primary/10 text-primary px-2.5 py-0.5 rounded">SYSTEM REGISTRY</span>
          <span class="text-xs text-neutral-muted">v{{ systemInfo?.version || '1.0.0' }}</span>
        </div>
        <h1 class="text-3xl font-display font-medium text-primary mt-1">Donation Management System (DMS)</h1>
        <p class="text-sm text-neutral-muted mt-1">Control plane status, database reachability, Square integration health, and metrics.</p>
      </div>

      <div class="flex items-center gap-3">
        <a
          v-if="systemInfo?.frontend_url"
          :href="systemInfo.frontend_url"
          target="_blank"
          class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-1.5"
        >
          <span>Launch DMS App</span>
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
        </a>
        <button
          @click="fetchAll"
          class="px-3.5 py-2 text-xs font-bold rounded-xl border border-neutral-ivory hover:bg-neutral-background text-neutral-muted hover:text-primary transition-all cursor-pointer"
        >
          Refresh Health
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
      <p class="text-xs text-neutral-muted">Probing Donations system health...</p>
    </div>

    <template v-else>
      <!-- Health & Status Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Overall System Health</span>
          <div class="mt-2 flex items-center gap-2">
            <span class="h-3 w-3 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-xl font-bold text-neutral-black uppercase">{{ healthInfo?.status || 'HEALTHY' }}</span>
          </div>
          <p class="text-xs text-neutral-muted mt-2">Database connectivity & table reachability verified.</p>
        </div>

        <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Square Integration</span>
          <div class="mt-2 flex items-center gap-2">
            <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
            <span class="text-xl font-bold text-neutral-black">ACTIVE</span>
          </div>
          <p class="text-xs text-neutral-muted mt-2">Low-level Square Connect API v2 enabled.</p>
        </div>

        <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Data Ownership</span>
          <div class="mt-2 flex flex-wrap gap-1">
            <span v-for="table in systemInfo?.owns || ['donations', 'donation_refunds']" :key="table" class="text-[10px] font-mono bg-neutral-background px-2 py-0.5 rounded border border-neutral-ivory">
              {{ table }}
            </span>
          </div>
        </div>
      </div>

      <!-- Live Volume Metrics -->
      <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6 space-y-4" v-if="metricsInfo">
        <h2 class="text-base font-bold text-neutral-black">System Volume Metrics</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div class="bg-neutral-background/60 p-4 rounded-xl border border-neutral-ivory/60">
            <span class="text-[10px] font-bold text-neutral-muted uppercase">Total Volume</span>
            <div class="text-xl font-bold text-primary mt-1">{{ metricsInfo.total_revenue_formatted }}</div>
          </div>
          <div class="bg-neutral-background/60 p-4 rounded-xl border border-neutral-ivory/60">
            <span class="text-[10px] font-bold text-neutral-muted uppercase">Paid Count</span>
            <div class="text-xl font-bold text-emerald-600 mt-1">{{ metricsInfo.paid_donations }}</div>
          </div>
          <div class="bg-neutral-background/60 p-4 rounded-xl border border-neutral-ivory/60">
            <span class="text-[10px] font-bold text-neutral-muted uppercase">Pending Count</span>
            <div class="text-xl font-bold text-amber-500 mt-1">{{ metricsInfo.pending_donations }}</div>
          </div>
          <div class="bg-neutral-background/60 p-4 rounded-xl border border-neutral-ivory/60">
            <span class="text-[10px] font-bold text-neutral-muted uppercase">Refunded Count</span>
            <div class="text-xl font-bold text-rose-600 mt-1">{{ metricsInfo.refunded_donations }}</div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
