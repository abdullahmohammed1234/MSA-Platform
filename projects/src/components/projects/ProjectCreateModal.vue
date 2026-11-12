<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { useProjectsStore } from '../../stores/projects';
import { useDepartmentsStore } from '../../stores/departments';
import type { DepartmentSummary } from '../../types/pm';
import {
  X,
  Lock,
  Globe,
  AlertCircle,
  Loader2,
  Search,
  Plus,
  RefreshCw,
} from 'lucide-vue-next';

const props = defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'created', uuid: string): void;
}>();

const authStore = useAuthStore();
const projectsStore = useProjectsStore();
const departmentsStore = useDepartmentsStore();

const name = ref('');
const owningDepartmentId = ref<number | null>(null);
const participatingDepartmentIds = ref<number[]>([]);
const participantSearch = ref('');
const visibility = ref<'department_private' | 'shared_msa'>('department_private');
const priority = ref<'low' | 'medium' | 'high' | 'urgent'>('medium');
const startDate = ref('');
const dueDate = ref('');
const description = ref('');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);

// Load canonical departments on mount or when modal opens
const loadDepartments = async (force = false) => {
  try {
    await departmentsStore.loadDepartments(force);
    // If no owning department is selected yet, default to user's assigned department if available
    if (owningDepartmentId.value === null && departmentsStore.departments.length > 0) {
      const userDeptId = authStore.departments[0]?.id;
      const foundInCanonical = departmentsStore.departments.find((d) => d.id === userDeptId);
      if (foundInCanonical) {
        owningDepartmentId.value = foundInCanonical.id;
      }
    }
  } catch (err: any) {
    console.error('Failed to load canonical departments:', err);
  }
};

onMounted(() => {
  loadDepartments();
});

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      errorMessage.value = null;
      loadDepartments();
    }
  }
);

// Requirement: Automatically remove previously selected participating department if it becomes the owning department
watch(owningDepartmentId, (newOwningId) => {
  if (newOwningId !== null) {
    participatingDepartmentIds.value = participatingDepartmentIds.value.filter(
      (id) => id !== newOwningId
    );
  }
});

// Canonical department list from backend
const canonicalDepartments = computed(() => departmentsStore.departments);

// Selected participating department objects
const selectedParticipatingDepartments = computed(() => {
  return participatingDepartmentIds.value
    .map((id) => canonicalDepartments.value.find((d) => d.id === id))
    .filter((d): d is DepartmentSummary => !!d);
});

// Available departments to add as participating (excluding owning dept and already selected depts)
const availableParticipatingDepartments = computed(() => {
  const query = participantSearch.value.trim().toLowerCase();
  return canonicalDepartments.value.filter((d) => {
    if (d.id === owningDepartmentId.value) return false;
    if (participatingDepartmentIds.value.includes(d.id)) return false;
    if (!query) return true;
    return (
      d.name.toLowerCase().includes(query) ||
      (d.code && d.code.toLowerCase().includes(query)) ||
      (d.slug && d.slug.toLowerCase().includes(query))
    );
  });
});

const addParticipatingDepartment = (deptId: number) => {
  if (deptId === owningDepartmentId.value) return;
  if (!participatingDepartmentIds.value.includes(deptId)) {
    participatingDepartmentIds.value.push(deptId);
  }
  participantSearch.value = '';
};

const removeParticipatingDepartment = (deptId: number) => {
  participatingDepartmentIds.value = participatingDepartmentIds.value.filter((id) => id !== deptId);
};

const canSubmit = computed(() => {
  return (
    name.value.trim().length > 0 &&
    owningDepartmentId.value !== null &&
    !isSubmitting.value &&
    !departmentsStore.isLoading
  );
});

