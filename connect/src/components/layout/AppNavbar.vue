<template>
  <header class="sticky top-0 z-40 bg-[#fffbf4]/95 backdrop-blur-md border-b border-[#ebe8de]/80 shadow-2xs">
    <div class="w-full max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between gap-2 sm:gap-4 h-16 min-w-0">
        <!-- 1. Left: Brand / Logo -->
        <router-link
          :to="logoDestination"
          :aria-label="logoAriaLabel"
          class="flex items-center gap-2.5 sm:gap-3 group focus:outline-none focus-visible:ring-2 focus-visible:ring-[#640c0e] rounded-xl p-1 shrink-0 select-none"
        >
          <div class="relative w-9 h-9 rounded-xl bg-white border border-[#ebe8de] shadow-xs flex items-center justify-center overflow-hidden shrink-0 group-hover:border-[#640c0e]/40 transition-colors">
            <img
              src="/logo.webp"
              alt="SFU MSA Logo"
              class="w-7 h-7 object-contain"
            />
          </div>
          <div class="flex flex-col shrink-0">
            <!-- Primary Line: SFU MSA Connect (guaranteed single line) -->
            <span class="font-extrabold tracking-tight text-base sm:text-lg text-[#640c0e] leading-tight font-display whitespace-nowrap">
              SFU MSA <span class="font-semibold text-[#b02e32]">Connect</span>
            </span>
            <!-- Secondary Line: Member Portal -->
            <span class="text-[9.5px] uppercase tracking-widest text-neutral-500 font-semibold leading-none whitespace-nowrap">
              Member Portal
            </span>
          </div>
        </router-link>

        <!-- 2. Center: Authenticated Navigation Links (Desktop lg+) -->
        <nav
          v-if="authStore.isAuthenticated"
          class="hidden lg:flex items-center gap-1 xl:gap-1.5 shrink min-w-0 overflow-x-auto scrollbar-none"
          aria-label="Main Navigation"
        >
          <router-link
            to="/app/dashboard"
            class="px-2.5 xl:px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200"
            :class="$route.path === '/app/dashboard'
              ? 'bg-[#640c0e] text-white shadow-xs ring-1 ring-[#640c0e]'
              : 'text-neutral-700 hover:bg-white hover:text-[#640c0e] border border-transparent hover:border-[#ebe8de]'"
          >
            Dashboard
          </router-link>

          <router-link
            to="/app/events"
            class="px-2.5 xl:px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200"
            :class="$route.path.startsWith('/app/events')
              ? 'bg-[#640c0e] text-white shadow-xs ring-1 ring-[#640c0e]'
              : 'text-neutral-700 hover:bg-white hover:text-[#640c0e] border border-transparent hover:border-[#ebe8de]'"
          >
            Events
          </router-link>

          <router-link
            to="/app/tickets"
            class="px-2.5 xl:px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200"
            :class="$route.path === '/app/tickets'
              ? 'bg-[#640c0e] text-white shadow-xs ring-1 ring-[#640c0e]'
              : 'text-neutral-700 hover:bg-white hover:text-[#640c0e] border border-transparent hover:border-[#ebe8de]'"
          >
            My Passes
          </router-link>

          <router-link
            to="/app/support"
            class="relative px-2.5 xl:px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200 flex items-center gap-1.5"
            :class="$route.path.startsWith('/app/support')
              ? 'bg-[#640c0e] text-white shadow-xs ring-1 ring-[#640c0e]'
              : 'text-neutral-700 hover:bg-white hover:text-[#640c0e] border border-transparent hover:border-[#ebe8de]'"
          >
            <span>Support</span>
            <span
              v-if="supportUnreadCount > 0"
              class="px-1.5 py-0.2 rounded-full text-[10px] font-bold shrink-0"
              :class="$route.path.startsWith('/app/support') ? 'bg-[#ffdc83] text-[#640c0e]' : 'bg-[#b02e32] text-white'"
            >
              {{ supportUnreadCount > 9 ? '9+' : supportUnreadCount }}
            </span>
          </router-link>

          <router-link
            to="/app/messages"
            class="relative px-2.5 xl:px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200 flex items-center gap-1.5"
            :class="$route.path.startsWith('/app/messages')
              ? 'bg-[#640c0e] text-white shadow-xs ring-1 ring-[#640c0e]'
              : 'text-neutral-700 hover:bg-white hover:text-[#640c0e] border border-transparent hover:border-[#ebe8de]'"
          >
            <span>Messages</span>
            <span
              v-if="messagingStore.unreadCount > 0"
              class="px-1.5 py-0.2 rounded-full text-[10px] font-bold shrink-0"
              :class="$route.path.startsWith('/app/messages') ? 'bg-[#ffdc83] text-[#640c0e]' : 'bg-[#b02e32] text-white'"
            >
              {{ messagingStore.unreadCount > 9 ? '9+' : messagingStore.unreadCount }}
            </span>
          </router-link>

          <router-link
            v-if="isResourcesEnabled"
            to="/app/resources"
            class="px-2.5 xl:px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200"
            :class="$route.path.startsWith('/app/resources')
              ? 'bg-[#640c0e] text-white shadow-xs ring-1 ring-[#640c0e]'
              : 'text-neutral-700 hover:bg-white hover:text-[#640c0e] border border-transparent hover:border-[#ebe8de]'"
          >
            Resources
          </router-link>

          <router-link
            to="/app/profile"
            class="px-2.5 xl:px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200"
            :class="$route.path.startsWith('/app/profile')
              ? 'bg-[#640c0e] text-white shadow-xs ring-1 ring-[#640c0e]'
              : 'text-neutral-700 hover:bg-white hover:text-[#640c0e] border border-transparent hover:border-[#ebe8de]'"
          >
            Profile
          </router-link>

          <router-link
            to="/app/help"
            class="px-2.5 xl:px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200"
            :class="$route.path.startsWith('/app/help')
              ? 'bg-[#640c0e] text-white shadow-xs ring-1 ring-[#640c0e]'
              : 'text-neutral-700 hover:bg-white hover:text-[#640c0e] border border-transparent hover:border-[#ebe8de]'"
          >
            Help
          </router-link>
        </nav>

        <!-- 3. Right: Account Actions / Sign Out -->
        <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0 whitespace-nowrap">
          <!-- Main platform link -->
          <a
            href="https://sfumsa.ca"
            target="_blank"
            rel="noopener noreferrer"
            class="hidden xl:inline-flex items-center gap-1 text-xs text-neutral-500 hover:text-[#640c0e] transition-colors font-medium px-2 py-1 shrink-0"
          >
            <span>sfumsa.ca</span>
            <ExternalLink class="w-3 h-3" />
          </a>

          <!-- When Authenticated -->
          <template v-if="authStore.isAuthenticated">
            <!-- Staff Triage shortcut for authorized staff -->
            <router-link
              v-if="isStaff"
              to="/app/support/staff"
              class="hidden 2xl:inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-[#b02e32] hover:bg-rose-50 border border-rose-200 transition-colors shrink-0"
              title="Staff Support Triage"
            >
              <ShieldAlert class="w-3.5 h-3.5" />
              <span>Staff Triage</span>
            </router-link>

            <!-- Notification Bell -->
            <router-link
              to="/app/notifications"
              class="relative p-2 rounded-xl text-neutral-600 hover:text-[#640c0e] hover:bg-[#ebe8de]/60 transition-colors shrink-0"
              title="Notifications Centre"
              aria-label="Notifications Centre"
            >
              <Bell class="w-5 h-5" />
              <span
                v-if="unreadCount > 0"
                class="absolute top-1 right-1 min-w-[16px] h-4 px-1 rounded-full bg-[#b02e32] text-white text-[9.5px] font-bold flex items-center justify-center ring-2 ring-[#fffbf4]"
              >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
              </span>
            </router-link>

            <!-- Member Identity & Sign Out Group -->
            <div class="flex items-center gap-2 sm:gap-2.5 pl-1.5 sm:pl-2.5 border-l border-[#ebe8de] shrink-0">
              <!-- User Info Badge -->
              <div class="hidden sm:flex flex-col text-right shrink-0">
                <span class="text-xs font-semibold text-neutral-800 leading-tight truncate max-w-[120px] md:max-w-[160px]">
                  {{ authStore.user?.name || 'Member' }}
                </span>
                <span class="text-[10px] text-neutral-500 capitalize leading-none mt-0.5">
                  {{ primaryRole }}
                </span>
              </div>

              <!-- Avatar / Initials -->
              <div class="w-8 h-8 rounded-full bg-[#640c0e] text-white flex items-center justify-center font-bold text-xs ring-2 ring-[#ffdc83] shrink-0 select-none">
                {{ userInitials }}
              </div>

              <!-- Sign Out Button (Strictly Single-Line with whitespace-nowrap) -->
              <Button
                variant="ghost"
                size="sm"
                :is-loading="isLoggingOut"
                @click="handleLogout"
                class="text-neutral-600 hover:text-red-700 shrink-0 whitespace-nowrap"
                title="Sign Out"
              >
                <LogOut class="w-4 h-4 shrink-0" />
                <span class="hidden sm:inline text-xs font-semibold whitespace-nowrap">Sign Out</span>
              </Button>
            </div>
          </template>

          <!-- When Unauthenticated -->
          <template v-else>
            <router-link to="/login" class="shrink-0">
              <Button variant="primary" size="sm" class="whitespace-nowrap shrink-0">
                <span>Sign In</span>
                <template #right-icon>
                  <ArrowRight class="w-3.5 h-3.5 shrink-0" />
                </template>
              </Button>
            </router-link>
          </template>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { useSupportStore } from '@/stores/support';
