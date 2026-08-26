<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import AuthLayout from '@/layouts/AuthLayout.vue';
import DamsLayout from '@/layouts/DamsLayout.vue';

const authStore = useAuthStore();
const showAuthLayout = computed(() => !authStore.isAuthenticated);
</script>

<template>
  <AuthLayout v-if="showAuthLayout">
    <router-view />
  </AuthLayout>
  <DamsLayout v-else>
    <router-view />
  </DamsLayout>
</template>
