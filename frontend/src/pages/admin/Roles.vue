<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { rolePermissionService } from '@/services/admin/rolePermission.service';
import type { Role, Permission } from '@/types/auth';
import { useToastStore } from '@/components/feedback/toast';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAuthorization } from '@/composables/auth/useAuthorization';

const toast = useToastStore();
const { can } = useAuthorization();

const roles = ref<Role[]>([]);
const permissions = ref<Permission[]>([]);
const isLoading = ref(false);
const search = ref('');
const selectedModuleFilter = ref<string>('all');

// Form state
const showForm = ref(false);
const isEditing = ref(false);
const currentRoleUuid = ref<string | null>(null);
const formName = ref('');
const formSlug = ref('');
const formDescription = ref('');
const formPermissions = ref<string[]>([]);
const formPermissionSearch = ref('');

onMounted(async () => {
  await fetchData();
});

const fetchData = async () => {
  isLoading.value = true;
  try {
    const rolesData = await rolePermissionService.getRoles();
    roles.value = rolesData.roles;

    const permissionsData = await rolePermissionService.getPermissions();
    permissions.value = permissionsData.permissions;
  } catch (error: any) {
    toast.error('Failed to load roles and permissions data.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const modulesList = computed(() => {
  const mods = new Set<string>();
  permissions.value.forEach((p) => {
    if (p.module) mods.add(p.module);
  });
  return Array.from(mods).sort();
});

// Filtered roles list
const filteredRoles = computed(() => {
  return roles.value.filter((r) => {
    const matchesSearch =
      !search.value ||
      r.name.toLowerCase().includes(search.value.toLowerCase()) ||
      r.slug.toLowerCase().includes(search.value.toLowerCase()) ||
      (r.description && r.description.toLowerCase().includes(search.value.toLowerCase()));

    const matchesModule =
      selectedModuleFilter.value === 'all' ||
      (r.permissions && r.permissions.some((p) => p.module === selectedModuleFilter.value));

    return matchesSearch && matchesModule;
  });
});

// Group permissions by module
const groupedPermissions = computed(() => {
  const groups: Record<string, Permission[]> = {};
  const filterQuery = formPermissionSearch.value.toLowerCase().trim();

  permissions.value.forEach((p) => {
    if (
      filterQuery &&
      !p.name.toLowerCase().includes(filterQuery) &&
      !p.slug.toLowerCase().includes(filterQuery) &&
      !(p.description && p.description.toLowerCase().includes(filterQuery))
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

const openCreateModal = () => {
  isEditing.value = false;
  currentRoleUuid.value = null;
  formName.value = '';
  formSlug.value = '';
  formDescription.value = '';
  formPermissions.value = [];
  formPermissionSearch.value = '';
  showForm.value = true;
};

const openEditModal = (role: Role) => {
  if (role.slug === 'super-admin') {
    toast.warning('Super Admin permissions are dynamically assigned all capabilities.');
    return;
  }
  isEditing.value = true;
  currentRoleUuid.value = role.uuid;
  formName.value = role.name;
  formSlug.value = role.slug;
  formDescription.value = role.description || '';
  formPermissions.value = role.permissions ? role.permissions.map((p) => p.slug) : [];
  formPermissionSearch.value = '';
  showForm.value = true;
};

const handleSaveRole = async () => {
  if (!formName.value || !formSlug.value) {
    toast.warning('Please fill in all required fields.');
    return;
  }

  isLoading.value = true;
  try {
    const payload = {
      name: formName.value,
      slug: formSlug.value,
      description: formDescription.value,
      permissions: formPermissions.value,
    };

    if (isEditing.value && currentRoleUuid.value) {
      await rolePermissionService.updateRole(currentRoleUuid.value, payload);
      toast.success('Role updated successfully.');
    } else {
      await rolePermissionService.createRole(payload);
      toast.success('Role created successfully.');
    }
    showForm.value = false;
    await fetchData();
  } catch (error: any) {
    toast.error(error.response?.data?.message || 'Error occurred while saving role.');
  } finally {
    isLoading.value = false;
  }
};

const handleDeleteRole = async (role: Role) => {
  if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
    isLoading.value = true;
    try {
      await rolePermissionService.deleteRole(role.uuid);
      toast.success('Role deleted successfully.');
      await fetchData();
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to delete role.');
    } finally {
      isLoading.value = false;
    }
  }
};

const togglePermission = (slug: string) => {
  const index = formPermissions.value.indexOf(slug);
  if (index > -1) {
    formPermissions.value.splice(index, 1);
  } else {
    formPermissions.value.push(slug);
  }
};

const toggleAllModulePermissions = (moduleName: string) => {
  const perms = (groupedPermissions.value[moduleName] || []).map((p) => p.slug);
  const hasAll = perms.every((slug) => formPermissions.value.includes(slug));

  if (hasAll) {
    formPermissions.value = formPermissions.value.filter((slug) => !perms.includes(slug));
  } else {
    perms.forEach((slug) => {
      if (!formPermissions.value.includes(slug)) {
        formPermissions.value.push(slug);
      }
    });
  }
};

const moduleHasAll = (moduleName: string) => {
  const perms = (groupedPermissions.value[moduleName] || []).map((p) => p.slug);
  if (perms.length === 0) return false;
  return perms.every((slug) => formPermissions.value.includes(slug));
};

// Module colors mapping for visual tags
const getModuleBadgeClass = (module: string) => {
  const mapping: Record<string, string> = {
    Admin: 'bg-red-500/10 text-red-500 border border-red-500/20',
    Academy: 'bg-secondary/10 text-secondary border border-secondary/20',
    Website: 'bg-primary/10 text-blue-500 border border-primary/15',
    Store: 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20',
    System: 'bg-indigo-500/10 text-indigo-600 border border-indigo-500/20',
    Analytics: 'bg-accent-gold/20 text-amber-500 border border-accent-gold/30',
    Security: 'bg-rose-500/10 text-rose-600 border border-rose-500/20',
    EMS: 'bg-violet-500/10 text-violet-600 border border-violet-500/20',
  };
  return mapping[module] || 'bg-neutral-500/10 text-neutral-500 border border-neutral-500/20';
};
</script>

<template>
  <div class="space-y-8 pb-12">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
      <div>
        <h1 class="text-3xl font-display font-medium text-primary">Roles & Authorization</h1>
        <p class="text-sm text-neutral-muted mt-1">Manage user roles and assign modular platform capabilities across applications.</p>
      </div>
      <Button
        v-if="can('manage_roles')"
        variant="primary"
        @click="openCreateModal"
        class="shadow-lg hover:shadow-primary/25 transition-all"
      >
        <span class="mr-2">+</span> Create Role
      </Button>
    </div>

    <!-- Search & Filters -->
    <div class="bg-white border border-neutral-ivory p-4 rounded-2xl shadow-soft flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full max-w-2xl">
        <div class="relative max-w-xs w-full">
          <input
            v-model="search"
            type="text"
            placeholder="Search roles by name or slug..."
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
            @click="selectedModuleFilter = 'all'"
            :class="[
              'px-2.5 py-1 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap',
              selectedModuleFilter === 'all'
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
            @click="selectedModuleFilter = mod"
            :class="[
              'px-2.5 py-1 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap',
              selectedModuleFilter === mod
                ? 'bg-white text-primary shadow-xs border border-neutral-ivory/60 font-bold'
                : 'text-neutral-muted hover:text-primary'
            ]"
          >
            {{ mod }}
          </button>
        </div>
      </div>

      <div class="text-xs text-neutral-muted whitespace-nowrap">
        Showing <span class="font-bold text-neutral-black">{{ filteredRoles.length }}</span> of {{ roles.length }} roles
      </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <!-- Roles Cards List -->
      <div :class="[showForm ? 'lg:col-span-7' : 'lg:col-span-12', 'grid grid-cols-1 md:grid-cols-2 gap-6 transition-all duration-300']">
        <div
          v-for="role in filteredRoles"
          :key="role.uuid"
          class="bg-white border border-neutral-ivory hover:border-primary/30 p-6 rounded-2xl shadow-soft hover:shadow-md transition-all duration-300 flex flex-col justify-between relative overflow-hidden group"
        >
          <!-- Subtle Glow Pattern -->
          <div class="absolute -right-10 -top-10 w-24 h-24 bg-primary/5 rounded-full blur-xl group-hover:bg-primary/10 transition-all duration-300"></div>

          <div>
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="text-lg font-bold text-neutral-black tracking-wide">{{ role.name }}</h3>
                <span class="text-xs font-mono text-primary bg-primary/10 px-2 py-0.5 rounded mt-1 inline-block">
                  {{ role.slug }}
                </span>
              </div>
              <div class="flex gap-2" v-if="can('manage_roles')">
                <button
                  @click="openEditModal(role)"
                  class="p-1.5 rounded-lg hover:bg-neutral-background text-neutral-muted hover:text-primary transition-colors cursor-pointer"
                  title="Edit Role"
                  :disabled="role.slug === 'super-admin'"
                  :class="{ 'opacity-30 cursor-not-allowed': role.slug === 'super-admin' }"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </button>
                <button
                  @click="handleDeleteRole(role)"
                  class="p-1.5 rounded-lg hover:bg-red-50 text-neutral-muted hover:text-red-500 transition-colors cursor-pointer"
                  title="Delete Role"
                  :disabled="['super-admin', 'admin', 'volunteer', 'student'].includes(role.slug)"
                  :class="{ 'opacity-30 cursor-not-allowed': ['super-admin', 'admin', 'volunteer', 'student'].includes(role.slug) }"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>

            <p class="text-xs text-neutral-muted mb-5 leading-relaxed">{{ role.description }}</p>
          </div>

          <!-- Role Permissions Summary -->
          <div class="border-t border-neutral-ivory/50 pt-4">
            <h4 class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted mb-2">Permissions Summary</h4>
            <div class="flex flex-wrap gap-1">
              <span
                v-if="role.slug === 'super-admin'"
                class="text-[10px] px-2 py-0.5 rounded-full font-semibold bg-primary text-white"
              >
                All Capabilities (*)
              </span>
              <span
                v-else-if="!role.permissions || role.permissions.length === 0"
                class="text-[10px] px-2 py-0.5 rounded-full text-neutral-muted bg-neutral-ivory/40"
              >
                No Permissions Inherited
              </span>
              <template v-else>
                <span
                  v-for="perm in role.permissions.slice(0, 5)"
                  :key="perm.uuid"
                  :class="['text-[9px] px-1.5 py-0.5 rounded font-mono', getModuleBadgeClass(perm.module)]"
                >
                  {{ perm.slug }}
                </span>
                <span
                  v-if="role.permissions.length > 5"
                  class="text-[9px] px-1.5 py-0.5 rounded font-mono bg-primary/10 text-primary font-bold"
                >
                  +{{ role.permissions.length - 5 }} more
                </span>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Drawer / Edit & Create Form (Glassmorphism Card) -->
      <div
        v-if="showForm"
        class="lg:col-span-5 bg-white/95 backdrop-blur-md border border-neutral-ivory p-6 rounded-2xl shadow-soft sticky top-24 z-10 animate-fade-in-right space-y-6"
      >
        <div class="flex justify-between items-center pb-3 border-b border-neutral-ivory/50">
          <div>
            <h2 class="text-lg font-bold text-neutral-black">
              {{ isEditing ? 'Edit Role Details' : 'Create Custom Role' }}
            </h2>
            <p class="text-xs text-neutral-muted">Define title, unique key, and assign capabilities.</p>
          </div>
          <button
            @click="showForm = false"
            class="text-neutral-muted hover:text-neutral-black transition-colors text-sm font-semibold cursor-pointer"
          >
            Cancel
          </button>
        </div>

        <form @submit.prevent="handleSaveRole" class="space-y-5">
          <Input
            v-model="formName"
            label="Role Name"
            placeholder="e.g. Store Manager"
            required
          />

          <Input
            v-model="formSlug"
            label="Role Slug"
            placeholder="e.g. store-manager"
            required
            :disabled="isEditing"
          />

          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-neutral-muted mb-1.5">Description</label>
            <textarea
              v-model="formDescription"
              class="w-full min-h-[70px] p-3 text-sm rounded-xl border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/30"
              placeholder="Describe the responsibilities and scope of this role..."
            ></textarea>
          </div>

          <!-- Permission Filter Input in Form -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-xs font-bold uppercase tracking-wider text-neutral-muted">Assign Permissions</label>
              <span class="text-xs font-semibold text-primary">
                {{ formPermissions.length }} selected
              </span>
            </div>

            <input
              v-model="formPermissionSearch"
              type="text"
              placeholder="Filter permissions..."
              class="w-full px-3 py-1.5 text-xs rounded-lg border border-neutral-ivory focus:border-primary focus:outline-none bg-neutral-background/40 mb-3"
            />

            <!-- Modular Permissions Matrix -->
            <div class="space-y-4 max-h-[300px] overflow-y-auto pr-2">
              <div v-for="(perms, moduleName) in groupedPermissions" :key="moduleName" class="space-y-2">
                <div class="flex items-center justify-between border-b border-neutral-ivory/40 pb-1">
                  <span class="text-xs font-bold text-neutral-black">{{ moduleName }} Module</span>
                  <button
                    type="button"
                    @click="toggleAllModulePermissions(moduleName as string)"
                    class="text-[10px] font-semibold text-primary hover:underline cursor-pointer"
                  >
                    {{ moduleHasAll(moduleName as string) ? 'Deselect All' : 'Select All' }}
                  </button>
                </div>
                <div class="grid grid-cols-1 gap-2 pl-1">
                  <label
                    v-for="perm in perms"
                    :key="perm.uuid"
                    class="flex items-start gap-2.5 p-2 rounded-xl hover:bg-neutral-background cursor-pointer select-none transition-colors border border-transparent hover:border-neutral-ivory/30"
                    :class="{ 'bg-primary/5 border-primary/20': formPermissions.includes(perm.slug) }"
                  >
                    <input
                      type="checkbox"
                      :checked="formPermissions.includes(perm.slug)"
                      @change="togglePermission(perm.slug)"
                      class="mt-0.5 h-4 w-4 rounded border-neutral-ivory text-primary focus:ring-primary focus:ring-offset-0 cursor-pointer"
                    />
                    <div>
                      <div class="text-xs font-medium text-neutral-black">{{ perm.name }}</div>
                      <div class="text-[10px] text-neutral-muted mt-0.5 leading-relaxed">{{ perm.description }}</div>
                    </div>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-3 pt-2">
            <Button variant="outline" @click="showForm = false">Cancel</Button>
            <Button
              variant="primary"
              type="submit"
              :isLoading="isLoading"
            >
              {{ isEditing ? 'Save Changes' : 'Create Role' }}
            </Button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in-right {
  animation: fadeInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeInRight {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}
</style>
