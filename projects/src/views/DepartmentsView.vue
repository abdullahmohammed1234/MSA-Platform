<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { projectsApi } from '../api/projects';
import { useAuthStore } from '../stores/auth';
import {
  Users,
  Lock,
  FolderKanban,
  CheckCircle2,
  Loader2,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const departments = ref<any[]>([]);
const isLoading = ref(true);

const loadDepartments = async () => {
  isLoading.value = true;
  try {
    departments.value = await projectsApi.getDepartments();
  } catch (err) {
    console.error('Failed to load departments:', err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  loadDepartments();
});

const isUserMemberOf = (deptId: number) => {
  return authStore.departments.some((d) => d.id === deptId);
};

const getUserRoleInDept = (deptId: number) => {
  const d = authStore.departments.find((dep) => dep.id === deptId);
  return d?.role || 'Member';
};
</script>

<template>
  <div class="flex flex-col gap-[var(--spacing-section)]">
    <header class="pm-page-header">
      <div class="min-w-0">
        <h1 class="pm-page-title">SFU MSA Canonical Departments</h1>
        <p class="pm-page-subtitle">
          Departmental organization, appointed leadership, and privacy boundaries.
        </p>
      </div>
    </header>

    <!-- Security & Privacy Notice -->
    <div class="p-4 rounded-2xl bg-white border border-[#ebe8de] shadow-xs flex items-start gap-3.5">
      <div class="w-9 h-9 rounded-xl bg-[#640c0e]/10 text-[#640c0e] flex items-center justify-center shrink-0">
        <Lock class="w-5 h-5" />
      </div>
      <div class="text-xs space-y-1">
        <h4 class="font-bold text-[#1e1e1e]">Department Privacy Model</h4>
        <p class="text-[#6b7280] leading-relaxed">
          Projects marked as <span class="font-semibold text-[#640c0e]">Department Private</span> are strictly confined to members of that specific department and explicitly accepted collaborators. Super-admin and executive accounts do not bypass this privacy barrier without formal membership or an invitation.
        </p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="py-24 flex items-center justify-center text-[#6b7280]">
      <Loader2 class="w-8 h-8 animate-spin text-[#640c0e]" />
    </div>

    <!-- Departments Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="dept in departments"
        :key="dept.id"
        class="bg-white rounded-2xl border p-5 shadow-xs flex flex-col justify-between space-y-4 transition-all"
        :class="[
          isUserMemberOf(dept.id)
            ? 'border-[#640c0e]/40 ring-1 ring-[#640c0e]/10'
            : 'border-[#ebe8de] hover:border-[#640c0e]/20',
        ]"
      >
        <div>
          <div class="flex items-center justify-between mb-3">
            <span
              class="text-xs font-bold px-2.5 py-0.5 rounded-full"
              :style="{
                backgroundColor: `${dept.color || '#640c0e'}15`,
                color: dept.color || '#640c0e',
              }"
            >
              {{ dept.code || 'DEPT' }}
            </span>

            <span
              v-if="isUserMemberOf(dept.id)"
              class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1"
            >
              <CheckCircle2 class="w-3 h-3" />
              <span>My Department ({{ getUserRoleInDept(dept.id) }})</span>
            </span>
          </div>

          <h3 class="text-base font-bold text-[#1e1e1e] font-display">
            {{ dept.name }}
          </h3>
          <p class="text-xs text-[#6b7280] mt-1 leading-relaxed">
            {{ dept.description || 'Core Simon Fraser University MSA operational department.' }}
          </p>
        </div>

        <div class="pt-3 border-t border-[#ebe8de] flex items-center justify-between text-xs text-[#6b7280]">
          <span class="flex items-center gap-1">
            <Users class="w-3.5 h-3.5 text-[#6b7280]" />
            <span>{{ dept.members_count || 0 }} appointed members</span>
          </span>
          <span class="flex items-center gap-1">
            <FolderKanban class="w-3.5 h-3.5 text-[#6b7280]" />
            <span>{{ dept.projects_count || 0 }} projects</span>
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
