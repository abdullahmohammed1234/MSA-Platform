<script setup lang="ts">
import { ref } from 'vue';
import { projectsApi } from '../../api/projects';
import type { Milestone, MilestoneStatus } from '../../types/pm';
import { Flag, Plus, CheckCircle2, Trash2, Calendar } from 'lucide-vue-next';

const props = defineProps<{
  projectUuid: string;
  milestones: Milestone[];
  canEdit?: boolean;
}>();

const emit = defineEmits<{
  (e: 'refresh'): void;
}>();

const showAddModal = ref(false);
const title = ref('');
const description = ref('');
const dueDate = ref('');
const isSubmitting = ref(false);

const handleCreateMilestone = async () => {
  if (!title.value.trim() || isSubmitting.value) return;
  isSubmitting.value = true;
  try {
    await projectsApi.createMilestone(props.projectUuid, {
      title: title.value.trim(),
      description: description.value.trim() || undefined,
      due_date: dueDate.value || undefined,
    });
    title.value = '';
    description.value = '';
    dueDate.value = '';
    showAddModal.value = false;
    emit('refresh');
  } catch (err) {
    console.error('Failed to create milestone:', err);
  } finally {
    isSubmitting.value = false;
  }
};

const handleUpdateStatus = async (milestone: Milestone, newStatus: MilestoneStatus) => {
  try {
    await projectsApi.updateMilestone(milestone.uuid, { status: newStatus });
    emit('refresh');
  } catch (err) {
    console.error('Failed to update milestone:', err);
  }
};

const handleDelete = async (milestone: Milestone) => {
  if (!confirm(`Delete milestone "${milestone.title}"?`)) return;
  try {
    await projectsApi.deleteMilestone(milestone.uuid);
    emit('refresh');
  } catch (err) {
    console.error('Failed to delete milestone:', err);
  }
};
</script>

<template>
  <div class="space-y-4">
    <!-- Header with Add Milestone button -->
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-bold text-[#1e1e1e] font-display">Key Milestones & Deliverables</h3>
        <p class="text-xs text-[#6b7280]">Target dates for major project phases and signoffs.</p>
      </div>

      <button
        v-if="canEdit"
        type="button"
        @click="showAddModal = true"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] transition-colors"
      >
        <Plus class="w-3.5 h-3.5" /> Add Milestone
      </button>
    </div>

    <!-- Milestones List -->
    <div class="space-y-3">
      <div
        v-for="ms in milestones"
        :key="ms.uuid"
        class="p-4 rounded-xl bg-white border border-[#ebe8de] shadow-xs hover:border-[#640c0e]/30 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3"
      >
        <div class="flex items-start gap-3">
          <div
            class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
            :class="[
              ms.status === 'reached'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-[#640c0e]/10 text-[#640c0e]',
            ]"
          >
            <Flag class="w-4 h-4" />
          </div>

          <div>
            <div class="flex items-center gap-2">
              <h4 class="text-sm font-bold text-[#1e1e1e]">{{ ms.title }}</h4>
              <span
                class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                :class="[
                  ms.status === 'reached'
                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                    : 'bg-amber-50 text-amber-700 border border-amber-200',
                ]"
              >
                {{ ms.status }}
              </span>
            </div>
            <p v-if="ms.description" class="text-xs text-[#6b7280] mt-0.5">{{ ms.description }}</p>
            <div class="flex items-center gap-3 mt-1 text-[11px] text-[#6b7280]">
              <span v-if="ms.due_date" class="flex items-center gap-1">
                <Calendar class="w-3 h-3" /> Due {{ ms.due_date }}
              </span>
              <span v-if="ms.tasks_count">
                {{ ms.completed_tasks_count || 0 }} of {{ ms.tasks_count }} tasks completed
              </span>
            </div>
          </div>
        </div>

        <!-- Controls -->
        <div v-if="canEdit" class="flex items-center gap-2 self-end sm:self-center">
          <button
            v-if="ms.status !== 'reached'"
            type="button"
            @click="handleUpdateStatus(ms, 'reached')"
            class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors flex items-center gap-1"
          >
            <CheckCircle2 class="w-3.5 h-3.5" /> Mark Reached
          </button>
          <button
            type="button"
            @click="handleDelete(ms)"
            class="p-1.5 rounded-lg text-[#6b7280] hover:text-[#ea2128] hover:bg-[#ea2128]/5 transition-colors"
            title="Delete Milestone"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>

      <div
        v-if="milestones.length === 0"
        class="py-10 text-center text-xs text-[#6b7280] border border-dashed border-[#ded9cb] rounded-xl bg-white/50"
      >
        No milestones established yet for this project.
      </div>
    </div>

    <!-- Add Milestone Modal -->
    <div
      v-if="showAddModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
    >
      <div class="bg-white rounded-2xl shadow-elevated border border-[#ebe8de] w-full max-w-md p-6 space-y-4">
        <h3 class="text-base font-bold text-[#640c0e] font-display">New Milestone</h3>

        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Title *</label>
          <input
            v-model="title"
            type="text"
            required
            placeholder="e.g., Venue Deposit & Agreement"
            class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Target Due Date</label>
          <input
            v-model="dueDate"
            type="date"
            class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Description</label>
          <textarea
            v-model="description"
            rows="2"
            placeholder="Key outcomes expected..."
            class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none resize-none"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="showAddModal = false"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-[#6b7280] hover:bg-[#fbfbf9]"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="!title.trim() || isSubmitting"
            @click="handleCreateMilestone"
            class="px-5 py-2 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] disabled:opacity-50"
          >
            Create Milestone
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
