<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  ShoppingBag,
  Package,
  Layers,
  ShoppingBasket,
  LayoutDashboard,
  Menu,
  X,
  ArrowLeft,
} from 'lucide-vue-next';
import { ToastContainer, useToastStore } from '@/components/feedback/toast';
import { useAuthStore, UNCONFIRMED_LOGOUT_MESSAGE } from '@/stores/auth';

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

const closeMobileNav = () => {
  isMobileNavOpen.value = false;
};

// Route changes dismiss drawer
watch(() => route.path, () => {
  closeMobileNav();
});

// Scroll locking and keyboard escape handling
watch(isMobileNavOpen, (open) => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = open ? 'hidden' : '';
  }
});

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isMobileNavOpen.value) {
    closeMobileNav();
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
  if (typeof document !== 'undefined') {
    document.body.style.overflow = '';
  }
});

const handleLogout = async () => {
  try {
    const serverConfirmed = await authStore.logout();
    if (serverConfirmed) {
      toast.success('Logged out successfully.');
    } else {
      toast.warning(UNCONFIRMED_LOGOUT_MESSAGE, 8000);
    }
    void router.push('/');
  } catch (error) {
    void router.push('/');
  }
};
</script>

<template>
  <div class="min-h-screen flex bg-neutral-background text-neutral-black overflow-x-hidden">
    <ToastContainer />

    <!-- Mobile Drawer Backdrop Overlay (STORE-01) -->
    <transition
      enter-active-class="transition-opacity duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isMobileNavOpen"
        class="fixed inset-0 bg-neutral-black/50 backdrop-blur-xs z-40 lg:hidden"
        @click="closeMobileNav"
        aria-hidden="true"
      />
    </transition>

    <!-- Mobile Slide-Over Navigation Drawer (STORE-01) -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 w-72 max-w-[85vw] bg-white border-r border-neutral-gray/20 flex flex-col justify-between shadow-premium z-50 lg:hidden transition-transform duration-300 ease-in-out',
        isMobileNavOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
      style="padding-top: max(1rem, env(safe-area-inset-top)); padding-bottom: max(1rem, env(safe-area-inset-bottom));"
      role="dialog"
      aria-modal="true"
      aria-label="Store navigation menu"
    >
      <div class="p-5 flex-1 overflow-y-auto space-y-6">
        <!-- Drawer Header with Brand & Close Button -->
        <div class="flex items-center justify-between border-b border-neutral-gray/10 pb-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary/10 text-primary rounded-2xl flex items-center justify-center font-black">
              <ShoppingBag :size="22" />
            </div>
            <div>
              <h2 class="font-black text-base text-neutral-black leading-tight">MSA Store</h2>
              <p class="text-[11px] font-bold text-neutral-muted uppercase tracking-wider">Store Admin Shell</p>
            </div>
          </div>
          <button
            @click="closeMobileNav"
            class="p-2 rounded-xl text-neutral-muted hover:text-neutral-black hover:bg-neutral-background transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
            aria-label="Close navigation menu"
          >
            <X :size="20" />
          </button>
        </div>

        <!-- Mobile Navigation items -->
        <nav class="space-y-1.5" aria-label="Mobile store navigation">
          <router-link
            v-for="item in navigation"
            :key="item.to"
            :to="item.to"
            @click="closeMobileNav"
            :class="[
              'flex items-center gap-3 px-4 py-3.5 rounded-2xl font-bold text-xs transition-all cursor-pointer min-h-[44px]',
              route.path === item.to || (item.to !== '/store/admin' && route.path.startsWith(item.to))
                ? 'bg-primary text-white shadow-sm'
                : 'text-neutral-muted hover:text-neutral-black hover:bg-neutral-background',
            ]"
          >
            <component :is="item.icon" :size="18" />
            <span>{{ item.label }}</span>
          </router-link>
        </nav>
      </div>

      <!-- Mobile Quick Links & User Info Footer -->
      <div class="p-5 border-t border-neutral-gray/10 space-y-3 bg-neutral-background/40">
        <router-link
          to="/admin"
          @click="closeMobileNav"
          class="flex items-center gap-2 px-4 py-3 rounded-2xl border border-neutral-gray/20 text-xs font-bold text-neutral-muted hover:text-neutral-black hover:bg-white transition-all min-h-[44px]"
        >
          <ArrowLeft :size="16" /> Return to MSA Admin
        </router-link>

        <div class="pt-2 flex items-center justify-between text-xs">
          <span class="font-semibold text-neutral-black truncate">{{ authStore.user?.name || 'Store Administrator' }}</span>
          <button @click="handleLogout" class="font-bold text-primary hover:underline cursor-pointer py-1 px-2">Logout</button>
        </div>
      </div>
    </aside>

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
            class="lg:hidden p-2 rounded-xl border border-neutral-gray/20 text-neutral-muted hover:text-neutral-black min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer transition-colors"
            aria-label="Toggle navigation menu"
            :aria-expanded="isMobileNavOpen"
          >
            <Menu :size="20" />
          </button>
          <span class="font-bold text-sm text-neutral-black">Store Management System (SMS)</span>
        </div>

        <div class="flex items-center gap-4 text-xs">
          <span class="font-semibold text-neutral-muted hidden sm:inline">{{ authStore.user?.name || 'Store Administrator' }}</span>
          <button @click="handleLogout" class="font-bold text-primary hover:underline cursor-pointer py-1 px-2">Logout</button>
        </div>
      </header>

      <!-- Viewport Content -->
      <main class="flex-grow p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>
