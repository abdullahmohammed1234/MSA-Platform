import { defineStore } from 'pinia';
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';

export const useVmsAccessStore = defineStore('vmsAccess', () => {
  const authStore = useAuthStore();
  const permissions = ref<string[]>([]);
  const resolved = ref(false);

  const resolve = async () => {
    if (resolved.value) return;
    permissions.value = authStore.user?.permissions || [];
    resolved.value = true;
  };

  const canAny = (required: string[]) => {
    if (authStore.isPrivilegedAdmin) return true;
    const vmsAccess = authStore.user?.application_access?.vms?.access ?? authStore.user?.application_access?.volunteering?.access;
    if (vmsAccess === true) return true;
    if (required.length === 0) return true;
    return required.some((perm) => permissions.value.includes(perm));
  };

  return { permissions, resolved, resolve, canAny };
});
