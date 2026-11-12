<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import client from '@/services/api';
import { useToastStore } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';
import { getPmsLaunchUrl } from '@/config/api';

interface DepartmentMembership {
  id: number;
  department_id: number;
  department_name: string;
  department_role: string;
  status: string;
  start_date: string | null;
  end_date: string | null;
  is_active: boolean;
}

interface UserAccessItem {
  id: number;
  uuid: string;
  name: string;
  email: string;
  avatar?: string;
  has_pms_access: boolean;
  access_status?: 'active' | 'suspended' | 'none';
  access_source: 'privileged' | 'explicit' | 'role' | 'none';
  roles: string[];
  pm_roles: string[];
  department_memberships: DepartmentMembership[];
}

interface DepartmentItem {
  id: number;
  name: string;
  slug: string;
  description: string;
  active_members_count: number;
  lead?: {
    id: number;
    name: string;
    email: string;
  } | null;
}

interface AccessLogItem {
  id: number;
  action: string;
  target_user?: { id: number; name: string; email: string } | null;
  actor?: { id: number; name: string; email: string } | null;
  notes?: string | null;
  previous_state?: any;
  new_state?: any;
  created_at: string;
}

const toast = useToastStore();
const authStore = useAuthStore();
const pmsLaunchUrl = computed(() => getPmsLaunchUrl(authStore.token));

const activeTab = ref<'users' | 'departments' | 'logs' | 'matrix'>('users');

// Users Tab State
const users = ref<UserAccessItem[]>([]);
const search = ref('');
const departmentFilter = ref<number | ''>('');
const accessFilter = ref<'all' | 'granted' | 'suspended' | 'none'>('all');
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const isLoading = ref(false);
const isSaving = ref<Record<number, boolean>>({});

// Departments Tab State
const departments = ref<DepartmentItem[]>([]);
const isDepartmentsLoading = ref(false);

// Logs Tab State
const accessLogs = ref<AccessLogItem[]>([]);
const isLogsLoading = ref(false);
const logsPage = ref(1);
const logsLastPage = ref(1);

// Roles & Permissions Matrix Tab State
const rolesReference = ref<any>(null);
const roleHistoryLogs = ref<any[]>([]);
const isMatrixLoading = ref(false);
const matrixSubTab = ref<'roles' | 'project-roles' | 'permissions' | 'history'>('roles');

// Modals
const showAssignDeptModal = ref(false);
const selectedUserForDept = ref<UserAccessItem | null>(null);
const selectedDeptId = ref<number | ''>('');
const selectedDeptRole = ref<'member' | 'coordinator' | 'lead' | 'guest'>('member');
const isSubmittingDept = ref(false);

const showAssignRoleModal = ref(false);
const selectedUserForRole = ref<UserAccessItem | null>(null);
const selectedPmRole = ref<string>('coordinator');
const roleAction = ref<'attach' | 'detach'>('attach');
const isSubmittingRole = ref(false);

onMounted(async () => {
  await Promise.all([
    fetchUsers(),
    fetchDepartments(),
  ]);
});

const fetchUsers = async (page = 1) => {
  isLoading.value = true;
  try {
    const response = await client.get('/admin/pms/access/users', {
      params: {
        page,
        search: search.value || undefined,
        department_id: departmentFilter.value || undefined,
        access_filter: accessFilter.value !== 'all' ? accessFilter.value : undefined,
      },
    });
    users.value = response.data.users || [];
    currentPage.value = response.data.meta?.current_page || 1;
    lastPage.value = response.data.meta?.last_page || 1;
    total.value = response.data.meta?.total || 0;
  } catch (error) {
    toast.error('Failed to load PMS user access list.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const fetchDepartments = async () => {
  isDepartmentsLoading.value = true;
  try {
    const response = await client.get('/admin/pms/access/departments');
    departments.value = response.data.departments || [];
  } catch (error) {
    console.error('Failed to load departments', error);
  } finally {
    isDepartmentsLoading.value = false;
  }
};

const fetchAccessLogs = async (page = 1) => {
  isLogsLoading.value = true;
  try {
    const response = await client.get('/admin/pms/access/logs', {
      params: { page },
    });
    accessLogs.value = response.data.logs || [];
    logsPage.value = response.data.meta?.current_page || 1;
    logsLastPage.value = response.data.meta?.last_page || 1;
  } catch (error) {
    console.error('Failed to load access logs', error);
  } finally {
    isLogsLoading.value = false;
  }
};

const fetchRolesReference = async () => {
  isMatrixLoading.value = true;
  try {
    const [refRes, histRes] = await Promise.all([
      client.get('/admin/pms/access/roles-reference'),
      client.get('/admin/pms/access/role-history'),
    ]);
    rolesReference.value = refRes.data;
    roleHistoryLogs.value = histRes.data.history || [];
  } catch (error) {
    console.error('Failed to load PMS roles reference', error);
    toast.error('Failed to load PMS roles and permissions reference.');
  } finally {
    isMatrixLoading.value = false;
  }
};

let searchTimeout: any = null;
watch([search, departmentFilter, accessFilter], () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchUsers(1);
  }, 350);
});

