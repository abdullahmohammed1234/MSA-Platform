<script setup lang="ts">
import { watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import LoginPage from '@/pages/auth/LoginPage.vue';
import Dashboard from '@/pages/admin/Dashboard.vue';

const authStore = useAuthStore();

// Non-admin sessions (common on phones) used to redirect /admin → home.
// Clear the non-admin session and keep the admin login screen visible.
watch(
  () => [authStore.isAuthenticated, authStore.isPrivilegedAdmin] as const,
  async ([isAuthenticated, isPrivilegedAdmin]) => {
    if (isAuthenticated && !isPrivilegedAdmin) {
      await authStore.logout();
    }
  },
  { immediate: true }
);
</script>

<template>
  <LoginPage v-if="!authStore.isAuthenticated || !authStore.isPrivilegedAdmin" />
  <Dashboard v-else />
</template>
