<script setup lang="ts">
import { useNotificationsStore } from '@/stores/notifications';
import { useRouter } from 'vue-router';
import { resolveNotificationDestination } from '@/utils/notificationResolver';
import type { Notification } from '@/services/notifications';

const emit = defineEmits(['close']);
const notificationsStore = useNotificationsStore();
const router = useRouter();

const formatTimeAgo = (dateStr: string) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays === 1) return 'Yesterday';
  if (diffDays < 7) return `${diffDays}d ago`;
  
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

const handleMarkAllRead = async () => {
  await notificationsStore.markAllAsRead();
};

const handleNotificationClick = async (item: Notification) => {
  if (!item.read_at) {
    await notificationsStore.markAsRead(item.uuid);
  }
  emit('close');
  const targetRoute = resolveNotificationDestination(item);
  router.push(targetRoute);
};

const handleViewAll = () => {
  emit('close');
  router.push('/notifications');
};
</script>

<template>
  <div class="absolute right-0 mt-3 w-80 sm:w-96 bg-white rounded-2xl shadow-premium border border-neutral-ivory overflow-hidden z-50 transform origin-top-right transition-all">
    <!-- Header -->
    <div class="px-4 py-3 border-b border-neutral-ivory bg-neutral-background flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="font-extrabold text-xs uppercase tracking-wider text-neutral-black">Notifications</span>
        <span
          v-if="notificationsStore.unreadCount > 0"
          class="bg-red-500 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-full font-mono shadow-sm"
        >
          {{ notificationsStore.unreadCount }} new
        </span>
      </div>
      <button
        v-if="notificationsStore.unreadCount > 0"
        @click="handleMarkAllRead"
        class="text-[11px] font-extrabold uppercase tracking-wider text-primary hover:text-secondary cursor-pointer transition-colors"
      >
        Mark all read
      </button>
    </div>

    <!-- List -->
    <div class="max-h-80 overflow-y-auto divide-y divide-neutral-ivory/60">
      <div v-if="notificationsStore.latestUnread.length === 0" class="px-4 py-8 text-center text-sm text-neutral-muted">
        <svg class="h-8 w-8 mx-auto text-neutral-black/20 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0V9a2 2 0 00-2-2H6a2 2 0 00-2 2v4h12z" />
        </svg>
        <p class="font-semibold text-xs text-neutral-black/60">No unread notifications</p>
      </div>

      <div
        v-for="item in notificationsStore.latestUnread"
        :key="item.uuid"
        @click="handleNotificationClick(item)"
        class="p-3.5 hover:bg-neutral-background/80 cursor-pointer transition-colors flex gap-3 text-left items-start group"
      >
        <!-- Gold Indicator Dot for Unread -->
        <span class="mt-1.5 h-2.5 w-2.5 rounded-full bg-amber-400 border border-amber-500 shadow-sm flex-shrink-0 animate-pulse"></span>

        <!-- Info -->
        <div class="flex-grow min-w-0">
          <div class="flex items-center justify-between gap-2">
            <p class="font-bold text-xs text-neutral-black group-hover:text-primary transition-colors truncate">{{ item.title }}</p>
            <span class="text-[9px] text-neutral-muted font-mono shrink-0">
              {{ formatTimeAgo(item.created_at) }}
            </span>
          </div>
          <p class="text-[11px] text-neutral-muted line-clamp-2 mt-0.5 leading-relaxed">{{ item.message }}</p>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <button
      @click="handleViewAll"
      class="block w-full py-3 bg-neutral-background hover:bg-primary/5 text-center font-extrabold text-xs uppercase tracking-wider text-primary border-t border-neutral-ivory cursor-pointer transition-colors"
    >
      View All Notifications →
    </button>
  </div>
</template>
