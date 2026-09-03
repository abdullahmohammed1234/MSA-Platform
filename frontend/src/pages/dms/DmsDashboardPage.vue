<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { donationsService } from '@/services/donations.service';
import { useToastStore } from '@/components/feedback/toast';

const toast = useToastStore();

const metrics = ref<any>(null);
const recentDonations = ref<any[]>([]);
const isLoading = ref(true);

onMounted(async () => {
  await fetchDashboard();
});

const fetchDashboard = async () => {
  isLoading.value = true;
  try {
    const data = await donationsService.getDmsDashboard();
    metrics.value = data.metrics;
    recentDonations.value = data.recent_donations || [];
  } catch (error) {
    toast.error('Failed to load DMS dashboard metrics.');
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
        <h1 class="text-3xl font-display font-medium text-primary">Donations Dashboard</h1>
        <p class="text-sm text-neutral-muted mt-1">Real-time overview of community contributions, revenue metrics, and recent donations.</p>
      </div>
      <button
        @click="fetchDashboard"
        class="px-3.5 py-2 text-xs font-bold rounded-xl border border-neutral-ivory hover:bg-neutral-background text-neutral-muted hover:text-primary transition-all cursor-pointer"
      >
        Refresh Metrics
      </button>
    </div>

    <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
      <p class="text-xs text-neutral-muted">Loading donation metrics...</p>
    </div>

    <template v-else-if="metrics">
      <!-- Metric Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Total Paid Revenue</span>
          <div class="mt-2 text-2xl font-bold text-emerald-600">{{ metrics.total_revenue_formatted }}</div>
          <p class="text-[11px] text-neutral-muted mt-1">{{ metrics.total_donations_count }} confirmed paid transactions</p>
        </div>

        <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">This Month</span>
          <div class="mt-2 text-2xl font-bold text-primary">{{ metrics.this_month_formatted }}</div>
          <p class="text-[11px] text-neutral-muted mt-1">{{ metrics.this_month_count }} donations received this month</p>
        </div>

        <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Pending Checkouts</span>
          <div class="mt-2 text-2xl font-bold text-amber-500">{{ metrics.pending_donations_count }}</div>
          <p class="text-[11px] text-neutral-muted mt-1">Awaiting Square checkout completion</p>
        </div>

        <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Total Refunded</span>
          <div class="mt-2 text-2xl font-bold text-rose-600">{{ metrics.refunded_donations_count }}</div>
          <p class="text-[11px] text-neutral-muted mt-1">Refunded via Square API</p>
        </div>
      </div>

      <!-- Recent Donations List -->
      <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft p-6 space-y-4">
        <div class="flex justify-between items-center pb-3 border-b border-neutral-ivory/50">
          <h2 class="text-base font-bold text-neutral-black">Recent Donations Feed</h2>
          <router-link to="/donations/admin/donations" class="text-xs font-bold text-primary hover:underline">
            View All Donations →
          </router-link>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-neutral-background/50 border-b border-neutral-ivory/50 text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
                <th class="px-4 py-3">Ref Number</th>
                <th class="px-4 py-3">Donor Name</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory/30 text-xs">
              <tr v-for="d in recentDonations" :key="d.uuid" class="hover:bg-neutral-background/30 transition-colors">
                <td class="px-4 py-3 font-mono font-bold text-neutral-black">{{ d.donation_number }}</td>
                <td class="px-4 py-3">
                  <span :class="{'italic text-neutral-muted': d.is_anonymous}">
                    {{ d.is_anonymous ? 'Anonymous' : d.donor_name }}
                  </span>
                </td>
                <td class="px-4 py-3 font-bold text-primary">${{ (d.amount_cents / 100).toFixed(2) }} CAD</td>
                <td class="px-4 py-3">
                  <span
                    :class="[
                      'text-[9px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider',
                      d.status === 'paid' ? 'bg-emerald-100 text-emerald-700' : d.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700'
                    ]"
                  >
                    {{ d.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-neutral-muted font-mono">{{ new Date(d.created_at).toLocaleDateString() }}</td>
              </tr>

              <tr v-if="recentDonations.length === 0">
                <td colspan="5" class="px-4 py-8 text-center text-neutral-muted italic">No donations recorded yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