watch(activeTab, (tab) => {
  if (tab === 'logs' && accessLogs.value.length === 0) {
    fetchAccessLogs();
  } else if (tab === 'departments' && departments.value.length === 0) {
    fetchDepartments();
  } else if (tab === 'matrix' && !rolesReference.value) {
    fetchRolesReference();
  }
});

const handleGrantAccess = async (user: UserAccessItem) => {
  isSaving.value[user.id] = true;
  try {
    const res = await client.post('/admin/pms/access/grant', {
      user_id: user.id,
      reason: 'Granted via Admin PMS Access Console',
    });
    user.has_pms_access = true;
    user.access_status = 'active';
    user.access_source = 'explicit';
    toast.success(res.data.message || 'PMS access granted successfully.');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to grant PMS access.');
  } finally {
    isSaving.value[user.id] = false;
  }
};

const handleSuspendAccess = async (user: UserAccessItem) => {
  if (!confirm(`Are you sure you want to suspend PMS application access for ${user.name}?`)) return;
  isSaving.value[user.id] = true;
  try {
    const res = await client.post('/admin/pms/access/suspend', {
      user_id: user.id,
      reason: 'Suspended via Admin PMS Access Console',
    });
    user.has_pms_access = false;
    user.access_status = 'suspended';
    toast.success(res.data.message || 'PMS application access suspended.');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to suspend access.');
  } finally {
    isSaving.value[user.id] = false;
  }
};

const handleRestoreAccess = async (user: UserAccessItem) => {
  isSaving.value[user.id] = true;
  try {
    const res = await client.post('/admin/pms/access/restore', {
      user_id: user.id,
      reason: 'Restored via Admin PMS Access Console',
    });
    user.has_pms_access = true;
    user.access_status = 'active';
    user.access_source = 'explicit';
    toast.success(res.data.message || 'PMS application access restored.');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to restore access.');
  } finally {
    isSaving.value[user.id] = false;
  }
};

const handleRevokeAccess = async (user: UserAccessItem) => {
  if (!confirm(`Are you sure you want to revoke PMS access for ${user.name}?`)) return;
  isSaving.value[user.id] = true;
  try {
    const res = await client.post('/admin/pms/access/revoke', {
      user_id: user.id,
      reason: 'Revoked via Admin PMS Access Console',
    });
    user.has_pms_access = false;
    user.access_status = 'none';
    user.access_source = 'none';
    toast.success(res.data.message || 'PMS application access revoked.');
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to revoke PMS access.');
  } finally {
    isSaving.value[user.id] = false;
  }
};

const openAssignDeptModal = (user: UserAccessItem) => {
  selectedUserForDept.value = user;
  selectedDeptId.value = departments.value[0]?.id || '';
  selectedDeptRole.value = 'member';
  showAssignDeptModal.value = true;
};

const handleAssignDepartment = async () => {
  if (!selectedUserForDept.value || !selectedDeptId.value) return;
  isSubmittingDept.value = true;

  try {
    const res = await client.post('/admin/pms/access/departments/assign', {
      user_id: selectedUserForDept.value.id,
      department_id: selectedDeptId.value,
      department_role: selectedDeptRole.value,
    });

    toast.success(res.data.message || 'User successfully assigned to department.');
    showAssignDeptModal.value = false;
    await fetchUsers(currentPage.value);
    await fetchDepartments();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to assign department.');
  } finally {
    isSubmittingDept.value = false;
  }
};

const handleRemoveDepartment = async (user: UserAccessItem, departmentId: number) => {
  if (!confirm('Are you sure you want to remove this user from the department?')) return;

  try {
    const res = await client.post('/admin/pms/access/departments/remove', {
      user_id: user.id,
      department_id: departmentId,
    });

    toast.success(res.data.message || 'User removed from department.');
    await fetchUsers(currentPage.value);
    await fetchDepartments();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to remove from department.');
  }
};

const openAssignRoleModal = (user: UserAccessItem) => {
  selectedUserForRole.value = user;
  selectedPmRole.value = 'coordinator';
  roleAction.value = 'attach';
  showAssignRoleModal.value = true;
};

const handleAssignRole = async () => {
  if (!selectedUserForRole.value) return;
  isSubmittingRole.value = true;

  try {
    const res = await client.post('/admin/pms/access/assign-role', {
      user_id: selectedUserForRole.value.id,
      role: selectedPmRole.value,
      action: roleAction.value,
    });

    toast.success(res.data.message || 'PMS role updated.');
    showAssignRoleModal.value = false;
    await fetchUsers(currentPage.value);
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to update role.');
  } finally {
    isSubmittingRole.value = false;
  }
};

