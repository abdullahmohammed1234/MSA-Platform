<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import client from '@/services/api';
import { useToastStore } from '@/components/feedback/toast';

interface AppAccessItem {
  access: boolean;
  source: 'privileged' | 'explicit' | 'role' | 'none';
}

interface UserAccessItem {
  id: number;
  uuid: string;
  name: string;
  email: string;
  roles: string[];
  application_access: Record<string, AppAccessItem>;
}

const toast = useToastStore();

const users = ref<UserAccessItem[]>([]);
const search = ref('');
const sourceFilter = ref<'all' | 'privileged' | 'explicit' | 'role'>('all');
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const isLoading = ref(false);
const isSaving = ref<Record<number, boolean>>({});

const appsList = [
  { key: 'main-website', label: 'Main Website' },
  { key: 'cms', label: 'CMS' },
  { key: 'dawah-academy', label: 'Dawah Academy' },
  { key: 'dams', label: 'DAMS' },
  { key: 'ems', label: 'EMS' },
  { key: 'store', label: 'MSA Store' },
  { key: 'donations', label: 'Donations' },
  { key: 'sponsorship', label: 'SPMS (Sponsorship)' },
  { key: 'admin-portal', label: 'Admin Portal' },
];

onMounted(async () => {
  await fetchUsers();
});

const fetchUsers = async (page = 1) => {
  isLoading.value = true;
  try {
    const response = await client.get('/admin/application-access', {
      params: {
        page,
        search: search.value || undefined,
      },
    });
    users.value = response.data.users || [];
    currentPage.value = response.data.meta?.current_page || 1;
    lastPage.value = response.data.meta?.last_page || 1;
    total.value = response.data.meta?.total || 0;
  } catch (error) {
    toast.error('Failed to load application access list.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

let searchTimeout: any = null;
watch(search, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchUsers(1);
  }, 400);
});

const filteredUsers = computed(() => {
  if (sourceFilter.value === 'all') return users.value;
  return users.value.filter((user) => {
    return Object.values(user.application_access || {}).some(
      (item) => item.access && item.source === sourceFilter.value
    );
  });
});

const handleToggleAccess = async (user: UserAccessItem, appKey: string, currentVal: boolean) => {
  isSaving.value[user.id] = true;
  try {
    const targetAccess = !currentVal;
    
    // We send only the updated app access mapping for safety
    const updatedAccess: Record<string, boolean> = {};
    appsList.forEach((app) => {
      const isCurrent = user.application_access[app.key]?.access ?? false;
      updatedAccess[app.key] = app.key === appKey ? targetAccess : isCurrent;
    });

    const response = await client.put(`/admin/application-access/${user.id}`, {
      access: updatedAccess,
    });

    user.application_access = response.data.application_access;
    toast.success(`Access to ${appKey.toUpperCase()} updated for ${user.name}.`);
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Failed to update application access.');
    console.error(error);
  } finally {
    isSaving.value[user.id] = false;
  }
};

const getBadgeClass = (source: string) => {
  if (source === 'privileged') return 'bg-amber-500/10 text-amber-700 border border-amber-500/20';
  if (source === 'role') return 'bg-blue-500/10 text-blue-700 border border-blue-500/20';
  if (source === 'explicit') return 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/20';
  return 'bg-neutral-100 text-neutral-500 border border-neutral-200';
};

const getBadgeText = (source: string) => {
  if (source === 'privileged') return 'Super Admin';
  if (source === 'role') return 'By Role';
  if (source === 'explicit') return 'Explicit Access';
  return 'None';
};

const getUserInitials = (name: string) => {
  return name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .substring(0, 2)
    .toUpperCase();
};
</script>

