<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  ShoppingBag,
  Package,
  Layers,
  ShoppingBasket,
  LayoutDashboard,
  Menu,
  ArrowLeft,
} from 'lucide-vue-next';
import { ToastContainer, useToastStore } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const authStore = useAuthStore();

const isMobileNavOpen = ref(false);

const navigation = [
  {
    label: 'Dashboard',
    to: '/store/admin',
    icon: LayoutDashboard,
  },
  {
    label: 'Products',
    to: '/store/admin/products',
    icon: Package,
  },
  {
    label: 'Inventory',
    to: '/store/admin/inventory',
    icon: Layers,
  },
  {
    label: 'Orders',
    to: '/store/admin/orders',
    icon: ShoppingBasket,
  },
];

const handleLogout = async () => {
  try {
    await authStore.logout();
    toast.success('Logged out successfully.');
    void router.push('/');
  } catch (error) {
    void router.push('/');
  }
};
</script>

<template>
  <div class="min-h-screen flex bg-neutral-background text-neutral-black overflow-x-hidden">
    <ToastContainer />

    <!-- Desktop Sidebar -->
    <aside class="hidden lg:flex flex-col w-64 bg-white border-r border-neutral-gray/20 p-6 shrink-0 shadow-soft fixed inset-y-0 left-0 h-screen z-40">
      <div class="space-y-6 flex-1">
        <!-- Logo / App Header -->
        <div class="flex items-center gap-3 border-b border-neutral-gray/10 pb-6">
          <div class="w-10 h-10 bg-primary/10 text-primary rounded-2xl flex items-center justify-center font-black">
            <ShoppingBag :size="22" />
          </div>
          <div>
            <h1 class="font-black text-base text-neutral-black leading-tight">MSA Store</h1>
            <p class="text-[11px] font-bold text-neutral-muted uppercase tracking-wider">Store Admin Shell</p>
          </div>
        </div>

        <!-- Navigation items -->
        <nav class="space-y-1">
          <router-link
            v-for="item in navigation"
            :key="item.to"
            :to="item.to"
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-2xl font-bold text-xs transition-all cursor-pointer',
              route.path === item.to || (item.to !== '/store/admin' && route.path.startsWith(item.to))
                ? 'bg-primary text-white shadow-sm'
                : 'text-neutral-muted hover:text-neutral-black hover:bg-neutral-background',
            ]"
          >
            <component :is="item.icon" :size="18" />
            {{ item.label }}
          </router-link>
        </nav>
      </div>

      <!-- Quick Link to MSA Admin -->
      <div class="pt-6 border-t border-neutral-gray/10 space-y-3">
        <router-link
          to="/admin"
          class="flex items-center gap-2 px-4 py-2.5 rounded-2xl border border-neutral-gray/20 text-xs font-bold text-neutral-muted hover:text-neutral-black hover:bg-neutral-background transition-all"
        >
          <ArrowLeft :size="16" /> Return to MSA Admin
        </router-link>
      </div>
    </aside>

    <!-- Desktop Layout Spacer -->
    <div class="hidden lg:block w-64 shrink-0 pointer-events-none" aria-hidden="true" />

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col min-w-0">
      <!-- Top Sticky Header -->
      <header class="h-16 border-b border-neutral-gray/20 bg-white/85 backdrop-blur-md flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-soft sticky top-0 z-20">
        <div class="flex items-center gap-3">
          <button
            @click="isMobileNavOpen = !isMobileNavOpen"
            class="lg:hidden p-2 rounded-xl border border-neutral-gray/20 text-neutral-muted hover:text-neutral-black"
          >
            <Menu :size="20" />
          </button>
          <span class="font-bold text-sm text-neutral-black">Store Management System (SMS)</span>
        </div>

        <div class="flex items-center gap-4 text-xs">
          <span class="font-semibold text-neutral-muted hidden sm:inline">{{ authStore.user?.name || 'Store Administrator' }}</span>
          <button @click="handleLogout" class="font-bold text-primary hover:underline cursor-pointer">Logout</button>
        </div>
      </header>

      <!-- Viewport Content -->
      <main class="flex-grow p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>
