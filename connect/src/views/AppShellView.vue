<template>
  <div class="min-h-screen flex flex-col bg-[#fffbf4]">
    <AppNavbar />

    <!-- Loading State while restoring session -->
    <div v-if="!authStore.isInitialized" class="flex-1 flex items-center justify-center py-24">
      <div class="text-center space-y-3">
        <Spinner size="lg" />
        <p class="text-xs text-neutral-500 font-medium">Verifying platform session...</p>
      </div>
    </div>

    <!-- Authenticated Content Container -->
    <main v-else class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-7 space-y-6 pb-[max(6rem,calc(5rem+env(safe-area-inset-bottom)))] md:pb-12">
      <!-- Route View Outlet: Each view manages its own focused PageHero, RouteTabs, and Content -->
      <router-view />
    </main>

    <!-- Mobile Bottom Navigation Bar (md:hidden) -->
    <nav
      v-if="authStore.isAuthenticated"
      class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-[#fffbf4]/95 backdrop-blur-md border-t border-[#ebe8de] px-1 py-1.5 pb-[max(0.375rem,env(safe-area-inset-bottom))] flex items-center justify-around shadow-lg"
      aria-label="Mobile Navigation"
    >
      <router-link
        to="/app/dashboard"
        class="flex flex-col items-center justify-center min-w-[48px] min-h-[44px] py-1 text-[10px] font-medium transition-colors"
        :class="$route.path === '/app/dashboard' ? 'text-[#640c0e] font-bold' : 'text-neutral-500 hover:text-neutral-800'"
      >
        <LayoutDashboard class="w-5 h-5 mb-0.5" />
        <span>Home</span>
      </router-link>

      <router-link
        to="/app/events"
        class="flex flex-col items-center justify-center min-w-[48px] min-h-[44px] py-1 text-[10px] font-medium transition-colors"
        :class="$route.path.startsWith('/app/events') ? 'text-[#640c0e] font-bold' : 'text-neutral-500 hover:text-neutral-800'"
      >
        <Calendar class="w-5 h-5 mb-0.5" />
        <span>Events</span>
      </router-link>

      <router-link
        to="/app/tickets"
        class="flex flex-col items-center justify-center min-w-[48px] min-h-[44px] py-1 text-[10px] font-medium transition-colors"
        :class="$route.path === '/app/tickets' ? 'text-[#640c0e] font-bold' : 'text-neutral-500 hover:text-neutral-800'"
      >
        <Ticket class="w-5 h-5 mb-0.5" />
        <span>Passes</span>
      </router-link>

      <router-link
        to="/app/support"
        class="relative flex flex-col items-center justify-center min-w-[44px] min-h-[44px] py-1 text-[10px] font-medium transition-colors"
        :class="$route.path.startsWith('/app/support') ? 'text-[#640c0e] font-bold' : 'text-neutral-500 hover:text-neutral-800'"
      >
        <MessageSquareText class="w-5 h-5 mb-0.5" />
        <span>Support</span>
        <span
          v-if="supportUnreadCount > 0"
          class="absolute top-1 right-1.5 min-w-[14px] h-3.5 px-0.5 rounded-full bg-[#b02e32] text-white text-[8.5px] font-bold flex items-center justify-center ring-2 ring-[#fffbf4]"
        >
          {{ supportUnreadCount > 9 ? '9+' : supportUnreadCount }}
        </span>
      </router-link>

      <router-link
        to="/app/messages"
        class="relative flex flex-col items-center justify-center min-w-[44px] min-h-[44px] py-1 text-[10px] font-medium transition-colors"
        :class="$route.path.startsWith('/app/messages') ? 'text-[#640c0e] font-bold' : 'text-neutral-500 hover:text-neutral-800'"
      >
        <MessageSquare class="w-5 h-5 mb-0.5" />
        <span>Messages</span>
        <span
          v-if="messagingStore.unreadCount > 0"
          class="absolute top-1 right-1.5 min-w-[14px] h-3.5 px-0.5 rounded-full bg-[#b02e32] text-white text-[8.5px] font-bold flex items-center justify-center ring-2 ring-[#fffbf4]"
        >
          {{ messagingStore.unreadCount > 9 ? '9+' : messagingStore.unreadCount }}
        </span>
      </router-link>

      <router-link
        to="/app/notifications"
        class="relative flex flex-col items-center justify-center min-w-[44px] min-h-[44px] py-1 text-[10px] font-medium transition-colors"
        :class="$route.path.startsWith('/app/notifications') ? 'text-[#640c0e] font-bold' : 'text-neutral-500 hover:text-neutral-800'"
      >
        <Bell class="w-5 h-5 mb-0.5" />
        <span>Alerts</span>
        <span
          v-if="unreadCount > 0"
          class="absolute top-1 right-1.5 min-w-[14px] h-3.5 px-0.5 rounded-full bg-[#b02e32] text-white text-[8.5px] font-bold flex items-center justify-center ring-2 ring-[#fffbf4]"
        >
          {{ unreadCount > 9 ? '9+' : unreadCount }}
        </span>
      </router-link>

      <router-link
        to="/app/profile"
        class="flex flex-col items-center justify-center min-w-[44px] min-h-[44px] py-1 text-[10px] font-medium transition-colors"
        :class="$route.path.startsWith('/app/profile') ? 'text-[#640c0e] font-bold' : 'text-neutral-500 hover:text-neutral-800'"
      >
        <User class="w-5 h-5 mb-0.5" />
        <span>Profile</span>
      </router-link>
    </nav>

    <AppFooter class="hidden md:block" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { useSupportStore } from '@/stores/support';
import { useMessagingStore } from '@/stores/messaging';
import AppNavbar from '@/components/layout/AppNavbar.vue';
import AppFooter from '@/components/layout/AppFooter.vue';
import Spinner from '@/components/ui/Spinner.vue';
import {
  LayoutDashboard,
  Calendar,
  Ticket,
  Bell,
  User,
  MessageSquareText,
  MessageSquare,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const notificationsStore = useNotificationsStore();
const supportStore = useSupportStore();
const messagingStore = useMessagingStore();

const unreadCount = computed(() => notificationsStore.unreadCount);
const supportUnreadCount = computed(() => supportStore.memberUnreadCount);
</script>
