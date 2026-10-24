<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import {
  BookOpen,
  QrCode,
  Layers,
  Users,
  Clock,
  Bookmark,
  BarChart3,
  Settings,
  ChevronRight,
  ExternalLink,
  ShieldCheck,
  PackagePlus,
  Home,
  Menu,
  X,
} from 'lucide-vue-next';
import { ToastContainer } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const authStore = useAuthStore();

const isMobileNavOpen = ref(false);

const navItems = [
  { name: 'Dashboard', path: '/library/admin', icon: Layers, exact: true },
  { name: 'Book Intake (ISBN)', path: '/library/admin/intake', icon: PackagePlus },
  { name: 'Catalog Books', path: '/library/admin/books', icon: BookOpen },
  { name: 'Copy Inventory', path: '/library/admin/copies', icon: QrCode },
  { name: 'Members Roster', path: '/library/admin/members', icon: Users },
  { name: 'Active Loans', path: '/library/admin/loans', icon: Clock },
  { name: 'Hold Reservations', path: '/library/admin/reservations', icon: Bookmark },
  { name: 'Reports & Export', path: '/library/admin/reports', icon: BarChart3 },
  { name: 'Library Settings', path: '/library/admin/settings', icon: Settings },
];

const isActive = (path: string, exact = false) => {
  if (exact) return route.path === path;
  return route.path.startsWith(path);
};

const closeMobileNav = () => {
  isMobileNavOpen.value = false;
};

// Route change dismisses mobile navigation
watch(() => route.path, () => {
  closeMobileNav();
});

// Scroll lock & keyboard escape listener
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
</script>

