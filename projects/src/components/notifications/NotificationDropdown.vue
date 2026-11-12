<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useNotificationStore } from '../../stores/notifications';
import { useAuthStore } from '../../stores/auth';
import type { PmNotification } from '../../api/notifications';
import {
  Bell,
  Check,
  CheckCheck,
  X,
  Clock,
  Calendar,
  MessageSquare,
  Sparkles,
  ChevronRight,
} from 'lucide-vue-next';

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const router = useRouter();
const notificationStore = useNotificationStore();
const authStore = useAuthStore();

const activeTab = ref<'notifications' | 'invitations'>('notifications');

onMounted(async () => {
  await Promise.all([
    notificationStore.fetchUnreadCount(),
    authStore.fetchPendingInvitations(),
  ]);
});

const onAcceptInvite = async (memberId: number) => {
  await authStore.acceptInvitation(memberId);
};

const onDeclineInvite = async (memberId: number) => {
  await authStore.declineInvitation(memberId);
};

const onNotificationClick = async (notif: PmNotification) => {
  if (!notif.read_at) {
    await notificationStore.markAsRead(notif.uuid || notif.id);
  }

  emit('close');

  const actionUrl = notif.data?.action_url;
  if (actionUrl) {
    router.push(actionUrl);
  } else if (notif.data?.project_uuid) {
    router.push(`/projects/${notif.data.project_uuid}`);
  } else {
    router.push('/notifications');
  }
};

const onMarkAllRead = async () => {
  await notificationStore.markAllAsRead();
};

const onViewAll = () => {
  emit('close');
  router.push('/notifications');
};

