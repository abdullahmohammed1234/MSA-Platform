<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const isSidebarOpen = ref(false);

const navigationItems = [
  { name: 'Dashboard', path: '/donations/admin', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { name: 'Donations', path: '/donations/admin/donations', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { name: 'Donors Roster', path: '/donations/admin/donors', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
  { name: 'Refunds Console', path: '/donations/admin/refunds', icon: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6' },
  { name: 'Financial Reports', path: '/donations/admin/reports', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
];

const isCurrentRoute = (path: string) => {
  if (path === '/donations/admin') return route.path === '/donations/admin';
  return route.path.startsWith(path);
};

const handleLogout = async () => {
  await authStore.logout();
  router.push('/login');
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background flex font-sans">
    <!-- Viewport Fixed Sidebar (DMS) -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 h-screen z-40 w-64 bg-white border-r border-neutral-ivory flex flex-col justify-between transition-transform duration-300',
        isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <div>
        <!-- Sidebar Brand Header -->
        <div class="p-6 border-b border-neutral-ivory/50 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="h-9 w-9 bg-emerald-600 text-white rounded-xl flex items-center justify-center font-bold text-sm shadow-md">
              DMS
            </div>
            <div>
              <h2 class="text-sm font-bold text-neutral-black tracking-wide">Donation System</h2>
              <span class="text-[10px] text-neutral-muted font-mono">SFU MSA DMS v1.0</span>
            </div>
          </div>
          <button @click="isSidebarOpen = false" class="lg:hidden text-neutral-muted hover:text-neutral-black p-1">
            ✕
          </button>
        </div>

        <!-- Navigation Links -->
        <nav class="p-4 space-y-1.5">
          <router-link
            v-for="item in navigationItems"
            :key="item.path"
            :to="item.path"
            @click="isSidebarOpen = false"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all cursor-pointer select-none',
              isCurrentRoute(item.path)
                ? 'bg-primary text-white shadow-md font-bold'
                : 'text-neutral-muted hover:text-neutral-black hover:bg-neutral-background'
            ]"
          >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
            </svg>
            <span>{{ item.name }}</span>
          </router-link>
        </nav>
      </div>

      <!-- Quick Platform Navigation & Logout -->
      <div class="p-4 border-t border-neutral-ivory/50 space-y-2">
        <router-link
          to="/admin"
          class="flex items-center justify-between px-3 py-2 text-xs font-medium text-neutral-muted hover:text-primary rounded-xl hover:bg-neutral-background transition-colors"
        >
          <span>← Central Admin</span>
          <span class="text-[10px] bg-neutral-ivory px-1.5 py-0.5 rounded font-mono">Platform</span>
        </router-link>
        <button
          @click="handleLogout"
          class="w-full text-left px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 rounded-xl transition-colors cursor-pointer"
        >
          Sign Out
        </button>
      </div>
    </aside>

    <!-- Layout Desktop Spacer -->
    <div class="hidden lg:block shrink-0 w-64" aria-hidden="true"></div>

    <!-- Main Workspace Surface -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Top Mobile Navigation Bar -->
      <header class="lg:hidden bg-white border-b border-neutral-ivory px-4 py-3 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3">
          <button @click="isSidebarOpen = true" class="p-2 text-neutral-muted hover:text-neutral-black rounded-lg border border-neutral-ivory">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <span class="font-bold text-neutral-black text-sm">Donation Admin</span>
        </div>
      </header>

      <!-- Main Content Page Area -->
      <main class="flex-1 p-6 sm:p-8 overflow-y-auto max-w-7xl w-full mx-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>
