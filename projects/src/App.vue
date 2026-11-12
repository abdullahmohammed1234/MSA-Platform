<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import AppNavbar from './components/layout/AppNavbar.vue';
import AppSidebar from './components/layout/AppSidebar.vue';
import MobileNav from './components/layout/MobileNav.vue';
import ProjectCreateModal from './components/projects/ProjectCreateModal.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const showGlobalCreateModal = ref(false);

const isAuthPage = computed(() => {
  return route.name === 'login';
});

const onProjectCreated = (uuid: string) => {
  router.push(`/projects/${uuid}`);
};
</script>

<template>
  <div class="min-h-screen flex flex-col bg-[#fbfbf9] text-[#1e1e1e]">
    <!-- Top Bar (shown when authenticated) -->
    <AppNavbar v-if="!isAuthPage && authStore.isAuthenticated" />

    <!-- Main Workspace with Sidebar -->
    <div v-if="!isAuthPage && authStore.isAuthenticated" class="flex-1 flex w-full">
      <AppSidebar @open-create-modal="showGlobalCreateModal = true" />

      <main class="flex-1 p-4 sm:p-6 lg:p-8 min-w-0 max-w-full overflow-x-hidden pb-20 md:pb-8">
        <router-view />
      </main>
    </div>

    <!-- Mobile Navigation Bottom Bar -->
    <MobileNav v-if="!isAuthPage && authStore.isAuthenticated" />

    <!-- Full-screen Auth Pages (Login) -->
    <main v-else class="flex-1">
      <router-view />
    </main>

    <!-- Global Project Creation Modal -->
    <ProjectCreateModal
      :is-open="showGlobalCreateModal"
      @close="showGlobalCreateModal = false"
      @created="onProjectCreated"
    />
  </div>
</template>
