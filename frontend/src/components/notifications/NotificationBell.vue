<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';
import NotificationDropdown from './NotificationDropdown.vue';

const notificationsStore = useNotificationsStore();
const isDropdownOpen = ref(false);
const bellRef = ref<HTMLElement | null>(null);

const toggleDropdown = () => {
  isDropdownOpen.value = !isDropdownOpen.value;
  if (isDropdownOpen.value) {
    notificationsStore.fetchUnread();
  }
};

const closeDropdown = (e: MouseEvent) => {
  if (bellRef.value && !bellRef.value.contains(e.target as Node)) {
    isDropdownOpen.value = false;
  }
};

onMounted(() => {
  notificationsStore.fetchUnread();
  document.addEventListener('click', closeDropdown);
});

onUnmounted(() => {
  document.removeEventListener('click', closeDropdown);
});
</script>

<template>
  <div ref="bellRef" class="relative">
    <button
      @click="toggleDropdown"
      class="relative p-2 text-neutral-muted hover:text-primary rounded-full hover:bg-neutral-ivory/50 transition-all focus:outline-none focus:ring-2 focus:ring-primary/20"
      aria-label="View notifications"
    >
      <!-- Bell Icon SVG -->
      <svg
        class="h-6 w-6 transform transition-transform duration-300 hover:rotate-12"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
        />
      </svg>

      <!-- Badge Ping Animation & Count (Red Badge) -->
      <span
        v-if="notificationsStore.unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 px-1"
      >
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-4 min-w-4 px-1 bg-red-500 text-[9px] font-black text-white items-center justify-center font-mono shadow-sm">
          {{ notificationsStore.unreadCount > 99 ? '99+' : notificationsStore.unreadCount }}
        </span>
      </span>
    </button>

    <!-- Dropdown Panel -->
    <NotificationDropdown
      v-if="isDropdownOpen"
      @close="isDropdownOpen = false"
    />
  </div>
</template>
