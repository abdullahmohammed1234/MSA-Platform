<script setup lang="ts">
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
} from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const authStore = useAuthStore();

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
</script>

<template>
  <div class="min-h-screen bg-neutral-background text-neutral-black flex flex-col md:flex-row font-sans">
    <!-- Sidebar Navigation -->
    <aside class="w-full md:w-64 bg-white border-r border-neutral-ivory flex flex-col justify-between shrink-0 shadow-sm">
      <div>
        <div class="p-6 border-b border-neutral-ivory flex items-center space-x-3">
          <div class="w-10 h-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary p-1 overflow-hidden shrink-0">
            <img src="/logo.webp" alt="SFU MSA Logo" class="h-full w-full object-contain" />
          </div>
          <div>
            <h1 class="font-display font-bold text-lg text-neutral-black leading-tight">SFU MSA</h1>
            <p class="text-xs text-primary font-bold tracking-wide uppercase">MLibMS Admin</p>
          </div>
        </div>

        <nav class="p-4 space-y-1.5">
          <router-link
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            :class="[
              'flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200',
              isActive(item.path, item.exact)
                ? 'bg-primary/10 text-primary border border-primary/20 shadow-sm'
                : 'text-neutral-muted hover:text-neutral-black hover:bg-neutral-background'
            ]"
          >
            <component :is="item.icon" class="w-4 h-4 shrink-0" />
            <span>{{ item.name }}</span>
          </router-link>
        </nav>
      </div>

      <!-- Footer & Return to Main -->
      <div class="p-4 border-t border-neutral-ivory space-y-2">
        <a
          href="/"
          class="flex items-center justify-between px-3.5 py-2 rounded-xl text-xs text-neutral-muted hover:text-neutral-black hover:bg-neutral-background transition-colors font-medium"
        >
          <span class="flex items-center space-x-2">
            <Home class="w-3.5 h-3.5 text-primary" />
            <span>Main Website</span>
          </span>
          <ChevronRight class="w-3 h-3 text-neutral-muted" />
        </a>

        <a
          href="/admin"
          class="flex items-center justify-between px-3.5 py-2 rounded-xl text-xs text-neutral-muted hover:text-neutral-black hover:bg-neutral-background transition-colors font-medium"
        >
          <span class="flex items-center space-x-2">
            <ShieldCheck class="w-3.5 h-3.5 text-primary" />
            <span>MSA Admin Portal</span>
          </span>
          <ChevronRight class="w-3 h-3 text-neutral-muted" />
        </a>

        <a
          href="/library"
          target="_blank"
          class="flex items-center justify-between px-3.5 py-2 rounded-xl text-xs text-neutral-muted hover:text-neutral-black hover:bg-neutral-background transition-colors font-medium"
        >
          <span class="flex items-center space-x-2">
            <ExternalLink class="w-3.5 h-3.5" />
            <span>Public Catalog</span>
          </span>
          <ChevronRight class="w-3 h-3 text-neutral-muted" />
        </a>

        <div class="pt-2 border-t border-neutral-ivory flex items-center justify-between">
          <div class="truncate text-xs">
            <p class="text-neutral-black font-semibold truncate">{{ authStore.user?.name || 'Staff User' }}</p>
            <p class="text-neutral-muted text-[10px] truncate">{{ authStore.user?.email }}</p>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 min-w-0 bg-neutral-background overflow-y-auto">
      <header class="h-16 border-b border-neutral-ivory px-6 flex items-center justify-between bg-white/80 sticky top-0 z-10 backdrop-blur-md">
        <div class="flex items-center space-x-2 text-sm text-neutral-muted">
          <span class="text-neutral-black font-bold">MLibMS Admin Workbench</span>
        </div>
        <div class="flex items-center space-x-3">
          <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-700 border border-emerald-500/20">
            <ShieldCheck class="w-3.5 h-3.5 mr-1 text-emerald-600" />
            Operational
          </span>
        </div>
      </header>

      <div class="p-6 md:p-8 max-w-7xl mx-auto">
        <router-view />
      </div>
    </main>
  </div>
</template>

