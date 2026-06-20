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
  if (t.includes('coursecompleted') || t.includes('course_completion')) return 'bg-emerald-50 text-emerald-600 border-emerald-100';
  if (t.includes('announcement')) return 'bg-blue-50 text-blue-600 border-blue-100';
  if (t.includes('certificate') || t.includes('award')) return 'bg-amber-50 text-amber-600 border-amber-100';
  if (t.includes('training') || t.includes('scheduled')) return 'bg-indigo-50 text-indigo-600 border-indigo-100';
  return 'bg-neutral-50 text-neutral-600 border-neutral-100';
};
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="item in items"
      :key="item.uuid"
      class="p-4 sm:p-5 bg-white rounded-xl border border-neutral-ivory shadow-soft hover:shadow-md transition-all flex flex-col sm:flex-row gap-4 items-start justify-between relative group"
      :class="{ 'border-l-4 border-l-primary bg-primary/[0.01]': !item.read_at }"
    >
      <div class="flex gap-4 items-start min-w-0">
        <!-- Icon Badge -->
        <div
          class="h-10 w-10 rounded-xl flex items-center justify-center border flex-shrink-0"
          :class="getCategoryIconClass(item.type)"
        >
          <!-- Course Completed Icon -->
          <svg v-if="item.type.toLowerCase().includes('coursecompleted') || item.type.toLowerCase().includes('course_completion')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <!-- Announcement Icon -->
          <svg v-else-if="item.type.toLowerCase().includes('announcement')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
          </svg>
          <!-- Certificate Icon -->
          <svg v-else-if="item.type.toLowerCase().includes('certificate') || item.type.toLowerCase().includes('award')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />
          </svg>
          <!-- Training Icon -->
          <svg v-else-if="item.type.toLowerCase().includes('training') || item.type.toLowerCase().includes('scheduled')" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <!-- Standard Bell Icon -->
          <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </div>

        <!-- Copy -->
        <div class="min-w-0 text-left">
          <h4 class="font-bold text-sm text-neutral-black flex items-center gap-2">
            {{ item.title }}
            <span v-if="!item.read_at" class="h-1.5 w-1.5 rounded-full bg-primary flex-shrink-0"></span>
          </h4>
          <p class="text-xs text-neutral-muted mt-1 leading-relaxed">{{ item.message }}</p>
          <span class="text-[10px] text-neutral-muted font-mono mt-2 block">
            {{ formatTimeAgo(item.created_at) }}
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2 self-end sm:self-center">
        <button
          @click="emit('view-details', item)"
          class="px-3 py-1.5 text-xs font-semibold text-primary hover:text-secondary bg-primary/5 hover:bg-primary/10 rounded-lg transition-colors cursor-pointer"
        >
          View details
        </button>
        <button
          v-if="!item.read_at"
          @click="notificationsStore.markAsRead(item.uuid)"
          class="p-1.5 text-neutral-muted hover:text-primary rounded-lg hover:bg-neutral-background cursor-pointer transition-colors"
          title="Mark as read"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </button>
        <button
          @click="notificationsStore.deleteNotification(item.uuid)"
          class="p-1.5 text-neutral-muted hover:text-red-600 rounded-lg hover:bg-red-50 cursor-pointer transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100"
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
