<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { sponsorshipService } from '@/services/sponsorship.service';
import {
  Building2,
  DollarSign,
  TrendingUp,
  CheckCircle2,
  Plus,
  ArrowRight,
  Handshake,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const router = useRouter();
const loading = ref(true);
const metrics = ref({
  total_committed_cents: 0,
  total_collected_cents: 0,
  outstanding_cents: 0,
  total_in_kind_cents: 0,
  active_sponsorships_count: 0,
  total_organizations_count: 0,
  pending_follow_ups_count: 0,
  fulfillment_rate_percent: 0,
});
const recentSponsorships = ref<any[]>([]);

onMounted(async () => {
  try {
    const res = await sponsorshipService.getDashboardMetrics();
    if (res.success) {
      metrics.value = res.data.metrics;
      recentSponsorships.value = res.data.recent_sponsorships || [];
    }
  } catch (err) {
    console.error('Failed to load SPMS dashboard metrics:', err);
  } finally {
    loading.value = false;
  }
});

const formatCurrency = (cents: number) => {
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
    maximumFractionDigits: 0,
  }).format((cents || 0) / 100);
};
</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-neutral-black tracking-tight">Sponsorship & Partnerships Dashboard</h1>
        <p class="text-xs text-neutral-muted mt-1">
          Executive overview of corporate partners, active deals, financial commitments, and deliverable fulfillment.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <Button variant="outline" @click="router.push('/sponsorship/admin/organizations')">
          <Building2 class="w-4 h-4 mr-2" />
          Sponsors CRM
        </Button>
        <Button variant="primary" @click="router.push('/sponsorship/admin/sponsorships')">
          <Plus class="w-4 h-4 mr-2" />
          New Sponsorship Deal
        </Button>
      </div>
    </div>

    <!-- Metrics Overview Grid -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary border-t-transparent"></div>
      <p class="mt-3 text-xs text-neutral-muted uppercase tracking-widest font-bold">Loading Financial Metrics...</p>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Total Committed</span>
          <div class="p-2 bg-emerald-50 rounded-xl text-emerald-600">
            <DollarSign class="w-5 h-5" />
          </div>
        </div>
        <div class="mt-4 text-2xl font-extrabold text-neutral-black">
          {{ formatCurrency(metrics.total_committed_cents) }}
        </div>
        <p class="text-[10px] text-neutral-muted mt-1">Contractual deal value</p>
      </div>

      <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Total Collected</span>
          <div class="p-2 bg-primary/10 rounded-xl text-primary">
            <TrendingUp class="w-5 h-5" />
          </div>
        </div>
        <div class="mt-4 text-2xl font-extrabold text-primary">
          {{ formatCurrency(metrics.total_collected_cents) }}
        </div>
        <p class="text-[10px] text-emerald-600 font-bold mt-1">
          {{ formatCurrency(metrics.outstanding_cents) }} outstanding
        </p>
      </div>

      <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Active Partners</span>
          <div class="p-2 bg-blue-50 rounded-xl text-blue-600">
            <Building2 class="w-5 h-5" />
          </div>
        </div>
        <div class="mt-4 text-2xl font-extrabold text-neutral-black">
          {{ metrics.total_organizations_count }}
        </div>
        <p class="text-[10px] text-neutral-muted mt-1">{{ metrics.active_sponsorships_count }} active deals</p>
      </div>

      <div class="bg-white rounded-2xl p-6 border border-neutral-ivory shadow-soft">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Fulfillment Rate</span>
          <div class="p-2 bg-amber-50 rounded-xl text-amber-600">
            <CheckCircle2 class="w-5 h-5" />
          </div>
        </div>
        <div class="mt-4 text-2xl font-extrabold text-neutral-black">
          {{ metrics.fulfillment_rate_percent }}%
        </div>
        <p class="text-[10px] text-neutral-muted mt-1">{{ metrics.pending_follow_ups_count }} pending follow-ups</p>
      </div>
    </div>

    <!-- Recent Deals List -->
    <div class="bg-white rounded-2xl border border-neutral-ivory p-6 shadow-soft">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-bold text-neutral-black">Recent Sponsorship Deals</h2>
        <button
          @click="router.push('/sponsorship/admin/sponsorships')"
          class="text-xs font-bold text-primary hover:underline flex items-center gap-1 cursor-pointer"
        >
          <span>View All Deals</span>
          <ArrowRight class="w-3.5 h-3.5" />
        </button>
      </div>

      <div v-if="recentSponsorships.length === 0" class="text-center py-8 text-neutral-muted text-xs">
        No sponsorship deals recorded yet.
      </div>

      <div v-else class="divide-y divide-neutral-ivory">
        <div
          v-for="deal in recentSponsorships"
          :key="deal.uuid"
          class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-neutral-background/50 px-3 rounded-xl transition-colors"
        >
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary font-bold text-xs shrink-0">
              <Handshake class="w-5 h-5" />
            </div>
            <div>
              <span class="text-xs font-bold text-neutral-black block">{{ deal.title }}</span>
              <span class="text-[10px] text-neutral-muted">{{ deal.organization?.display_name || 'Organization' }} • {{ deal.sponsorship_number }}</span>
            </div>
          </div>

          <div class="flex items-center gap-6">
            <div class="text-right">
              <span class="text-sm font-extrabold text-neutral-black block">{{ formatCurrency(deal.total_committed_cents) }}</span>
              <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-bold">
                {{ deal.status }}
              </span>
            </div>

            <button
              @click="router.push(`/sponsorship/admin/sponsorships/${deal.uuid}`)"
              class="text-xs font-bold text-primary hover:underline cursor-pointer"
            >
              Open
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
