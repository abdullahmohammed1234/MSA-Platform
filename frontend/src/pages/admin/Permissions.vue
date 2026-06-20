<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { rolePermissionService } from '@/services/admin/rolePermission.service';
import client from '@/services/api/client';
import type { Permission, AuditLog, Role } from '@/types/auth';
import { useToastStore } from '@/components/feedback/toast';
import { Button } from '@/components/ui/button';
import { useAuthorization } from '@/composables/auth/useAuthorization';

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

// Selected user for role/permission assignment
const selectedUserUuid = ref('');
const userRoles = ref<string[]>([]);
const userPermissions = ref<string[]>([]);

const selectedUser = computed(() => {
  return users.value.find(u => u.uuid === selectedUserUuid.value) || null;
});

onMounted(async () => {
  await loadBaseData();
});

const loadBaseData = async () => {
  isLoading.value = true;
  try {
    // 1. Fetch permissions
    const permsData = await rolePermissionService.getPermissions();
    permissions.value = permsData.permissions;

    // 2. Fetch roles
    const rolesData = await rolePermissionService.getRoles();
    roles.value = rolesData.roles;

    // 3. Fetch users
    const usersResponse = await client.get('/admin/users');
    users.value = usersResponse.data.users || [];

    // 4. Fetch audit logs
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

// Group permissions by module
const groupedPermissions = computed(() => {
  const groups: Record<string, Permission[]> = {};
  permissions.value.forEach(p => {
    if (!groups[p.module]) {
      groups[p.module] = [];
    }
    groups[p.module].push(p);
  });
  return groups;
});

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const getActionClass = (action: string) => {
  const mapping: Record<string, string> = {
    role_assigned: 'bg-secondary/10 text-secondary border border-secondary/20/50',
    role_removed: 'bg-red-50 text-red-600 border border-red-200/50',
    roles_synced: 'bg-primary/5 text-primary border border-blue-200/50',
    permission_granted: 'bg-primary/5 text-primary border border-primary/15/50',
    permission_revoked: 'bg-accent-gold/20 text-amber-600 border border-amber-200/50',
    user_permissions_synced: 'bg-purple-50 text-purple-600 border border-purple-200/50',
    role_created: 'bg-green-50 text-green-600 border border-green-200/50',
    role_deleted: 'bg-accent-red/10 text-rose-600 border border-accent-red/20/50',
  };
  return mapping[action] || 'bg-neutral-50 text-neutral-600 border border-neutral-200/50';
};
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-medium text-primary">Permissions & Security</h1>
      <p class="text-sm text-neutral-muted mt-1">Assign direct permissions, review the master permission list, and check security audit logs.</p>
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
      <div class="lg:col-span-4 bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
        <h2 class="text-base font-bold text-neutral-black mb-4">Select User</h2>
        
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1.5">User Account</label>
          <select
            v-model="selectedUserUuid"
            @change="handleSelectUser"
            class="w-full p-2.5 text-sm rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 cursor-pointer"
          >
            <option value="">-- Choose User --</option>
            <option v-for="user in users" :key="user.uuid" :value="user.uuid">
              {{ user.name }} ({{ user.email }})
            </option>
          </select>
        </div>

        <!-- Selected User Quick Info -->
        <div v-if="selectedUser" class="mt-6 pt-6 border-t border-neutral-ivory/50 space-y-4">
          <div>
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Active Roles</h4>
            <div class="flex flex-wrap gap-1 mt-1.5">
              <span
                v-for="roleSlug in selectedUser.roles"
                :key="roleSlug"
                class="text-[10px] bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded font-mono"
              >
                {{ roleSlug }}
              </span>
              <span v-if="selectedUser.roles.length === 0" class="text-xs text-neutral-muted italic">None</span>
            </div>
          </div>

          <div>
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">Permissions Summary</h4>
            <p class="text-xs text-neutral-muted mt-1">Has access to {{ selectedUser.permissions.length }} direct / inherited system operations.</p>
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
              <p class="text-xs text-neutral-muted mt-0.5">Role changes will overwrite the user's current roles.</p>
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
              <p class="text-xs text-neutral-muted mt-0.5">Use direct permissions to grant ad-hoc access without changing roles.</p>
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

          <div class="space-y-6 max-h-[360px] overflow-y-auto pr-2">
            <div v-for="(perms, moduleName) in groupedPermissions" :key="moduleName" class="space-y-2">
              <div class="text-xs font-bold text-neutral-black border-b border-neutral-ivory/30 pb-1">
                {{ moduleName }} Module
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pl-1">
                <label
                  v-for="perm in perms"
                  :key="perm.uuid"
                  class="flex items-start gap-2.5 p-2 rounded-lg hover:bg-neutral-background cursor-pointer select-none transition-colors border border-neutral-ivory/30"
                  :class="{ 'border-primary/20 bg-neutral-background/40': userPermissions.includes(perm.slug) }"
                >
                  <input
                    type="checkbox"
                    :checked="userPermissions.includes(perm.slug)"
                    @change="toggleUserPermission(perm.slug)"
                    class="mt-0.5 h-4 w-4 rounded border-neutral-ivory text-primary focus:ring-primary focus:ring-offset-0 cursor-pointer"
                  />
                  <div>
                    <span class="text-xs font-semibold text-neutral-black">{{ perm.name }}</span>
                    <p class="text-[10px] text-neutral-muted leading-relaxed">{{ perm.description }}</p>
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
        <p class="text-xs text-neutral-muted max-w-sm text-center">Choose a user from the dropdown to assign system roles and direct capabilities.</p>
      </div>
    </div>

    <!-- Tab 2: Permission Directory -->
    <div v-if="activeTab === 'directory'" class="space-y-6 animate-fade-in">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div v-for="(perms, moduleName) in groupedPermissions" :key="moduleName" class="bg-white border border-neutral-ivory p-6 rounded-2xl shadow-soft">
          <h2 class="text-sm font-bold uppercase tracking-wider text-primary border-b border-neutral-ivory/50 pb-2 mb-4">
            {{ moduleName }} Permissions
          </h2>
          
          <div class="divide-y divide-neutral-ivory/30 space-y-4">
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
      <div class="px-6 py-4 border-b border-neutral-ivory/50 flex justify-between items-center">
        <h2 class="text-base font-bold text-neutral-black">Authorization Log Trail</h2>
        <span class="text-xs font-semibold text-primary bg-primary/10 px-2 py-0.5 rounded-full">
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
            <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-neutral-background/30 transition-colors">
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
            <tr v-if="auditLogs.length === 0">
              <td colspan="4" class="px-6 py-12 text-center text-neutral-muted italic">
                No security audit logs found.
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
            class="px-3 py-1.5 rounded-lg border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer text-neutral-muted"
          >
            Previous
          </button>
          <button
            @click="loadAuditLogs(auditLogsPage + 1)"
            :disabled="auditLogsPage === auditLogsLastPage"
            class="px-3 py-1.5 rounded-lg border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer text-neutral-muted"
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
