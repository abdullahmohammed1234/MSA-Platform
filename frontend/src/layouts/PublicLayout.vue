<script setup lang="ts">
import PublicNavbar from '@/components/navigation/navbar/PublicNavbar.vue';
import PublicFooter from '@/components/navigation/footer/PublicFooter.vue';
</script>

<template>
  <div class="min-h-screen flex flex-col bg-neutral-background selection:bg-secondary selection:text-white overflow-x-hidden">
    <!-- Floating header navigation bar -->
    <PublicNavbar />
    
    <!-- Main page wrapper (no constraints, page containers handle max-width) -->
    <main class="flex-grow overflow-x-hidden">
      <router-view />
    </main>
    
    <!-- Premium design system footer -->
    <PublicFooter />
  </div>
</template>

