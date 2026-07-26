<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { BarChart, Check, Plus } from 'lucide-vue-next';
import { useEmsAccessStore } from '@/stores/ems/emsAccess';
import { EMS_PERMISSIONS } from '@/constants/ems';
import { analyticsService, type EventComparisonItem } from '@/services/ems/analyticsService';
import { eventsService } from '@/services/ems/eventsService';
import type { Event } from '@/types/ems';
import SvgBarChart from '@/components/ems/charts/SvgBarChart.vue';
import { useToastStore } from '@/components/feedback/toast';

const access = useEmsAccessStore();
const toast = useToastStore();

const allEvents = ref<Event[]>([]);
const selectedUuids = ref<string[]>([]);
const comparisonData = ref<EventComparisonItem[]>([]);
const isLoading = ref(false);

const showFinancial = computed(() => access.can(EMS_PERMISSIONS.ANALYTICS_VIEW_FINANCIAL));

// Fetch all events for selection list
onMounted(async () => {
  try {
    const res = await eventsService.list({ per_page: 100 });
    allEvents.value = res.items;

    // Default select the first two events if available
    if (res.items.length >= 2) {
      selectedUuids.value = [res.items[0].uuid, res.items[1].uuid];
    }
  } catch (err) {
    console.error('Failed to load events', err);
  }
});

// Fetch comparison data when selected UUIDs change
const fetchComparison = async () => {
  if (selectedUuids.value.length === 0) {
    comparisonData.value = [];
    return;
  }
  isLoading.value = true;
  try {
    const data = await analyticsService.compare(selectedUuids.value);
    comparisonData.value = data;
  } catch (err: any) {
    toast.error('Failed to load comparison data');
  } finally {
    isLoading.value = false;
  }
};

watch(selectedUuids, () => {
  void fetchComparison();
}, { deep: true });

const formatCurrency = (val: number) => {
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD' }).format(val);
};

const toggleEvent = (uuid: string) => {
  const index = selectedUuids.value.indexOf(uuid);
  if (index >= 0) {
    selectedUuids.value.splice(index, 1);
  } else {
    if (selectedUuids.value.length >= 5) {
      toast.error('You can compare a maximum of 5 events.');
      return;
    }
    selectedUuids.value.push(uuid);
  }
};

// Map comparisonData to SvgBarChart format
const barChartRegistrations = computed(() => {
  return comparisonData.value.map((item) => ({
    label: item.name,
    value: item.registrations,
    secondaryValue: item.capacity !== null ? item.capacity : undefined,
  }));
});

