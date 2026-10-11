<script setup lang="ts">
import { type Notification } from '@/services/notifications';
import { useNotificationsStore } from '@/stores/notifications';

defineProps<{
  items: Notification[];
}>();

const emit = defineEmits(['view-details']);
const notificationsStore = useNotificationsStore();

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
  
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

const getCategoryIconClass = (type: string) => {
  const t = type.toLowerCase();
  if (t.includes('event') || t.includes('ticket')) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
  if (t.includes('volunteer') || t.includes('shift')) return 'bg-rose-50 text-rose-700 border-rose-200';
  if (t.includes('store') || t.includes('order')) return 'bg-indigo-50 text-indigo-700 border-indigo-200';
  if (t.includes('coursecompleted') || t.includes('course_completion') || t.includes('course')) return 'bg-teal-50 text-teal-700 border-teal-200';
  if (t.includes('announcement')) return 'bg-blue-50 text-blue-700 border-blue-200';
  if (t.includes('certificate') || t.includes('award')) return 'bg-amber-50 text-amber-700 border-amber-200';
  return 'bg-neutral-100 text-neutral-700 border-neutral-200';
};
</script>

<template>
  <div class="space-y-3.5">
    <div
      v-for="item in items"
      :key="item.uuid"
      class="p-5 bg-white rounded-2xl border border-neutral-ivory shadow-soft hover:shadow-md transition-all flex flex-col sm:flex-row gap-4 items-start justify-between relative group"
      :class="{ 'border-l-4 border-l-amber-500 bg-amber-50/20': !item.read_at }"
    >
      <div class="flex gap-4 items-start min-w-0">
        <!-- Gold Indicator Dot for Unread -->
        <span v-if="!item.read_at" class="mt-2.5 h-2.5 w-2.5 rounded-full bg-amber-400 border border-amber-500 shadow-sm flex-shrink-0 animate-pulse"></span>
        <span v-else class="mt-2.5 h-2.5 w-2.5 rounded-full bg-neutral-200 flex-shrink-0"></span>

        <!-- Icon Badge -->
        <div
          class="h-11 w-11 rounded-2xl flex items-center justify-center border flex-shrink-0 shadow-sm"
          :class="getCategoryIconClass(item.type)"
        >
          <!-- Event / Ticket Icon -->
          <svg v-if="item.type.toLowerCase().includes('event') || item.type.toLowerCase().includes('ticket')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
          </svg>
          <!-- Volunteer / Shift Icon -->
          <svg v-else-if="item.type.toLowerCase().includes('volunteer') || item.type.toLowerCase().includes('shift')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
          </svg>
          <!-- Store Order Icon -->
          <svg v-else-if="item.type.toLowerCase().includes('store') || item.type.toLowerCase().includes('order')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
          </svg>
          <!-- Course Icon -->
          <svg v-else-if="item.type.toLowerCase().includes('course')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          <!-- Announcement Icon -->
          <svg v-else-if="item.type.toLowerCase().includes('announcement')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
          </svg>
          <!-- Certificate Icon -->
          <svg v-else-if="item.type.toLowerCase().includes('certificate') || item.type.toLowerCase().includes('award')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
          </svg>
          <!-- Standard Bell Icon -->
          <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </div>

        <!-- Copy -->
        <div class="min-w-0 text-left">
          <div class="flex items-center gap-2 flex-wrap">
            <h4 class="font-extrabold text-sm text-neutral-black tracking-tight">
              {{ item.title }}
            </h4>
            <span v-if="!item.read_at" class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 border border-amber-500/20">
              Unread
            </span>
          </div>
          <p class="text-xs text-neutral-black/70 mt-1 leading-relaxed font-medium">{{ item.message }}</p>
          <span class="text-[10px] text-neutral-black/40 font-mono font-bold mt-2 block">
            {{ formatTimeAgo(item.created_at) }}
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
        <button
          @click="emit('view-details', item)"
          class="px-3.5 py-2 text-xs font-extrabold uppercase tracking-wider text-primary hover:text-white bg-primary/5 hover:bg-primary rounded-xl transition-all cursor-pointer shadow-sm"
        >
          View details
        </button>
        <button
          v-if="!item.read_at"
          @click="notificationsStore.markAsRead(item.uuid)"
          class="p-2 text-neutral-black/50 hover:text-primary rounded-xl hover:bg-neutral-background cursor-pointer transition-colors border border-transparent hover:border-neutral-ivory"
          title="Mark as read"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </button>
        <button
          @click="notificationsStore.deleteNotification(item.uuid)"
          class="p-2 text-neutral-black/40 hover:text-red-600 rounded-xl hover:bg-red-50 cursor-pointer transition-colors opacity-80 sm:opacity-0 group-hover:opacity-100 focus:opacity-100 border border-transparent hover:border-red-100"
          title="Delete notification"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>
