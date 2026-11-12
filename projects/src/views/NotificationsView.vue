<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationStore } from '../stores/notifications';
import type { PmNotification } from '../api/notifications';
import {
  Bell,
  CheckCheck,
  Search,
  Calendar,
  MessageSquare,
  Sparkles,
  Clock,
  Check,
  ArrowRight,
} from 'lucide-vue-next';

const router = useRouter();
const notificationStore = useNotificationStore();

const currentFilter = ref<'all' | 'unread'>('all');
const searchQuery = ref('');
const currentPage = ref(1);

const load = async () => {
  await notificationStore.fetchNotifications({
    unread: currentFilter.value === 'unread' ? true : undefined,
    search: searchQuery.value.trim() || undefined,
    page: currentPage.value,
  });
};

onMounted(async () => {
  await load();
  await notificationStore.fetchUnreadCount();
});

watch([currentFilter], () => {
  currentPage.value = 1;
  load();
});

let searchTimeout: any = null;
const onSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    load();
  }, 350);
};

const onMarkAsRead = async (notif: PmNotification) => {
  await notificationStore.markAsRead(notif.uuid || notif.id);
};

const onMarkAllAsRead = async () => {
  await notificationStore.markAllAsRead();
  await load();
};

const onNavigate = (notif: PmNotification) => {
  if (!notif.read_at) {
    notificationStore.markAsRead(notif.uuid || notif.id);
  }

  const actionUrl = notif.data?.action_url;
  if (actionUrl) {
    router.push(actionUrl);
  } else if (notif.data?.project_uuid) {
    router.push(`/projects/${notif.data.project_uuid}`);
  }
};

const formatDateTime = (dateStr: string) => {
  try {
    return new Date(dateStr).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  } catch {
    return dateStr;
  }
};

const getNotificationIcon = (type?: string) => {
  switch (type) {
    case 'pm.deadline_warning':
    case 'pm.task_overdue':
      return Calendar;
    case 'pm.comment_mention':
      return MessageSquare;
    case 'pm.milestone_completed':
      return Sparkles;
    case 'pm.daily_digest':
      return Clock;
    default:
      return Bell;
  }
};
</script>