import { useMessagingStore } from '@/stores/messaging';
import { resourcesService } from '@/services/resources.service';
import Button from '@/components/ui/Button.vue';

import {
  LogOut,
  ArrowRight,
  ExternalLink,
  Bell,
  ShieldAlert,
} from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const notificationsStore = useNotificationsStore();
const supportStore = useSupportStore();
const messagingStore = useMessagingStore();

const isLoggingOut = ref(false);
const isResourcesEnabled = ref(false);

const logoDestination = computed(() => {
  return authStore.isAuthenticated ? '/app/dashboard' : '/';
});
const logoAriaLabel = computed(() => {
  return authStore.isAuthenticated ? 'SFU MSA Connect Dashboard' : 'SFU MSA Connect Home';
});

const unreadCount = computed(() => notificationsStore.unreadCount);
const supportUnreadCount = computed(() => supportStore.memberUnreadCount);

const isStaff = computed(() => {
  const roles = authStore.roles;
  const permissions = authStore.permissions;
  return (
    roles.includes('super-admin') ||
    roles.includes('admin') ||
    roles.includes('director') ||
    roles.includes('dawah-coordinator') ||
    permissions.includes('manage_support')
  );
});

onMounted(async () => {
  if (authStore.isAuthenticated) {
    notificationsStore.fetchUnreadCount();
    supportStore.fetchInquiries(1, true);
    messagingStore.fetchUnreadCount();
    isResourcesEnabled.value = await resourcesService.isEnabled();
  }
});

const primaryRole = computed(() => {
  const roles = authStore.roles;
  if (roles.includes('super-admin') || roles.includes('admin')) return 'Admin';
  if (roles.includes('mentor')) return 'Mentor';
  if (roles.includes('volunteer')) return 'Volunteer';
  return 'Member';
});

const userInitials = computed(() => {
  const name = authStore.user?.name || 'M';
  const parts = name.trim().split(' ');
  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
});

async function handleLogout() {
  isLoggingOut.value = true;
  try {
    await authStore.logout();
    router.push('/');
  } finally {
    isLoggingOut.value = false;
  }
}
</script>
