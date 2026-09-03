<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/components/feedback/toast';
import {
  Heart,
  LayoutDashboard,
  Receipt,
  Users,
  RotateCcw,
  BarChart3,
  ArrowLeft,
  Shield,
  LogOut,
} from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const toast = useToastStore();

const isSidebarOpen = ref(false);

const isSuperOrAdmin = computed(() => {
  if (typeof authStore.isPrivilegedAdmin === 'boolean') {
    return authStore.isPrivilegedAdmin;
  }
  return authStore.roles.includes('admin') || authStore.roles.includes('super-admin');
});

const navigationItems = [
  { name: 'Dashboard', path: '/donations/admin', icon: LayoutDashboard },
  { name: 'Donations', path: '/donations/admin/donations', icon: Receipt },
  { name: 'Donors Roster', path: '/donations/admin/donors', icon: Users },
  { name: 'Refunds Console', path: '/donations/admin/refunds', icon: RotateCcw },
  { name: 'Financial Reports', path: '/donations/admin/reports', icon: BarChart3 },
];

const isCurrentRoute = (path: string) => {
  if (path === '/donations/admin') return route.path === '/donations/admin';
  return route.path.startsWith(path);
};

const handleLogout = async () => {
  try {
    await authStore.logout();
    toast.success('Signed out from DMS.');
    void router.push('/');
  } catch (error) {
    void router.push('/');
  }
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background flex font-sans">
    <!-- Viewport Fixed Sidebar (DMS) -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 h-screen z-40 w-64 bg-white border-r border-neutral-ivory flex flex-col justify-between transition-transform duration-300 shadow-soft',
        isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <div class="flex-1 flex flex-col min-h-0">
        <!-- Sidebar Brand Header -->
        <div class="p-6 border-b border-neutral-ivory/50 flex items-center justify-between">
          <router-link to="/" class="flex items-center gap-3 group">
            <div class="h-10 w-10 bg-emerald-600 text-white rounded-2xl flex items-center justify-center font-bold text-sm shadow-md group-hover:scale-105 transition-transform">
              <Heart class="w-5 h-5 fill-white/20 text-white" />
            </div>
            <div>
              <h2 class="text-sm font-bold text-neutral-black tracking-wide group-hover:text-primary transition-colors">MSA Donations</h2>
              <span class="text-[10px] text-neutral-muted font-mono block">DMS Shell v1.0</span>
            </div>
          </router-link>
          <button @click="isSidebarOpen = false" class="lg:hidden text-neutral-muted hover:text-neutral-black p-1">
            ✕
          </button>
        </div>

        <!-- Navigation Links -->
        <nav class="p-4 space-y-1.5 flex-1 overflow-y-auto">
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
            <component :is="item.icon" class="h-4 w-4 shrink-0" />
            <span>{{ item.name }}</span>
          </router-link>
        </nav>
      </div>

      <!-- Footer Navigation (Back to Main Website & Optional Central Admin) -->
      <div class="p-4 border-t border-neutral-ivory/50 space-y-2 shrink-0">
        <router-link
          to="/"
          class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-neutral-muted hover:text-primary rounded-xl hover:bg-neutral-background transition-colors"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Main Website</span>
        </router-link>

        <router-link
          v-if="isSuperOrAdmin"
          to="/admin"
          class="flex items-center justify-between px-3 py-2 text-xs font-medium text-neutral-muted hover:text-primary rounded-xl hover:bg-neutral-background transition-colors"
        >
          <span class="flex items-center gap-1.5"><Shield class="w-3.5 h-3.5" /> Central Admin</span>
          <span class="text-[10px] bg-neutral-ivory px-1.5 py-0.5 rounded font-mono">Platform</span>
        </router-link>

        <button
          @click="handleLogout"
          class="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 rounded-xl transition-colors cursor-pointer"
        >
          <LogOut class="w-4 h-4" />
          <span>Sign Out</span>
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
          <span class="font-bold text-neutral-black text-sm">Donation Admin (DMS)</span>
        </div>
        <router-link to="/" class="text-xs font-bold text-primary hover:underline">
          Main Site
        </router-link>
      </header>

      <!-- Main Content Page Area -->
      <main class="flex-1 p-6 sm:p-8 overflow-y-auto max-w-7xl w-full mx-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>