<template>
  <div class="flex flex-col gap-[var(--spacing-section)]">
    <header class="pm-page-header">
      <div class="min-w-0">
        <h1 class="pm-page-title flex items-center gap-2.5">
          <Bell class="w-6 h-6 text-[#640c0e] shrink-0" />
          Notification Center
        </h1>
        <p class="text-xs text-[#6b7280] mt-1">
          Stay informed on task assignments, milestone completions, comments, and project deadlines.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          v-if="notificationStore.hasUnread"
          type="button"
          @click="onMarkAllAsRead"
          class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-white border border-[#ebe8de] text-[#640c0e] hover:bg-[#fbfbf9] shadow-xs transition-colors"
        >
          <CheckCheck class="w-4 h-4" />
          Mark all as read
        </button>
      </div>
    </header>

    <!-- Filters & Search Toolbar -->
    <div class="pm-panel p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <!-- Tabs -->
      <div class="flex items-center gap-1.5 bg-[#fbfbf9] p-1 rounded-xl border border-[#ebe8de]">
        <button
          type="button"
          @click="currentFilter = 'all'"
          :class="[
            'px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-all',
            currentFilter === 'all'
              ? 'bg-white text-[#640c0e] shadow-xs'
              : 'text-[#6b7280] hover:text-[#1e1e1e]',
          ]"
        >
          All
        </button>
        <button
          type="button"
          @click="currentFilter = 'unread'"
          :class="[
            'px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5',
            currentFilter === 'unread'
              ? 'bg-white text-[#640c0e] shadow-xs'
              : 'text-[#6b7280] hover:text-[#1e1e1e]',
          ]"
        >
          Unread
          <span
            v-if="notificationStore.unreadCount > 0"
            class="px-1.5 py-0.2 bg-[#ea2128] text-white text-[10px] rounded-full font-bold"
          >
            {{ notificationStore.unreadCount }}
          </span>
        </button>
      </div>

      <!-- Search Input -->
      <div class="relative w-full sm:w-64">
        <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#9ca3af]" />
        <input
          v-model="searchQuery"
          @input="onSearchInput"
          type="text"
          placeholder="Search notifications..."
          class="w-full pl-9 pr-3 py-1.5 bg-[#fbfbf9] border border-[#ebe8de] rounded-xl text-xs text-[#1e1e1e] placeholder:text-[#9ca3af] focus:outline-hidden focus:border-[#640c0e] transition-colors"
        />
      </div>
    </div>

    <!-- Notification List -->
    <div class="bg-white rounded-2xl border border-[#ebe8de] shadow-xs overflow-hidden">
      <!-- Loading State -->
      <div v-if="notificationStore.loading" class="py-16 text-center">
        <div class="w-8 h-8 border-2 border-[#640c0e] border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-xs text-[#6b7280]">Loading notifications...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="notificationStore.notifications.length === 0" class="py-16 text-center px-4">
        <div class="w-12 h-12 rounded-full bg-[#fbfbf9] border border-[#ebe8de] flex items-center justify-center mx-auto text-[#6b7280] mb-3">
          <Bell class="w-6 h-6 text-[#9ca3af]" />
        </div>
        <h3 class="text-sm font-bold text-[#1e1e1e]">No notifications found</h3>
        <p class="text-xs text-[#6b7280] max-w-sm mx-auto mt-1">
          {{ currentFilter === 'unread' ? 'You have caught up with all notifications.' : 'There are no notification records to display.' }}
        </p>
      </div>

      <!-- Notifications Table / List -->
      <div v-else class="divide-y divide-[#ebe8de]">
        <div
          v-for="notif in notificationStore.notifications"
          :key="notif.id"
          :class="[
            'p-4 transition-colors flex items-start gap-4 group',
            !notif.read_at ? 'bg-[#fbfbf9]/70 hover:bg-[#fbfbf9]' : 'hover:bg-[#fbfbf9]/40',
          ]"
        >
          <!-- Category Icon -->
          <div
            :class="[
              'w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5',
              notif.data?.type === 'pm.task_overdue'
                ? 'bg-red-50 text-red-600'
                : notif.data?.type === 'pm.deadline_warning'
                ? 'bg-amber-50 text-amber-600'
                : notif.data?.type === 'pm.milestone_completed'
                ? 'bg-emerald-50 text-emerald-600'
                : 'bg-[#640c0e]/10 text-[#640c0e]',
            ]"
          >
            <component :is="getNotificationIcon(notif.data?.type)" class="w-5 h-5" />
          </div>

          <!-- Body -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="text-sm font-bold text-[#1e1e1e]">{{ notif.title }}</h3>
              <span
                v-if="!notif.read_at"
                class="px-1.5 py-0.5 text-[10px] font-bold bg-[#ea2128]/10 text-[#ea2128] rounded-full"
              >
                NEW
              </span>
            </div>

            <p class="text-xs text-[#4b5563] mt-1 leading-relaxed">
              {{ notif.message }}
            </p>

            <div class="flex items-center gap-4 mt-2">
              <span class="text-[11px] text-[#9ca3af] font-medium">
                {{ formatDateTime(notif.created_at) }}
              </span>

              <button
                v-if="notif.data?.action_url || notif.data?.project_uuid"
                type="button"
                @click="onNavigate(notif)"
                class="text-xs font-semibold text-[#640c0e] hover:underline flex items-center gap-1"
              >
                View Details
                <ArrowRight class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              v-if="!notif.read_at"
              type="button"
              @click="onMarkAsRead(notif)"
              class="p-1.5 text-[#6b7280] hover:text-[#640c0e] rounded-lg hover:bg-white transition-colors"
              title="Mark as read"
            >
              <Check class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div
        v-if="notificationStore.pagination && notificationStore.pagination.last_page > 1"
        class="p-4 bg-[#fbfbf9] border-t border-[#ebe8de] flex items-center justify-between text-xs text-[#6b7280]"
      >
        <span>
          Showing page {{ notificationStore.pagination.current_page }} of {{ notificationStore.pagination.last_page }} ({{ notificationStore.pagination.total }} total)
        </span>

        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="currentPage <= 1"
            @click="currentPage--; load()"
            class="px-3 py-1.5 bg-white border border-[#ebe8de] rounded-lg font-semibold disabled:opacity-50 hover:bg-[#fbfbf9] transition-colors"
          >
            Previous
          </button>
          <button
            type="button"
            :disabled="currentPage >= notificationStore.pagination.last_page"
            @click="currentPage++; load()"
            class="px-3 py-1.5 bg-white border border-[#ebe8de] rounded-lg font-semibold disabled:opacity-50 hover:bg-[#fbfbf9] transition-colors"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
