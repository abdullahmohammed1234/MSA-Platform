<script setup lang="ts">
import { ref, watch } from 'vue';
import { projectsApi } from '../../api/projects';
import type { ResourceBalancingSuggestion } from '../../types/pm';

interface Props {
  projectUuid: string;
  isOpen: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'reassigned'): void;
}>();

const loading = ref(false);
const suggestions = ref<ResourceBalancingSuggestion[]>([]);
const applyingId = ref<string | null>(null);

const loadSuggestions = async () => {
  if (!props.isOpen) return;
  loading.value = true;
  try {
    suggestions.value = await projectsApi.getBalancingSuggestions(props.projectUuid);
  } catch (err: any) {
    console.error('Failed to load balancing suggestions:', err);
  } finally {
    loading.value = false;
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal) loadSuggestions();
});

const applySuggestion = async (sugg: ResourceBalancingSuggestion) => {
  applyingId.value = sugg.id;
  try {
    await projectsApi.applyBalancingSuggestion(props.projectUuid, {
      task_uuid: sugg.task_uuid,
      source_user_id: sugg.source_user.id,
      target_user_id: sugg.suggested_assignee.id,
    });
    emit('reassigned');
    await loadSuggestions();
  } catch (err: any) {
    alert(err.response?.data?.message || 'Failed to apply reassignment.');
  } finally {
    applyingId.value = null;
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4 animate-in fade-in"
  >
    <div class="bg-white border border-[#ebe8de] rounded-2xl shadow-elevated max-w-2xl w-full p-6 text-[#1e1e1e] flex flex-col max-h-[85vh]">
      <!-- Header -->
      <div class="flex items-center justify-between pb-4 border-b border-[#ebe8de]">
        <div class="flex items-center gap-2.5">
          <span class="text-xl">⚖️</span>
          <div>
            <h3 class="text-base font-bold text-[#640c0e]">Advisory Resource Balancing</h3>
            <p class="text-xs text-[#6b7280]">Automated recommendations to resolve over-allocated contributors</p>
          </div>
        </div>

        <button
          type="button"
          class="text-[#6b7280] hover:text-[#1e1e1e] p-1 rounded-lg hover:bg-[#fbfbf9] transition-colors"
          @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto py-4 space-y-4">
        <div v-if="loading" class="flex items-center justify-center py-12 text-[#6b7280]">
          <div class="animate-spin w-6 h-6 border-2 border-[#640c0e] border-t-transparent rounded-full mr-3" />
          <span class="text-xs font-medium">Analyzing team commitments and schedule...</span>
        </div>

        <div
          v-else-if="suggestions.length === 0"
          class="text-center py-12 px-4 rounded-xl bg-[#fbfbf9] border border-[#ebe8de]"
        >
          <p class="text-sm font-bold text-[#1e1e1e] mb-1">Team workload is balanced</p>
          <p class="text-xs text-[#6b7280]">
            No contributors exceed 100% capacity during active deliverable windows.
          </p>
        </div>

        <div
          v-for="sugg in suggestions"
          :key="sugg.id"
          class="p-4 rounded-xl border border-[#ebe8de] bg-[#fbfbf9] hover:border-[#640c0e]/30 transition-all space-y-3"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <h4 class="text-xs font-bold text-[#1e1e1e] mb-0.5">{{ sugg.task_title }}</h4>
              <p class="text-[11px] text-[#6b7280]">
                Effort: <span class="text-[#1e1e1e] font-mono font-semibold">{{ sugg.estimated_hours }}h</span> •
                Dates: <span class="text-[#1e1e1e]">{{ sugg.start_date }} → {{ sugg.due_date }}</span>
              </p>
            </div>

            <button
              type="button"
              class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#640c0e] hover:bg-[#7e1012] text-white shadow-2xs transition-colors shrink-0 disabled:opacity-50"
              :disabled="applyingId === sugg.id"
              @click="applySuggestion(sugg)"
            >
              {{ applyingId === sugg.id ? 'Reassigning...' : 'Apply Reassignment' }}
            </button>
          </div>

          <!-- Transfer Dynamics Cards -->
          <div class="grid grid-cols-2 gap-3 text-xs">
            <!-- Source User -->
            <div class="p-2.5 rounded-xl bg-white border border-[#ebe8de]">
              <span class="text-[10px] text-[#991b1b] font-bold uppercase tracking-wider block mb-1">
                From Over-allocated Contributor
              </span>
              <p class="font-semibold text-[#1e1e1e]">{{ sugg.source_user.name }}</p>
              <p class="text-[10px] text-[#6b7280] mt-0.5">
                Utilization: <span class="text-[#991b1b] font-bold">{{ sugg.source_user.current_utilization }}%</span>
                → <span class="text-[#105232] font-semibold">{{ sugg.source_user.projected_utilization }}%</span>
              </p>
            </div>

            <!-- Target User -->
            <div class="p-2.5 rounded-xl bg-white border border-[#ebe8de]">
              <span class="text-[10px] text-[#105232] font-bold uppercase tracking-wider block mb-1">
                To Eligible Peer with Capacity
              </span>
              <p class="font-semibold text-[#1e1e1e]">{{ sugg.suggested_assignee.name }}</p>
              <p class="text-[10px] text-[#6b7280] mt-0.5">
                Utilization: <span class="text-[#6b7280] font-medium">{{ sugg.suggested_assignee.current_utilization }}%</span>
                → <span class="text-[#640c0e] font-bold">{{ sugg.suggested_assignee.projected_utilization }}%</span>
              </p>
            </div>
          </div>

          <p class="text-[11px] text-[#6b7280] italic">
            {{ sugg.reason }}
          </p>
        </div>
      </div>

      <!-- Footer -->
      <div class="pt-4 border-t border-[#ebe8de] flex justify-end">
        <button
          type="button"
          class="px-4 py-1.5 rounded-xl text-xs font-semibold bg-[#fbfbf9] hover:bg-[#f4f2ea] text-[#1e1e1e] border border-[#ebe8de] transition-colors"
          @click="emit('close')"
        >
          Done
        </button>
      </div>
    </div>
  </div>
</template>

