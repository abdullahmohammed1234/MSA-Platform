<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { projectsApi } from '../../api/projects';
import type { ProjectAnalytics } from '../../types/pm';
import StatusDonutChart from './StatusDonutChart.vue';
import VelocityBarChart from './VelocityBarChart.vue';
import {
  CheckCircle2,
  Clock,
  AlertCircle,
  Flame,
  Hourglass,
  Sparkles,
} from 'lucide-vue-next';

const props = defineProps<{
  projectUuid: string;
}>();

const loading = ref(true);
const error = ref<string | null>(null);
const analytics = ref<ProjectAnalytics | null>(null);

const loadAnalytics = async () => {
  loading.value = true;
  error.value = null;
  try {
    const data = await projectsApi.getProjectAnalytics(props.projectUuid);
    analytics.value = data;
  } catch (e: any) {
    error.value = e?.response?.data?.message || e?.message || 'Failed to load project analytics';
  } finally {
    loading.value = false;
  }
};

onMounted(loadAnalytics);
watch(() => props.projectUuid, loadAnalytics);
</script>

<template>
  <div class="space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="py-16 text-center">
      <div class="w-8 h-8 border-2 border-[#640c0e] border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
      <p class="text-xs text-[#6b7280]">Calculating project analytics...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="p-4 rounded-2xl bg-red-50 border border-red-200 text-xs text-red-700">
      {{ error }}
    </div>

    <!-- Content -->
    <div v-else-if="analytics" class="space-y-6">
      <!-- KPI Top Summary Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <!-- Completion Rate -->
        <div class="bg-white p-4 rounded-2xl border border-[#ebe8de] shadow-xs">
          <div class="flex items-center justify-between text-xs text-[#6b7280]">
            <span class="font-medium">Completion Rate</span>
            <CheckCircle2 class="w-4 h-4 text-[#10b981]" />
          </div>
          <div class="text-2xl font-black text-[#1e1e1e] font-display mt-1">
            {{ analytics.project.completion_rate }}%
          </div>
          <div class="w-full bg-[#f3f4f6] h-1.5 rounded-full mt-2 overflow-hidden">
            <div
              class="bg-[#10b981] h-full rounded-full transition-all duration-500"
              :style="{ width: `${analytics.project.completion_rate}%` }"
            ></div>
          </div>
        </div>

        <!-- Remaining Effort -->
        <div class="bg-white p-4 rounded-2xl border border-[#ebe8de] shadow-xs">
          <div class="flex items-center justify-between text-xs text-[#6b7280]">
            <span class="font-medium">Remaining Effort</span>
            <Hourglass class="w-4 h-4 text-[#640c0e]" />
          </div>
          <div class="text-2xl font-black text-[#1e1e1e] font-display mt-1">
            {{ analytics.effort_hours.remaining }} <span class="text-sm font-semibold text-[#6b7280]">hrs</span>
          </div>
          <p class="text-[11px] text-[#6b7280] mt-1.5">
            of {{ analytics.effort_hours.total }} total estimated hours
          </p>
        </div>

        <!-- Overdue Tasks -->
        <div class="bg-white p-4 rounded-2xl border border-[#ebe8de] shadow-xs">
          <div class="flex items-center justify-between text-xs text-[#6b7280]">
            <span class="font-medium">Overdue Tasks</span>
            <AlertCircle class="w-4 h-4 text-[#ea2128]" />
          </div>
          <div class="text-2xl font-black text-[#ea2128] font-display mt-1">
            {{ analytics.task_counts.overdue }}
          </div>
          <p class="text-[11px] text-[#6b7280] mt-1.5">
            Require immediate attention
          </p>
        </div>

        <!-- Milestones Performance -->
        <div class="bg-white p-4 rounded-2xl border border-[#ebe8de] shadow-xs">
          <div class="flex items-center justify-between text-xs text-[#6b7280]">
            <span class="font-medium">Milestone Delivery</span>
            <Sparkles class="w-4 h-4 text-[#8b5cf6]" />
          </div>
          <div class="text-2xl font-black text-[#1e1e1e] font-display mt-1">
            {{ analytics.milestones.on_time_rate }}%
          </div>
          <p class="text-[11px] text-[#6b7280] mt-1.5">
            {{ analytics.milestones.completed }} of {{ analytics.milestones.total }} reached on time
          </p>
        </div>
      </div>

      <!-- Mid Section: Task Status Donut + Cycle Times -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Status Donut Card -->
        <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-[#ebe8de] shadow-xs space-y-4">
          <h3 class="text-sm font-bold text-[#1e1e1e]">Task Status Distribution</h3>
          <StatusDonutChart :counts="analytics.task_counts" />
        </div>

        <!-- Cycle & Lead Time Card -->
        <div class="bg-white p-5 rounded-2xl border border-[#ebe8de] shadow-xs space-y-4">
          <h3 class="text-sm font-bold text-[#1e1e1e]">Efficiency & Delivery Times</h3>

          <div class="space-y-3">
            <div class="p-3 bg-[#fbfbf9] rounded-xl border border-[#ebe8de]/70">
              <div class="flex items-center justify-between text-xs">
                <span class="text-[#6b7280] font-medium">Avg Lead Time</span>
                <Clock class="w-3.5 h-3.5 text-[#6b7280]" />
              </div>
              <div class="text-lg font-black text-[#1e1e1e] mt-0.5">
                {{ analytics.cycle_time_metrics.avg_lead_time_days !== null ? `${analytics.cycle_time_metrics.avg_lead_time_days} days` : 'N/A' }}
              </div>
              <p class="text-[10px] text-[#9ca3af] mt-0.5">From task creation to completion</p>
            </div>

            <div class="p-3 bg-[#fbfbf9] rounded-xl border border-[#ebe8de]/70">
              <div class="flex items-center justify-between text-xs">
                <span class="text-[#6b7280] font-medium">Avg Cycle Time</span>
                <Flame class="w-3.5 h-3.5 text-[#f59e0b]" />
              </div>
              <div class="text-lg font-black text-[#1e1e1e] mt-0.5">
                {{ analytics.cycle_time_metrics.avg_cycle_time_days !== null ? `${analytics.cycle_time_metrics.avg_cycle_time_days} days` : 'N/A' }}
              </div>
              <p class="text-[10px] text-[#9ca3af] mt-0.5">From work started to completion</p>
            </div>
          </div>

          <p v-if="!analytics.cycle_time_metrics.has_sufficient_history" class="text-[10px] text-[#9ca3af] italic">
            * Minimum 3 completed tasks required for statistically significant averages.
          </p>
        </div>
      </div>

      <!-- Velocity Bar Chart -->
      <div class="bg-white p-5 rounded-2xl border border-[#ebe8de] shadow-xs">
        <VelocityBarChart :weeks="analytics.velocity_trend" />
      </div>
    </div>
  </div>
</template>
