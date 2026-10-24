<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { rolePermissionService } from '@/services/admin/rolePermission.service';
import client from '@/services/api';
import type { Permission, AuditLog, Role } from '@/types/auth';
import { useToastStore } from '@/components/feedback/toast';
import { Button } from '@/components/ui/button';
import { useAuthorization } from '@/composables/auth/useAuthorization';
import {
  PERMISSION_SYSTEMS,
  classifyPermissionSystem,
  filterPermissionsByGroup,
} from '@/utils/permissionSystems';

interface UserItem {
  id: number;
  uuid: string;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
}

const toast = useToastStore();
const { can } = useAuthorization();

const permissions = ref<Permission[]>([]);
const roles = ref<Role[]>([]);
const users = ref<UserItem[]>([]);
const auditLogs = ref<AuditLog[]>([]);
const auditLogsTotal = ref(0);
const auditLogsPage = ref(1);
const auditLogsLastPage = ref(1);

const isLoading = ref(false);
const activeTab = ref('users'); // 'users' | 'directory' | 'audit'

// User search & selection
const userSearchQuery = ref('');
const selectedUserUuid = ref('');
const userRoles = ref<string[]>([]);
const userPermissions = ref<string[]>([]);

// Direct Permissions Group & Search Filtering (Tab 1)
const selectedPermissionGroup = ref('all');
const directPermissionSearchQuery = ref('');

// Permission Directory search & filter (Tab 2)
const directorySearchQuery = ref('');
const directoryModuleFilter = ref('all');

// Audit Log search & filter
const auditLogActionFilter = ref('all');

const selectedUser = computed(() => {
  return users.value.find((u) => u.uuid === selectedUserUuid.value) || null;
});

