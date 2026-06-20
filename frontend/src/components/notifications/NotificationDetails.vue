<script setup lang="ts">
import { type Notification } from '@/services/notifications';
import { computed } from 'vue';

const props = defineProps<{
  notification: Notification | null;
  isOpen: boolean;
}>();

const emit = defineEmits(['close']);

const formatDate = (dateStr: string) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
};

const categoryName = computed(() => {
  if (!props.notification) return '';
  const t = props.notification.type.toLowerCase();
  if (t.includes('coursecompleted') || t.includes('course_completion')) return 'Course Completion';
  if (t.includes('announcement')) return 'Announcement';
  if (t.includes('certificate') || t.includes('award')) return 'Academy Credential';
  if (t.includes('training') || t.includes('scheduled')) return 'Upcoming Event';
  return 'System Notification';
});
</script>

<template>
  <div
    v-if="isOpen && notification"
    class="fixed inset-0 overflow-hidden z-50 flex items-center justify-end"
  >
    <!-- Overlay -->
    <div
      @click="emit('close')"
      class="absolute inset-0 bg-neutral-black/40 backdrop-blur-sm transition-opacity"
    ></div>

    <!-- Drawer Panel -->
    <div class="relative w-full max-w-md bg-white h-full shadow-2xl flex flex-col justify-between transform transition-transform duration-300">
      <!-- Header -->
      <div class="px-6 py-5 border-b border-neutral-ivory flex items-center justify-between">
        <div>
          <span class="inline-block text-[10px] font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-full uppercase tracking-wider">
            {{ categoryName }}
          </span>
          <h3 class="font-bold text-lg text-neutral-black mt-1">Notification Details</h3>
        </div>
        <button
          @click="emit('close')"
          class="p-2 text-neutral-muted hover:text-neutral-black rounded-lg hover:bg-neutral-background cursor-pointer"
        >
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Body -->
      <div class="flex-grow p-6 overflow-y-auto space-y-6 text-left">
        <!-- Main Info -->
        <div>
          <h4 class="font-bold text-base text-neutral-black">{{ notification.title }}</h4>
          <p class="text-sm text-neutral-muted mt-2 whitespace-pre-wrap leading-relaxed">
            {{ notification.message }}
          </p>
          <span class="text-xs text-neutral-muted font-mono mt-4 block">
            Received: {{ formatDate(notification.created_at) }}
          </span>
        </div>

        <!-- Custom Metadata Context Box -->
        <div
          v-if="notification.data && Object.keys(notification.data).length > 0"
          class="bg-neutral-background p-4 rounded-xl border border-neutral-ivory"
        >
          <h5 class="font-bold text-xs text-neutral-black uppercase tracking-wider mb-3">Context Details</h5>
          
          <div class="space-y-2.5 text-xs text-neutral-muted">
            <!-- Course Completion Metadata -->
            <template v-if="notification.type.toLowerCase().includes('coursecompleted') || notification.type.toLowerCase().includes('course_completion')">
              <p><span class="font-semibold text-neutral-black">Course Completed:</span> {{ notification.data.course_name }}</p>
              <p><span class="font-semibold text-neutral-black">Date Completed:</span> {{ notification.data.completion_date ? formatDate(notification.data.completion_date) : 'Recently' }}</p>
            </template>

            <!-- Certificate Metadata -->
            <template v-else-if="notification.type.toLowerCase().includes('certificate') || notification.type.toLowerCase().includes('award')">
              <p><span class="font-semibold text-neutral-black">Credential Code:</span> <code class="bg-white border border-neutral-ivory px-1.5 py-0.5 rounded font-mono font-bold text-primary">{{ notification.data.code }}</code></p>
              <p v-if="notification.data.verify_url">
                <span class="font-semibold text-neutral-black">Verification URL:</span> 
                <a :href="notification.data.verify_url" target="_blank" class="text-primary hover:underline font-semibold block truncate mt-0.5">{{ notification.data.verify_url }}</a>
              </p>
            </template>

            <!-- Training Metadata -->
            <template v-else-if="notification.type.toLowerCase().includes('training') || notification.type.toLowerCase().includes('scheduled')">
              <p><span class="font-semibold text-neutral-black">Training Session:</span> {{ notification.data.training_title }}</p>
              <p><span class="font-semibold text-neutral-black">Scheduled Time:</span> {{ notification.data.training_date }}</p>
              <p><span class="font-semibold text-neutral-black">Location:</span> {{ notification.data.training_location }}</p>
            </template>

            <!-- General Announcement Metadata -->
            <template v-else-if="notification.type.toLowerCase().includes('announcement')">
              <p v-if="notification.data.audience"><span class="font-semibold text-neutral-black">Target Audience:</span> {{ notification.data.audience }}</p>
            </template>
          </div>
        </div>
      </div>

      <!-- Action Button Footer -->
      <div class="p-6 border-t border-neutral-ivory bg-neutral-background flex flex-col gap-2">
        <template v-if="notification.type.toLowerCase().includes('certificate') || notification.type.toLowerCase().includes('award')">
          <a
            v-if="notification.data.verify_url"
            :href="notification.data.verify_url"
            target="_blank"
            class="w-full py-2.5 bg-primary hover:bg-secondary text-white text-center font-bold text-sm rounded-xl transition-colors shadow-soft"
          >
            Verify Credential
          </a>
        </template>
        
        <button
          @click="emit('close')"
          class="w-full py-2.5 bg-white hover:bg-neutral-ivory/20 text-neutral-black border border-neutral-ivory text-center font-bold text-sm rounded-xl transition-colors cursor-pointer"
        >
          Close Detail View
        </button>
      </div>
    </div>
  </div>
</template>
