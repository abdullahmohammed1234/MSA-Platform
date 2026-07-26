<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  CalendarDays,
  ChevronDown,
  LayoutDashboard,
  LogOut,
  Menu,
  ShieldCheck,
  Tags,
  TrendingUp,
  X,
  Percent,
  LayoutTemplate,
  MessageSquare,
} from 'lucide-vue-next';
import { ToastContainer, useToastStore } from '@/components/feedback/toast';
import { useAuthStore } from '@/stores/auth';
import { useEmsAccessStore } from '@/stores/ems/emsAccess';
import { EMS_PERMISSIONS } from '@/constants/ems';

/**
 * The EMS application shell.
 *
 * Navigation is permission-aware: an item only appears when the viewer holds
 * the permission behind it. That is a usability decision, not a security one —
 * the route guard re-checks it and every endpoint authorizes independently.
 */
const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const authStore = useAuthStore();
const access = useEmsAccessStore();

const isMobileNavOpen = ref(false);
const isUserMenuOpen = ref(false);

onMounted(() => {
  // The guard already resolves this before the route renders; this covers a
  // direct mount, e.g. during hot reload.
  void access.resolve();
});

const navigation = computed(() =>
  [
    {
      label: 'Dashboard',
      to: { name: 'ems-dashboard' },
      icon: LayoutDashboard,
      permission: EMS_PERMISSIONS.EVENTS_VIEW,
    },
    {
      label: 'Events',
      to: { name: 'ems-events' },
      icon: CalendarDays,
      permission: EMS_PERMISSIONS.EVENTS_VIEW,
    },
    {
      label: 'Templates',
      to: { name: 'ems-templates' },
      icon: LayoutTemplate,
      permission: EMS_PERMISSIONS.EVENTS_VIEW,
    },
    {
      label: 'Promo Codes',
      to: { name: 'ems-promo-codes' },
      icon: Percent,
      permission: EMS_PERMISSIONS.EVENTS_VIEW,
    },
    {
      label: 'Feedback',
      to: { name: 'ems-feedback' },
      icon: MessageSquare,
      permission: EMS_PERMISSIONS.EVENTS_VIEW,
    },
    {
      label: 'Categories',
      to: { name: 'ems-categories' },
      icon: Tags,
      permission: EMS_PERMISSIONS.CATEGORIES_VIEW,
    },
    {
      label: 'Analytics',
      to: { name: 'ems-analytics' },
      icon: TrendingUp,
      permission: EMS_PERMISSIONS.ANALYTICS_VIEW,
    },
    {
      label: 'Roles & Permissions',
      to: { name: 'ems-access' },
      icon: ShieldCheck,
      permission: EMS_PERMISSIONS.SYSTEM_VIEW,
    },
  ].filter((item) => access.can(item.permission))
);

const currentUser = computed(() => access.profile);

const primaryRole = computed(() => currentUser.value?.roles[0]?.name ?? 'Event Management');

const initials = computed(() =>
  (currentUser.value?.name ?? '?')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
);

const isActive = (name: string): boolean => route.name === name;

const closeMobileNav = () => {
  isMobileNavOpen.value = false;
};

const handleLogout = async () => {
  isUserMenuOpen.value = false;

  try {
    // The platform auth store owns logout; the EMS only clears its own cache.
    await authStore.logout();
    toast.success('Signed out successfully.');
  } finally {
    access.reset();
    await router.push({ name: 'login' });
  }
};
</script>

