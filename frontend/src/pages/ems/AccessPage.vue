<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { accessService } from '@/services/ems';
import { EmsErrorState, EmsPageHeader } from '@/components/ems';
import { useEmsApiError } from '@/composables/ems/useEmsApiError';
import { useEmsAccessStore } from '@/stores/ems/emsAccess';
import type { EmsPermission, EmsRole } from '@/types/ems';

/**
 * The EMS access model, read-only.
 *
 * Roles and permissions are administered from the platform's existing RBAC
 * screens; this page exists so an EMS administrator can see how the module's
 * permissions map onto roles without leaving the EMS.
 */
const access = useEmsAccessStore();
const { handle } = useEmsApiError();

const roles = ref<EmsRole[]>([]);
const permissions = ref<EmsPermission[]>([]);
const isLoading = ref(true);
const error = ref<string | null>(null);

/** Permissions grouped the way the backend labels them. */
const groups = computed(() => {
  const grouped = new Map<string, EmsPermission[]>();

  for (const permission of permissions.value) {
    const existing = grouped.get(permission.group) ?? [];
    existing.push(permission);
    grouped.set(permission.group, existing);
  }

  return [...grouped.entries()].map(([name, items]) => ({ name, items }));
});

const load = async () => {
  isLoading.value = true;
  error.value = null;

  try {
    const [loadedRoles, loadedPermissions] = await Promise.all([
      accessService.roles(),
      accessService.permissions(),
    ]);

    roles.value = loadedRoles;
    permissions.value = loadedPermissions;
  } catch (caught) {
    error.value = handle(caught, { silent: true }).message;
  } finally {
    isLoading.value = false;
  }
};

onMounted(load);

const holds = (role: EmsRole, slug: string) => role.permissions.includes(slug);
</script>

<template>
  <div>
    <EmsPageHeader
      title="Roles &amp; Permissions"
      description="How EMS capabilities map onto roles. Assignments are managed from the platform admin."
    />

    <EmsErrorState v-if="error" :message="error" @retry="load" />

    <div v-else-if="isLoading" class="space-y-3" role="status" aria-label="Loading access model">
      <div v-for="row in 6" :key="row" class="h-12 animate-pulse rounded-xl bg-neutral-ivory/60" />
    </div>

    <template v-else>
      <!-- Your own access, first: the most common question this page answers. -->
      <section class="mb-6 rounded-2xl border border-neutral-ivory bg-white p-5 shadow-soft">
        <h2 class="text-[11px] font-bold uppercase tracking-wider text-neutral-muted">Your access</h2>
        <p class="mt-2 text-sm text-neutral-black">
          {{ access.profile?.name }} —
          <span class="text-neutral-muted">
            {{ access.roles.map((role) => role.name).join(', ') || 'no EMS role' }}
          </span>
        </p>
        <p class="mt-1 text-xs text-neutral-muted">
          {{ access.permissions.length }} EMS permissions granted.
        </p>
      </section>

      <section
        v-for="group in groups"
        :key="group.name"
        class="mb-6 overflow-hidden rounded-2xl border border-neutral-ivory bg-white shadow-soft"
      >
        <h2 class="border-b border-neutral-ivory px-5 py-3 text-sm font-bold text-neutral-black">
          {{ group.name }}
        </h2>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <caption class="sr-only">{{ group.name }} permissions by role</caption>
            <thead class="bg-neutral-background/50">
              <tr class="text-[10px] font-bold uppercase tracking-wider text-neutral-muted">
                <th scope="col" class="px-5 py-2.5">Permission</th>
                <th v-for="role in roles" :key="role.slug" scope="col" class="px-3 py-2.5 text-center">
                  {{ role.name }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-ivory">
              <tr v-for="permission in group.items" :key="permission.slug">
                <th scope="row" class="px-5 py-3 text-left font-semibold text-neutral-black">
                  <span class="block">{{ permission.slug }}</span>
                  <span class="block font-normal text-neutral-muted">{{ permission.description }}</span>
                </th>
                <td v-for="role in roles" :key="role.slug" class="px-3 py-3 text-center">
                  <span
                    :class="holds(role, permission.slug) ? 'text-emerald-600' : 'text-neutral-gray'"
                    :aria-label="holds(role, permission.slug) ? 'Granted' : 'Not granted'"
                  >
                    {{ holds(role, permission.slug) ? '✓' : '—' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
