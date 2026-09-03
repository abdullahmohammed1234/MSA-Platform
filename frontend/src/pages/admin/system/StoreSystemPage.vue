<script setup lang="ts">
import { ref, onMounted } from 'vue';
import client from '@/services/api';
import { ExternalLink, RefreshCw, CheckCircle } from 'lucide-vue-next';

const loading = ref(false);
const systemStatus = ref<any>(null);
const healthData = ref<any>(null);
const metricsData = ref<any>(null);

const loadAll = async () => {
  loading.value = true;
  try {
    const [sys, health, metrics] = await Promise.all([
      client.get('/admin/systems/store'),
      client.get('/admin/systems/store/health'),
      client.get('/admin/systems/store/metrics'),
    ]);
    if (sys.data.success) systemStatus.value = sys.data.system;
    if (health.data.success) healthData.value = health.data.health;
    if (metrics.data.success) metricsData.value = metrics.data.metrics;
  } catch (err) {
    console.error('Failed to load Store system status', err);
  } finally {
    loading.value = false;
  }
};

onMounted(loadAll);
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-display font-bold text-neutral-black">Store Management System</h1>
        <p class="text-sm text-neutral-muted mt-1">
          Store application registry entry — merchandise catalogue, inventory, and orders control plane.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory px-3 py-2 text-sm font-semibold hover:bg-neutral-background cursor-pointer"
          @click="loadAll"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>
        <router-link
          to="/store/admin"
          class="inline-flex items-center gap-2 rounded-xl bg-primary px-3 py-2 text-sm font-semibold text-white hover:opacity-90 cursor-pointer"
        >
          Open Store Admin
          <ExternalLink class="h-4 w-4" />
        </router-link>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Status</p>
        <p class="mt-2 flex items-center gap-2 text-lg font-semibold text-neutral-black">
          <CheckCircle class="h-5 w-5 text-secondary" />
          {{ systemStatus?.status || '—' }}
        </p>
        <p class="mt-1 text-xs text-neutral-muted">{{ systemStatus?.name }}</p>
      </div>
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Health</p>
        <p class="mt-2 text-lg font-semibold text-neutral-black capitalize">{{ healthData?.health_status || healthData?.status || '—' }}</p>
        <p class="mt-1 text-xs text-neutral-muted">{{ healthData?.checked_at || '' }}</p>
      </div>
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
        <p class="text-[10px] font-black uppercase tracking-widest text-neutral-muted">Frontend</p>
        <p class="mt-2 text-sm font-semibold text-primary break-all">{{ systemStatus?.frontend_url || '/store/admin' }}</p>
      </div>
    </div>

    <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
      <h2 class="text-sm font-bold text-neutral-black">Store metrics</h2>
      <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">
        <div><dt class="text-neutral-muted">Total Products</dt><dd class="font-semibold">{{ metricsData?.products ?? '—' }}</dd></div>
        <div><dt class="text-neutral-muted">Active Products</dt><dd class="font-semibold">{{ metricsData?.products_active ?? '—' }}</dd></div>
        <div><dt class="text-neutral-muted">Total Orders</dt><dd class="font-semibold">{{ metricsData?.orders ?? '—' }}</dd></div>
        <div><dt class="text-neutral-muted">Pending Orders</dt><dd class="font-semibold">{{ metricsData?.orders_pending ?? '—' }}</dd></div>
        <div><dt class="text-neutral-muted">Completed Orders</dt><dd class="font-semibold">{{ metricsData?.orders_completed ?? '—' }}</dd></div>
      </dl>
    </div>

    <div class="rounded-2xl border border-neutral-ivory bg-white p-5">
      <h2 class="text-sm font-bold text-neutral-black">Owns (logical)</h2>
      <ul class="mt-3 flex flex-wrap gap-2">
        <li
          v-for="table in systemStatus?.owns || ['store_products', 'store_inventory', 'store_orders']"
          :key="table"
          class="rounded-full bg-neutral-background px-3 py-1 text-xs font-semibold text-neutral-black"
        >
          {{ table }}
        </li>
      </ul>
    </div>
  </div>
</template>
