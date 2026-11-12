<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import AppNavbar from './components/layout/AppNavbar.vue';
import AppSidebar from './components/layout/AppSidebar.vue';
import MobileDrawer from './components/layout/MobileDrawer.vue';
import ProjectCreateModal from './components/projects/ProjectCreateModal.vue';
import PwaInstallBanner from './components/pwa/PwaInstallBanner.vue';
import PwaUpdateBanner from './components/pwa/PwaUpdateBanner.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const showGlobalCreateModal = ref(false);
const drawerOpen = ref(false);

const isAuthPage = computed(() => route.name === 'login');
const showShell = computed(() => !isAuthPage.value && authStore.isAuthenticated);
const isWideRoute = computed(() =>
  route.name === 'project-detail' || route.name === 'resources' || route.name === 'calendar'
);

const onProjectCreated = (uuid: string) => {
  router.push(`/projects/${uuid}`);
};
</script>

<template>
  <div class="min-h-screen flex flex-col bg-[#fffbf4] text-[#1e1e1e]">
    <AppNavbar
      v-if="showShell"
      @toggle-drawer="drawerOpen = !drawerOpen"
    />

    <div v-if="showShell" class="flex-1 flex w-full min-w-0">
      <AppSidebar @open-create-modal="showGlobalCreateModal = true" />

      <main
        class="flex-1 min-w-0 max-w-full overflow-x-hidden safe-area-pad"
        :style="{
          paddingInline: 'var(--spacing-page-x)',
          paddingBlock: 'var(--spacing-page-y)',
        }"
      >
        <div :class="isWideRoute ? 'pm-page pm-page--wide' : 'pm-page'">
          <PwaUpdateBanner />
          <PwaInstallBanner />
          <router-view />
        </div>
      </main>
    </div>

    <MobileDrawer
      v-if="showShell"
      :open="drawerOpen"
      @close="drawerOpen = false"
      @open-create-modal="showGlobalCreateModal = true"
    />

    <main v-else class="flex-1">
      <router-view />
    </main>

    <ProjectCreateModal
      :is-open="showGlobalCreateModal"
      @close="showGlobalCreateModal = false"
      @created="onProjectCreated"
    />
  </div>
</template>