<template>
  <div class="min-h-screen bg-neutral-background flex">
    <ToastContainer />

    <!-- Mobile nav backdrop -->
    <div
      v-if="isMobileNavOpen"
      class="fixed inset-0 z-30 bg-black/40 backdrop-blur-xs lg:hidden"
      @click="closeMobileNav"
    />

    <!-- Sidebar -->
    <aside
      class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-neutral-ivory bg-white transition-transform duration-200 lg:static lg:translate-x-0"
      :class="isMobileNavOpen ? 'translate-x-0' : '-translate-x-full'"
      aria-label="Event management navigation"
    >
      <div class="flex h-16 items-center justify-between border-b border-neutral-ivory px-5">
        <router-link :to="{ name: 'ems-dashboard' }" class="min-w-0" @click="closeMobileNav">
          <p class="text-sm font-bold text-primary leading-tight">MSA Events</p>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-neutral-muted">
            Management System
          </p>
        </router-link>

        <button
          type="button"
          class="rounded-lg p-1 text-neutral-muted hover:bg-neutral-background lg:hidden cursor-pointer"
          aria-label="Close navigation"
          @click="closeMobileNav"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <nav class="flex-1 space-y-1 overflow-y-auto p-3">
        <router-link
          v-for="item in navigation"
          :key="item.label"
          :to="item.to"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors"
          :class="
            isActive((item.to as { name: string }).name)
              ? 'bg-primary text-white'
              : 'text-neutral-muted hover:bg-neutral-background hover:text-primary'
          "
          :aria-current="isActive((item.to as { name: string }).name) ? 'page' : undefined"
          @click="closeMobileNav"
        >
          <component :is="item.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
          {{ item.label }}
        </router-link>
      </nav>

      <div class="border-t border-neutral-ivory p-3">
        <router-link
          to="/"
          class="block rounded-xl px-3 py-2 text-xs font-semibold text-neutral-muted hover:text-primary"
        >
          &larr; Back to MSA Platform
        </router-link>
      </div>
    </aside>

    <!-- Content -->
    <div class="flex min-w-0 flex-1 flex-col">
      <header
        class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-neutral-ivory bg-white/85 px-4 backdrop-blur-md sm:px-6"
      >
        <button
          type="button"
          class="rounded-lg p-2 text-neutral-muted hover:bg-neutral-background lg:hidden cursor-pointer"
          aria-label="Open navigation"
          @click="isMobileNavOpen = true"
        >
          <Menu class="h-5 w-5" />
        </button>

        <p class="hidden truncate text-sm font-semibold text-neutral-black sm:block">
          {{ route.meta.title ?? 'Event Management' }}
        </p>

        <div class="relative ml-auto">
          <button
            type="button"
            class="flex items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-neutral-background cursor-pointer"
            :aria-expanded="isUserMenuOpen"
            aria-haspopup="menu"
            @click="isUserMenuOpen = !isUserMenuOpen"
          >
            <span
              class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-white"
              aria-hidden="true"
            >
              {{ initials }}
            </span>
            <span class="hidden text-left sm:block">
              <span class="block text-xs font-bold text-neutral-black">
                {{ currentUser?.name ?? 'Loading…' }}
              </span>
              <span class="block text-[10px] text-neutral-muted">{{ primaryRole }}</span>
            </span>
            <ChevronDown class="h-4 w-4 text-neutral-muted" aria-hidden="true" />
          </button>

          <div
            v-if="isUserMenuOpen"
            class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-neutral-ivory bg-white shadow-premium-md"
            role="menu"
          >
            <div class="border-b border-neutral-ivory px-4 py-3">
              <p class="truncate text-xs font-bold text-neutral-black">{{ currentUser?.name }}</p>
              <p class="truncate text-[11px] text-neutral-muted">{{ currentUser?.email }}</p>
            </div>

            <button
              type="button"
              class="flex w-full items-center gap-2 px-4 py-3 text-left text-xs font-semibold text-secondary hover:bg-neutral-background cursor-pointer"
              role="menuitem"
              @click="handleLogout"
            >
              <LogOut class="h-4 w-4" aria-hidden="true" />
              Sign out
            </button>
          </div>
        </div>
      </header>

      <main class="mx-auto w-full max-w-7xl flex-1 p-4 sm:p-6 lg:p-8" @click="isUserMenuOpen = false">
        <router-view v-slot="{ Component }">
          <component :is="Component" />
        </router-view>
      </main>
    </div>
  </div>
</template>