const barChartAttendance = computed(() => {
  return comparisonData.value.map((item) => ({
    label: item.name,
    value: item.attendance_rate,
    secondaryValue: item.no_show_rate,
  }));
});
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold font-serif text-primary">Event Comparison</h1>
      <p class="text-xs text-neutral-muted">Compare attendance, registrations, waitlists, and revenue metrics across events side-by-side.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-4">
      <!-- Sidebar Selector -->
      <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft space-y-4">
        <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-black">Select Events (Max 5)</h3>
        <div class="space-y-1.5 max-h-[350px] overflow-y-auto pr-1">
          <button
            v-for="e in allEvents"
            :key="e.uuid"
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-xl border px-3 py-2 text-left text-xs font-medium transition-colors cursor-pointer"
            :class="
              selectedUuids.includes(e.uuid)
                ? 'border-primary bg-primary/5 text-primary'
                : 'border-neutral-ivory hover:bg-neutral-background text-neutral-black'
            "
            @click="toggleEvent(e.uuid)"
          >
            <span class="truncate">{{ e.name }}</span>
            <Check v-if="selectedUuids.includes(e.uuid)" class="h-3.5 w-3.5 shrink-0" />
            <Plus v-else class="h-3.5 w-3.5 shrink-0 text-neutral-muted" />
          </button>
        </div>
        <p class="text-[10px] text-neutral-muted">Select events in the list above to compare their performance metrics side-by-side.</p>
      </div>

      <!-- Comparison Content -->
      <div class="lg:col-span-3 space-y-6">
        <div v-if="selectedUuids.length === 0" class="flex h-60 flex-col items-center justify-center rounded-2xl border border-dashed border-neutral-ivory bg-white text-center p-6 text-neutral-muted">
          <BarChart class="h-10 w-10 text-neutral-muted/50 mb-3" />
          <p class="text-sm font-semibold">No events selected</p>
          <p class="text-xs mt-1">Choose at least one event from the sidebar selector to analyze.</p>
        </div>

        <div v-else-if="isLoading && comparisonData.length === 0" class="h-60 animate-pulse rounded-2xl bg-neutral-ivory/50" />

        <template v-else>
          <!-- Side-by-Side Table -->
          <div class="rounded-2xl border border-neutral-ivory bg-white shadow-soft overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-neutral-background/70 border-b border-neutral-ivory">
                    <th class="p-3 font-semibold text-neutral-muted min-w-[150px]">Metric</th>
                    <th v-for="item in comparisonData" :key="item.uuid" class="p-3 font-semibold text-neutral-black text-center min-w-[120px]">
                      {{ item.name }}
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-neutral-ivory">
                  <tr>
                    <td class="p-3 font-semibold text-neutral-muted">Date</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center text-neutral-black">
                      {{ item.start_at ? new Date(item.start_at).toLocaleDateString() : 'N/A' }}
                    </td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold text-neutral-muted">Capacity Limit</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center text-neutral-black">
                      {{ item.capacity !== null ? item.capacity : 'Unlimited' }}
                    </td>
                  </tr>
                  <tr class="bg-neutral-background/20 font-medium">
                    <td class="p-3 font-semibold text-neutral-muted">Registrations</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center text-neutral-black font-bold">
                      {{ item.registrations }}
                    </td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold text-neutral-muted">Checked-In</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center text-neutral-black">
                      {{ item.checked_in }}
                    </td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold text-neutral-muted">No-Shows</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center text-amber-600">
                      {{ item.no_shows }}
                    </td>
                  </tr>
                  <tr class="font-bold">
                    <td class="p-3 font-semibold text-neutral-muted">Attendance Rate</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center text-emerald-800">
                      {{ item.attendance_rate }}%
                    </td>
                  </tr>
                  <tr v-if="showFinancial" class="bg-primary/5 text-primary font-bold">
                    <td class="p-3 font-semibold text-neutral-muted">Net Revenue</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center">
                      {{ formatCurrency(item.net_revenue) }}
                    </td>
                  </tr>
                  <tr v-if="showFinancial">
                    <td class="p-3 font-semibold text-neutral-muted">Gross Revenue</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center text-neutral-black">
                      {{ formatCurrency(item.gross_revenue) }}
                    </td>
                  </tr>
                  <tr v-if="showFinancial">
                    <td class="p-3 font-semibold text-neutral-muted">Refunds</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center text-neutral-muted">
                      {{ formatCurrency(item.refunds) }}
                    </td>
                  </tr>
                  <tr>
                    <td class="p-3 font-semibold text-neutral-muted">Waitlist Size</td>
                    <td v-for="item in comparisonData" :key="item.uuid" class="p-3 text-center text-neutral-black">
                      {{ item.waitlist_size }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Comparative Charts -->
          <div class="grid gap-6 md:grid-cols-2">
            <!-- Registrations comparative bar chart -->
            <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
              <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-black mb-3">Registrations vs Capacity</h4>
              <p class="text-[10px] text-neutral-muted mb-4">Primary bar: Registrations | Secondary bar: Capacity limit</p>
              <SvgBarChart :data="barChartRegistrations" :height="200" />
            </div>

            <!-- Attendance comparative bar chart -->
            <div class="rounded-2xl border border-neutral-ivory bg-white p-4 shadow-soft">
              <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-black mb-3">Attendance vs No-Shows</h4>
              <p class="text-[10px] text-neutral-muted mb-4">Primary bar: Attendance rate | Secondary bar: No-show rate</p>
              <SvgBarChart :data="barChartAttendance" :height="200" bar-color="#065f46" secondary-bar-color="#d97706" />
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
