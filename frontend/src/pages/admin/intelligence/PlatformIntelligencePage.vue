<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
  TrendingUp,
  Activity,
  Calendar,
  DollarSign,
  ShoppingBag,
  BookOpen,
  Mail,
  HeartHandshake,
  MessageSquare,
  AlertTriangle,
  RefreshCw,
  Loader2,
  ShieldCheck,
  Server,
  Users,
  Clock,
  Award,
} from 'lucide-vue-next';
import DateRangeSelector from '@/components/admin/intelligence/DateRangeSelector.vue';
import IntelligenceKpiCard from '@/components/admin/intelligence/IntelligenceKpiCard.vue';
import IntelligenceTrendChart from '@/components/admin/intelligence/IntelligenceTrendChart.vue';
import { platformIntelligenceService, type PlatformIntelligenceData } from '@/services/admin/platformIntelligenceService';

const period = ref<'today' | '7d' | '30d' | '90d' | 'this_year' | 'custom'>('30d');
const startDate = ref<string | undefined>();
const endDate = ref<string | undefined>();

const activeTab = ref<'overview' | 'ems' | 'donations' | 'store' | 'mlibms' | 'communications' | 'volunteers' | 'feedback'>('overview');
const loading = ref(true);
const errorMsg = ref('');
const data = ref<PlatformIntelligenceData | null>(null);

const fetchIntelligence = async () => {
  loading.value = true;
  errorMsg.value = '';
  try {
    const res = await platformIntelligenceService.getOverview({
      period: period.value,
      start_date: startDate.value,
      end_date: endDate.value,
    });
    data.value = res;
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.message || 'Failed to load platform intelligence data.';
  } finally {
    loading.value = false;
  }
};

const handleTimeframeChange = (payload: { period: string; startDate?: string; endDate?: string }) => {
  period.value = payload.period as any;
  startDate.value = payload.startDate;
  endDate.value = payload.endDate;
  fetchIntelligence();
};

onMounted(() => {
  fetchIntelligence();
});
</script>

