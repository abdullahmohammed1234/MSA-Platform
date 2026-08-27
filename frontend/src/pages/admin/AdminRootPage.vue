<script setup lang="ts">
import { watch, computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import LoginPage from '@/pages/auth/LoginPage.vue';
import Dashboard from '@/pages/admin/Dashboard.vue';

const authStore = useAuthStore();

const hasAdminAccess = computed(() => {
  return authStore.isPrivilegedAdmin || (authStore.user?.application_access?.['admin-portal']?.access ?? false);
});

// Non-admin sessions (common on phones) used to redirect /admin → home.
// Clear the non-admin session and keep the admin login screen visible.
watch(
  () => [authStore.isAuthenticated, hasAdminAccess.value] as const,
  async ([isAuthenticated, allowed]) => {
    if (isAuthenticated && !allowed) {
      await authStore.logout();
    }
  },
  { immediate: true }
);
</script>

<template>
  <LoginPage v-if="!authStore.isAuthenticated || !hasAdminAccess" />
  <Dashboard v-else />
</template>
