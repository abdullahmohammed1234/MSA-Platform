<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import {
  ArrowLeft,
  CheckCircle2,
  Clock,
  Download,
  RefreshCw,
} from 'lucide-vue-next';
import { useEmsAccessStore } from '@/stores/ems/emsAccess';
import { EMS_PERMISSIONS } from '@/constants/ems';
import {
  analyticsService,
  type AnalyticsPayload,
  type AnalyticsReportItem,
} from '@/services/ems/analyticsService';
import { eventsService } from '@/services/ems/eventsService';
import type { Event } from '@/types/ems';
import SvgLineChart from '@/components/ems/charts/SvgLineChart.vue';
import SvgPieChart from '@/components/ems/charts/SvgPieChart.vue';
import { useToastStore } from '@/components/feedback/toast';

const route = useRoute();
const toast = useToastStore();
const access = useEmsAccessStore();

const eventUuid = computed(() => String(route.params.uuid));

const activeTab = ref<'analytics' | 'reports'>('analytics');
const isEventLoading = ref(true);
const isAnalyticsLoading = ref(true);
const isReportsLoading = ref(true);
const isExporting = ref(false);

const event = ref<Event | null>(null);
const payload = ref<AnalyticsPayload | null>(null);
const reportsList = ref<AnalyticsReportItem[]>([]);

const showFinancial = computed(() => access.can(EMS_PERMISSIONS.ANALYTICS_VIEW_FINANCIAL));

// Custom report builder state
const reportTitle = ref('');
const reportFormat = ref<'csv' | 'xlsx' | 'pdf'>('pdf');
const reportStartDate = ref('');
const reportEndDate = ref('');
const reportSections = ref({
  registrations: true,
  revenue: true,
  attendance: true,
  ticket_sales: true,
  payments: true,
  waitlist: true,
  check_ins: true,
});

let pollInterval: any = null;

const fetchEvent = async () => {
  isEventLoading.value = true;
  try {
    event.value = await eventsService.show(eventUuid.value);
    reportTitle.value = `Event Summary - ${event.value.name}`;
  } catch (err: any) {
    toast.error('Failed to load event details.');
  } finally {
    isEventLoading.value = false;
  }
};

const fetchAnalytics = async () => {
  isAnalyticsLoading.value = true;
  try {
    payload.value = await analyticsService.eventAnalytics(eventUuid.value);
  } catch (err: any) {
    toast.error('Failed to load analytics metrics.');
  } finally {
    isAnalyticsLoading.value = false;
  }
};

const fetchReports = async () => {
  isReportsLoading.value = true;
  try {
    reportsList.value = await analyticsService.eventReports(eventUuid.value);
  } catch (err: any) {
    console.error('Failed to load reports', err);
  } finally {
    isReportsLoading.value = false;
  }
};

const startReportPolling = () => {
  if (pollInterval) clearInterval(pollInterval);
  pollInterval = setInterval(async () => {
    // Check if any report is still processing
    const hasProcessing = reportsList.value.some((r) => r.file_path === null);
    if (hasProcessing) {
      try {
        reportsList.value = await analyticsService.eventReports(eventUuid.value);
      } catch (e) {
        console.error('Polling reports failed', e);
      }
    }
  }, 4000);
};

onMounted(async () => {
  await Promise.all([fetchEvent(), fetchAnalytics(), fetchReports()]);
  startReportPolling();
});

onBeforeUnmount(() => {
  if (pollInterval) clearInterval(pollInterval);
});

// Watch to sync title
watch(event, (newVal) => {
  if (newVal) {
    reportTitle.value = `Report - ${newVal.name}`;
  }
});

