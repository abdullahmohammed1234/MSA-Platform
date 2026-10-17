<template>
  <router-view />
  <PwaInstallPrompt />
</template>

<script setup lang="ts">
import PwaInstallPrompt from '@/components/ui/PwaInstallPrompt.vue';
</script>
