<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useSystemStore } from '@/stores/system';
import { systemService } from '@/services/system';
import { BarChart3, Download, RefreshCw, Trash2 } from 'lucide-vue-next';

const systemStore = useSystemStore();
const isGenerating = ref(false);

const summary = computed(() => ({
  total: systemStore.reports.length,
  daily: systemStore.reports.filter((report) => report.type === 'daily').length,
  weekly: systemStore.reports.filter((report) => report.type === 'weekly').length,
  monthly: systemStore.reports.filter((report) => report.type === 'monthly').length,
}));

const loadReports = async () => {
  await systemStore.fetchReports();
};

const generateReport = async (period: 'daily' | 'weekly' | 'monthly') => {
  isGenerating.value = true;
  try {
    await systemStore.generateReport(period);
  } finally {
    isGenerating.value = false;
  }
};

const deleteReport = async (uuid: string) => {
  if (!window.confirm('Delete this report?')) return;
  await systemStore.deleteReport(uuid);
};

const downloadReport = (uuid: string) => {
  window.open(systemService.getDownloadUrl(uuid), '_blank');
};

onMounted(() => {
  loadReports();
});
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto p-1 text-neutral-black">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary/70">Academy Admin</p>
        <h1 class="text-3xl font-display font-extrabold text-primary">Admin Reports</h1>
        <p class="text-sm text-neutral-muted mt-1">Generate and manage academy reporting exports for operations and oversight.</p>
      </div>
      <button @click="loadReports" class="inline-flex items-center gap-2 rounded-xl border border-neutral-ivory bg-white px-4 py-2.5 text-sm hover:bg-neutral-background transition"><RefreshCw class="h-4 w-4" /> Refresh</button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"><p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-muted">Total</p><p class="mt-2 text-3xl font-display font-bold text-primary">{{ summary.total }}</p></div>
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"><p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-muted">Daily</p><p class="mt-2 text-3xl font-display font-bold text-primary">{{ summary.daily }}</p></div>
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"><p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-muted">Weekly</p><p class="mt-2 text-3xl font-display font-bold text-primary">{{ summary.weekly }}</p></div>
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft"><p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-muted">Monthly</p><p class="mt-2 text-3xl font-display font-bold text-primary">{{ summary.monthly }}</p></div>
    </div>

    <section class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3"><BarChart3 class="h-5 w-5 text-primary" /><h2 class="text-lg font-display font-semibold">Generate a report</h2></div>
        <div class="flex flex-wrap gap-2">
          <button @click="generateReport('daily')" :disabled="isGenerating" class="rounded-xl border border-neutral-ivory px-3 py-2 text-sm hover:bg-neutral-background transition">Daily</button>
          <button @click="generateReport('weekly')" :disabled="isGenerating" class="rounded-xl border border-neutral-ivory px-3 py-2 text-sm hover:bg-neutral-background transition">Weekly</button>
          <button @click="generateReport('monthly')" :disabled="isGenerating" class="rounded-xl border border-neutral-ivory px-3 py-2 text-sm hover:bg-neutral-background transition">Monthly</button>
        </div>
      </div>

      <div class="mt-6 space-y-3">
        <article v-for="report in systemStore.reports" :key="report.uuid" class="flex flex-col gap-3 rounded-2xl border border-neutral-ivory p-4 md:flex-row md:items-center md:justify-between">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="font-display text-base font-semibold text-neutral-black">{{ report.title }}</h3>
              <span class="rounded-full bg-neutral-background px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-neutral-muted">{{ report.type }}</span>
            </div>
            <p class="mt-1 text-sm text-neutral-muted">Generated {{ new Date(report.generated_at).toLocaleString() }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button @click="downloadReport(report.uuid)" class="inline-flex items-center gap-1 rounded-xl border border-neutral-ivory px-3 py-2 text-sm hover:bg-neutral-background transition"><Download class="h-4 w-4" /> Download</button>
            <button @click="deleteReport(report.uuid)" class="inline-flex items-center gap-1 rounded-xl border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50 transition"><Trash2 class="h-4 w-4" /> Delete</button>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>