const handleExport = async () => {
  if (!reportTitle.value.trim()) {
    toast.error('Please enter a report title.');
    return;
  }

  isExporting.value = true;
  try {
    const payloadData = {
      title: reportTitle.value,
      format: reportFormat.value,
      start_date: reportStartDate.value || undefined,
      end_date: reportEndDate.value || undefined,
      sections: {
        registrations: reportSections.value.registrations,
        revenue: showFinancial.value ? reportSections.value.revenue : false,
        attendance: reportSections.value.attendance,
        ticket_sales: reportSections.value.ticket_sales,
        payments: showFinancial.value ? reportSections.value.payments : false,
        waitlist: reportSections.value.waitlist,
        check_ins: reportSections.value.check_ins,
      },
    };

    await analyticsService.exportReport(eventUuid.value, payloadData);
    toast.success('Report generation has been queued successfully!');
    reportTitle.value = `Report - ${event.value?.name ?? ''}`;
    // Reload reports list immediately
    await fetchReports();
  } catch (err: any) {
    toast.error(err.message || 'Failed to queue report export.');
  } finally {
    isExporting.value = false;
  }
};

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(val);
};

const lineChartData = computed(() => {
  if (!payload.value?.charts.registrations_over_time) return [];
  return payload.value.charts.registrations_over_time.map((d) => ({
    label: d.date,
    value: d.count,
  }));
});

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

const getDownloadLink = (uuid: string) => {
  return analyticsService.getDownloadUrl(uuid);
};

const getFormatBadgeColor = (format: string) => {
  if (format === 'pdf') return 'bg-rose-100 text-rose-800';
  if (format === 'xlsx') return 'bg-emerald-100 text-emerald-800';
  return 'bg-blue-100 text-blue-800';
};
</script>