const getSourceBadgeClass = (source: string) => {
  switch (source) {
    case 'privileged':
      return 'bg-purple-100 text-purple-800 border-purple-200';
    case 'role':
      return 'bg-blue-100 text-blue-800 border-blue-200';
    case 'explicit':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    default:
      return 'bg-gray-100 text-gray-500 border-gray-200';
  }
};

const getRoleBadgeClass = (role: string) => {
  switch (role) {
    case 'lead':
      return 'bg-amber-100 text-amber-900 border-amber-300 font-bold';
    case 'coordinator':
      return 'bg-sky-100 text-sky-800 border-sky-200 font-semibold';
    case 'member':
      return 'bg-neutral-background text-neutral-dark border-neutral-ivory';
    default:
      return 'bg-gray-100 text-gray-600 border-gray-200';
  }
};
</script>

<template>
  <div class="p-6 max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-neutral-ivory/80 pb-5">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary uppercase tracking-wider">
            SFU MSA Digital Platform
          </span>
          <span class="text-xs text-neutral-muted">• projects.sfumsa.ca</span>
        </div>
        <h1 class="text-2xl font-bold text-neutral-black mt-1">Project Management System Access</h1>
        <p class="text-sm text-neutral-muted mt-0.5">
          Centralized authority for PMS platform access, canonical department rosters, and role appointments.
        </p>
      </div>

      <div class="flex items-center gap-3">
        <a
          :href="pmsLaunchUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-xs font-semibold hover:bg-primary-hover shadow-sm transition-all"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
          Open PMS Application
        </a>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex border-b border-neutral-ivory gap-6 text-sm font-medium">
      <button
        @click="activeTab = 'users'"
        :class="[
          'pb-3 border-b-2 transition-colors cursor-pointer',
          activeTab === 'users'
            ? 'border-primary text-primary font-bold'
            : 'border-transparent text-neutral-muted hover:text-neutral-black',
        ]"
      >
        Team Access & Roster ({{ total }})
      </button>
      <button
        @click="activeTab = 'departments'"
        :class="[
          'pb-3 border-b-2 transition-colors cursor-pointer',
          activeTab === 'departments'
            ? 'border-primary text-primary font-bold'
            : 'border-transparent text-neutral-muted hover:text-neutral-black',
        ]"
      >
        Canonical Departments ({{ departments.length }})
      </button>
      <button
        @click="activeTab = 'matrix'"
        :class="[
          'pb-3 border-b-2 transition-colors cursor-pointer',
          activeTab === 'matrix'
            ? 'border-primary text-primary font-bold'
            : 'border-transparent text-neutral-muted hover:text-neutral-black',
        ]"
      >
        Roles & Permissions Matrix
      </button>
      <button
        @click="activeTab = 'logs'"
        :class="[
          'pb-3 border-b-2 transition-colors cursor-pointer',
          activeTab === 'logs'
            ? 'border-primary text-primary font-bold'
            : 'border-transparent text-neutral-muted hover:text-neutral-black',
        ]"
      >
        Access Audit Logs
      </button>
    </div>

    <!-- TAB 1: USERS & ACCESS -->
    <div v-if="activeTab === 'users'" class="space-y-4">
      <!-- Filters -->
      <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between bg-white p-4 rounded-2xl border border-neutral-ivory/80 shadow-xs">
        <div class="flex flex-1 items-center gap-3">
          <div class="relative flex-1 max-w-md">
            <input
              v-model="search"
              type="text"
              placeholder="Search by name or email..."
              class="w-full pl-9 pr-4 py-2 rounded-xl text-xs bg-neutral-background border border-neutral-ivory focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition-all text-neutral-black"
            />
            <svg class="w-4 h-4 text-neutral-muted absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>

          <!-- Department Filter -->
          <select
            v-model="departmentFilter"
            class="text-xs px-3 py-2 rounded-xl bg-neutral-background border border-neutral-ivory focus:bg-white focus:outline-none text-neutral-black cursor-pointer"
          >
            <option value="">All Departments</option>
            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>

          <!-- Access Status Filter -->
          <select
            v-model="accessFilter"
            class="text-xs px-3 py-2 rounded-xl bg-neutral-background border border-neutral-ivory focus:bg-white focus:outline-none text-neutral-black cursor-pointer"
          >
            <option value="all">All Access States</option>
            <option value="granted">Active Access Granted</option>
            <option value="suspended">Suspended Access</option>
            <option value="none">No Access</option>
          </select>
        </div>

        <button
          @click="fetchUsers(1)"
          :disabled="isLoading"
          class="px-3.5 py-2 rounded-xl border border-neutral-ivory hover:bg-neutral-background text-xs font-semibold text-neutral-muted transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
        >
          <svg :class="['w-3.5 h-3.5', isLoading ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Refresh
        </button>
      </div>

      <!-- Users Table -->
      <div class="bg-white rounded-2xl border border-neutral-ivory/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-neutral-ivory/80 bg-neutral-background/60 text-[11px] font-bold text-neutral-muted uppercase tracking-wider">
                <th class="px-5 py-3.5">User</th>
                <th class="px-4 py-3.5">PMS Access Status</th>
                <th class="px-4 py-3.5">Departments & Roles</th>
                <th class="px-4 py-3.5">PMS System Roles</th>
                <th class="px-5 py-3.5 text-right">Access Controls</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory/50 text-xs">
              <tr v-if="isLoading && users.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-neutral-muted">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <svg class="w-6 h-6 animate-spin text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Loading team members...</span>
                  </div>
                </td>
              </tr>

              <tr v-else-if="users.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-neutral-muted">
                  No users found matching your search criteria.
                </td>
              </tr>

              <tr v-for="user in users" :key="user.id" class="hover:bg-neutral-background/40 transition-colors">
                <!-- User Column -->
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center border border-primary/20 shrink-0">
                      {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <div class="font-semibold text-neutral-black">{{ user.name }}</div>
                      <div class="text-[11px] text-neutral-muted">{{ user.email }}</div>
                    </div>
                  </div>
                </td>

                <!-- PMS Access Column -->
                <td class="px-4 py-3.5">
                  <div class="flex items-center gap-2">
                    <span
                      v-if="user.access_status === 'suspended'"
                      class="px-2.5 py-1 rounded-full text-[11px] font-semibold border flex items-center gap-1.5 bg-amber-50 text-amber-800 border-amber-200"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                      Suspended
                    </span>
                    <span
                      v-else-if="user.has_pms_access"
                      class="px-2.5 py-1 rounded-full text-[11px] font-semibold border flex items-center gap-1.5 bg-emerald-50 text-emerald-800 border-emerald-200"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                      Active
                    </span>
                    <span
                      v-else
                      class="px-2.5 py-1 rounded-full text-[11px] font-semibold border flex items-center gap-1.5 bg-neutral-background text-neutral-muted border-neutral-ivory"
                    >
                      <span class="w-1.5 h-1.5 rounded-full bg-neutral-muted"></span>
                      No Access
                    </span>
                    <span :class="['text-[9px] px-1.5 py-0.5 rounded border uppercase font-medium', getSourceBadgeClass(user.access_source)]">
                      {{ user.access_source }}
                    </span>
                  </div>
                </td>

                <!-- Department Memberships -->
                <td class="px-4 py-3.5">
                  <div class="flex flex-wrap gap-1.5 items-center">
                    <span
                      v-for="dept in user.department_memberships"
                      :key="dept.id"
                      :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] border', getRoleBadgeClass(dept.department_role)]"
                    >
                      <span>{{ dept.department_name }}:</span>
                      <span class="capitalize font-semibold">{{ dept.department_role }}</span>
                      <button
                        @click="handleRemoveDepartment(user, dept.department_id)"
                        class="text-neutral-muted hover:text-rose-600 transition-colors ml-0.5 cursor-pointer"
                        title="Remove from department"
                      >
                        ×
                      </button>
                    </span>
                    <button
                      @click="openAssignDeptModal(user)"
                      class="text-[10px] text-primary hover:text-primary-hover font-semibold px-1.5 py-0.5 border border-dashed border-primary/40 rounded-md hover:bg-primary/5 transition-all cursor-pointer"
                    >
                      + Dept
                    </button>
                  </div>
                </td>

                <!-- PMS Roles -->
                <td class="px-4 py-3.5">
                  <div class="flex flex-wrap gap-1 items-center">
                    <span
                      v-for="role in user.pm_roles"
                      :key="role"
                      class="px-2 py-0.5 rounded bg-neutral-background border border-neutral-ivory text-[10px] font-mono text-neutral-black"
                    >
                      {{ role }}
                    </span>
                    <span v-if="user.pm_roles.length === 0" class="text-neutral-muted italic text-[11px]">None</span>
                    <button
                      @click="openAssignRoleModal(user)"
                      class="text-[10px] text-neutral-muted hover:text-primary font-semibold px-1 py-0.5 cursor-pointer ml-1"
                      title="Manage PMS roles"
                    >
                      ✎
                    </button>
                  </div>
                </td>

                <!-- Actions -->
                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <!-- Active Access: Suspend or Revoke -->
                    <template v-if="user.has_pms_access && user.access_source !== 'privileged'">
                      <button
                        @click="handleSuspendAccess(user)"
                        :disabled="isSaving[user.id]"
                        class="px-2 py-1 rounded-lg border border-amber-200 bg-amber-50/50 hover:bg-amber-100 text-[11px] font-semibold text-amber-900 transition-colors cursor-pointer"
                        title="Suspend PMS application access"
                      >
                        Suspend
                      </button>
                      <button
                        @click="handleRevokeAccess(user)"
                        :disabled="isSaving[user.id]"
                        class="px-2 py-1 rounded-lg border border-rose-200 bg-rose-50/50 hover:bg-rose-100 text-[11px] font-semibold text-rose-800 transition-colors cursor-pointer"
                        title="Revoke PMS application access"
                      >
                        Revoke
                      </button>
                    </template>

                    <!-- Suspended Access: Restore or Revoke -->
                    <template v-else-if="user.access_status === 'suspended'">
                      <button
                        @click="handleRestoreAccess(user)"
                        :disabled="isSaving[user.id]"
                        class="px-2 py-1 rounded-lg border border-emerald-200 bg-emerald-50/50 hover:bg-emerald-100 text-[11px] font-semibold text-emerald-800 transition-colors cursor-pointer"
                        title="Restore active PMS application access"
                      >
                        Restore
                      </button>
                      <button
                        @click="handleRevokeAccess(user)"
                        :disabled="isSaving[user.id]"
                        class="px-2 py-1 rounded-lg border border-rose-200 bg-rose-50/50 hover:bg-rose-100 text-[11px] font-semibold text-rose-800 transition-colors cursor-pointer"
                        title="Revoke PMS application access"
                      >
                        Revoke
                      </button>
                    </template>

                    <!-- Excluded: Grant Access -->
                    <template v-else-if="user.access_source !== 'privileged'">
                      <button
                        @click="handleGrantAccess(user)"
                        :disabled="isSaving[user.id]"
                        class="px-2.5 py-1 rounded-lg border border-emerald-200 bg-emerald-50/50 hover:bg-emerald-100 text-[11px] font-semibold text-emerald-800 transition-colors cursor-pointer"
                        title="Grant PMS application access"
                      >
                        Grant Access
                      </button>
                    </template>

                    <button
                      @click="openAssignDeptModal(user)"
                      class="px-2 py-1 rounded-lg border border-neutral-ivory hover:bg-neutral-background text-[11px] font-semibold text-neutral-black transition-colors cursor-pointer"
                    >
                      Dept
                    </button>
                    <button
                      @click="openAssignRoleModal(user)"
                      class="px-2 py-1 rounded-lg border border-neutral-ivory hover:bg-neutral-background text-[11px] font-semibold text-neutral-black transition-colors cursor-pointer"
                    >
                      Roles
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="px-5 py-3 border-t border-neutral-ivory/80 flex items-center justify-between text-xs text-neutral-muted">
          <span>Showing page {{ currentPage }} of {{ lastPage }} ({{ total }} total)</span>
          <div class="flex gap-2">
            <button
              @click="fetchUsers(currentPage - 1)"
              :disabled="currentPage === 1 || isLoading"
              class="px-3 py-1 rounded-lg border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer"
            >
              Previous
            </button>
            <button
              @click="fetchUsers(currentPage + 1)"
              :disabled="currentPage === lastPage || isLoading"
              class="px-3 py-1 rounded-lg border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: DEPARTMENTS OVERVIEW -->
    <div v-else-if="activeTab === 'departments'" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="dept in departments"
          :key="dept.id"
          class="bg-white p-5 rounded-2xl border border-neutral-ivory/80 shadow-xs flex flex-col justify-between"
        >
          <div>
            <div class="flex items-start justify-between gap-2">
              <h3 class="text-base font-bold text-neutral-black">{{ dept.name }}</h3>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-neutral-background text-neutral-muted border border-neutral-ivory font-mono">
                /{{ dept.slug }}
              </span>
            </div>
            <p class="text-xs text-neutral-muted mt-2 leading-relaxed">
              {{ dept.description || 'No description provided.' }}
            </p>
          </div>

          <div class="mt-5 pt-4 border-t border-neutral-ivory/60 space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="text-neutral-muted">Department Lead:</span>
              <span class="font-semibold text-neutral-black">
                {{ dept.lead ? dept.lead.name : 'Unassigned' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-neutral-muted">Active Team Members:</span>
              <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary">
                {{ dept.active_members_count }} members
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 3: AUDIT LOGS -->
    <div v-else-if="activeTab === 'logs'" class="space-y-4">
      <div class="bg-white rounded-2xl border border-neutral-ivory/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-neutral-ivory/80 bg-neutral-background/60 text-[11px] font-bold text-neutral-muted uppercase tracking-wider">
                <th class="px-5 py-3.5">Timestamp</th>
                <th class="px-4 py-3.5">Action</th>
                <th class="px-4 py-3.5">Target Member</th>
                <th class="px-4 py-3.5">Admin Actor</th>
                <th class="px-5 py-3.5">Notes & Context</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory/50">
              <tr v-if="isLogsLoading && accessLogs.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-neutral-muted">
                  Loading access logs...
                </td>
              </tr>
              <tr v-else-if="accessLogs.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-neutral-muted">
                  No access modification logs recorded yet.
                </td>
              </tr>
              <tr v-for="log in accessLogs" :key="log.id" class="hover:bg-neutral-background/40 transition-colors">
                <td class="px-5 py-3 text-neutral-muted whitespace-nowrap font-mono text-[11px]">
                  {{ new Date(log.created_at).toLocaleString() }}
                </td>
                <td class="px-4 py-3 font-semibold text-neutral-black capitalize">
                  {{ log.action.replace(/_/g, ' ') }}
                </td>
                <td class="px-4 py-3 text-neutral-black">
                  {{ log.target_user?.name || 'Unknown' }}
                  <span class="text-[10px] text-neutral-muted block">{{ log.target_user?.email }}</span>
                </td>
                <td class="px-4 py-3 text-neutral-black">
                  {{ log.actor?.name || 'System' }}
                </td>
                <td class="px-5 py-3 text-neutral-muted">
                  {{ log.notes || '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 4: ROLES & PERMISSIONS MATRIX -->
    <div v-else-if="activeTab === 'matrix'" class="space-y-5">
      <!-- Sub-navigation -->
      <div class="flex items-center justify-between border-b border-neutral-ivory pb-3">
        <div class="flex gap-2">
          <button
            @click="matrixSubTab = 'roles'"
            :class="[
              'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer',
              matrixSubTab === 'roles' ? 'bg-primary text-white shadow-xs' : 'bg-neutral-background text-neutral-muted hover:text-neutral-black'
            ]"
          >
            Canonical PMS Roles (6)
          </button>
          <button
            @click="matrixSubTab = 'project-roles'"
            :class="[
              'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer',
              matrixSubTab === 'project-roles' ? 'bg-primary text-white shadow-xs' : 'bg-neutral-background text-neutral-muted hover:text-neutral-black'
            ]"
          >
            Project-Level Roles (4)
          </button>
          <button
            @click="matrixSubTab = 'permissions'"
            :class="[
              'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer',
              matrixSubTab === 'permissions' ? 'bg-primary text-white shadow-xs' : 'bg-neutral-background text-neutral-muted hover:text-neutral-black'
            ]"
          >
            Granular Permissions (25)
          </button>
          <button
            @click="matrixSubTab = 'history'"
            :class="[
              'px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer',
              matrixSubTab === 'history' ? 'bg-primary text-white shadow-xs' : 'bg-neutral-background text-neutral-muted hover:text-neutral-black'
            ]"
          >
            Role Assignment History
          </button>
        </div>

        <div class="flex items-center gap-2">
          <router-link
            to="/admin/roles"
            class="text-xs font-semibold text-primary hover:underline flex items-center gap-1"
          >
            Open System Roles &rarr;
          </router-link>
          <router-link
            to="/admin/permissions"
            class="text-xs font-semibold text-primary hover:underline flex items-center gap-1 ml-3"
          >
            Open System Permissions &rarr;
          </router-link>
        </div>
      </div>

      <div v-if="isMatrixLoading" class="py-12 text-center text-neutral-muted">
        <div class="flex flex-col items-center justify-center gap-2">
          <svg class="w-6 h-6 animate-spin text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          <span>Loading authorization matrix and definitions...</span>
        </div>
      </div>

      <!-- SUB-TAB 1: Canonical PMS Roles -->
      <div v-else-if="matrixSubTab === 'roles'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="(role, slug) in (rolesReference?.canonical_pms_roles || {})"
          :key="slug"
          class="bg-white p-5 rounded-2xl border border-neutral-ivory/80 shadow-xs flex flex-col justify-between"
        >
          <div>
            <div class="flex items-start justify-between gap-2">
              <h4 class="text-sm font-bold text-neutral-black">{{ role.name }}</h4>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-neutral-background text-neutral-muted border border-neutral-ivory">
                {{ slug }}
              </span>
            </div>
            <p class="text-xs text-neutral-muted mt-2 leading-relaxed">
              {{ role.description }}
            </p>
          </div>

          <div class="mt-4 pt-3 border-t border-neutral-ivory/60 space-y-2">
            <span class="text-[11px] font-semibold text-neutral-dark block">Assigned Permissions ({{ role.permissions?.length || 0 }}):</span>
            <div class="flex flex-wrap gap-1 max-h-32 overflow-y-auto">
              <span
                v-for="p in role.permissions"
                :key="p"
                class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200 text-[9px] font-mono"
              >
                {{ p }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- SUB-TAB 2: Project-Level Roles -->
      <div v-else-if="matrixSubTab === 'project-roles'" class="space-y-4">
        <div class="bg-white rounded-2xl border border-neutral-ivory/80 shadow-xs overflow-hidden">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-neutral-ivory/80 bg-neutral-background/60 text-[11px] font-bold text-neutral-muted uppercase tracking-wider">
                <th class="px-5 py-3.5">Project Role</th>
                <th class="px-5 py-3.5">Scope & Description</th>
                <th class="px-4 py-3.5 text-center">Update Project</th>
                <th class="px-4 py-3.5 text-center">Delete Project</th>
                <th class="px-4 py-3.5 text-center">Assign Roles</th>
                <th class="px-4 py-3.5 text-center">Manage Tasks</th>
                <th class="px-4 py-3.5 text-center">Comments</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory/50">
              <tr v-for="(prole, rKey) in (rolesReference?.project_level_roles || {})" :key="rKey" class="hover:bg-neutral-background/40">
                <td class="px-5 py-3.5 font-bold text-neutral-black capitalize">
                  {{ prole.name || rKey }}
                </td>
                <td class="px-5 py-3.5 text-neutral-muted leading-relaxed">
                  {{ prole.description }}
                </td>
                <td class="px-4 py-3.5 text-center font-bold">
                  <span :class="prole.can_update_project ? 'text-emerald-600' : 'text-neutral-300'">
                    {{ prole.can_update_project ? '✓' : '—' }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-center font-bold">
                  <span :class="prole.can_delete_project ? 'text-emerald-600' : 'text-neutral-300'">
                    {{ prole.can_delete_project ? '✓' : '—' }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-center font-bold">
                  <span :class="prole.can_assign_roles ? 'text-emerald-600' : 'text-neutral-300'">
                    {{ prole.can_assign_roles ? '✓' : '—' }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-center font-bold">
                  <span :class="prole.can_manage_tasks ? 'text-emerald-600' : 'text-neutral-300'">
                    {{ prole.can_manage_tasks ? '✓' : '—' }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-center font-bold">
                  <span :class="prole.can_comment ? 'text-emerald-600' : 'text-neutral-300'">
                    {{ prole.can_comment ? '✓' : '—' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- SUB-TAB 3: Granular Permissions -->
      <div v-else-if="matrixSubTab === 'permissions'" class="space-y-4">
        <div class="bg-white rounded-2xl border border-neutral-ivory/80 shadow-xs overflow-hidden">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-neutral-ivory/80 bg-neutral-background/60 text-[11px] font-bold text-neutral-muted uppercase tracking-wider">
                <th class="px-5 py-3.5">Permission Identifier (Slug)</th>
                <th class="px-5 py-3.5">Display Name</th>
                <th class="px-5 py-3.5">Module Scope</th>
                <th class="px-5 py-3.5">Description & Enforcement</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory/50">
              <tr v-for="(pdef, pslug) in (rolesReference?.pms_permissions || {})" :key="pslug" class="hover:bg-neutral-background/40">
                <td class="px-5 py-3 font-mono text-[11px] font-semibold text-primary">
                  {{ pslug }}
                </td>
                <td class="px-5 py-3 font-semibold text-neutral-black">
                  {{ pdef.name }}
                </td>
                <td class="px-5 py-3 text-neutral-muted uppercase font-bold text-[10px]">
                  {{ pdef.module || 'PMS' }}
                </td>
                <td class="px-5 py-3 text-neutral-muted">
                  {{ pdef.description }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- SUB-TAB 4: Role Assignment History -->
      <div v-else-if="matrixSubTab === 'history'" class="space-y-4">
        <div class="bg-white rounded-2xl border border-neutral-ivory/80 shadow-xs overflow-hidden">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-neutral-ivory/80 bg-neutral-background/60 text-[11px] font-bold text-neutral-muted uppercase tracking-wider">
                <th class="px-5 py-3.5">Date & Time</th>
                <th class="px-4 py-3.5">Operation</th>
                <th class="px-4 py-3.5">Target User</th>
                <th class="px-4 py-3.5">Admin Actor</th>
                <th class="px-5 py-3.5">State Changes & Notes</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory/50">
              <tr v-if="roleHistoryLogs.length === 0">
                <td colspan="5" class="px-6 py-12 text-center text-neutral-muted">
                  No PMS role assignment history recorded yet.
                </td>
              </tr>
              <tr v-for="h in roleHistoryLogs" :key="h.id" class="hover:bg-neutral-background/40">
                <td class="px-5 py-3 text-neutral-muted whitespace-nowrap font-mono text-[11px]">
                  {{ new Date(h.created_at).toLocaleString() }}
                </td>
                <td class="px-4 py-3 font-semibold text-neutral-black capitalize">
                  {{ h.action.replace(/_/g, ' ') }}
                </td>
                <td class="px-4 py-3 text-neutral-black">
                  {{ h.target_user?.name || 'Unknown' }}
                  <span class="text-[10px] text-neutral-muted block">{{ h.target_user?.email }}</span>
                </td>
                <td class="px-4 py-3 text-neutral-black">
                  {{ h.actor?.name || 'System' }}
                </td>
                <td class="px-5 py-3 text-neutral-muted">
                  {{ h.notes || '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MODAL: Assign Department -->
    <div
      v-if="showAssignDeptModal"
      class="fixed inset-0 z-50 bg-neutral-black/40 backdrop-blur-xs flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl border border-neutral-ivory p-6 max-w-md w-full shadow-lg space-y-4">
        <div>
          <h3 class="text-lg font-bold text-neutral-black">Assign to Department</h3>
          <p class="text-xs text-neutral-muted mt-1">
            Assign <span class="font-semibold text-neutral-black">{{ selectedUserForDept?.name }}</span> to an MSA department roster.
          </p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-neutral-dark mb-1">Select Department</label>
            <select
              v-model="selectedDeptId"
              class="w-full text-xs px-3 py-2 rounded-xl bg-neutral-background border border-neutral-ivory focus:bg-white text-neutral-black"
            >
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-neutral-dark mb-1">Department Role</label>
            <select
              v-model="selectedDeptRole"
              class="w-full text-xs px-3 py-2 rounded-xl bg-neutral-background border border-neutral-ivory focus:bg-white text-neutral-black"
            >
              <option value="member">Department Member</option>
              <option value="coordinator">Project Coordinator</option>
              <option value="lead">Department Lead</option>
              <option value="guest">Guest / Observer</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-neutral-ivory">
          <button
            @click="showAssignDeptModal = false"
            class="px-4 py-2 rounded-xl border border-neutral-ivory text-xs font-semibold text-neutral-muted hover:bg-neutral-background transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="handleAssignDepartment"
            :disabled="isSubmittingDept"
            class="px-4 py-2 rounded-xl bg-primary text-white text-xs font-semibold hover:bg-primary-hover disabled:opacity-50 transition-colors cursor-pointer"
          >
            {{ isSubmittingDept ? 'Assigning...' : 'Confirm Assignment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL: Assign PMS Role -->
    <div
      v-if="showAssignRoleModal"
      class="fixed inset-0 z-50 bg-neutral-black/40 backdrop-blur-xs flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl border border-neutral-ivory p-6 max-w-md w-full shadow-lg space-y-4">
        <div>
          <h3 class="text-lg font-bold text-neutral-black">Manage PMS System Role</h3>
          <p class="text-xs text-neutral-muted mt-1">
            Configure canonical PMS role appointment for <span class="font-semibold text-neutral-black">{{ selectedUserForRole?.name }}</span>.
          </p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-neutral-dark mb-1">Canonical PMS Role</label>
            <select
              v-model="selectedPmRole"
              class="w-full text-xs px-3 py-2 rounded-xl bg-neutral-background border border-neutral-ivory focus:bg-white text-neutral-black"
            >
              <option value="super-admin">Super Admin (super-admin)</option>
              <option value="president">MSA President (president)</option>
              <option value="executive">MSA Executive (executive)</option>
              <option value="department-lead">Department Lead (department-lead)</option>
              <option value="coordinator">Coordinator (coordinator)</option>
              <option value="team-member">Team Member (team-member)</option>
              <option value="pms-administrator">PMS Administrator (alias)</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-semibold text-neutral-dark mb-1">Action</label>
            <div class="flex gap-3">
              <label class="inline-flex items-center gap-1.5 text-xs text-neutral-black cursor-pointer">
                <input type="radio" v-model="roleAction" value="attach" class="text-primary focus:ring-0" />
                <span>Grant / Attach Role</span>
              </label>
              <label class="inline-flex items-center gap-1.5 text-xs text-neutral-black cursor-pointer">
                <input type="radio" v-model="roleAction" value="detach" class="text-primary focus:ring-0" />
                <span>Revoke / Detach Role</span>
              </label>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-neutral-ivory">
          <button
            @click="showAssignRoleModal = false"
            class="px-4 py-2 rounded-xl border border-neutral-ivory text-xs font-semibold text-neutral-muted hover:bg-neutral-background transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="handleAssignRole"
            :disabled="isSubmittingRole"
            class="px-4 py-2 rounded-xl bg-primary text-white text-xs font-semibold hover:bg-primary-hover disabled:opacity-50 transition-colors cursor-pointer"
          >
            {{ isSubmittingRole ? 'Saving...' : 'Update Role' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