const filteredUsers = computed(() => {
  if (!userSearchQuery.value.trim()) return users.value;
  const q = userSearchQuery.value.toLowerCase().trim();
  return users.value.filter(
    (u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
  );
});

onMounted(async () => {
  await loadBaseData();
});

const loadBaseData = async () => {
  isLoading.value = true;
  try {
    const permsData = await rolePermissionService.getPermissions();
    permissions.value = permsData.permissions;

    const rolesData = await rolePermissionService.getRoles();
    roles.value = rolesData.roles;

    const usersResponse = await client.get('/admin/users');
    users.value = usersResponse.data.users || [];

    await loadAuditLogs();
  } catch (error) {
    toast.error('Failed to load authorization data.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const loadAuditLogs = async (page = 1) => {
  try {
    const logsData = await rolePermissionService.getAuditLogs(page);
    auditLogs.value = logsData.audit_logs.data;
    auditLogsTotal.value = logsData.audit_logs.total;
    auditLogsPage.value = logsData.audit_logs.current_page;
    auditLogsLastPage.value = logsData.audit_logs.last_page;
  } catch (error) {
    console.error('Failed to load audit logs', error);
  }
};

const handleSelectUser = () => {
  if (selectedUser.value) {
    userRoles.value = [...selectedUser.value.roles];
    userPermissions.value = [...selectedUser.value.permissions];
  } else {
    userRoles.value = [];
    userPermissions.value = [];
  }
};

const handleUpdateRoles = async () => {
  if (!selectedUserUuid.value) return;
  isLoading.value = true;
  try {
    await rolePermissionService.assignUserRoles(selectedUserUuid.value, userRoles.value);
    toast.success('User roles updated successfully.');
    await loadBaseData();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to update user roles.');
  } finally {
    isLoading.value = false;
  }
};

const handleUpdatePermissions = async () => {
  if (!selectedUserUuid.value) return;
  isLoading.value = true;
  try {
    await rolePermissionService.assignUserPermissions(selectedUserUuid.value, userPermissions.value);
    toast.success('User direct permissions updated successfully.');
    await loadBaseData();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to update direct permissions.');
  } finally {
    isLoading.value = false;
  }
};

const toggleUserRole = (slug: string) => {
  const index = userRoles.value.indexOf(slug);
  if (index > -1) {
    userRoles.value.splice(index, 1);
  } else {
    userRoles.value.push(slug);
  }
};

const toggleUserPermission = (slug: string) => {
  const index = userPermissions.value.indexOf(slug);
  if (index > -1) {
    userPermissions.value.splice(index, 1);
  } else {
    userPermissions.value.push(slug);
  }
};

const modulesList = computed(() => {
  const mods = new Set<string>();
  permissions.value.forEach((p) => {
    if (p.module) mods.add(p.module);
  });
  return Array.from(mods).sort();
});

// System-based Direct Permissions Grouping & Filtering (Tab 1)
const availablePermissionGroups = computed(() => {
  const counts: Record<string, number> = {};
  PERMISSION_SYSTEMS.forEach((g) => {
    counts[g.id] = 0;
  });

  permissions.value.forEach((p) => {
    const sysId = classifyPermissionSystem(p);
    counts[sysId] = (counts[sysId] || 0) + 1;
  });

  // Always include 'all' and 'general', plus any system group with at least 1 permission
  return PERMISSION_SYSTEMS.filter((g) => {
    if (g.id === 'all' || g.id === 'general') return true;
    return (counts[g.id] || 0) > 0;
  });
});

const getGroupPermissionCount = (groupId: string): number => {
  if (groupId === 'all') return permissions.value.length;
  return permissions.value.filter((p) => classifyPermissionSystem(p) === groupId).length;
};

const currentGroupName = computed(() => {
  const group = PERMISSION_SYSTEMS.find((g) => g.id === selectedPermissionGroup.value);
  return group ? group.shortName : 'System';
});

const currentGroupDescription = computed(() => {
  const group = PERMISSION_SYSTEMS.find((g) => g.id === selectedPermissionGroup.value);
  return group ? group.description : '';
});

const filteredDirectPermissions = computed(() => {
  return filterPermissionsByGroup(
    permissions.value,
    selectedPermissionGroup.value,
    directPermissionSearchQuery.value
  );
});

const selectedGroupAssignedCount = computed(() => {
  const groupPerms = filterPermissionsByGroup(permissions.value, selectedPermissionGroup.value, '');
  return groupPerms.filter((p) => userPermissions.value.includes(p.slug)).length;
});

const groupedDirectPermissions = computed(() => {
  const groups: Record<string, { label: string; permissions: Permission[] }> = {};
  const perms = filteredDirectPermissions.value;

  perms.forEach((p) => {
    const sysId = classifyPermissionSystem(p);
    const sysDef = PERMISSION_SYSTEMS.find((g) => g.id === sysId);
    const label = sysDef ? sysDef.name : sysId.toUpperCase();

    if (!groups[sysId]) {
      groups[sysId] = {
        label,
        permissions: [],
      };
    }
    groups[sysId].permissions.push(p);
  });

  return groups;
});

// Group permissions by module with optional search filtering (Tab 2: Directory)
const groupedPermissions = computed(() => {
  const groups: Record<string, Permission[]> = {};
  const query = directorySearchQuery.value.toLowerCase().trim();

  permissions.value.forEach((p) => {
    if (
      directoryModuleFilter.value !== 'all' &&
      p.module !== directoryModuleFilter.value
    ) {
      return;
    }

    if (
      query &&
      !p.name.toLowerCase().includes(query) &&
      !p.slug.toLowerCase().includes(query) &&
      !(p.description && p.description.toLowerCase().includes(query))
    ) {
      return;
    }

    if (!groups[p.module]) {
      groups[p.module] = [];
    }
    groups[p.module].push(p);
  });
  return groups;
});

const filteredAuditLogs = computed(() => {
  if (auditLogActionFilter.value === 'all') return auditLogs.value;
  return auditLogs.value.filter((log) => log.action === auditLogActionFilter.value);
});

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getActionClass = (action: string) => {
  const mapping: Record<string, string> = {
    role_assigned: 'bg-secondary/10 text-secondary border border-secondary/20',
    role_removed: 'bg-red-50 text-red-600 border border-red-200',
    roles_synced: 'bg-primary/5 text-primary border border-blue-200',
    permission_granted: 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    permission_revoked: 'bg-amber-50 text-amber-600 border border-amber-200',
    user_permissions_synced: 'bg-purple-50 text-purple-600 border border-purple-200',
    role_created: 'bg-emerald-50 text-emerald-600 border border-emerald-200',
    role_deleted: 'bg-rose-50 text-rose-600 border border-rose-200',
    grant_application_access: 'bg-blue-50 text-blue-600 border border-blue-200',
    revoke_application_access: 'bg-amber-50 text-amber-600 border border-amber-200',
  };
  return mapping[action] || 'bg-neutral-50 text-neutral-600 border border-neutral-200';
};
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-medium text-primary">Permissions & Security</h1>
      <p class="text-sm text-neutral-muted mt-1">Assign direct capabilities, inspect the platform permission catalog, and review audit logs.</p>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-neutral-ivory/50 flex gap-6">
      <button
        @click="activeTab = 'users'"
        :class="[activeTab === 'users' ? 'border-primary text-primary border-b-2 font-semibold' : 'text-neutral-muted border-b-2 border-transparent hover:text-primary transition-colors', 'pb-3 text-sm cursor-pointer']"
      >
        User Assignments
      </button>
      <button
        @click="activeTab = 'directory'"
        :class="[activeTab === 'directory' ? 'border-primary text-primary border-b-2 font-semibold' : 'text-neutral-muted border-b-2 border-transparent hover:text-primary transition-colors', 'pb-3 text-sm cursor-pointer']"
      >
        Permission Directory
      </button>
      <button
        @click="activeTab = 'audit'"
        :class="[activeTab === 'audit' ? 'border-primary text-primary border-b-2 font-semibold' : 'text-neutral-muted border-b-2 border-transparent hover:text-primary transition-colors', 'pb-3 text-sm cursor-pointer']"
      >
        Security Audit Logs
      </button>
    </div>

    <!-- Tab 1: User Assignments -->
    <div v-if="activeTab === 'users'" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start animate-fade-in">
      <!-- User Selector Panel -->
      <div class="lg:col-span-4 bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft space-y-4">
        <h2 class="text-base font-bold text-neutral-black">Select User Account</h2>
        
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1.5">Filter User Roster</label>
          <input
            v-model="userSearchQuery"
            type="text"
            placeholder="Search by name or email..."
            class="w-full p-2.5 text-xs rounded-xl border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 mb-2"
          />

          <select
            v-model="selectedUserUuid"
            @change="handleSelectUser"
            class="w-full p-2.5 text-sm rounded-xl border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 cursor-pointer"
          >
            <option value="">-- Choose User ({{ filteredUsers.length }}) --</option>
            <option v-for="user in filteredUsers" :key="user.uuid" :value="user.uuid">
              {{ user.name }} ({{ user.email }})
            </option>
          </select>
        </div>

        <!-- Selected User Quick Info -->
        <div v-if="selectedUser" class="pt-4 border-t border-neutral-ivory/50 space-y-4">
          <div>
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Active Roles</h4>
            <div class="flex flex-wrap gap-1 mt-1.5">
              <span
                v-for="roleSlug in selectedUser.roles"
                :key="roleSlug"
                class="text-[10px] bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded-full font-mono font-semibold"
              >
                {{ roleSlug }}
              </span>
              <span v-if="selectedUser.roles.length === 0" class="text-xs text-neutral-muted italic">None</span>
            </div>
          </div>

          <div>
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Permissions Overview</h4>
            <p class="text-xs text-neutral-muted mt-1 leading-relaxed">
              Granted <span class="font-bold text-neutral-black">{{ selectedUser.permissions.length }}</span> total system capabilities via assigned roles or direct grants.
            </p>
          </div>
        </div>
      </div>

      <!-- Roles & Permissions Assignments Forms -->
      <div class="lg:col-span-8 space-y-6" v-if="selectedUser">
        <!-- 1. Roles Assignment Card -->
        <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <div class="flex justify-between items-center mb-6">
            <div>
              <h2 class="text-base font-bold text-neutral-black">Assign Roles to {{ selectedUser.name }}</h2>
              <p class="text-xs text-neutral-muted mt-0.5">Role changes update the inherited capability baseline for this account.</p>
            </div>
            <Button
              v-if="can('manage_users')"
              variant="primary"
              size="sm"
              @click="handleUpdateRoles"
              :isLoading="isLoading"
            >
              Save Roles
            </Button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label
              v-for="role in roles"
              :key="role.uuid"
              class="flex items-start gap-3 p-3 rounded-xl border border-neutral-ivory/50 hover:bg-neutral-background cursor-pointer select-none transition-all"
              :class="{ 'border-primary/30 bg-primary/5': userRoles.includes(role.slug) }"
            >
              <input
                type="checkbox"
                :checked="userRoles.includes(role.slug)"
                @change="toggleUserRole(role.slug)"
                class="mt-0.5 h-4 w-4 rounded border-neutral-ivory text-primary focus:ring-primary focus:ring-offset-0 cursor-pointer"
                :disabled="role.slug === 'super-admin' && !can('manage_roles')"
              />
              <div>
                <span class="text-xs font-semibold text-neutral-black">{{ role.name }}</span>
                <p class="text-[10px] text-neutral-muted mt-0.5 leading-relaxed">{{ role.description }}</p>
              </div>
            </label>
          </div>
        </div>

        <!-- 2. Direct Permissions Assignment Card -->
        <div class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <div class="flex justify-between items-center mb-6">
            <div>
              <h2 class="text-base font-bold text-neutral-black">Assign Direct Permissions</h2>
              <p class="text-xs text-neutral-muted mt-0.5">Use direct permissions to grant ad-hoc access without altering roles.</p>
            </div>
            <Button
              v-if="can('manage_users')"
              variant="outline"
              size="sm"
              @click="handleUpdatePermissions"
              :isLoading="isLoading"
            >
              Save Direct Permissions
            </Button>
          </div>

          <!-- Assigned Permissions Group Selector & Scoped Search -->
          <div class="mb-5 space-y-2.5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 bg-neutral-background/70 border border-neutral-ivory/80 rounded-xl">
              <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <label for="assigned-permissions-group-select" class="text-xs font-bold text-neutral-black whitespace-nowrap">
                  Assigned Permissions Group:
                </label>
                <select
                  id="assigned-permissions-group-select"
                  v-model="selectedPermissionGroup"
                  class="p-2 text-xs font-semibold rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-white cursor-pointer shadow-2xs text-neutral-800"
                >
                  <option v-for="group in availablePermissionGroups" :key="group.id" :value="group.id">
                    {{ group.name }} ({{ getGroupPermissionCount(group.id) }})
                  </option>
                </select>
              </div>

              <!-- Scoped Search within Selected Group -->
              <div class="relative w-full sm:w-56">
                <input
                  v-model="directPermissionSearchQuery"
                  type="text"
                  :placeholder="`Search ${currentGroupName}...`"
                  class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-white"
                />
                <span class="absolute left-2.5 top-2 text-neutral-muted">
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </span>
              </div>
            </div>

            <!-- Group Description & Assignment Counter -->
            <div class="flex items-center justify-between text-[11px] text-neutral-muted px-1">
              <span class="truncate max-w-md">{{ currentGroupDescription }}</span>
              <span class="font-semibold text-neutral-700 whitespace-nowrap shrink-0 ml-2">
                {{ selectedGroupAssignedCount }} assigned in this view
              </span>
            </div>
          </div>

          <!-- Permissions List by System -->
          <div class="space-y-6 max-h-[380px] overflow-y-auto pr-2">
            <!-- Empty state when no permissions match the selected group/filter -->
            <div
              v-if="filteredDirectPermissions.length === 0"
              class="py-12 text-center text-xs text-neutral-muted border border-neutral-ivory/50 border-dashed rounded-xl p-6"
            >
              <p class="font-semibold text-neutral-700 mb-1">No permissions found</p>
              <p>There are no capabilities matching this system group or filter criteria.</p>
            </div>

            <div v-for="(group, sysId) in groupedDirectPermissions" :key="sysId" class="space-y-2">
              <div class="text-xs font-bold text-neutral-black border-b border-neutral-ivory/30 pb-1 flex items-center justify-between">
                <span>{{ group.label }}</span>
                <span class="text-[10px] font-medium text-neutral-500">{{ group.permissions.length }} permissions</span>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pl-1">
                <label
                  v-for="perm in group.permissions"
                  :key="perm.uuid"
                  class="flex items-start gap-2.5 p-2 rounded-xl hover:bg-neutral-background cursor-pointer select-none transition-colors border border-neutral-ivory/30"
                  :class="{ 'border-primary/20 bg-primary/5': userPermissions.includes(perm.slug) }"
                >
                  <input
                    type="checkbox"
                    :checked="userPermissions.includes(perm.slug)"
                    @change="toggleUserPermission(perm.slug)"
                    class="mt-0.5 h-4 w-4 rounded border-neutral-ivory text-primary focus:ring-primary focus:ring-offset-0 cursor-pointer"
                  />
                  <div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="text-xs font-semibold text-neutral-black">{{ perm.name }}</span>
                      <span class="text-[9.5px] px-1.5 py-0.2 rounded font-mono font-medium bg-neutral-100 text-neutral-600">
                        {{ perm.slug }}
                      </span>
                    </div>
                    <p class="text-[10px] text-neutral-muted leading-relaxed mt-0.5">{{ perm.description }}</p>
                  </div>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="lg:col-span-8 flex flex-col items-center justify-center py-20 bg-white border border-neutral-ivory border-dashed rounded-2xl shadow-soft">
        <svg class="h-12 w-12 text-neutral-muted mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <h3 class="font-bold text-neutral-black mb-1">No User Selected</h3>
        <p class="text-xs text-neutral-muted max-w-sm text-center">Choose a user from the dropdown menu to inspect and manage system capabilities.</p>
      </div>
    </div>

    <!-- Tab 2: Permission Directory -->
    <div v-if="activeTab === 'directory'" class="space-y-6 animate-fade-in">
      <!-- Search & Filters for Directory -->
      <div class="bg-white border border-neutral-ivory p-4 rounded-2xl shadow-soft flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full max-w-2xl">
          <div class="relative max-w-xs w-full">
            <input
              v-model="directorySearchQuery"
              type="text"
              placeholder="Search permission slug or name..."
              class="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40"
            />
            <span class="absolute left-3 top-2.5 text-neutral-muted">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
          </div>

          <div class="flex items-center gap-1 bg-neutral-background p-1 rounded-xl border border-neutral-ivory overflow-x-auto">
            <button
              type="button"
              @click="directoryModuleFilter = 'all'"
              :class="[
                'px-2.5 py-1 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap',
                directoryModuleFilter === 'all'
                  ? 'bg-white text-primary shadow-xs border border-neutral-ivory/60 font-bold'
                  : 'text-neutral-muted hover:text-primary'
              ]"
            >
              All Modules
            </button>
            <button
              type="button"
              v-for="mod in modulesList"
              :key="mod"
              @click="directoryModuleFilter = mod"
              :class="[
                'px-2.5 py-1 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap',
                directoryModuleFilter === mod
                  ? 'bg-white text-primary shadow-xs border border-neutral-ivory/60 font-bold'
                  : 'text-neutral-muted hover:text-primary'
              ]"
            >
              {{ mod }}
            </button>
          </div>
        </div>

        <div class="text-xs text-neutral-muted whitespace-nowrap">
          Total: <span class="font-bold text-neutral-black">{{ permissions.length }}</span> permissions registered
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div v-for="(perms, moduleName) in groupedPermissions" :key="moduleName" class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <div class="flex items-center justify-between border-b border-neutral-ivory/50 pb-3 mb-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-primary">
              {{ moduleName }} Module
            </h2>
            <span class="text-xs font-semibold text-neutral-muted bg-neutral-background px-2.5 py-1 rounded-full border border-neutral-ivory">
              {{ perms.length }} permissions
            </span>
          </div>
          
          <div class="divide-y divide-neutral-ivory/30 space-y-3">
            <div v-for="perm in perms" :key="perm.uuid" class="pt-3 first:pt-0 flex justify-between items-start gap-4">
              <div>
                <h3 class="text-xs font-bold text-neutral-black">{{ perm.name }}</h3>
                <p class="text-[11px] text-neutral-muted mt-0.5 leading-relaxed">{{ perm.description }}</p>
              </div>
              <span class="text-[9px] font-mono bg-neutral-background px-2 py-0.5 rounded border border-neutral-ivory/50 whitespace-nowrap text-neutral-muted select-all">
                {{ perm.slug }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 3: Security Audit Logs -->
    <div v-if="activeTab === 'audit'" class="bg-white border border-neutral-ivory rounded-2xl shadow-soft overflow-hidden animate-fade-in">
      <div class="px-6 py-4 border-b border-neutral-ivory/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h2 class="text-base font-bold text-neutral-black">Authorization Log Trail</h2>
          <p class="text-xs text-neutral-muted">Immutable log of role changes, permission grants, and application access updates.</p>
        </div>
        <span class="text-xs font-semibold text-primary bg-primary/10 px-3 py-1 rounded-full">
          {{ auditLogsTotal }} entries logged
        </span>
      </div>

      <!-- Logs Table -->
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left">
          <thead>
            <tr class="bg-neutral-background/50 border-b border-neutral-ivory/50 text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
              <th class="px-6 py-3">Timestamp</th>
              <th class="px-6 py-3">Actor</th>
              <th class="px-6 py-3">Event Action</th>
              <th class="px-6 py-3">Description</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory/30 text-xs">
            <tr v-for="log in filteredAuditLogs" :key="log.id" class="hover:bg-neutral-background/30 transition-colors">
              <td class="px-6 py-4 whitespace-nowrap text-neutral-muted font-mono">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="log.user" class="font-semibold text-neutral-black">{{ log.user.name }}</span>
                <span v-else class="text-neutral-muted italic">System Process</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="['text-[10px] px-2 py-0.5 rounded-full font-semibold font-mono border', getActionClass(log.action)]">
                  {{ log.action }}
                </span>
              </td>
              <td class="px-6 py-4 text-neutral-muted max-w-sm truncate" :title="log.description || ''">
                {{ log.description }}
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-if="filteredAuditLogs.length === 0">
              <td colspan="4" class="px-6 py-12 text-center text-neutral-muted italic">
                No security audit logs match the current filter.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="px-6 py-4 border-t border-neutral-ivory/50 flex justify-between items-center text-xs" v-if="auditLogsLastPage > 1">
        <span class="text-neutral-muted">Page {{ auditLogsPage }} of {{ auditLogsLastPage }}</span>
        <div class="flex gap-2">
          <button
            @click="loadAuditLogs(auditLogsPage - 1)"
            :disabled="auditLogsPage === 1"
            class="px-3 py-1.5 rounded-xl border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer text-neutral-muted font-semibold"
          >
            Previous
          </button>
          <button
            @click="loadAuditLogs(auditLogsPage + 1)"
            :disabled="auditLogsPage === auditLogsLastPage"
            class="px-3 py-1.5 rounded-xl border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer text-neutral-muted font-semibold"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.25s ease-out forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>