<template>
  <div class="space-y-6">
    <!-- Back to details link -->
    <div class="flex items-center justify-between">
      <RouterLink
        :to="{ name: 'ems-event-detail', params: { uuid: eventUuid } }"
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-muted hover:text-primary transition-colors"
      >
        <ArrowLeft class="h-4 w-4" />
        Back to Event Details
      </RouterLink>
    </div>

    <!-- Header Block -->
    <div v-if="event" class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-primary">
            {{ event.category?.name ?? 'General' }}
          </span>
          <h2 class="text-xl font-bold font-serif text-neutral-black mt-1.5">{{ event.name }}</h2>
          <p class="text-xs text-neutral-muted mt-1">
            {{ event.location || 'Location not specified' }} ·
            {{ event.start_at ? new Date(event.start_at).toLocaleString() : '' }}
          </p>
        </div>

        <!-- Custom tabs selection -->
        <div class="flex items-center rounded-xl bg-neutral-background p-1">
          <button
            type="button"
            class="rounded-lg px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer"
            :class="activeTab === 'analytics' ? 'bg-white text-primary shadow-sm' : 'text-neutral-muted hover:text-primary'"
            @click="activeTab = 'analytics'"
          >
            Analytics
          </button>
          <button
            type="button"
            class="rounded-lg px-3.5 py-1.5 text-xs font-bold transition-all cursor-pointer"
            :class="activeTab === 'reports' ? 'bg-white text-primary shadow-sm' : 'text-neutral-muted hover:text-primary'"
            @click="activeTab = 'reports'"
          >
            Reports & Exports
          </button>
        </div>
      </div>
    </div>

    <!-- TAB 1: Analytics -->
    <template v-if="activeTab === 'analytics'">
      <div v-if="isAnalyticsLoading" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-2xl bg-neutral-ivory/50" />
      </div>

      <div v-else-if="payload" class="space-y-6">
        <!-- KPIs Row -->
        <section aria-label="Key Performance Indicators" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Registrations</span>
            <p class="mt-1 text-xl font-bold font-serif text-neutral-black">{{ payload.kpis.total_registrations }}</p>
            <p class="text-[9px] text-neutral-muted">Capacity Limit: {{ event?.capacity || 'Unlimited' }}</p>
          </div>

          <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Attendance Rate</span>
            <p class="mt-1 text-xl font-bold font-serif text-emerald-800">{{ payload.kpis.attendance_rate }}%</p>
            <p class="text-[9px] text-neutral-muted">Checked in: {{ payload.kpis.checked_in }}</p>
          </div>

          <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">No-Show Rate</span>
            <p class="mt-1 text-xl font-bold font-serif text-amber-600">{{ payload.kpis.no_show_rate }}%</p>
            <p class="text-[9px] text-neutral-muted">No-shows count: {{ payload.kpis.no_shows }}</p>
          </div>

          <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
            <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Net Revenue</span>
            <p class="mt-1 text-xl font-bold font-serif text-primary">
              {{ showFinancial ? formatCurrency(payload.kpis.net_revenue) : 'Gated' }}
            </p>
            <p class="text-[9px] text-neutral-muted">Refunds: {{ showFinancial ? formatCurrency(payload.kpis.refunds) : 'Gated' }}</p>
          </div>
        </section>

        <!-- Charts grids -->
        <div class="grid gap-6 lg:grid-cols-3">
          <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft lg:col-span-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black mb-4">Registration Trends Over Time</h3>
            <SvgLineChart :data="lineChartData" :height="220" />
          </div>

          <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
            <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black mb-4">Attendee Breakdown</h3>
            <SvgPieChart :data="breakdownSegments" />
          </div>
        </div>

        <!-- Ticket Performance Table -->
        <div class="rounded-2xl border border-neutral-ivory bg-white shadow-soft overflow-hidden">
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
      </div>
    </template>

  <!-- TAB 2: Reports & Exports -->
  <template v-else-if="activeTab === 'reports'">
    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Report Builder Form -->
      <div class="rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft space-y-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black border-b border-neutral-ivory pb-2.5">
          Custom Report Builder
        </h3>

        <form @submit.prevent="handleExport" class="space-y-4 text-xs">
          <!-- Title -->
          <div class="space-y-1.5">
            <label for="report-title" class="font-bold text-neutral-muted uppercase tracking-wider text-[10px]">Report Title</label>
            <input
              id="report-title"
              type="text"
              v-model="reportTitle"
              class="w-full rounded-lg border border-neutral-ivory px-3 py-2 text-neutral-black focus:border-primary focus:outline-none"
              placeholder="Enter report title..."
              required
            />
          </div>

          <!-- Format -->
          <div class="space-y-1.5">
            <label for="report-format" class="font-bold text-neutral-muted uppercase tracking-wider text-[10px]">Export Format</label>
            <select
              id="report-format"
              v-model="reportFormat"
              class="w-full rounded-lg border border-neutral-ivory bg-white px-3 py-2 text-neutral-black focus:border-primary focus:outline-none"
            >
              <option value="pdf">PDF Document (.pdf)</option>
              <option value="xlsx">Excel Spreadsheet (.xlsx)</option>
              <option value="csv">Comma Separated Values (.csv)</option>
            </select>
          </div>

          <!-- Date Filters -->
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-1.5">
              <label for="report-start" class="font-bold text-neutral-muted uppercase tracking-wider text-[10px]">From Date</label>
              <input
                id="report-start"
                type="date"
                v-model="reportStartDate"
                class="w-full rounded-lg border border-neutral-ivory px-3 py-2 text-neutral-black focus:border-primary focus:outline-none"
              />
            </div>
            <div class="space-y-1.5">
              <label for="report-end" class="font-bold text-neutral-muted uppercase tracking-wider text-[10px]">To Date</label>
              <input
                id="report-end"
                type="date"
                v-model="reportEndDate"
                class="w-full rounded-lg border border-neutral-ivory px-3 py-2 text-neutral-black focus:border-primary focus:outline-none"
              />
            </div>
          </div>

          <!-- Selectable Sections checkboxes -->
          <div class="space-y-2">
            <label class="font-bold text-neutral-muted uppercase tracking-wider text-[10px] block mb-1">Selectable Sections</label>
            <div class="grid gap-2 sm:grid-cols-2">
              <label class="flex items-center gap-2 cursor-pointer font-medium text-neutral-black">
                <input type="checkbox" v-model="reportSections.registrations" class="rounded text-primary focus:ring-primary" />
                Registrations
              </label>
              <label class="flex items-center gap-2 cursor-pointer font-medium text-neutral-black">
                <input type="checkbox" v-model="reportSections.attendance" class="rounded text-primary focus:ring-primary" />
                Attendance
              </label>
              <label class="flex items-center gap-2 cursor-pointer font-medium text-neutral-black">
                <input type="checkbox" v-model="reportSections.ticket_sales" class="rounded text-primary focus:ring-primary" />
                Ticket Sales
              </label>
              <label class="flex items-center gap-2 cursor-pointer font-medium text-neutral-black">
                <input type="checkbox" v-model="reportSections.check_ins" class="rounded text-primary focus:ring-primary" />
                Check-ins
              </label>
              <label class="flex items-center gap-2 cursor-pointer font-medium text-neutral-black">
                <input type="checkbox" v-model="reportSections.waitlist" class="rounded text-primary focus:ring-primary" />
                Waitlist
              </label>
              <label v-if="showFinancial" class="flex items-center gap-2 cursor-pointer font-medium text-neutral-black">
                <input type="checkbox" v-model="reportSections.revenue" class="rounded text-primary focus:ring-primary" />
                Revenue Info
              </label>
              <label v-if="showFinancial" class="flex items-center gap-2 cursor-pointer font-medium text-neutral-black">
                <input type="checkbox" v-model="reportSections.payments" class="rounded text-primary focus:ring-primary" />
                Payments Log
              </label>
            </div>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isExporting"
            class="w-full inline-flex h-9 items-center justify-center rounded-xl bg-primary px-4 text-xs font-bold text-white transition-colors hover:bg-primary/95 focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-neutral-ivory disabled:cursor-not-allowed cursor-pointer"
          >
            <RefreshCw v-if="isExporting" class="h-3.5 w-3.5 mr-2 animate-spin" />
            Queue Report Export
          </button>
        </form>
      </div>

      <!-- Reports History Log -->
      <div class="rounded-2xl border border-neutral-ivory bg-white shadow-soft lg:col-span-2 overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-neutral-ivory">
          <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black">Report Archive & Status</h3>
        </div>

        <div v-if="isReportsLoading" class="p-6 text-center text-xs text-neutral-muted space-y-2">
          <RefreshCw class="mx-auto h-5 w-5 animate-spin" />
          <p>Loading archive reports...</p>
        </div>

        <div v-else-if="reportsList.length === 0" class="p-8 text-center text-xs text-neutral-muted">
          No reports generated for this event yet. Use the Custom Report Builder to initiate one.
        </div>

        <div v-else class="flex-1 overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-neutral-background/70 border-b border-neutral-ivory">
                <th class="p-3 font-semibold text-neutral-muted">Report Title</th>
                <th class="p-3 font-semibold text-neutral-muted text-center">Format</th>
                <th class="p-3 font-semibold text-neutral-muted">Generated At</th>
                <th class="p-3 font-semibold text-neutral-muted text-center">Status</th>
                <th class="p-3 font-semibold text-neutral-muted text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory">
              <tr v-for="r in reportsList" :key="r.id" class="hover:bg-neutral-background/30">
                <td class="p-3 font-bold text-neutral-black">{{ r.title }}</td>
                <td class="p-3 text-center">
                  <span
                    class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                    :class="getFormatBadgeColor(r.filters?.format || 'pdf')"
                  >
                    {{ r.filters?.format || 'pdf' }}
                  </span>
                </td>
                <td class="p-3 text-neutral-muted">
                  {{ r.generated_at ? new Date(r.generated_at).toLocaleString() : 'Processing...' }}
                </td>
                <td class="p-3 text-center">
                  <span
                    v-if="r.file_path"
                    class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[9px] font-bold uppercase inline-flex items-center gap-1"
                  >
                    <CheckCircle2 class="h-3 w-3" />
                    Ready
                  </span>
                  <span
                    v-else
                    class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[9px] font-bold uppercase inline-flex items-center gap-1"
                  >
                    <Clock class="h-3 w-3 animate-pulse" />
                    Processing
                  </span>
                </td>
                <td class="p-3 text-right">
                  <a
                    v-if="r.file_path"
                    :href="getDownloadLink(r.uuid)"
                    target="_blank"
                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-neutral-background hover:bg-neutral-ivory/60 text-neutral-black hover:text-primary transition-all cursor-pointer"
                    title="Download Report File"
                  >
                    <Download class="h-3.5 w-3.5" />
                  </a>
                  <span v-else class="text-neutral-muted text-[10px]">Processing...</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
