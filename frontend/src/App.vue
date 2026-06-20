<script setup lang="ts">
</script>

<template>
  <router-view v-slot="{ Component, route }">
    <component :is="Component" :key="route.path" class="w-full min-h-screen" />
  </router-view>
</template>
