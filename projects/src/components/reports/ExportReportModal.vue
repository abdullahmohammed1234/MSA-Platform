<script setup lang="ts">
import { ref } from 'vue';
import { projectsApi } from '../../api/projects';
import {
  X,
  Download,
  FileSpreadsheet,
  FileText,
  File,
  Loader2,
  CheckCircle2,
  AlertCircle,
  Layers,
  Users,
} from 'lucide-vue-next';

const props = defineProps<{
  isOpen: boolean;
  projectUuid: string;
  projectName?: string;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

type ReportType = 'progress' | 'tasks' | 'workload';
type ReportFormat = 'csv' | 'xlsx' | 'pdf';

const selectedType = ref<ReportType>('progress');
const selectedFormat = ref<ReportFormat>('pdf');
const taskStatusFilter = ref<string>('');
const taskPriorityFilter = ref<string>('');

const defaultStartDate = new Date().toISOString().split('T')[0];
const defaultEndDate = new Date(Date.now() + 28 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
const workloadStartDate = ref(defaultStartDate);
const workloadEndDate = ref(defaultEndDate);

const isGenerating = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const reportTypes = [
  {
    id: 'progress' as ReportType,
    title: 'Project Progress Report',
    description: 'Executive overview, milestone completion, KPI metrics, and active/overdue tasks.',
    icon: Layers,
  },
  {
    id: 'tasks' as ReportType,
    title: 'Detailed Task Breakdown',
    description: 'Comprehensive task roster with sections, assignees, dates, and estimated hours.',
    icon: FileText,
  },
  {
    id: 'workload' as ReportType,
    title: 'Resource Workload Allocation',
    description: 'Contributor capacity, allocated effort, active task counts, and utilization rates.',
    icon: Users,
  },
];

const formats = [
  {
    id: 'pdf' as ReportFormat,
    label: 'PDF Document',
    extension: '.pdf',
    icon: FileText,
    badge: 'Branded',
  },
  {
    id: 'xlsx' as ReportFormat,
    label: 'Excel Spreadsheet',
    extension: '.xlsx',
    icon: FileSpreadsheet,
    badge: 'Multi-Sheet',
  },
  {
    id: 'csv' as ReportFormat,
    label: 'CSV Data',
    extension: '.csv',
    icon: File,
    badge: 'UTF-8 BOM',
  },
];

const handleExport = async () => {
  isGenerating.value = true;
  errorMessage.value = null;
  successMessage.value = null;

  try {
    const params: any = {
      type: selectedType.value,
      format: selectedFormat.value,
    };

    if (selectedType.value === 'tasks') {
      if (taskStatusFilter.value) params.status = taskStatusFilter.value;
      if (taskPriorityFilter.value) params.priority = taskPriorityFilter.value;
    } else if (selectedType.value === 'workload') {
      params.start_date = workloadStartDate.value;
      params.end_date = workloadEndDate.value;
    }

    const res = await projectsApi.exportProjectReport(props.projectUuid, params);

    // Trigger browser file download
    const blob = new Blob([res.data], {
      type:
        selectedFormat.value === 'pdf'
          ? 'application/pdf'
          : selectedFormat.value === 'xlsx'
          ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
          : 'text/csv;charset=utf-8;',
    });

    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.setAttribute('download', res.filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);

    successMessage.value = `Report downloaded: ${res.filename}`;
    setTimeout(() => {
      emit('close');
      successMessage.value = null;
    }, 1800);
  } catch (err: any) {
    console.error('Export failed:', err);
    errorMessage.value =
      err.response?.data?.message || err.message || 'Failed to generate report export. Please check permissions.';
  } finally {
    isGenerating.value = false;
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-200"
  >
    <div
      class="bg-white rounded-3xl border border-[#ebe8de] shadow-2xl w-full max-w-xl overflow-hidden animate-in zoom-in-95 duration-200 flex flex-col max-h-[90vh]"
    >
      <!-- Header -->
      <div class="p-6 border-b border-[#ebe8de] flex items-center justify-between bg-[#fffbf4]">
        <div>
          <h3 class="text-base font-bold text-[#640c0e] font-display flex items-center gap-2">
            <Download class="w-5 h-5 text-[#640c0e]" />
            <span>Export Project Intelligence Report</span>
          </h3>
          <p class="text-xs text-[#6b7280] mt-0.5">
            {{ projectName || 'Current Project' }} &bull; Option B Compliant Export
          </p>
        </div>
        <button
          type="button"
          @click="emit('close')"
          class="p-2 rounded-xl text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#ebe8de]/50 transition-colors"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Body / Config -->
      <div class="p-6 overflow-y-auto space-y-6">
        <!-- Error / Success Alert -->
        <div
          v-if="errorMessage"
          class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center gap-2"
        >
          <AlertCircle class="w-4 h-4 shrink-0 text-rose-600" />
          <span>{{ errorMessage }}</span>
        </div>

        <div
          v-if="successMessage"
          class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2"
        >
          <CheckCircle2 class="w-4 h-4 shrink-0 text-emerald-600" />
          <span>{{ successMessage }}</span>
        </div>

        <!-- 1. Select Report Type -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-[#1e1e1e] uppercase tracking-wider">
            1. Select Report Scope
          </label>
          <div class="grid grid-cols-1 gap-2.5">
            <button
              v-for="rtype in reportTypes"
              :key="rtype.id"
              type="button"
              @click="selectedType = rtype.id"
              class="w-full text-left p-3.5 rounded-2xl border transition-all flex items-start gap-3.5"
              :class="[
                selectedType === rtype.id
                  ? 'border-[#640c0e] bg-[#640c0e]/5 ring-1 ring-[#640c0e]'
                  : 'border-[#ebe8de] bg-[#fbfbf9] hover:bg-white hover:border-[#640c0e]/30',
              ]"
            >
              <div
                class="p-2 rounded-xl shrink-0 transition-colors"
                :class="selectedType === rtype.id ? 'bg-[#640c0e] text-white' : 'bg-white border border-[#ebe8de] text-[#640c0e]'"
              >
                <component :is="rtype.icon" class="w-4 h-4" />
              </div>
              <div class="flex-1">
                <div class="text-xs font-bold text-[#1e1e1e]">
                  {{ rtype.title }}
                </div>
                <div class="text-[11px] text-[#6b7280] mt-0.5 leading-snug">
                  {{ rtype.description }}
                </div>
              </div>
            </button>
          </div>
        </div>

        <!-- 2. Select Output Format -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-[#1e1e1e] uppercase tracking-wider">
            2. Choose Format
          </label>
          <div class="grid grid-cols-3 gap-3">
            <button
              v-for="fmt in formats"
              :key="fmt.id"
              type="button"
              @click="selectedFormat = fmt.id"
              class="p-3 rounded-2xl border text-center transition-all flex flex-col items-center justify-center gap-1.5"
              :class="[
                selectedFormat === fmt.id
                  ? 'border-[#640c0e] bg-[#640c0e]/5 ring-1 ring-[#640c0e]'
                  : 'border-[#ebe8de] bg-[#fbfbf9] hover:bg-white hover:border-[#640c0e]/30',
              ]"
            >
              <component
                :is="fmt.icon"
                class="w-5 h-5"
                :class="selectedFormat === fmt.id ? 'text-[#640c0e]' : 'text-[#6b7280]'"
              />
              <span class="text-xs font-bold text-[#1e1e1e]">{{ fmt.label }}</span>
              <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-gray-100 text-[#6b7280]">
                {{ fmt.badge }}
              </span>
            </button>
          </div>
        </div>

        <!-- 3. Dynamic Filters (when tasks report) -->
        <div v-if="selectedType === 'tasks'" class="p-4 rounded-2xl bg-[#fbfbf9] border border-[#ebe8de] space-y-3">
          <span class="text-xs font-bold text-[#1e1e1e]">Optional Task Filters</span>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-medium text-[#6b7280] mb-1">Status</label>
              <select
                v-model="taskStatusFilter"
                class="w-full px-3 py-1.5 rounded-xl border border-[#ebe8de] text-xs bg-white focus:outline-none focus:border-[#640c0e]"
              >
                <option value="">All Statuses</option>
                <option value="todo">To Do</option>
                <option value="in_progress">In Progress</option>
                <option value="review">Review</option>
                <option value="completed">Completed</option>
              </select>
            </div>
            <div>
              <label class="block text-[11px] font-medium text-[#6b7280] mb-1">Priority</label>
              <select
                v-model="taskPriorityFilter"
                class="w-full px-3 py-1.5 rounded-xl border border-[#ebe8de] text-xs bg-white focus:outline-none focus:border-[#640c0e]"
              >
                <option value="">All Priorities</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>
          </div>
        </div>

        <!-- 4. Dynamic Date Range (when workload report) -->
        <div v-if="selectedType === 'workload'" class="p-4 rounded-2xl bg-[#fbfbf9] border border-[#ebe8de] space-y-3">
          <span class="text-xs font-bold text-[#1e1e1e]">Analysis Timeline Window</span>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-medium text-[#6b7280] mb-1">Start Date</label>
              <input
                type="date"
                v-model="workloadStartDate"
                class="w-full px-3 py-1.5 rounded-xl border border-[#ebe8de] text-xs bg-white focus:outline-none focus:border-[#640c0e]"
              />
            </div>
            <div>
              <label class="block text-[11px] font-medium text-[#6b7280] mb-1">End Date</label>
              <input
                type="date"
                v-model="workloadEndDate"
                class="w-full px-3 py-1.5 rounded-xl border border-[#ebe8de] text-xs bg-white focus:outline-none focus:border-[#640c0e]"
              />
            </div>
          </div>
        </div>
        <!-- Truncation / Safety Disclosure -->
        <div class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-[11px] leading-snug">
          <AlertCircle class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
          <div>
            <span class="font-bold">Export Cap Notice:</span>
            Exports are capped at 1,000 tasks per download to ensure responsiveness. For large projects, please apply status or priority filters to export specific subsets.
          </div>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="p-6 border-t border-[#ebe8de] flex items-center justify-between bg-[#fffbf4]">
        <button
          type="button"
          @click="emit('close')"
          :disabled="isGenerating"
          class="px-4 py-2 rounded-xl text-xs font-semibold text-[#6b7280] hover:text-[#1e1e1e] hover:bg-gray-100 transition-colors"
        >
          Cancel
        </button>

        <button
          type="button"
          @click="handleExport"
          :disabled="isGenerating"
          class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] disabled:opacity-50 transition-all shadow-xs"
        >
          <Loader2 v-if="isGenerating" class="w-4 h-4 animate-spin" />
          <Download v-else class="w-4 h-4" />
          <span>{{ isGenerating ? 'Generating...' : 'Download Report' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>