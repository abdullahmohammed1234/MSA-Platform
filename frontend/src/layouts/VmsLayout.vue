<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Sidebar } from '@/components/navigation/sidebar';
import { useToastStore, ToastContainer } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';
import { useVmsAccessStore } from '@/stores/vms/vmsAccess';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import { useAppAccess } from '@/composables/auth/useAppAccess';

const toast = useToastStore();
const authStore = useAuthStore();
const vmsAccess = useVmsAccessStore();
const { hasAdminAccess } = useAppAccess();
const isSidebarCollapsed = ref(false);
const isMobileOpen = ref(false);
const adminName = ref('VMS Manager');

onMounted(async () => {
  const storedName = localStorage.getItem('user_name');
  if (storedName) {
    adminName.value = storedName;
  }
  await vmsAccess.resolve();
});

const vmsItems = computed(() => {
  const children: Array<{ label: string; path: string; icon: string }> = [
    { label: 'Volunteer Positions', path: '/vms/opportunities', icon: 'users' },
    { label: 'Volunteer Registrars', path: '/vms/registrars', icon: 'file' },
    { label: 'Public Portal', path: '/volunteer', icon: 'home' }
  ];

  const platformChildren = [];

  if (hasAdminAccess.value) {
    platformChildren.push({ label: 'MSA Admin', path: '/admin', icon: 'dashboard' });
  }
  platformChildren.push({ label: 'Main Website', path: '/', icon: 'home' });

  return [
    {
      label: 'Volunteer Management',
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
  <div class="min-h-screen flex bg-neutral-background overflow-x-hidden">
    <ToastContainer />

    <Sidebar
      title="VMS"
      :items="vmsItems"
      :collapsed="isSidebarCollapsed"
      :mobileOpen="isMobileOpen"
      @collapse="(val) => (isSidebarCollapsed = val)"
      @closeMobile="isMobileOpen = false"
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
    </Sidebar>

    <div class="flex-1 flex flex-col min-w-0">
      <header class="h-16 border-b border-neutral-ivory bg-white flex items-center justify-between px-4 sm:px-6 shrink-0">
        <div class="flex items-center min-w-0">
          <button
            @click="isMobileOpen = true"
            class="lg:hidden p-2 mr-2.5 rounded-xl border border-neutral-ivory bg-white hover:bg-neutral-background text-primary transition shrink-0 cursor-pointer"
            aria-label="Open menu"
          >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div class="min-w-0">
            <p class="text-[10px] font-black uppercase tracking-widest text-primary/60 truncate">Volunteer Management System (VMS)</p>
            <p class="text-sm font-semibold text-neutral-black truncate">{{ adminName }}</p>
          </div>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <NotificationBell />
          <button
            type="button"
            class="text-xs font-bold uppercase tracking-wider text-secondary hover:underline cursor-pointer"
            @click="handleLogout"
          >
            Log out
          </button>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-4 sm:p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
