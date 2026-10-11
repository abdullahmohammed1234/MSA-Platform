<script setup lang="ts">
import PublicNavbar from '@/components/navigation/navbar/PublicNavbar.vue';
import PublicFooter from '@/components/navigation/footer/PublicFooter.vue';
</script>

<template>
  <div class="min-h-screen flex flex-col bg-neutral-background selection:bg-secondary selection:text-white overflow-x-hidden">
    <!-- Skip to main content link for keyboard / screen reader navigation (WCAG 2.4.1) -->
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:px-4 focus:py-2 focus:bg-primary focus:text-white focus:rounded-lg focus:shadow-premium focus:outline-none focus:ring-2 focus:ring-accent-gold"
    >
      Skip to main content
    </a>

    <!-- Floating header navigation bar -->
    <PublicNavbar />
    
    <!-- Main page wrapper (no constraints, page containers handle max-width) -->
    <main id="main-content" tabindex="-1" class="flex-grow overflow-x-hidden outline-none">
      <router-view />
    </main>
    
    <!-- Premium design system footer -->
    <PublicFooter />
  </div>
</template>

