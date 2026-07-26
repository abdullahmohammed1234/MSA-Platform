<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import {
  AlertCircle,
  Calendar,
  CheckCircle2,
  DollarSign,
  Filter,
  RefreshCw,
  Users,
} from 'lucide-vue-next';
import { useEmsAccessStore } from '@/stores/ems/emsAccess';
import { EMS_PERMISSIONS } from '@/constants/ems';
import { analyticsService, type AnalyticsPayload } from '@/services/ems/analyticsService';
import { eventsService } from '@/services/ems/eventsService';
import { categoriesService } from '@/services/ems/categoriesService';
import type { Event, EventCategory } from '@/types/ems';
import SvgLineChart from '@/components/ems/charts/SvgLineChart.vue';
import SvgPieChart from '@/components/ems/charts/SvgPieChart.vue';
import { useToastStore } from '@/components/feedback/toast';

const access = ref(useEmsAccessStore());
const toast = useToastStore();

const isLoading = ref(true);
const error = ref<string | null>(null);
const payload = ref<AnalyticsPayload | null>(null);

// Filters state
const filterEventUuid = ref<string>('');
const filterCategoryId = ref<number | null>(null);
const filterStartDate = ref<string>('');
const filterEndDate = ref<string>('');

// Dropdowns lists
const eventsList = ref<Event[]>([]);
const categoriesList = ref<EventCategory[]>([]);

const showFinancial = computed(() => access.value.can(EMS_PERMISSIONS.ANALYTICS_VIEW_FINANCIAL));

const loadDropdowns = async () => {
  try {
    const [eventsRes, catsRes] = await Promise.all([
      eventsService.list({ per_page: 100 }),
      categoriesService.list(),
    ]);
    eventsList.value = eventsRes.items;
    categoriesList.value = catsRes;
  } catch (err) {
    console.error('Failed to load dropdown filters', err);
  }
};

const advancedPayload = ref<any | null>(null);
const isLoadingAdvanced = ref(false);

const fetchAdvancedAnalytics = async () => {
  if (!filterEventUuid.value) {
    advancedPayload.value = null;
    return;
  }
  isLoadingAdvanced.value = true;
  try {
    const data = await analyticsService.advancedReport({
      event_uuid: filterEventUuid.value,
    });
    advancedPayload.value = data;
  } catch (err) {
    console.error('Failed to load advanced analytics funnel', err);
  } finally {
    isLoadingAdvanced.value = false;
  }
};

const fetchAnalytics = async () => {
  isLoading.value = true;
  error.value = null;
  try {
    const data = await analyticsService.dashboard({
      event_uuid: filterEventUuid.value || undefined,
      category_id: filterCategoryId.value || undefined,
      start_date: filterStartDate.value || undefined,
      end_date: filterEndDate.value || undefined,
    });
    payload.value = data;
  } catch (err: any) {
    error.value = err.message || 'Failed to load analytics payload.';
    toast.error('Error fetching analytics.');
  } finally {
    isLoading.value = false;
  }
};

onMounted(async () => {
  await loadDropdowns();
  await Promise.all([fetchAnalytics(), fetchAdvancedAnalytics()]);
});

// Watch filters to trigger fetch
watch(
  [filterEventUuid, filterCategoryId, filterStartDate, filterEndDate],
  () => {
    void fetchAnalytics();
    void fetchAdvancedAnalytics();
  }
);

// Formatters
const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(val);
};

// Map registration trends to line chart points
const lineChartData = computed(() => {
  if (!payload.value?.charts.registrations_over_time) return [];
  return payload.value.charts.registrations_over_time.map((d) => ({
    label: d.date,
    value: d.count,
  }));
});

// Map breakdown to pie segments
const breakdownSegments = computed(() => {
  if (!payload.value?.charts.member_breakdown) return [];
  const counts = payload.value.charts.member_breakdown.counts;
  return [
    { label: 'MSA Members', value: counts.members },
    { label: 'Volunteers', value: counts.volunteers },
    { label: 'Students', value: counts.students },
    { label: 'Guests', value: counts.guests },
    { label: 'Others', value: counts.others },
  ].filter((s) => s.value > 0);
});

