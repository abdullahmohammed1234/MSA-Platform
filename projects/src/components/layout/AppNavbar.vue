<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useNotificationStore } from '../../stores/notifications';
import NotificationDropdown from '../notifications/NotificationDropdown.vue';
import {
  Bell,
  LogOut,
  ExternalLink,
  ShieldAlert,
  ChevronDown,
  Menu,
} from 'lucide-vue-next';

defineEmits<{
  (e: 'toggle-drawer'): void;
}>();

const router = useRouter();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();

const showNotifications = ref(false);
const showUserMenu = ref(false);
const notificationsDropdownRef = ref<HTMLElement | null>(null);
const userMenuDropdownRef = ref<HTMLElement | null>(null);
const portalUrl = import.meta.env.VITE_PORTAL_URL || 'https://sfumsa.ca';

const totalUnreadCount = computed(() => {
  return (notificationStore.unreadCount || 0) + (authStore.pendingInvitations.length || 0);
});

const handleOutsideClick = (e: MouseEvent) => {
  if (notificationsDropdownRef.value && !notificationsDropdownRef.value.contains(e.target as Node)) {
    showNotifications.value = false;
  }
  if (userMenuDropdownRef.value && !userMenuDropdownRef.value.contains(e.target as Node)) {
    showUserMenu.value = false;
  }
};

onMounted(async () => {
  document.addEventListener('click', handleOutsideClick);
  if (authStore.token !== 'layout-preview') {
    await notificationStore.fetchUnreadCount();
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick);
});

const onLogout = async () => {
  await authStore.logout();
  router.push('/login');
};
</script>

<template>
  <header class="h-14 sm:h-16 border-b border-[#ebe8de] bg-white/90 backdrop-blur-md sticky top-0 z-40 px-3 sm:px-5 lg:px-6 flex items-center justify-between shadow-xs safe-area-top safe-area-pad">
    <div class="flex items-center gap-3 sm:gap-3.5 min-w-0">
      <button
        type="button"
        class="lg:hidden touch-target p-2 -ml-1 rounded-xl text-[#6b7280] hover:text-[#640c0e] hover:bg-[#fffbf4] transition-colors"
        aria-label="Open navigation menu"
        @click="$emit('toggle-drawer')"
      >
        <Menu class="w-5 h-5" />
      </button>

      <router-link
        to="/"
        class="flex items-center gap-2.5 group min-w-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#640c0e] rounded-xl"
      >
        <div class="relative w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-white border border-[#ebe8de] shadow-xs flex items-center justify-center overflow-hidden shrink-0 group-hover:border-[#640c0e]/40 transition-colors">
          <img src="/logo.webp" alt="SFU MSA logo" class="w-6 h-6 sm:w-7 sm:h-7 object-contain" />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <span class="font-extrabold text-[#640c0e] text-sm sm:text-base tracking-tight font-display whitespace-nowrap">
              SFU MSA <span class="font-semibold text-[#b02e32]">Projects</span>
            </span>
          </div>
          <p class="text-[10px] sm:text-[11px] text-[#6b7280] hidden xs:block sm:block font-medium truncate">
            Project Management System
          </p>
        </div>
      </router-link>
    </div>

    <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
      <div class="relative" ref="notificationsDropdownRef">
        <button
          type="button"
          class="relative touch-target p-2 rounded-xl text-[#6b7280] hover:text-[#640c0e] hover:bg-[#fffbf4] transition-colors"
          title="Notifications & Invitations"
          aria-label="Notifications"
          @click.stop="showNotifications = !showNotifications; showUserMenu = false"
        >
          <Bell class="w-5 h-5" />
          <span
            v-if="totalUnreadCount > 0"
            class="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 bg-[#ea2128] text-white text-[10px] font-bold rounded-full ring-2 ring-white flex items-center justify-center"
          >
            {{ totalUnreadCount > 99 ? '99+' : totalUnreadCount }}
          </span>
        </button>

        <NotificationDropdown
          v-if="showNotifications"
          class="absolute right-0 mt-2"
          @close="showNotifications = false"
        />
      </div>

      <div class="relative" ref="userMenuDropdownRef">
        <button
          type="button"
          class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-[#fffbf4] transition-colors touch-target"
          aria-label="User menu"
          @click.stop="showUserMenu = !showUserMenu; showNotifications = false"
        >
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#640c0e] to-[#b02e32] text-white flex items-center justify-center text-xs font-bold shadow-xs">
            {{ authStore.userInitials }}
          </div>
          <span class="text-xs font-semibold text-[#1e1e1e] hidden md:block max-w-[120px] truncate">
            {{ authStore.user?.name }}
          </span>
          <ChevronDown class="w-3.5 h-3.5 text-[#6b7280] hidden md:block" />
        </button>

        <div
          v-if="showUserMenu"
          class="absolute right-0 mt-2 w-64 max-w-[calc(100vw-1.5rem)] bg-white rounded-2xl shadow-elevated border border-[#ebe8de] p-2 z-50"
        >
          <div class="px-3 py-2 border-b border-[#ebe8de]">
            <p class="text-sm font-bold text-[#1e1e1e]">{{ authStore.user?.name }}</p>
            <p class="text-xs text-[#6b7280] truncate">{{ authStore.user?.email }}</p>
            <div class="flex flex-wrap gap-1 mt-2">
              <span
                v-for="dept in authStore.departments"
                :key="dept.id"
                class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-[#fffbf4] text-[#640c0e] border border-[#ebe8de]"
              >
                {{ dept.name }} ({{ dept.role || 'Member' }})
              </span>
            </div>
          </div>

          <div class="py-1">
            <a
              :href="portalUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="flex items-center justify-between px-3 py-2.5 text-xs font-medium text-[#1e1e1e] hover:bg-[#fffbf4] rounded-xl transition-colors"
            >
              <span class="flex items-center gap-2">
                <ExternalLink class="w-4 h-4 text-[#6b7280]" /> Main Digital Platform
              </span>
            </a>

            <a
              v-if="authStore.user?.is_admin"
              :href="`${portalUrl}/admin/pms/access`"
              target="_blank"
              rel="noopener noreferrer"
              class="flex items-center justify-between px-3 py-2.5 text-xs font-medium text-[#640c0e] hover:bg-[#640c0e]/5 rounded-xl transition-colors"
            >
              <span class="flex items-center gap-2">
                <ShieldAlert class="w-4 h-4 text-[#640c0e]" /> PMS Access Admin
              </span>
            </a>
          </div>

          <div class="pt-1 border-t border-[#ebe8de]">
            <button
              type="button"
              class="w-full flex items-center gap-2 px-3 py-2.5 text-xs font-semibold text-[#ea2128] hover:bg-[#ea2128]/5 rounded-xl transition-colors"
              @click="onLogout"
            >
              <LogOut class="w-4 h-4" /> Sign Out
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>
