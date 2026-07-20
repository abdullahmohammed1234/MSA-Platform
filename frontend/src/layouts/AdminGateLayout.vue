<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import AuthLayout from '@/layouts/AuthLayout.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const authStore = useAuthStore();
const showAuthLayout = computed(() => !authStore.isAuthenticated);
</script>

<template>
  <AuthLayout v-if="showAuthLayout">
    <router-view />
  </AuthLayout>
  <AdminLayout v-else>
    <router-view />
  </AdminLayout>
</template>
