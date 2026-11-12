<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { projectsApi } from '../api/projects';
import { useDepartmentsStore } from '../stores/departments';
import type { MemberWorkload } from '../types/pm';
import WorkloadHeatmap from '../components/workload/WorkloadHeatmap.vue';
import UserCapacityModal from '../components/workload/UserCapacityModal.vue';
import { Users, Building2, Loader2 } from 'lucide-vue-next';

const departmentsStore = useDepartmentsStore();

const selectedDepartmentId = ref<number | null>(null);
const startDate = ref<string>(new Date().toISOString().split('T')[0]);
const endDate = ref<string>(new Date(Date.now() + 28 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);

const loading = ref(false);
const error = ref<string | null>(null);
const departmentWorkloadData = ref<MemberWorkload[]>([]);

const showCapacityModal = ref(false);
const selectedMember = ref<MemberWorkload | null>(null);

const departments = computed(() => departmentsStore.departments);

const loadDepartmentWorkload = async () => {
  if (!selectedDepartmentId.value) return;
  loading.value = true;
  error.value = null;

  try {
    const res = await projectsApi.getDepartmentWorkload(
      selectedDepartmentId.value,
      startDate.value,
      endDate.value
    );
    departmentWorkloadData.value = res.members_workload || [];
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to load department workload.';
    departmentWorkloadData.value = [];
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  if (departments.value.length === 0) {
    await departmentsStore.loadDepartments();
  }
  if (departments.value.length > 0) {
    selectedDepartmentId.value = departments.value[0].id;
    await loadDepartmentWorkload();
  }
});

const onDepartmentChange = () => {
  loadDepartmentWorkload();
};

const openCapacityModal = (member: MemberWorkload) => {
  selectedMember.value = member;
  showCapacityModal.value = true;
};
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-2xl border border-[#ebe8de] p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-extrabold text-[#1e1e1e] font-display flex items-center gap-2.5">
          <Users class="w-6 h-6 text-[#640c0e]" />
          <span>Resource & Capacity Balancing</span>
        </h1>
        <p class="text-xs text-[#6b7280] mt-1">
          Monitor volunteer effort, prevent contributor overload, and rebalance project commitments.
        </p>
      </div>

      <!-- Controls -->
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Department Selector -->
        <div class="flex items-center gap-1.5 bg-[#fbfbf9] px-3 py-1.5 rounded-xl border border-[#ebe8de]">
          <Building2 class="w-4 h-4 text-[#6b7280]" />
          <select
            v-model="selectedDepartmentId"
            class="bg-transparent text-xs font-semibold text-[#1e1e1e] outline-none cursor-pointer"
            @change="onDepartmentChange"
          >
            <option v-for="dept in departments" :key="dept.id" :value="dept.id">
              {{ dept.name }}
            </option>
          </select>
        </div>

        <button
          type="button"
          class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-[#640c0e] text-white hover:bg-[#7e1012] shadow-xs transition-colors"
          @click="loadDepartmentWorkload"
        >
          Refresh
        </button>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-if="error"
      class="p-4 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700"
    >
      {{ error }}
    </div>

    <!-- Loading State -->
    <div v-else-if="loading" class="py-24 flex items-center justify-center text-[#6b7280]">
      <Loader2 class="w-8 h-8 animate-spin text-[#640c0e] mr-3" />
      <span class="text-xs font-medium">Computing team capacity and commitments...</span>
    </div>

    <!-- Heatmap & Member Roster -->
    <div v-else class="space-y-6">
      <WorkloadHeatmap
        :team-workload="departmentWorkloadData"
        :start-date="startDate"
        :end-date="endDate"
        @edit-capacity="openCapacityModal"
      />
    </div>

    <!-- Capacity Modal -->
    <UserCapacityModal
      :user-id="selectedMember?.user.id || null"
      :user-name="selectedMember?.user.name || ''"
      :is-open="showCapacityModal"
      @close="showCapacityModal = false"
      @saved="loadDepartmentWorkload"
    />
  </div>
</template>

