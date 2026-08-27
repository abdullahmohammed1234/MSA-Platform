<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Sidebar } from '@/components/navigation/sidebar';
import { useToastStore, ToastContainer } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';
import { useCmsAccessStore } from '@/stores/cms/cmsAccess';
import NotificationBell from '@/components/notifications/NotificationBell.vue';

import { useAppAccess } from '@/composables/auth/useAppAccess';

const toast = useToastStore();
const authStore = useAuthStore();
const cmsAccess = useCmsAccessStore();
const { hasAdminAccess } = useAppAccess();
const isSidebarCollapsed = ref(false);
const adminName = ref('CMS Editor');

onMounted(async () => {
  const storedName = localStorage.getItem('user_name');
  if (storedName) {
    adminName.value = storedName;
  }
  await cmsAccess.resolve();
});

const cmsItems = computed(() => {
  const isSuper = typeof authStore.isPrivilegedAdmin === 'boolean'
    ? authStore.isPrivilegedAdmin
    : authStore.roles.includes('admin') || authStore.roles.includes('super-admin');

  const children: Array<{ label: string; path: string; icon: string }> = [];

  if (isSuper || cmsAccess.permissions.includes('view_analytics')) {
    children.push({ label: 'CMS Dashboard', path: '/cms', icon: 'dashboard' });
  }
  if (isSuper || cmsAccess.permissions.includes('manage_homepage')) {
    children.push({ label: 'Homepage Sections', path: '/cms/homepage', icon: 'home' });
  }
  if (isSuper || cmsAccess.permissions.includes('manage_announcements')) {
    children.push({ label: 'Announcements', path: '/cms/announcements', icon: 'file' });
  }
  if (isSuper || cmsAccess.permissions.includes('manage_team')) {
    children.push({ label: 'Team Members', path: '/cms/team', icon: 'users' });
  }
  if (isSuper || cmsAccess.permissions.includes('manage_resources')) {
    children.push({ label: 'Resources Library', path: '/cms/resources', icon: 'book' });
  }
  if (isSuper || cmsAccess.permissions.includes('manage_media')) {
    children.push({ label: 'Media Library', path: '/cms/media', icon: 'image' });
  }

  const platformChildren = [];

  if (hasAdminAccess.value) {
    platformChildren.push({ label: 'MSA Admin', path: '/admin', icon: 'dashboard' });
  }
  platformChildren.push({ label: 'Main Website', path: '/', icon: 'home' });

  return [
    {
      label: 'Content Management',
      path: '#',
      children,
    },
    {
      label: 'Platform',
      path: '#',
      children: platformChildren,
    },
  ];
});

const handleLogout = async () => {
  try {
    await authStore.logout();
    toast.success('Logged out successfully.');
    setTimeout(() => {
      window.location.href = '/';
    }, 1000);
  } catch {
    localStorage.removeItem('auth_token');
    window.location.href = '/';
  }
};
</script>

<template>
  <div class="min-h-screen flex bg-neutral-background">
    <ToastContainer />

    <Sidebar
      title="CMS"
      :items="cmsItems"
      :collapsed="isSidebarCollapsed"
      @collapse="(val) => (isSidebarCollapsed = val)"
    >
      <template #dashboard>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
        </svg>
      </template>
      <template #home>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z" />
        </svg>
      </template>
      <template #file>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      </template>
      <template #users>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
      </template>
      <template #book>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
      </template>
      <template #image>
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </template>
    </Sidebar>

    <div class="flex-1 flex flex-col min-w-0">
      <header class="h-16 border-b border-neutral-ivory bg-white flex items-center justify-between px-6 shrink-0">
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-primary/60">Content Management System</p>
          <p class="text-sm font-semibold text-neutral-black">{{ adminName }}</p>
        </div>
        <div class="flex items-center gap-3">
          <NotificationBell />
          <button
            type="button"
            class="text-xs font-bold uppercase tracking-wider text-secondary hover:underline"
            @click="handleLogout"
          >
            Log out
          </button>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
