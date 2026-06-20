<script setup lang="ts">
import { useNotificationsStore } from '@/stores/notifications';
import { useRouter } from 'vue-router';

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

const handleNotificationClick = async (uuid: string) => {
  await notificationsStore.markAsRead(uuid);
  emit('close');
  router.push('/notifications');
};

const handleViewAll = () => {
  emit('close');
  router.push('/notifications');
};
</script>

<template>
  <div class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-xl border border-neutral-ivory overflow-hidden z-50 transform origin-top-right transition-all">
    <!-- Header -->
    <div class="px-4 py-3 border-b border-neutral-ivory bg-neutral-background flex items-center justify-between">
      <span class="font-semibold text-sm text-neutral-black">Notifications</span>
      <button
        v-if="notificationsStore.unreadCount > 0"
        @click="handleMarkAllRead"
        class="text-xs font-medium text-primary hover:text-secondary hover:underline cursor-pointer"
      >
        Mark all as read
      </button>
    </div>

    <!-- List -->
    <div class="max-h-80 overflow-y-auto divide-y divide-neutral-ivory">
      <div v-if="notificationsStore.latestUnread.length === 0" class="px-4 py-8 text-center text-sm text-neutral-muted">
        <svg class="h-8 w-8 mx-auto text-neutral-muted/50 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0V9a2 2 0 00-2-2H6a2 2 0 00-2 2v4h12z" />
        </svg>
        No unread notifications
      </div>

      <div
        v-for="item in notificationsStore.latestUnread"
        :key="item.uuid"
        @click="handleNotificationClick(item.uuid)"
        class="p-4 hover:bg-neutral-background/50 cursor-pointer transition-colors flex gap-3 text-left items-start"
      >
        <!-- Indicator Circle -->
        <span class="mt-1.5 h-2 w-2 rounded-full bg-primary flex-shrink-0"></span>

        <!-- Info -->
        <div class="flex-grow min-w-0">
          <p class="font-semibold text-xs text-neutral-black truncate">{{ item.title }}</p>
          <p class="text-xs text-neutral-muted line-clamp-2 mt-0.5">{{ item.message }}</p>
          <span class="text-[10px] text-neutral-muted font-mono mt-1 block">
            {{ formatTimeAgo(item.created_at) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <button
      @click="handleViewAll"
      class="block w-full py-2.5 bg-neutral-background hover:bg-neutral-ivory/30 text-center font-semibold text-xs text-primary border-t border-neutral-ivory cursor-pointer transition-colors"
    >
      View All Notifications
    </button>
  </div>
</template>
