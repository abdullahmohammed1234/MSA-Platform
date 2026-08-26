<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import AuthLayout from '@/layouts/AuthLayout.vue';
import CmsLayout from '@/layouts/CmsLayout.vue';

const authStore = useAuthStore();
const showAuthLayout = computed(() => !authStore.isAuthenticated);
</script>

<template>
  <AuthLayout v-if="showAuthLayout">
    <router-view />
  </AuthLayout>
  <CmsLayout v-else>
    <router-view />
  </CmsLayout>
</template>