<template>
  <div class="space-y-6">
    <!-- Top Control Bar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-neutral-ivory shadow-soft">
      <div>
        <div class="flex items-center gap-2 text-xs font-bold text-primary uppercase tracking-wider mb-1">
          <Activity class="w-4 h-4" />
          <span>Operational Telemetry & Analytics</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-display font-medium text-primary">Platform Intelligence Control Center</h1>
        <p class="text-xs text-neutral-muted mt-1">Cross-system insights aggregated directly from production sources of truth.</p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <DateRangeSelector
          :period="period"
          :start-date="startDate"
          :end-date="endDate"
          @change="handleTimeframeChange"
        />

        <button
          @click="fetchIntelligence"
          :disabled="loading"
          class="p-2.5 bg-white hover:bg-neutral-background text-neutral-black hover:text-primary rounded-xl border border-neutral-ivory shadow-soft transition-all disabled:opacity-50 cursor-pointer"
          title="Refresh Data"
        >
          <RefreshCw :class="['w-4 h-4', loading ? 'animate-spin text-primary' : '']" />
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="errorMsg" class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-3 shadow-soft">
      <AlertTriangle class="w-5 h-5 shrink-0" />
      <span>{{ errorMsg }}</span>
    </div>

    <!-- Loading State Skeleton -->
    <div v-if="loading && !data" class="p-12 text-center text-neutral-muted flex flex-col items-center gap-3 bg-white border border-neutral-ivory rounded-2xl shadow-soft">
      <Loader2 class="w-8 h-8 animate-spin text-primary" />
      <span>Aggregating platform operational metrics...</span>
    </div>

    <template v-else-if="data">
      <!-- Platform Health Overview Banner -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-neutral-ivory rounded-2xl p-4 flex items-center gap-3 shadow-soft">
          <div class="p-3 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200">
            <ShieldCheck class="w-6 h-6" />
          </div>
          <div>
            <div class="text-[10px] uppercase font-bold text-neutral-muted">Platform Status</div>
            <div class="text-lg font-display font-bold text-neutral-black uppercase font-mono">{{ data.overall_health.status }}</div>
          </div>
        </div>

        <div class="bg-white border border-neutral-ivory rounded-2xl p-4 flex items-center gap-3 shadow-soft">
          <div class="p-3 bg-blue-50 text-blue-700 rounded-xl border border-blue-200">
            <Server class="w-6 h-6" />
          </div>
          <div>
            <div class="text-[10px] uppercase font-bold text-neutral-muted">Applications</div>
            <div class="text-lg font-display font-bold text-neutral-black">{{ data.overall_health.healthy_apps }} / {{ data.overall_health.total_apps }} Healthy</div>
          </div>
        </div>

        <div class="bg-white border border-neutral-ivory rounded-2xl p-4 flex items-center gap-3 shadow-soft">
          <div class="p-3 bg-amber-50 text-amber-700 rounded-xl border border-amber-200">
            <AlertTriangle class="w-6 h-6" />
          </div>
          <div>
            <div class="text-[10px] uppercase font-bold text-neutral-muted">Active Alerts</div>
            <div class="text-lg font-display font-bold text-amber-800">{{ data.active_alerts_count }}</div>
          </div>
        </div>

        <div class="bg-white border border-neutral-ivory rounded-2xl p-4 flex items-center gap-3 shadow-soft">
          <div class="p-3 bg-red-50 text-red-700 rounded-xl border border-red-200">
            <Activity class="w-6 h-6" />
          </div>
          <div>
            <div class="text-[10px] uppercase font-bold text-neutral-muted">Failed Jobs</div>
            <div class="text-lg font-display font-bold text-red-800">{{ data.failed_jobs_count }}</div>
          </div>
        </div>
      </div>

      <!-- Domain Navigation Tabs -->
      <div class="bg-white border border-neutral-ivory p-1.5 rounded-2xl flex items-center gap-1 overflow-x-auto shadow-soft">
        <button
          v-for="t in [
            { id: 'overview', label: 'Cross-System Overview', icon: Activity },
            { id: 'ems', label: 'EMS Events', icon: Calendar },
            { id: 'donations', label: 'Donations', icon: DollarSign },
            { id: 'store', label: 'Store', icon: ShoppingBag },
            { id: 'mlibms', label: 'Library MLibMS', icon: BookOpen },
            { id: 'communications', label: 'Communications', icon: Mail },
            { id: 'volunteers', label: 'Volunteers', icon: HeartHandshake },
            { id: 'feedback', label: 'Feedback', icon: MessageSquare }
          ]"
          :key="t.id"
          @click="activeTab = t.id as any"
          :class="[
            'px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all whitespace-nowrap cursor-pointer',
            activeTab === t.id
              ? 'bg-primary text-white shadow-soft font-bold'
              : 'text-neutral-muted hover:text-neutral-black hover:bg-neutral-background'
          ]"
        >
          <component :is="t.icon" class="w-4 h-4" />
          <span>{{ t.label }}</span>
        </button>
      </div>

      <!-- TAB 1: OVERVIEW -->
      <div v-if="activeTab === 'overview'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <IntelligenceKpiCard
            title="EMS Registrations"
            :value="data.domains.ems?.kpis?.registrations?.current ?? 0"
            :previous-value="data.domains.ems?.kpis?.registrations?.previous ?? 0"
            :pct-change="data.domains.ems?.kpis?.registrations?.pct_change"
            :icon="Calendar"
            link="/admin/systems/ems"
            link-text="EMS Console"
          />

          <IntelligenceKpiCard
            title="Donations Volume"
            :value="data.domains.donations?.kpis?.donation_volume?.current ?? 0"
            :previous-value="data.domains.donations?.kpis?.donation_volume?.previous ?? 0"
            :pct-change="data.domains.donations?.kpis?.donation_volume?.pct_change"
            prefix="$"
            :icon="DollarSign"
            link="/donations/admin"
            link-text="Donations Admin"
          />

          <IntelligenceKpiCard
            title="Store Revenue"
            :value="data.domains.store?.kpis?.revenue?.current ?? 0"
            :previous-value="data.domains.store?.kpis?.revenue?.previous ?? 0"
            :pct-change="data.domains.store?.kpis?.revenue?.pct_change"
            prefix="$"
            :icon="ShoppingBag"
            link="/store/admin"
            link-text="Store Admin"
          />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <IntelligenceTrendChart
            title="EMS Event Registrations Trend"
            :data="data.domains.ems?.trends ?? []"
            data-key="registrations"
            color="emerald"
          />

          <IntelligenceTrendChart
            title="Donations Volume Trend ($)"
            :data="data.domains.donations?.trends ?? []"
            data-key="volume"
            prefix="$"
            color="amber"
          />
        </div>
      </div>

      <!-- TAB 2: EMS EVENTS -->
      <div v-else-if="activeTab === 'ems'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <IntelligenceKpiCard
            title="Total Events"
            :value="data.domains.ems?.kpis?.events?.current ?? 0"
            :pct-change="data.domains.ems?.kpis?.events?.pct_change"
            :icon="Calendar"
          />
          <IntelligenceKpiCard
            title="Registrations"
            :value="data.domains.ems?.kpis?.registrations?.current ?? 0"
            :pct-change="data.domains.ems?.kpis?.registrations?.pct_change"
            :icon="TrendingUp"
          />
          <IntelligenceKpiCard
            title="Event Revenue"
            :value="data.domains.ems?.kpis?.revenue?.current ?? 0"
            prefix="$"
            :pct-change="data.domains.ems?.kpis?.revenue?.pct_change"
            :icon="DollarSign"
          />
          <IntelligenceKpiCard
            title="Attendance Rate"
            :value="data.domains.ems?.kpis?.attendance_rate ?? 0"
            suffix="%"
            subtitle="Verified Check-ins"
            :icon="Activity"
          />
        </div>

        <!-- Registration Funnel Card -->
        <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft space-y-4">
          <h3 class="text-xs uppercase font-bold tracking-wider text-neutral-muted">EMS Registration & Check-in Funnel</h3>
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
            <div class="bg-neutral-background/70 p-4 rounded-xl border border-neutral-ivory">
              <div class="text-xs text-neutral-muted font-medium">Registered</div>
              <div class="text-2xl font-display font-bold text-neutral-black mt-1">{{ data.domains.ems?.funnel?.registered }}</div>
            </div>
            <div class="bg-neutral-background/70 p-4 rounded-xl border border-neutral-ivory">
              <div class="text-xs text-neutral-muted font-medium">Paid</div>
              <div class="text-2xl font-display font-bold text-emerald-700 mt-1">{{ data.domains.ems?.funnel?.paid }}</div>
            </div>
            <div class="bg-neutral-background/70 p-4 rounded-xl border border-neutral-ivory">
              <div class="text-xs text-neutral-muted font-medium">Tickets Issued</div>
              <div class="text-2xl font-display font-bold text-blue-700 mt-1">{{ data.domains.ems?.funnel?.tickets_issued }}</div>
            </div>
            <div class="bg-neutral-background/70 p-4 rounded-xl border border-neutral-ivory">
              <div class="text-xs text-neutral-muted font-medium">Checked In</div>
              <div class="text-2xl font-display font-bold text-primary mt-1">{{ data.domains.ems?.funnel?.check_ins }}</div>
            </div>
            <div class="bg-neutral-background/70 p-4 rounded-xl border border-neutral-ivory">
              <div class="text-xs text-neutral-muted font-medium">Feedback Left</div>
              <div class="text-2xl font-display font-bold text-amber-700 mt-1">{{ data.domains.ems?.funnel?.feedback }}</div>
            </div>
          </div>
        </div>

        <IntelligenceTrendChart
          title="Daily Registrations Trend"
          :data="data.domains.ems?.trends ?? []"
          data-key="registrations"
          color="emerald"
        />
      </div>

      <!-- TAB 3: DONATIONS -->
      <div v-else-if="activeTab === 'donations'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <IntelligenceKpiCard
            title="Total Raised"
            :value="data.domains.donations?.kpis?.donation_volume?.current ?? 0"
            prefix="$"
            :pct-change="data.domains.donations?.kpis?.donation_volume?.pct_change"
            :icon="DollarSign"
          />
          <IntelligenceKpiCard
            title="Donation Count"
            :value="data.domains.donations?.kpis?.donation_count?.current ?? 0"
            :pct-change="data.domains.donations?.kpis?.donation_count?.pct_change"
            :icon="TrendingUp"
          />
          <IntelligenceKpiCard
            title="Avg Donation Size"
            :value="data.domains.donations?.kpis?.average_donation_size ?? 0"
            prefix="$"
            subtitle="Per completed contribution"
            :icon="DollarSign"
          />
        </div>

        <IntelligenceTrendChart
          title="Daily Donations Revenue ($)"
          :data="data.domains.donations?.trends ?? []"
          data-key="volume"
          prefix="$"
          color="amber"
        />
      </div>

      <!-- TAB 4: STORE -->
      <div v-else-if="activeTab === 'store'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <IntelligenceKpiCard
            title="Completed Orders"
            :value="data.domains.store?.kpis?.order_count?.current ?? 0"
            :pct-change="data.domains.store?.kpis?.order_count?.pct_change"
            :icon="ShoppingBag"
          />
          <IntelligenceKpiCard
            title="Store Sales"
            :value="data.domains.store?.kpis?.revenue?.current ?? 0"
            prefix="$"
            :pct-change="data.domains.store?.kpis?.revenue?.pct_change"
            :icon="DollarSign"
          />
          <IntelligenceKpiCard
            title="Pending Fulfillment"
            :value="data.domains.store?.kpis?.pending_fulfillment ?? 0"
            subtitle="Unfulfilled orders"
            :icon="Activity"
          />
          <IntelligenceKpiCard
            title="Low Stock Alerts"
            :value="data.domains.store?.kpis?.low_stock_count ?? 0"
            subtitle="Products stock <= 5"
            :icon="AlertTriangle"
          />
        </div>

        <IntelligenceTrendChart
          title="Daily Sales Revenue ($)"
          :data="data.domains.store?.trends ?? []"
          data-key="revenue"
          prefix="$"
          color="blue"
        />
      </div>

      <!-- TAB 5: MLIBMS LIBRARY -->
      <div v-else-if="activeTab === 'mlibms'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <IntelligenceKpiCard
            title="Total Catalog Books"
            :value="data.domains.mlibms?.kpis?.total_books ?? 0"
            :icon="BookOpen"
          />
          <IntelligenceKpiCard
            title="Active Loans"
            :value="data.domains.mlibms?.kpis?.active_loans ?? 0"
            subtitle="Currently checked out"
            :icon="BookOpen"
          />
          <IntelligenceKpiCard
            title="Overdue Loans"
            :value="data.domains.mlibms?.kpis?.overdue_loans ?? 0"
            subtitle="Past due date"
            :icon="AlertTriangle"
          />
          <IntelligenceKpiCard
            title="Inventory Utilization"
            :value="data.domains.mlibms?.kpis?.utilization_rate ?? 0"
            suffix="%"
            subtitle="Copies on loan"
            :icon="Activity"
          />
        </div>

        <IntelligenceTrendChart
          title="New Loans Dispatched"
          :data="data.domains.mlibms?.trends ?? []"
          data-key="new_loans"
          color="indigo"
        />
      </div>

      <!-- TAB 6: COMMUNICATIONS -->
      <div v-else-if="activeTab === 'communications'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <IntelligenceKpiCard
            title="Dispatched"
            :value="data.domains.communications?.kpis?.total_dispatched ?? 0"
            :icon="Mail"
          />
          <IntelligenceKpiCard
            title="Delivery Rate"
            :value="data.domains.communications?.kpis?.delivery_rate ?? 100"
            suffix="%"
            :icon="ShieldCheck"
          />
          <IntelligenceKpiCard
            title="Failed Emails"
            :value="data.domains.communications?.kpis?.failed ?? 0"
            :icon="AlertTriangle"
          />
          <IntelligenceKpiCard
            title="Queued Messages"
            :value="data.domains.communications?.kpis?.queued ?? 0"
            :icon="Activity"
          />
        </div>

        <IntelligenceTrendChart
          title="Daily Sent Emails"
          :data="data.domains.communications?.trends ?? []"
          data-key="sent"
          color="emerald"
        />
      </div>

      <!-- TAB 7: VOLUNTEERS -->
      <div v-else-if="activeTab === 'volunteers'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <IntelligenceKpiCard
            title="Total Volunteers"
            :value="data.domains.volunteers?.kpis?.total_volunteers?.current ?? data.domains.volunteers?.kpis?.total_applications ?? 0"
            :icon="Users"
          />
          <IntelligenceKpiCard
            title="Service Hours"
            :value="data.domains.volunteers?.kpis?.total_service_hours?.current ?? 0"
            suffix=" hrs"
            :pct-change="data.domains.volunteers?.kpis?.total_service_hours?.pct_change"
            :icon="Clock"
          />
          <IntelligenceKpiCard
            title="Attendance Rate"
            :value="data.domains.volunteers?.kpis?.attendance_rate ?? 0"
            suffix="%"
            :icon="ShieldCheck"
          />
          <IntelligenceKpiCard
            title="Completion Rate"
            :value="data.domains.volunteers?.kpis?.completion_rate ?? 0"
            suffix="%"
            :icon="Award"
          />
        </div>

        <!-- Participation Funnel & Operational Summary -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft space-y-4">
            <h3 class="text-xs uppercase font-bold tracking-wider text-neutral-muted">Volunteer Participation Funnel</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
              <div class="bg-neutral-background/70 p-4 rounded-xl border border-neutral-ivory">
                <div class="text-xs text-neutral-muted font-medium">Total Signups</div>
                <div class="text-2xl font-display font-bold text-neutral-black mt-1">{{ data.domains.volunteers?.funnel?.signups ?? 0 }}</div>
              </div>
              <div class="bg-neutral-background/70 p-4 rounded-xl border border-neutral-ivory">
                <div class="text-xs text-neutral-muted font-medium">Confirmed</div>
                <div class="text-2xl font-display font-bold text-blue-700 mt-1">{{ data.domains.volunteers?.funnel?.confirmed ?? 0 }}</div>
              </div>
              <div class="bg-neutral-background/70 p-4 rounded-xl border border-neutral-ivory">
                <div class="text-xs text-neutral-muted font-medium">Attended</div>
                <div class="text-2xl font-display font-bold text-emerald-700 mt-1">{{ data.domains.volunteers?.funnel?.attended ?? 0 }}</div>
              </div>
              <div class="bg-neutral-background/70 p-4 rounded-xl border border-neutral-ivory">
                <div class="text-xs text-neutral-muted font-medium">Completed</div>
                <div class="text-2xl font-display font-bold text-primary mt-1">{{ data.domains.volunteers?.funnel?.completed ?? 0 }}</div>
              </div>
            </div>
            <div class="flex items-center justify-between text-xs text-neutral-muted border-t border-neutral-ivory pt-3 mt-2">
              <span>Returning Volunteers: <strong class="text-neutral-black font-semibold">{{ data.domains.volunteers?.kpis?.returning_volunteers?.current ?? 0 }}</strong></span>
              <span>First-time Volunteers: <strong class="text-neutral-black font-semibold">{{ data.domains.volunteers?.kpis?.first_time_volunteers?.current ?? 0 }}</strong></span>
            </div>
          </div>

          <div class="bg-white border border-neutral-ivory rounded-2xl p-6 shadow-soft space-y-4">
            <h3 class="text-xs uppercase font-bold tracking-wider text-neutral-muted">Operational Attention Required</h3>
            <div v-if="data.domains.volunteers?.attention_required?.length > 0" class="space-y-2.5">
              <div
                v-for="item in data.domains.volunteers.attention_required"
                :key="item.id"
                class="p-3.5 bg-amber-50/80 border border-amber-200 rounded-xl flex items-start gap-3 text-xs"
              >
                <AlertTriangle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                <div>
                  <div class="font-bold text-amber-900">{{ item.title }} ({{ item.count }})</div>
                  <div class="text-amber-800 mt-0.5">{{ item.description }}</div>
                </div>
              </div>
            </div>
            <div v-else class="p-6 text-center text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl font-medium">
              All volunteer shifts, attendance, and waitlists are in good operational standing.
            </div>
          </div>
        </div>

        <IntelligenceTrendChart
          title="Daily Volunteer Signups & Completed Hours"
          :data="data.domains.volunteers?.trends ?? []"
          data-key="signups"
          color="emerald"
        />
      </div>

      <!-- TAB 8: FEEDBACK -->
      <div v-else-if="activeTab === 'feedback'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <IntelligenceKpiCard
            title="Total Responses"
            :value="data.domains.feedback?.kpis?.total_responses ?? 0"
            :icon="MessageSquare"
          />
          <IntelligenceKpiCard
            title="Avg Overall Rating"
            :value="data.domains.feedback?.kpis?.average_overall ?? 0"
            suffix=" / 5"
            :icon="TrendingUp"
          />
          <IntelligenceKpiCard
            title="Program Quality"
            :value="data.domains.feedback?.kpis?.average_program ?? 0"
            suffix=" / 5"
            :icon="Activity"
          />
          <IntelligenceKpiCard
            title="Organization Score"
            :value="data.domains.feedback?.kpis?.average_organization ?? 0"
            suffix=" / 5"
            :icon="ShieldCheck"
          />
        </div>

        <!-- Rating Distribution -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
          <h3 class="text-xs uppercase font-semibold tracking-wider text-slate-400">Star Rating Distribution</h3>
          <div class="space-y-2">
            <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-3 text-xs">
              <span class="w-12 text-slate-400 font-bold">{{ star }} Stars</span>
              <div class="flex-1 bg-slate-800 rounded-full h-3 overflow-hidden">
                <div
                  class="bg-amber-400 h-full rounded-full transition-all"
                  :style="{
                    width: data.domains.feedback?.kpis?.total_responses > 0
                      ? `${((data.domains.feedback?.distribution?.[star] || 0) / data.domains.feedback?.kpis?.total_responses) * 100}%`
                      : '0%'
                  }"
                ></div>
              </div>
              <span class="w-8 text-right font-mono text-white">{{ data.domains.feedback?.distribution?.[star] || 0 }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
