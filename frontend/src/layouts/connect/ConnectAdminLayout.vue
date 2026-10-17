<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/components/feedback/toast';
import { ToastContainer } from '@/components/feedback/toast';
import {
  Smartphone,
  LayoutDashboard,
  SlidersVertical,
  Calendar,
  Megaphone,
  History,
  ArrowLeft,
  Shield,
  LogOut,
  Menu,
  X,
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
  { name: 'Overview', path: '/connect/admin', icon: LayoutDashboard },
  { name: 'Configuration', path: '/connect/admin/config', icon: SlidersVertical },
  { name: 'EMS Event Integration', path: '/connect/admin/events', icon: Calendar },
  { name: 'Communications', path: '/connect/admin/communications', icon: Megaphone },
  { name: 'Audit Log', path: '/connect/admin/audit', icon: History },
];

const isCurrentRoute = (path: string) => {
  if (path === '/connect/admin') return route.path === '/connect/admin';
  return route.path.startsWith(path);
};

const handleLogout = async () => {
  try {
    await authStore.logout();
    toast.success('Signed out from MSA Connect Management.');
    void router.push('/');
  } catch (error) {
    void router.push('/');
  }
};
</script>

<template>
  <div class="min-h-screen bg-[#faf8f5] flex font-sans overflow-x-hidden w-full text-neutral-800">
    <ToastContainer />

    <!-- Viewport Fixed Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 h-screen z-40 w-64 bg-white border-r border-[#ebe8de] flex flex-col justify-between transition-transform duration-300 shadow-xs',
        isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <div class="flex-1 flex flex-col min-h-0">
        <!-- Sidebar Brand Header -->
        <div class="p-5 border-b border-[#ebe8de] flex items-center justify-between">
          <router-link to="/connect/admin" class="flex items-center gap-3 group">
            <div class="h-10 w-10 bg-[#640c0e] text-[#ffdc83] rounded-2xl flex items-center justify-center font-bold text-sm shadow-xs group-hover:scale-105 transition-transform">
              <Smartphone class="w-5 h-5 text-[#ffdc83]" />
            </div>
            <div>
              <h2 class="text-sm font-bold text-neutral-900 tracking-wide group-hover:text-[#640c0e] transition-colors leading-tight">MSA Connect</h2>
              <span class="text-[10px] text-neutral-500 font-mono block">Management v1.0</span>
            </div>
          </router-link>
          <button @click="isSidebarOpen = false" class="lg:hidden text-neutral-400 hover:text-neutral-800 p-1">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Navigation Links -->
        <nav class="p-3.5 space-y-1.5 flex-1 overflow-y-auto">
          <router-link
            v-for="item in navigationItems"
            :key="item.path"
            :to="item.path"
            @click="isSidebarOpen = false"
            :class="[
              'flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all cursor-pointer select-none',
              isCurrentRoute(item.path)
                ? 'bg-[#640c0e] text-white shadow-xs font-bold'
                : 'text-neutral-600 hover:text-neutral-900 hover:bg-[#f6f3eb]'
            ]"
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0" />
            <span>{{ item.name }}</span>
          </router-link>
        </nav>
      </div>

      <!-- Footer Navigation -->
      <div class="p-3.5 border-t border-[#ebe8de] space-y-1.5 shrink-0">
        <router-link
          to="/"
          class="flex items-center gap-2 px-3 py-2 text-xs font-semibold text-neutral-600 hover:text-[#640c0e] rounded-xl hover:bg-[#f6f3eb] transition-colors"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back to Main Website</span>
        </router-link>

        <router-link
          v-if="isSuperOrAdmin"
          to="/admin"
          class="flex items-center justify-between px-3 py-2 text-xs font-medium text-neutral-600 hover:text-[#640c0e] rounded-xl hover:bg-[#f6f3eb] transition-colors"
        >
          <span class="flex items-center gap-1.5"><Shield class="w-3.5 h-3.5" /> Central Admin</span>
          <span class="text-[10px] bg-[#ebe8de] px-1.5 py-0.5 rounded font-mono">Platform</span>
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
      <header class="lg:hidden bg-white border-b border-[#ebe8de] px-4 py-3 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3">
          <button @click="isSidebarOpen = true" class="p-2 text-neutral-600 hover:text-neutral-900 rounded-lg border border-[#ebe8de]">
            <Menu class="h-5 w-5" />
          </button>
          <span class="font-bold text-neutral-900 text-sm">MSA Connect Management</span>
        </div>
        <router-link to="/" class="text-xs font-bold text-[#640c0e] hover:underline">
          Portal Home
        </router-link>
      </header>

      <!-- Main Page Content Slot -->
      <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>
