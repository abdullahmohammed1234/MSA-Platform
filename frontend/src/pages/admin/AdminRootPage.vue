<script setup lang="ts">
import { watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import LoginPage from '@/pages/auth/LoginPage.vue';
import Dashboard from '@/pages/admin/Dashboard.vue';

const authStore = useAuthStore();
const router = useRouter();

watch(
  () => authStore.isAuthenticated,
  (isAuthenticated) => {
    if (isAuthenticated && !authStore.isPrivilegedAdmin) {
      router.replace({ name: 'home' });
    }
  },
  { immediate: true }
);
</script>

<template>
  <LoginPage v-if="!authStore.isAuthenticated" />
  <Dashboard v-else-if="authStore.isPrivilegedAdmin" />
</template>
