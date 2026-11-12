<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useNotificationStore } from '../../stores/notifications';
import NotificationDropdown from '../notifications/NotificationDropdown.vue';
import {
  Bell,
  LogOut,
  FolderKanban,
  ExternalLink,
  ShieldAlert,
  ChevronDown,
} from 'lucide-vue-next';

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
  await notificationStore.fetchUnreadCount();
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
  <header class="h-16 border-b border-[#ebe8de] bg-white/90 backdrop-blur-md sticky top-0 z-40 px-4 md:px-6 flex items-center justify-between shadow-xs">
    <!-- Brand / System Title -->
    <div class="flex items-center gap-3">
      <router-link to="/" class="flex items-center gap-2.5 group">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#640c0e] to-[#b02e32] flex items-center justify-center text-white shadow-xs group-hover:scale-105 transition-transform">
          <FolderKanban class="w-5 h-5" />
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="font-extrabold text-[#640c0e] text-base tracking-tight font-display">SFU MSA</span>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-[#640c0e]/10 text-[#640c0e] uppercase tracking-wider">PMS</span>
          </div>
          <p class="text-[11px] text-[#6b7280] hidden sm:block font-medium">Project Management System</p>
        </div>
      </router-link>
    </div>

    <!-- Right Controls -->
    <div class="flex items-center gap-2.5 sm:gap-3">
      <!-- Notification Center & Invitations Dropdown -->
      <div class="relative" ref="notificationsDropdownRef">
        <button
          type="button"
          @click.stop="showNotifications = !showNotifications; showUserMenu = false"
          class="relative p-2 rounded-xl text-[#6b7280] hover:text-[#640c0e] hover:bg-[#fbfbf9] transition-colors"
          title="Notifications & Invitations"
        >
          <Bell class="w-5 h-5" />
          <span
            v-if="totalUnreadCount > 0"
            class="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 bg-[#ea2128] text-white text-[10px] font-bold rounded-full ring-2 ring-white flex items-center justify-center animate-pulse"
          >
            {{ totalUnreadCount > 99 ? '99+' : totalUnreadCount }}
          </span>
        </button>

        <!-- Notification Dropdown Menu -->
        <NotificationDropdown
          v-if="showNotifications"
          @close="showNotifications = false"
          class="absolute right-0 mt-2"
        />
      </div>

      <!-- User Profile & Action Menu -->
      <div class="relative" ref="userMenuDropdownRef">
        <button
          type="button"
          @click.stop="showUserMenu = !showUserMenu; showNotifications = false"
          class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-[#fbfbf9] transition-colors"
        >
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#640c0e] to-[#b02e32] text-white flex items-center justify-center text-xs font-bold shadow-xs">
            {{ authStore.userInitials }}
          </div>
          <span class="text-xs font-semibold text-[#1e1e1e] hidden md:block max-w-[120px] truncate">
            {{ authStore.user?.name }}
          </span>
          <ChevronDown class="w-3.5 h-3.5 text-[#6b7280] hidden md:block" />
        </button>

        <!-- User Dropdown Menu -->
        <div
          v-if="showUserMenu"
          class="absolute right-0 mt-2 w-64 bg-white rounded-2xl shadow-elevated border border-[#ebe8de] p-2 z-50 animate-in fade-in zoom-in-95 duration-150"
        >
          <div class="px-3 py-2 border-b border-[#ebe8de]">
            <p class="text-sm font-bold text-[#1e1e1e]">{{ authStore.user?.name }}</p>
            <p class="text-xs text-[#6b7280] truncate">{{ authStore.user?.email }}</p>
            <!-- Department Memberships -->
            <div class="flex flex-wrap gap-1 mt-2">
              <span
                v-for="dept in authStore.departments"
                :key="dept.id"
                class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-[#fbfbf9] text-[#640c0e] border border-[#ebe8de]"
              >
                {{ dept.name }} ({{ dept.role || 'Member' }})
              </span>
            </div>
          </div>

          <div class="py-1">
            <a
              :href="portalUrl"
              target="_blank"
              class="flex items-center justify-between px-3 py-2 text-xs font-medium text-[#1e1e1e] hover:bg-[#fbfbf9] rounded-xl transition-colors"
            >
              <span class="flex items-center gap-2">
                <ExternalLink class="w-4 h-4 text-[#6b7280]" /> Main Digital Platform
              </span>
            </a>

            <a
              v-if="authStore.user?.is_admin"
              :href="`${portalUrl}/admin/pms/access`"
              target="_blank"
              class="flex items-center justify-between px-3 py-2 text-xs font-medium text-[#640c0e] hover:bg-[#640c0e]/5 rounded-xl transition-colors"
            >
              <span class="flex items-center gap-2">
                <ShieldAlert class="w-4 h-4 text-[#640c0e]" /> PMS Access Admin
              </span>
            </a>
          </div>

          <div class="pt-1 border-t border-[#ebe8de]">
            <button
              type="button"
              @click="onLogout"
              class="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-[#ea2128] hover:bg-[#ea2128]/5 rounded-xl transition-colors"
            >
              <LogOut class="w-4 h-4" /> Sign Out
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>
