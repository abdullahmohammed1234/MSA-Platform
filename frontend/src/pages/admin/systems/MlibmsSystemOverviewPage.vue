<template>
  <div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 bg-primary/10 text-primary border border-primary/20 text-xs font-mono font-bold uppercase rounded-full">
            System Control Plane
          </span>
        </div>
        <h1 class="text-3xl font-display font-bold text-neutral-black mt-1">MLibMS — Library Management System</h1>
        <p class="text-neutral-muted text-sm">Centralized administration, RBAC access metrics, system health, and circulation overview.</p>
      </div>

      <div class="flex items-center gap-3">
        <router-link
          to="/library/admin"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary/90 text-white font-bold rounded-xl transition-colors text-sm shadow-soft"
        >
          <span>Launch MLibMS Admin Workbench</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
        </router-link>
      </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-2xl p-5 border border-neutral-ivory shadow-soft">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Catalog Titles</div>
        <div class="text-3xl font-black text-neutral-black mt-2">{{ stats.total_books ?? 0 }}</div>
        <div class="text-xs text-emerald-600 mt-1 flex items-center gap-1 font-medium">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active Catalog
        </div>
      </div>

      <div class="bg-white rounded-2xl p-5 border border-neutral-ivory shadow-soft">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Physical Copies</div>
        <div class="text-3xl font-black text-neutral-black mt-2">{{ stats.total_copies ?? 0 }}</div>
        <div class="text-xs text-neutral-muted mt-1 font-medium">Tracked Inventory</div>
      </div>

      <div class="bg-white rounded-2xl p-5 border border-neutral-ivory shadow-soft">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Active Loans</div>
        <div class="text-3xl font-black text-neutral-black mt-2">{{ stats.active_loans ?? 0 }}</div>
        <div class="text-xs text-amber-600 mt-1 font-medium" v-if="stats.overdue_loans > 0">
          {{ stats.overdue_loans }} Overdue Items
        </div>
        <div class="text-xs text-emerald-600 mt-1 font-medium" v-else>
          0 Overdue Items
        </div>
      </div>

      <div class="bg-white rounded-2xl p-5 border border-neutral-ivory shadow-soft">
        <div class="text-xs text-neutral-muted font-bold uppercase tracking-wider">Registered Members</div>
        <div class="text-3xl font-black text-neutral-black mt-2">{{ stats.total_members ?? 0 }}</div>
        <div class="text-xs text-neutral-muted mt-1 font-medium">{{ stats.guest_members ?? 0 }} Guest Accounts</div>
      </div>
    </div>

    <!-- System Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft space-y-4">
        <h3 class="text-lg font-bold text-neutral-black">MLibMS System Overview & Features</h3>
        <p class="text-sm text-neutral-muted leading-relaxed">
          MLibMS is a standalone, first-class business application operating within the SFU MSA Platform. It features self-service borrowing and returns, barcode/QR code physical copy tracking, automated daily 2-day due reminder queue dispatches, and an integrated staff ISBN intake workbench.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
          <div class="bg-neutral-background p-4 rounded-xl border border-neutral-ivory space-y-1">
            <div class="font-bold text-neutral-black text-sm">Self-Service Circulation</div>
            <p class="text-xs text-neutral-muted">Authenticated members check out and return physical copies directly from `/library/scan`.</p>
          </div>

          <div class="bg-neutral-background p-4 rounded-xl border border-neutral-ivory space-y-1">
            <div class="font-bold text-neutral-black text-sm">ISBN Cataloging Workbench</div>
            <p class="text-xs text-neutral-muted">Staff intake via barcode scan with automated Open Library metadata autofill.</p>
          </div>

          <div class="bg-neutral-background p-4 rounded-xl border border-neutral-ivory space-y-1">
            <div class="font-bold text-neutral-black text-sm">Concurrency & Pessimism</div>
            <p class="text-xs text-neutral-muted">Pessimistic row locking (`lockForUpdate`) protects loan states and copy availability.</p>
          </div>

          <div class="bg-neutral-background p-4 rounded-xl border border-neutral-ivory space-y-1">
            <div class="font-bold text-neutral-black text-sm">Idempotent Reminders</div>
            <p class="text-xs text-neutral-muted">Scheduled daily at 08:00 to dispatch 2-day due date notices without double dispatches.</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft space-y-4">
        <h3 class="text-lg font-bold text-neutral-black">Application Status</h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between items-center py-2 border-b border-neutral-ivory">
            <span class="text-neutral-muted">Public Portal</span>
            <span class="text-primary font-bold">/library</span>
          </div>
          <div class="flex justify-between items-center py-2 border-b border-neutral-ivory">
            <span class="text-neutral-muted">Admin Surface</span>
            <span class="text-primary font-bold">/library/admin</span>
          </div>
          <div class="flex justify-between items-center py-2 border-b border-neutral-ivory">
            <span class="text-neutral-muted">Control Plane</span>
            <span class="text-primary font-bold">/admin/systems/library</span>
          </div>
          <div class="flex justify-between items-center py-2">
            <span class="text-neutral-muted">Access Key</span>
            <span class="text-amber-700 font-mono font-bold">mlibms</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '@/services/api';

const stats = ref<Record<string, any>>({});

const fetchOverview = async () => {
  try {
    const res = await api.get('/admin/systems/library');
    stats.value = res.data?.data || res.data || {};
  } catch (err) {
    console.error('Failed to load MLibMS central system overview', err);
  }
};

onMounted(() => {
  fetchOverview();
});
</script>