const handleSubmit = async () => {
  if (!canSubmit.value) return;

  isSubmitting.value = true;
  errorMessage.value = null;

  try {
    const project = await projectsStore.createProject({
      name: name.value.trim(),
      owning_department_id: owningDepartmentId.value!,
      department_id: owningDepartmentId.value!,
      participating_department_ids: participatingDepartmentIds.value,
      visibility: visibility.value,
      priority: priority.value,
      start_date: startDate.value || undefined,
      due_date: dueDate.value || undefined,
      description: description.value.trim() || undefined,
    });

    // Reset form
    name.value = '';
    description.value = '';
    startDate.value = '';
    dueDate.value = '';
    priority.value = 'medium';
    owningDepartmentId.value = null;
    participatingDepartmentIds.value = [];
    participantSearch.value = '';

    emit('created', project.uuid);
    emit('close');
  } catch (err: any) {
    errorMessage.value =
      err.response?.data?.message ||
      err.response?.data?.errors?.[Object.keys(err.response?.data?.errors || {})[0]]?.[0] ||
      'Failed to create project. Please verify inputs.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs animate-in fade-in duration-150 overflow-y-auto"
  >
    <div
      class="bg-white rounded-2xl shadow-elevated border border-[#ebe8de] w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-150"
    >
      <!-- Header -->
      <div class="px-6 py-4 border-b border-[#ebe8de] flex items-center justify-between shrink-0">
        <div>
          <h3 class="text-base font-bold text-[#640c0e] font-display">Create New Project</h3>
          <p class="text-xs text-[#6b7280]">
            Start an owning department or cross-department MSA initiative.
          </p>
        </div>
        <button
          type="button"
          @click="$emit('close')"
          class="p-1.5 rounded-lg text-[#6b7280] hover:text-[#1e1e1e] hover:bg-[#fbfbf9] transition-colors"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Body / Form -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4 overflow-y-auto flex-1">
        <!-- Error Banner -->
        <div
          v-if="errorMessage"
          class="p-3 rounded-xl bg-[#ea2128]/10 border border-[#ea2128]/20 text-[#ea2128] text-xs flex items-center gap-2"
        >
          <AlertCircle class="w-4 h-4 shrink-0" />
          <span>{{ errorMessage }}</span>
        </div>

        <!-- Project Name -->
        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Project Name *</label>
          <input
            v-model="name"
            type="text"
            required
            placeholder="e.g., Ramadan Charity Campaign 2027"
            class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
          />
        </div>

        <!-- Department Loading State -->
        <div
          v-if="departmentsStore.isLoading"
          class="p-4 rounded-xl border border-[#ebe8de] bg-[#fbfbf9] flex items-center justify-center gap-2 text-xs text-[#6b7280]"
        >
          <Loader2 class="w-4 h-4 animate-spin text-[#640c0e]" />
          <span>Loading canonical departments...</span>
        </div>

        <!-- Department Loading Error State & Retry -->
        <div
          v-else-if="departmentsStore.error"
          class="p-3.5 rounded-xl bg-[#ea2128]/10 border border-[#ea2128]/20 text-xs text-[#ea2128] space-y-2"
        >
          <div class="flex items-center gap-2 font-semibold">
            <AlertCircle class="w-4 h-4 shrink-0" />
            <span>{{ departmentsStore.error }}</span>
          </div>
          <button
            type="button"
            @click="loadDepartments(true)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#ea2128] text-white font-bold hover:bg-[#c9181e] transition-colors"
          >
            <RefreshCw class="w-3.5 h-3.5" />
            <span>Retry Loading Departments</span>
          </button>
        </div>

        <!-- Department Empty State -->
        <div
          v-else-if="canonicalDepartments.length === 0"
          class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800"
        >
          No departments found in the registry. Please contact an administrator.
        </div>

        <!-- Department Fields -->
        <div v-else class="space-y-4">
          <!-- Owning Department (Single-select, Required) -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-bold text-[#1e1e1e]">
                Owning Department *
              </label>
              <span class="text-[11px] text-[#6b7280]">Accountable department</span>
            </div>
            <select
              v-model="owningDepartmentId"
              required
              class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white font-medium"
            >
              <option :value="null" disabled>Select an owning department...</option>
              <option
                v-for="dept in canonicalDepartments"
                :key="dept.id"
                :value="dept.id"
              >
                {{ dept.name }} ({{ dept.code || dept.slug?.toUpperCase() }})
              </option>
            </select>
          </div>

          <!-- Participating Departments (Searchable Multi-select, Optional) -->
          <div class="p-4 rounded-xl border border-[#ebe8de] bg-[#fbfbf9]/60 space-y-3">
            <div class="flex items-center justify-between">
              <div>
                <label class="block text-xs font-bold text-[#1e1e1e]">
                  Participating Departments
                </label>
                <p class="text-[11px] text-[#6b7280]">
                  Collaborating departments with Option B access to department-private projects.
                </p>
              </div>
              <span
                class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                :class="
                  participatingDepartmentIds.length > 0
                    ? 'bg-[#640c0e]/10 text-[#640c0e]'
                    : 'bg-gray-100 text-gray-500'
                "
              >
                {{ participatingDepartmentIds.length }} Selected
              </span>
            </div>

            <!-- Selected Chips -->
            <div v-if="selectedParticipatingDepartments.length > 0" class="flex flex-wrap gap-1.5 pt-1">
              <span
                v-for="dept in selectedParticipatingDepartments"
                :key="dept.id"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-white border border-[#640c0e]/30 text-[#640c0e] shadow-2xs"
              >
                <span class="font-bold text-[10px] px-1 py-0.2 bg-[#640c0e]/10 rounded">
                  {{ dept.code || dept.slug?.toUpperCase() }}
                </span>
                <span>{{ dept.name }}</span>
                <button
                  type="button"
                  @click="removeParticipatingDepartment(dept.id)"
                  class="text-[#640c0e]/60 hover:text-[#640c0e] transition-colors ml-0.5"
                  title="Remove department"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </span>
            </div>

            <!-- Search & Add Dropdown / List -->
            <div class="space-y-2">
              <div class="relative">
                <Search class="w-3.5 h-3.5 text-[#6b7280] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  v-model="participantSearch"
                  type="text"
                  placeholder="Search and add participating departments..."
                  class="w-full pl-8 pr-3 py-2 rounded-lg border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
                />
              </div>

              <!-- Available Departments Pills/List -->
              <div
                v-if="availableParticipatingDepartments.length > 0"
                class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto p-1 rounded-lg bg-white border border-[#ebe8de]/60"
              >
                <button
                  v-for="dept in availableParticipatingDepartments"
                  :key="dept.id"
                  type="button"
                  @click="addParticipatingDepartment(dept.id)"
                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium bg-[#fbfbf9] text-[#1e1e1e] border border-[#ebe8de] hover:border-[#640c0e] hover:text-[#640c0e] hover:bg-[#640c0e]/5 transition-all"
                >
                  <Plus class="w-3 h-3 text-[#640c0e]" />
                  <span>{{ dept.name }}</span>
                  <span class="text-[9px] text-[#6b7280] font-mono">({{ dept.code }})</span>
                </button>
              </div>
              <p
                v-else-if="participantSearch"
                class="text-[11px] text-[#6b7280] italic px-1"
              >
                No matching departments found.
              </p>
            </div>
          </div>
        </div>

        <!-- Visibility Selector -->
        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-2">Project Visibility *</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label
              class="flex flex-col p-3 rounded-xl border cursor-pointer transition-all"
              :class="[
                visibility === 'department_private'
                  ? 'border-[#640c0e] bg-[#640c0e]/5 text-[#640c0e]'
                  : 'border-[#ebe8de] hover:border-[#6b7280]',
              ]"
            >
              <div class="flex items-center gap-2 font-bold text-xs mb-1">
                <input
                  type="radio"
                  v-model="visibility"
                  value="department_private"
                  class="sr-only"
                />
                <Lock class="w-4 h-4" />
                <span>Department Private</span>
              </div>
              <p class="text-[11px] text-[#6b7280] leading-relaxed">
                Accessible to active members of owning & participating departments, and accepted invitees.
              </p>
            </label>

            <label
              class="flex flex-col p-3 rounded-xl border cursor-pointer transition-all"
              :class="[
                visibility === 'shared_msa'
                  ? 'border-[#640c0e] bg-[#640c0e]/5 text-[#640c0e]'
                  : 'border-[#ebe8de] hover:border-[#6b7280]',
              ]"
            >
              <div class="flex items-center gap-2 font-bold text-xs mb-1">
                <input
                  type="radio"
                  v-model="visibility"
                  value="shared_msa"
                  class="sr-only"
                />
                <Globe class="w-4 h-4" />
                <span>Shared MSA</span>
              </div>
              <p class="text-[11px] text-[#6b7280] leading-relaxed">
                Visible to all authorized SFU MSA members across all departments.
              </p>
            </label>
          </div>
        </div>

        <!-- Priority & Duration Dates -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Priority</label>
            <select
              v-model="priority"
              class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none bg-white"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Start Date</label>
            <input
              v-model="startDate"
              type="date"
              class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Target Completion Date</label>
            <input
              v-model="dueDate"
              type="date"
              class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none"
            />
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-bold text-[#1e1e1e] mb-1">Description (Optional)</label>
          <textarea
            v-model="description"
            rows="3"
            placeholder="Brief scope, deliverables, or objectives..."
            class="w-full px-3.5 py-2.5 rounded-xl border border-[#ebe8de] text-xs focus:border-[#640c0e] focus:ring-1 focus:ring-[#640c0e] outline-none resize-none"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="pt-3 border-t border-[#ebe8de] flex items-center justify-end gap-2 shrink-0">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 rounded-xl text-xs font-semibold text-[#6b7280] hover:bg-[#fbfbf9] transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="!canSubmit"
            class="flex items-center gap-1.5 px-5 py-2 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] disabled:opacity-50 transition-all shadow-xs"
          >
            <Loader2 v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
            <span>Create Project</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
