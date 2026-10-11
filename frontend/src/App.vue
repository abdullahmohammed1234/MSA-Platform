<script setup lang="ts">
import { onMounted } from 'vue';
import { RouterView } from 'vue-router';
import { usePwaStore } from '@/stores/pwa';
import PwaInstallPrompt from '@/components/common/PwaInstallPrompt.vue';

const pwaStore = usePwaStore();

onMounted(() => {
  pwaStore.initListeners();
});
</script>

<template>
  <RouterView />
  <PwaInstallPrompt />
</template>