<template>
  <div class="min-h-screen bg-neutral-background text-neutral-black flex overflow-x-hidden font-sans">
    <ToastContainer />

    <!-- Mobile Drawer Backdrop Overlay (LIB-04) -->
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

    <!-- Mobile Navigation Drawer (LIB-04) -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 w-72 max-w-[85vw] bg-white border-r border-neutral-ivory flex flex-col justify-between shadow-premium z-50 lg:hidden transition-transform duration-300 ease-in-out',
        isMobileNavOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
      style="padding-top: max(1rem, env(safe-area-inset-top)); padding-bottom: max(1rem, env(safe-area-inset-bottom));"
      role="dialog"
      aria-modal="true"
      aria-label="Library navigation menu"
    >
      <div class="p-5 flex-1 overflow-y-auto space-y-6">
        <!-- Header & Close -->
        <div class="flex items-center justify-between border-b border-neutral-ivory pb-4">
          <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary p-1 overflow-hidden shrink-0">
              <img src="/logo.webp" alt="SFU MSA Logo" class="h-full w-full object-contain" />
            </div>
            <div>
              <h2 class="font-display font-bold text-base text-neutral-black leading-tight">SFU MSA</h2>
              <p class="text-[11px] text-primary font-bold tracking-wide uppercase">MLibMS Admin</p>
            </div>
          </div>
          <button
            @click="closeMobileNav"
            class="p-2 rounded-xl text-neutral-muted hover:text-neutral-black hover:bg-neutral-background transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer"
            aria-label="Close navigation menu"
          >
            <X :size="20" />
          </button>
        </div>

        <!-- Navigation items -->
        <nav class="space-y-1" aria-label="Mobile library navigation">
          <router-link
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            @click="closeMobileNav"
            :class="[
              'flex items-center space-x-3 px-3.5 py-3 rounded-2xl text-xs font-bold transition-all min-h-[44px]',
              isActive(item.path, item.exact)
                ? 'bg-primary text-white shadow-sm'
                : 'text-neutral-muted hover:text-neutral-black hover:bg-neutral-background'
            ]"
          >
            <component :is="item.icon" class="w-4 h-4 shrink-0" />
            <span>{{ item.name }}</span>
          </router-link>
        </nav>
      </div>

      <!-- Footer Quick Links -->
      <div class="p-4 border-t border-neutral-ivory space-y-2 bg-neutral-background/40">
        <router-link
          to="/"
          @click="closeMobileNav"
          class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs text-neutral-muted hover:text-neutral-black hover:bg-white transition-colors font-medium min-h-[44px]"
        >
          <span class="flex items-center space-x-2">
            <Home class="w-4 h-4 text-primary" />
            <span>Main Website</span>
          </span>
          <ChevronRight class="w-3.5 h-3.5 text-neutral-muted" />
        </router-link>

        <router-link
          to="/admin"
          @click="closeMobileNav"
          class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs text-neutral-muted hover:text-neutral-black hover:bg-white transition-colors font-medium min-h-[44px]"
        >
          <span class="flex items-center space-x-2">
            <ShieldCheck class="w-4 h-4 text-primary" />
            <span>MSA Admin Portal</span>
          </span>
          <ChevronRight class="w-3.5 h-3.5 text-neutral-muted" />
        </router-link>

        <a
          href="/library"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs text-neutral-muted hover:text-neutral-black hover:bg-white transition-colors font-medium min-h-[44px]"
        >
          <span class="flex items-center space-x-2">
            <ExternalLink class="w-4 h-4 text-primary" />
            <span>Public Catalog</span>
          </span>
          <ChevronRight class="w-3.5 h-3.5 text-neutral-muted" />
        </a>

        <div class="pt-2 border-t border-neutral-ivory/60 flex items-center justify-between text-xs">
          <div class="truncate">
            <p class="text-neutral-black font-semibold truncate">{{ authStore.user?.name || 'Staff User' }}</p>
            <p class="text-neutral-muted text-[10px] truncate">{{ authStore.user?.email }}</p>
          </div>
        </div>
      </div>
    </aside>

    <!-- Desktop Sidebar -->
    <aside class="hidden lg:flex flex-col w-64 bg-white border-r border-neutral-ivory p-6 shrink-0 shadow-soft fixed inset-y-0 left-0 h-screen z-40 justify-between">
      <div class="space-y-6 flex-1 overflow-y-auto">
        <div class="flex items-center space-x-3 border-b border-neutral-ivory pb-6">
          <div class="w-10 h-10 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary p-1 overflow-hidden shrink-0">
            <img src="/logo.webp" alt="SFU MSA Logo" class="h-full w-full object-contain" />
          </div>
          <div>
            <h1 class="font-display font-bold text-base text-neutral-black leading-tight">SFU MSA</h1>
            <p class="text-[11px] text-primary font-bold tracking-wide uppercase">MLibMS Admin</p>
          </div>
        </div>

        <nav class="space-y-1">
          <router-link
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            :class="[
              'flex items-center space-x-3 px-4 py-3 rounded-2xl text-xs font-bold transition-all',
              isActive(item.path, item.exact)
                ? 'bg-primary text-white shadow-sm'
                : 'text-neutral-muted hover:text-neutral-black hover:bg-neutral-background'
            ]"
          >
            <component :is="item.icon" class="w-4 h-4 shrink-0" />
            <span>{{ item.name }}</span>
          </router-link>
        </nav>
      </div>

      <!-- Desktop Footer -->
      <div class="pt-4 border-t border-neutral-ivory space-y-2">
        <router-link
          to="/"
          class="flex items-center justify-between px-3.5 py-2 rounded-xl text-xs text-neutral-muted hover:text-neutral-black hover:bg-neutral-background transition-colors font-medium"
        >
          <span class="flex items-center space-x-2">
            <Home class="w-3.5 h-3.5 text-primary" />
            <span>Main Website</span>
          </span>
          <ChevronRight class="w-3 h-3 text-neutral-muted" />
        </router-link>

        <router-link
          to="/admin"
          class="flex items-center justify-between px-3.5 py-2 rounded-xl text-xs text-neutral-muted hover:text-neutral-black hover:bg-neutral-background transition-colors font-medium"
        >
          <span class="flex items-center space-x-2">
            <ShieldCheck class="w-3.5 h-3.5 text-primary" />
            <span>MSA Admin</span>
          </span>
          <ChevronRight class="w-3 h-3 text-neutral-muted" />
        </router-link>

        <a
          href="/library"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center justify-between px-3.5 py-2 rounded-xl text-xs text-neutral-muted hover:text-neutral-black hover:bg-neutral-background transition-colors font-medium"
        >
          <span class="flex items-center space-x-2">
            <ExternalLink class="w-3.5 h-3.5 text-primary" />
            <span>Public Catalog</span>
          </span>
          <ChevronRight class="w-3 h-3 text-neutral-muted" />
        </a>

        <div class="pt-2 border-t border-neutral-ivory flex items-center justify-between text-xs">
          <div class="truncate">
            <p class="text-neutral-black font-semibold truncate">{{ authStore.user?.name || 'Staff User' }}</p>
            <p class="text-neutral-muted text-[10px] truncate">{{ authStore.user?.email }}</p>
          </div>
        </div>
      </div>
    </aside>

    <!-- Desktop Spacer -->
    <div class="hidden lg:block w-64 shrink-0 pointer-events-none" aria-hidden="true" />

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col min-w-0">
      <!-- Top Sticky Header -->
      <header class="h-16 border-b border-neutral-ivory bg-white/85 backdrop-blur-md flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-soft sticky top-0 z-20">
        <div class="flex items-center gap-3">
          <button
            @click="isMobileNavOpen = !isMobileNavOpen"
            class="lg:hidden p-2 rounded-xl border border-neutral-ivory text-neutral-muted hover:text-neutral-black min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer transition-colors"
            aria-label="Toggle library menu"
            :aria-expanded="isMobileNavOpen"
          >
            <Menu :size="20" />
          </button>
          <span class="font-bold text-sm text-neutral-black">Muslim Library Management System (MLibMS)</span>
        </div>

        <div class="flex items-center space-x-3">
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-700 border border-emerald-500/20">
            <ShieldCheck class="w-3.5 h-3.5 mr-1 text-emerald-600" />
            <span class="hidden sm:inline">Operational</span>
          </span>
        </div>
      </header>

      <!-- Viewport Content Area -->
      <main class="flex-grow p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>