const formatTimeAgo = (dateStr: string) => {
  try {
    const diffMs = Date.now() - new Date(dateStr).getTime();
    const diffSec = Math.floor(diffMs / 1000);
    if (diffSec < 60) return 'Just now';
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHours = Math.floor(diffMin / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    const diffDays = Math.floor(diffHours / 24);
    return `${diffDays}d ago`;
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
  <div class="w-80 sm:w-96 bg-white rounded-2xl shadow-elevated border border-[#ebe8de] overflow-hidden z-50 animate-in fade-in zoom-in-95 duration-150">
    <!-- Header with Tabs -->
    <div class="p-3 bg-[#fbfbf9] border-b border-[#ebe8de] flex items-center justify-between">
      <div class="flex items-center gap-1.5 p-0.5 bg-[#ebe8de]/60 rounded-xl">
        <button
          type="button"
          @click="activeTab = 'notifications'"
          :class="[
            'px-3 py-1 text-xs font-semibold rounded-lg transition-all',
            activeTab === 'notifications'
              ? 'bg-white text-[#640c0e] shadow-xs'
              : 'text-[#6b7280] hover:text-[#1e1e1e]',
          ]"
        >
          Notifications
          <span
            v-if="notificationStore.unreadCount > 0"
            class="ml-1 px-1.5 py-0.2 bg-[#ea2128] text-white text-[10px] rounded-full"
          >
            {{ notificationStore.unreadCount }}
          </span>
        </button>
        <button
          type="button"
          @click="activeTab = 'invitations'"
          :class="[
            'px-3 py-1 text-xs font-semibold rounded-lg transition-all',
            activeTab === 'invitations'
              ? 'bg-white text-[#640c0e] shadow-xs'
              : 'text-[#6b7280] hover:text-[#1e1e1e]',
          ]"
        >
          Invitations
          <span
            v-if="authStore.pendingInvitations.length > 0"
            class="ml-1 px-1.5 py-0.2 bg-[#640c0e] text-white text-[10px] rounded-full"
          >
            {{ authStore.pendingInvitations.length }}
          </span>
        </button>
      </div>

      <button
        v-if="activeTab === 'notifications' && notificationStore.hasUnread"
        type="button"
        @click="onMarkAllRead"
        class="text-[11px] font-medium text-[#640c0e] hover:underline flex items-center gap-1"
        title="Mark all as read"
      >
        <CheckCheck class="w-3.5 h-3.5" />
        Mark read
      </button>
    </div>

    <!-- Tab 1: General Notifications -->
    <div v-if="activeTab === 'notifications'">
      <div v-if="notificationStore.latestUnread.length === 0" class="py-10 text-center px-4">
        <div class="w-10 h-10 rounded-full bg-[#fbfbf9] border border-[#ebe8de] flex items-center justify-center mx-auto text-[#6b7280] mb-2.5">
          <Bell class="w-5 h-5" />
        </div>
        <p class="text-xs font-semibold text-[#1e1e1e]">You're all caught up!</p>
        <p class="text-[11px] text-[#6b7280] mt-0.5">No unread notifications right now.</p>
      </div>

      <div v-else class="divide-y divide-[#ebe8de] max-h-80 overflow-y-auto">
        <div
          v-for="notif in notificationStore.latestUnread"
          :key="notif.id"
          @click="onNotificationClick(notif)"
          class="p-3 hover:bg-[#fbfbf9] cursor-pointer transition-colors flex items-start gap-3 group relative"
        >
          <div
            :class="[
              'w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5',
              notif.data?.type === 'pm.task_overdue'
                ? 'bg-red-50 text-red-600'
                : notif.data?.type === 'pm.deadline_warning'
                ? 'bg-amber-50 text-amber-600'
                : notif.data?.type === 'pm.milestone_completed'
                ? 'bg-emerald-50 text-emerald-600'
                : 'bg-[#640c0e]/10 text-[#640c0e]',
            ]"
          >
            <component :is="getNotificationIcon(notif.data?.type)" class="w-4 h-4" />
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
              <h4 class="text-xs font-bold text-[#1e1e1e] truncate group-hover:text-[#640c0e] transition-colors">
                {{ notif.title }}
              </h4>
              <span class="text-[10px] text-[#9ca3af] shrink-0 font-medium">
                {{ formatTimeAgo(notif.created_at) }}
              </span>
            </div>
            <p class="text-xs text-[#4b5563] line-clamp-2 mt-0.5 leading-relaxed">
              {{ notif.message }}
            </p>
          </div>

          <!-- Unread dot -->
          <span
            v-if="!notif.read_at"
            class="w-2 h-2 rounded-full bg-[#ea2128] shrink-0 mt-1.5"
            title="Unread"
          ></span>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-2.5 bg-[#fbfbf9] border-t border-[#ebe8de] text-center">
        <button
          type="button"
          @click="onViewAll"
          class="text-xs font-semibold text-[#640c0e] hover:text-[#7e1012] flex items-center justify-center gap-1.5 w-full py-1"
        >
          View all notifications
          <ChevronRight class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Tab 2: Project Invitations (Preserved) -->
    <div v-else>
      <div v-if="authStore.pendingInvitations.length === 0" class="py-10 text-center px-4">
        <p class="text-xs font-medium text-[#6b7280]">No pending project invitations.</p>
      </div>

      <div v-else class="divide-y divide-[#ebe8de] max-h-80 overflow-y-auto p-2 space-y-2">
        <div
          v-for="inv in authStore.pendingInvitations"
          :key="inv.id"
          class="p-2.5 flex flex-col gap-2 hover:bg-[#fbfbf9] rounded-xl border border-[#ebe8de] transition-colors"
        >
          <div>
            <h4 class="text-xs font-bold text-[#1e1e1e]">{{ inv.project?.name }}</h4>
            <p class="text-[11px] text-[#6b7280] mt-0.5">
              Invited as <span class="font-semibold text-[#640c0e] capitalize">{{ inv.project_role }}</span>
              <span v-if="inv.invited_by"> by {{ inv.invited_by.name }}</span>
            </p>
          </div>

          <div class="flex items-center gap-2 mt-0.5">
            <button
              type="button"
              @click="onAcceptInvite(inv.id)"
              class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#640c0e] text-white hover:bg-[#7e1012] transition-colors"
            >
              <Check class="w-3.5 h-3.5" /> Accept
            </button>
            <button
              type="button"
              @click="onDeclineInvite(inv.id)"
              class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white text-[#6b7280] hover:text-[#ea2128] border border-[#ebe8de] hover:border-[#ea2128]/30 transition-colors"
            >
              <X class="w-3.5 h-3.5" /> Decline
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