const clearFilters = () => {
  filterEventUuid.value = '';
  filterCategoryId.value = null;
  filterStartDate.value = '';
  filterEndDate.value = '';
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold font-serif text-primary">Analytics Dashboard</h1>
        <p class="text-xs text-neutral-muted">Real-time operational metrics and trends across events.</p>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex h-9 items-center gap-2 rounded-xl border border-neutral-ivory bg-white px-3.5 text-xs font-semibold text-neutral-black hover:bg-neutral-background cursor-pointer"
          @click="clearFilters"
        >
          Reset Filters
        </button>
        <button
          type="button"
          class="inline-flex h-9 items-center gap-2 rounded-xl bg-primary px-3.5 text-xs font-semibold text-white hover:bg-primary/95 cursor-pointer"
          @click="fetchAnalytics"
        >
          <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isLoading }" />
          Refresh
        </button>
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
      <div class="flex items-center gap-2 border-b border-neutral-ivory pb-3 mb-4">
        <Filter class="h-4 w-4 text-primary" />
        <span class="text-xs font-bold uppercase tracking-wider text-neutral-black">Dashboard Filters</span>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Event Filter -->
        <div class="space-y-1.5">
          <label for="filter-event" class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Filter by Event</label>
          <select
            id="filter-event"
            v-model="filterEventUuid"
            class="w-full rounded-lg border border-neutral-ivory bg-neutral-background/40 px-3 py-2 text-xs text-neutral-black focus:border-primary focus:outline-none"
          >
            <option value="">All Events</option>
            <option v-for="e in eventsList" :key="e.uuid" :value="e.uuid">
              {{ e.name }}
            </option>
          </select>
        </div>

        <!-- Category Filter -->
        <div class="space-y-1.5">
          <label for="filter-category" class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Filter by Category</label>
          <select
            id="filter-category"
            v-model="filterCategoryId"
            class="w-full rounded-lg border border-neutral-ivory bg-neutral-background/40 px-3 py-2 text-xs text-neutral-black focus:border-primary focus:outline-none"
          >
            <option :value="null">All Categories</option>
            <option v-for="c in categoriesList" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
        </div>

        <!-- Start Date Filter -->
        <div class="space-y-1.5">
          <label for="filter-start-date" class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">From Date</label>
          <input
            id="filter-start-date"
            type="date"
            v-model="filterStartDate"
            class="w-full rounded-lg border border-neutral-ivory bg-neutral-background/40 px-3 py-2 text-xs text-neutral-black focus:border-primary focus:outline-none"
          />
        </div>

        <!-- End Date Filter -->
        <div class="space-y-1.5">
          <label for="filter-end-date" class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">To Date</label>
          <input
            id="filter-end-date"
            type="date"
            v-model="filterEndDate"
            class="w-full rounded-lg border border-neutral-ivory bg-neutral-background/40 px-3 py-2 text-xs text-neutral-black focus:border-primary focus:outline-none"
          />
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-2xl bg-neutral-ivory/50" />
    </div>

    <div v-else-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-center text-sm text-red-800">
      <AlertCircle class="mx-auto h-8 w-8 mb-2 text-red-600" />
      {{ error }}
    </div>

    <template v-else-if="payload">
      <!-- Conversion Funnel Section -->
      <section v-if="advancedPayload && advancedPayload.funnel" class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft space-y-4">
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-3">
          <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black">Event Registration Conversion Funnel</h3>
          <span class="text-[10px] text-neutral-muted">Funnels details from views to check-ins</span>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5 text-center">
          <!-- Views -->
          <div class="p-4 rounded-xl bg-neutral-background/30 border border-neutral-ivory/50">
            <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">1. Event Views</p>
            <p class="text-2xl font-bold font-serif text-neutral-black mt-1">{{ advancedPayload.funnel.views }}</p>
            <p class="text-[10px] text-neutral-muted mt-0.5">Total page views</p>
          </div>

          <!-- Started -->
          <div class="p-4 rounded-xl bg-neutral-background/30 border border-neutral-ivory/50">
            <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">2. Checkout Started</p>
            <p class="text-2xl font-bold font-serif text-neutral-black mt-1">{{ advancedPayload.funnel.started }}</p>
            <p class="text-[10px] text-neutral-muted mt-0.5">
              Rate: {{ advancedPayload.funnel.rates.views_to_started }}%
            </p>
          </div>

          <!-- Completed -->
          <div class="p-4 rounded-xl bg-neutral-background/30 border border-neutral-ivory/50">
            <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">3. Checkout Completed</p>
            <p class="text-2xl font-bold font-serif text-neutral-black mt-1">{{ advancedPayload.funnel.completed }}</p>
            <p class="text-[10px] text-neutral-muted mt-0.5">
              Rate: {{ advancedPayload.funnel.rates.started_to_completed }}%
            </p>
          </div>

          <!-- Tickets Issued -->
          <div class="p-4 rounded-xl bg-neutral-background/30 border border-neutral-ivory/50">
            <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">4. Tickets Issued</p>
            <p class="text-2xl font-bold font-serif text-neutral-black mt-1">{{ advancedPayload.funnel.tickets_issued }}</p>
            <p class="text-[10px] text-neutral-muted mt-0.5">Expected attendees</p>
          </div>

          <!-- Checked In -->
          <div class="p-4 rounded-xl bg-neutral-background/30 border border-neutral-ivory/50">
            <p class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">5. Checked In</p>
            <p class="text-2xl font-bold font-serif text-emerald-800 mt-1">{{ advancedPayload.funnel.checked_in }}</p>
            <p class="text-[10px] text-neutral-muted mt-0.5">Actual attendance</p>
          </div>
        </div>

        <!-- Funnel Progress Bars -->
        <div class="space-y-2 mt-4 text-xs">
          <div>
            <div class="flex justify-between font-semibold mb-1 text-neutral-black">
              <span>View-to-Checkout Start Conversion Rate</span>
              <span>{{ advancedPayload.funnel.rates.views_to_started }}%</span>
            </div>
            <div class="w-full bg-neutral-background rounded-full h-1.5">
              <div class="bg-primary h-1.5 rounded-full" :style="{ width: `${advancedPayload.funnel.rates.views_to_started}%` }"></div>
            </div>
          </div>

          <div>
            <div class="flex justify-between font-semibold mb-1 text-neutral-black">
              <span>Checkout Start-to-Completion Conversion Rate</span>
              <span>{{ advancedPayload.funnel.rates.started_to_completed }}%</span>
            </div>
            <div class="w-full bg-neutral-background rounded-full h-1.5">
              <div class="bg-emerald-600 h-1.5 rounded-full" :style="{ width: `${advancedPayload.funnel.rates.started_to_completed}%` }"></div>
            </div>
          </div>
        </div>
      </section>

      <!-- KPI cards grid -->
      <section aria-label="Key metrics" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <!-- Registrations -->
        <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft transition-all duration-200 hover:shadow-premium-md">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Total Registrations</span>
            <Users class="h-4 w-4 text-primary" />
          </div>
          <p class="mt-2 text-2xl font-bold font-serif text-neutral-black">
            {{ payload.kpis.total_registrations }}
          </p>
          <p class="mt-1 text-[10px] text-neutral-muted">
            Confirmed: {{ payload.kpis.confirmed_registrations }} | Cancelled: {{ payload.kpis.cancelled_registrations }}
          </p>
        </div>

        <!-- Attendance Rate -->
        <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft transition-all duration-200 hover:shadow-premium-md">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Attendance Rate</span>
            <CheckCircle2 class="h-4 w-4 text-emerald-800" />
          </div>
          <p class="mt-2 text-2xl font-bold font-serif text-emerald-800">
            {{ payload.kpis.attendance_rate }}%
          </p>
          <p class="mt-1 text-[10px] text-neutral-muted">
            Checked-in: {{ payload.kpis.checked_in }} | Expected: {{ payload.kpis.tickets_issued }}
          </p>
        </div>

        <!-- No Show Rate -->
        <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft transition-all duration-200 hover:shadow-premium-md">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">No-Show Rate</span>
            <AlertCircle class="h-4 w-4 text-amber-600" />
          </div>
          <p class="mt-2 text-2xl font-bold font-serif text-amber-600">
            {{ payload.kpis.no_show_rate }}%
          </p>
          <p class="mt-1 text-[10px] text-neutral-muted">
            Absent Ticket Holders: {{ payload.kpis.no_shows }}
          </p>
        </div>

        <!-- Revenue (Gated) -->
        <div
          v-if="showFinancial"
          class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft transition-all duration-200 hover:shadow-premium-md"
        >
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Net Revenue</span>
            <DollarSign class="h-4 w-4 text-primary" />
          </div>
          <p class="mt-2 text-2xl font-bold font-serif text-primary">
            {{ formatCurrency(payload.kpis.net_revenue) }}
          </p>
          <p class="mt-1 text-[10px] text-neutral-muted">
            Gross: {{ formatCurrency(payload.kpis.gross_revenue) }} | Refunds: {{ formatCurrency(payload.kpis.refunds) }}
          </p>
        </div>

        <!-- Capacity (Alternate if no financial permission) -->
        <div
          v-else
          class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft transition-all duration-200 hover:shadow-premium-md"
        >
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Capacity Utilization</span>
            <Calendar class="h-4 w-4 text-primary" />
          </div>
          <p class="mt-2 text-2xl font-bold font-serif text-primary">
            {{ payload.kpis.capacity_utilization }}%
          </p>
          <p class="mt-1 text-[10px] text-neutral-muted">
            Total Seats: {{ payload.kpis.total_capacity !== null ? payload.kpis.total_capacity : 'Unlimited' }}
          </p>
        </div>
      </section>

      <!-- Charts layout (Bento grids style) -->
      <div class="grid gap-6 lg:grid-cols-3">
        <!-- Registrations line chart -->
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft lg:col-span-2">
          <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black mb-4">Registration Trends Over Time</h3>
          <SvgLineChart :data="lineChartData" :height="220" />
        </div>

        <!-- Attendee Breakdown pie/donut chart -->
        <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
          <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black mb-4">Attendee Breakdown</h3>
          <SvgPieChart :data="breakdownSegments" />
        </div>
      </div>

      <!-- More Metrics Details -->
      <div class="grid gap-6 lg:grid-cols-3">
        <!-- Ticket Sales Performance -->
        <div class="rounded-2xl border border-neutral-ivory bg-white shadow-soft lg:col-span-2 overflow-hidden">
          <div class="px-5 py-4 border-b border-neutral-ivory">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black">Ticket Types Performance</h3>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-neutral-background/70 border-b border-neutral-ivory">
                  <th class="p-3 font-semibold text-neutral-muted">Ticket Type</th>
                  <th class="p-3 font-semibold text-neutral-muted">Price</th>
                  <th class="p-3 font-semibold text-neutral-muted">Sold</th>
                  <th class="p-3 font-semibold text-neutral-muted">Remaining</th>
                  <th v-if="showFinancial" class="p-3 font-semibold text-neutral-muted">Revenue</th>
                  <th class="p-3 font-semibold text-neutral-muted">Sell-Through</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-neutral-ivory">
                <tr v-for="t in payload.charts.ticket_performance" :key="t.id" class="hover:bg-neutral-background/30">
                  <td class="p-3 font-semibold text-neutral-black">{{ t.name }}</td>
                  <td class="p-3 text-neutral-black">{{ formatCurrency(t.price) }}</td>
                  <td class="p-3 text-neutral-black">{{ t.sold }}</td>
                  <td class="p-3 text-neutral-muted">{{ t.remaining !== null ? t.remaining : 'Unlimited' }}</td>
                  <td v-if="showFinancial" class="p-3 font-semibold text-primary">{{ formatCurrency(t.revenue) }}</td>
                  <td class="p-3 text-neutral-black">
                    <span v-if="t.sell_through !== null">{{ t.sell_through }}%</span>
                    <span v-else class="text-neutral-muted">N/A</span>
                  </td>
                </tr>
                <tr v-if="payload.charts.ticket_performance.length === 0">
                  <td colspan="6" class="p-5 text-center text-neutral-muted">No ticket tiers defined.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- More side metrics (Early-Bird, Waitlist, No-Shows) -->
        <div class="space-y-6">
          <!-- Waitlist conversion card -->
          <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black mb-3">Waitlist Conversions</h3>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-2xl font-bold font-serif text-neutral-black">
                  {{ payload.kpis.waitlist_size }}
                </p>
                <p class="text-[10px] text-neutral-muted">Waiting entries currently in queue</p>
              </div>
              <div class="text-right">
                <p class="text-2xl font-bold font-serif text-emerald-800">
                  {{ payload.kpis.waitlist_conversions }}
                </p>
                <p class="text-[10px] text-neutral-muted">Conversions promoted to seats</p>
              </div>
            </div>
          </div>

          <!-- Early-Bird analytics card -->
          <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black mb-3">Early-Bird Performance</h3>
            <dl class="space-y-2 text-xs">
              <div class="flex justify-between">
                <dt class="text-neutral-muted">Early-Bird Tickets Sold:</dt>
                <dd class="font-bold text-neutral-black">{{ payload.charts.early_bird.comparison.early_bird.sold }}</dd>
              </div>
              <div v-if="showFinancial" class="flex justify-between">
                <dt class="text-neutral-muted">Early-Bird Revenue:</dt>
                <dd class="font-bold text-primary">{{ formatCurrency(payload.charts.early_bird.comparison.early_bird.revenue) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-neutral-muted">Remaining Early-Bird Inventory:</dt>
                <dd class="font-bold text-neutral-black">{{ payload.charts.early_bird.remaining_inventory }}</dd>
              </div>
            </dl>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