<template>
  <div class="space-y-6 pb-12">
    <!-- Header -->
    <div>
      <h1 class="text-3xl font-display font-medium text-primary">Application Access Control</h1>
      <p class="text-sm text-neutral-muted mt-1">
        Manage discrete user application access boundaries independently from Roles &amp; Permissions.
      </p>
    </div>

    <!-- Application Overview Cards Summary -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 xl:grid-cols-9 gap-3">
      <div
        v-for="app in appsList"
        :key="app.key"
        class="bg-white border border-neutral-ivory p-3 rounded-2xl shadow-soft flex flex-col justify-between"
      >
        <span class="text-[10px] font-black uppercase tracking-wider text-neutral-muted truncate" :title="app.label">
          {{ app.label }}
        </span>
        <div class="mt-2 flex items-center justify-between">
          <span class="text-lg font-bold text-primary">
            {{ users.filter((u) => u.application_access[app.key]?.access).length }}
          </span>
          <span class="text-[9px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full border border-emerald-200">
            Active
          </span>
        </div>
      </div>
    </div>

    <!-- Controls -->
    <div class="bg-white border border-neutral-ivory p-4 rounded-2xl shadow-soft flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full max-w-2xl">
        <!-- Search input -->
        <div class="relative max-w-xs w-full">
          <input
            v-model="search"
            type="text"
            placeholder="Search by name or email..."
            class="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40"
          />
          <span class="absolute left-3 top-2.5 text-neutral-muted">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </span>
        </div>

        <!-- Filter Source Buttons -->
        <div class="flex items-center gap-1 bg-neutral-background p-1 rounded-xl border border-neutral-ivory overflow-x-auto">
          <button
            type="button"
            v-for="filterOpt in [
              { id: 'all', label: 'All Users' },
              { id: 'explicit', label: 'Explicit' },
              { id: 'role', label: 'By Role' },
              { id: 'privileged', label: 'Super Admin' },
            ]"
            :key="filterOpt.id"
            @click="sourceFilter = filterOpt.id as any"
            :class="[
              'px-2.5 py-1 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap',
              sourceFilter === filterOpt.id
                ? 'bg-white text-primary shadow-xs border border-neutral-ivory/60 font-bold'
                : 'text-neutral-muted hover:text-primary'
            ]"
          >
            {{ filterOpt.label }}
          </button>
        </div>
      </div>

      <div class="text-xs text-neutral-muted whitespace-nowrap">
        Total: <span class="font-bold text-neutral-black">{{ total }}</span> users listed
      </div>
    </div>

    <!-- Workspace Table -->
    <div class="bg-white border border-neutral-ivory rounded-2xl shadow-soft overflow-hidden">
      <div v-if="isLoading && users.length === 0" class="flex flex-col items-center justify-center py-20">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
        <p class="text-xs text-neutral-muted">Loading user application access matrix...</p>
      </div>

      <div v-else-if="filteredUsers.length === 0" class="flex flex-col items-center justify-center py-20 text-neutral-muted italic">
        No users match your search and filter criteria.
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full border-collapse text-left">
          <thead>
            <tr class="bg-neutral-background/50 border-b border-neutral-ivory/50 text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
              <th class="px-6 py-4">User Details</th>
              <th class="px-6 py-4">Roles</th>
              <th v-for="app in appsList" :key="app.key" class="px-3 py-4 text-center whitespace-nowrap">
                {{ app.label }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-ivory/30 text-xs">
            <tr v-for="user in filteredUsers" :key="user.id" class="hover:bg-neutral-background/30 transition-colors">
              <!-- Name & Email -->
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="h-9 w-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                    {{ getUserInitials(user.name) }}
                  </div>
                  <div>
                    <div class="font-semibold text-neutral-black">{{ user.name }}</div>
                    <div class="text-neutral-muted text-[11px] font-mono mt-0.5">{{ user.email }}</div>
                  </div>
                </div>
              </td>

              <!-- Roles -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="role in user.roles"
                    :key="role"
                    class="text-[9px] bg-neutral-background text-neutral-black border border-neutral-ivory px-2 py-0.5 rounded-md font-mono font-medium"
                  >
                    {{ role }}
                  </span>
                  <span v-if="user.roles.length === 0" class="text-neutral-muted italic text-[11px]">None</span>
                </div>
              </td>

              <!-- Applications Checkboxes -->
              <td v-for="app in appsList" :key="app.key" class="px-3 py-4 text-center">
                <div class="flex flex-col items-center justify-center space-y-1.5">
                  <!-- Custom Toggle Switch -->
                  <label
                    class="relative inline-flex items-center select-none"
                    :class="[
                      user.application_access[app.key]?.source === 'privileged' || user.application_access[app.key]?.source === 'role' || isSaving[user.id]
                        ? 'cursor-not-allowed opacity-80'
                        : 'cursor-pointer'
                    ]"
                  >
                    <input
                      type="checkbox"
                      :checked="user.application_access[app.key]?.access ?? false"
                      :disabled="user.application_access[app.key]?.source === 'privileged' || user.application_access[app.key]?.source === 'role' || isSaving[user.id]"
                      @change="handleToggleAccess(user, app.key, user.application_access[app.key]?.access ?? false)"
                      class="sr-only peer"
                    />
                    <div class="w-9 h-5 bg-neutral-ivory rounded-full peer peer-focus:ring-0 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary transition-colors"></div>
                  </label>

                  <!-- Source Badge -->
                  <span
                    v-if="user.application_access[app.key]?.access"
                    :class="['text-[8px] px-1.5 py-0.5 rounded-full font-semibold whitespace-nowrap', getBadgeClass(user.application_access[app.key].source)]"
                    :title="user.application_access[app.key].source === 'privileged' ? 'Access granted by Super Admin role' : user.application_access[app.key].source === 'role' ? 'Access granted by assigned role' : 'Access granted explicitly'"
                  >
                    {{ getBadgeText(user.application_access[app.key].source) }}
                  </span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" class="px-6 py-4 border-t border-neutral-ivory/50 flex justify-between items-center text-xs">
        <span class="text-neutral-muted">Page {{ currentPage }} of {{ lastPage }}</span>
        <div class="flex gap-2">
          <button
            @click="fetchUsers(currentPage - 1)"
            :disabled="currentPage === 1 || isLoading"
            class="px-3 py-1.5 rounded-xl border border-neutral-ivory hover:bg-neutral-background disabled:opacity-40 transition-colors cursor-pointer text-neutral-muted font-semibold"
          >
            Previous
          </button>
          <button
            @click="fetchUsers(currentPage + 1)"
            :disabled="currentPage === lastPage || isLoading"
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
.peer-checked\:bg-primary:checked ~ div {
  background-color: var(--color-primary, #0f172a);
}
</style>
