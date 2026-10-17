<template>
  <div class="max-w-4xl mx-auto flex flex-col h-[calc(100vh-12rem)] min-h-[550px] space-y-4">
    <!-- Top Header Bar -->
    <header class="community-card p-4 sm:p-5 bg-white border border-[#ebe8de] shrink-0">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <!-- Back and Inquiry Info -->
        <div class="flex items-start sm:items-center gap-3 min-w-0">
          <router-link
            to="/app/support"
            class="p-2 rounded-xl text-neutral-500 hover:text-[#640c0e] hover:bg-[#ebe8de]/60 transition-colors shrink-0"
            aria-label="Back to inquiries list"
          >
            <ArrowLeft class="w-5 h-5" />
          </router-link>

          <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap text-xs">
              <span class="font-mono font-semibold text-neutral-500 text-[11px]">
                #{{ referenceCode }}
              </span>
              <Badge :variant="getCategoryBadgeVariant(inquiry?.category || '')" size="sm">
                {{ formatCategory(inquiry?.category || '') }}
              </Badge>
              <Badge :variant="getStatusBadgeVariant(inquiry?.status || 'open')" size="sm">
                {{ formatStatus(inquiry?.status || 'open') }}
              </Badge>
              <span class="flex items-center gap-1 text-[11px] text-neutral-400 font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Active thread</span>
              </span>
            </div>

            <h1 class="text-base sm:text-lg font-bold text-[#640c0e] truncate">
              {{ inquiry?.subject || 'Support Conversation' }}
            </h1>
          </div>
        </div>

        <!-- Header Actions -->
        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
          <Button
            v-if="inquiry && (inquiry.status === 'open' || inquiry.status === 'in_progress')"
            variant="outline"
            size="sm"
            :is-loading="isClosing"
            @click="handleCloseInquiry"
            class="text-xs text-neutral-600 hover:text-red-700"
            title="Mark this inquiry as resolved / closed"
          >
            <template #left-icon>
              <CheckCircle class="w-3.5 h-3.5 text-emerald-600" />
            </template>
            <span>Close Inquiry</span>
          </Button>

          <Button
            variant="outline"
            size="sm"
            :is-loading="supportStore.isLoading"
            @click="refreshThread"
            title="Refresh messages"
          >
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': supportStore.isLoading }" />
          </Button>
        </div>
      </div>
    </header>

    <!-- Error State -->
    <ErrorState
      v-if="supportStore.error && !inquiry"
      title="Inquiry Unavailable"
      :message="supportStore.error"
      @retry="loadThread"
    />

    <!-- Initial Loading State -->
    <div
      v-else-if="supportStore.isLoading && !inquiry"
      class="flex-1 flex items-center justify-center"
    >
      <div class="text-center space-y-3">
        <Spinner size="lg" />
        <p class="text-xs text-neutral-500 font-medium">Loading conversation...</p>
      </div>
    </div>

    <!-- Active Conversation Card -->
    <div
      v-else
      class="community-card flex-1 flex flex-col bg-[#fffbf4]/40 border border-[#ebe8de] overflow-hidden shadow-xs"
    >
      <!-- Message Scroll Area -->
      <div
        ref="messagesContainer"
        class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4"
        tabindex="0"
        aria-label="Conversation message history"
      >
        <!-- Notice of inquiry creation -->
        <div class="text-center py-2">
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white border border-[#ebe8de] text-[11px] text-neutral-500">
            <Lock class="w-3 h-3 text-neutral-400" />
            <span>Private inquiry opened on {{ formatDateTime(inquiry?.created_at || '') }}</span>
          </div>
        </div>

        <!-- Messages Flow -->
        <div
          v-for="msg in messages"
          :key="msg.id"
          class="flex flex-col"
          :class="msg.is_staff_reply ? 'items-start' : 'items-end'"
        >
          <!-- Bubble Container -->
          <div
            class="max-w-[85%] sm:max-w-[75%] rounded-2xl p-4 space-y-1.5 shadow-xs transition-shadow"
            :class="msg.is_staff_reply
              ? 'bg-white border border-[#ebe8de] text-neutral-900 rounded-tl-xs'
              : 'bg-gradient-to-br from-[#640c0e] to-[#7d1316] text-white rounded-tr-xs'"
          >
            <!-- Sender Header -->
            <div class="flex items-center justify-between gap-4 text-[11px] pb-1 border-b"
              :class="msg.is_staff_reply ? 'border-[#ebe8de] text-neutral-500' : 'border-white/20 text-[#ffdc83]'"
            >
              <div class="flex items-center gap-1.5 font-bold">
                <span v-if="msg.is_staff_reply" class="flex items-center gap-1 text-[#b02e32]">
                  <ShieldCheck class="w-3.5 h-3.5 text-[#b02e32]" />
                  <span>{{ msg.sender_name || (msg as any).user?.name || 'SFU MSA Staff' }}</span>
                </span>
                <span v-else>
                  {{ msg.sender_name || (msg as any).user?.name || 'You' }}
                </span>
              </div>

              <span class="text-[10px] font-medium opacity-80">
                {{ formatDateTime(msg.created_at) }}
              </span>
            </div>

            <!-- Message Content -->
            <div
              class="text-xs sm:text-sm leading-relaxed whitespace-pre-wrap break-words"
              :class="msg.is_staff_reply ? 'text-neutral-800' : 'text-white'"
            >
              {{ msg.body || (msg as any).message }}
            </div>
          </div>
        </div>

        <!-- Status Closed Banner if inquiry is closed -->
        <div v-if="isClosed" class="text-center py-4">
          <div class="inline-flex flex-col items-center gap-1 p-3 rounded-2xl bg-neutral-100 border border-neutral-200 text-xs text-neutral-600 max-w-md mx-auto">
            <span class="font-bold text-neutral-700">Inquiry Closed</span>
            <span>This inquiry has been marked as {{ inquiry?.status }}. If you have additional questions, feel free to open a new request.</span>
          </div>
        </div>
      </div>

      <!-- Composer Bar (Bottom) -->
      <footer class="p-3 sm:p-4 bg-white border-t border-[#ebe8de] shrink-0">
        <!-- When Open/In Progress: Interactive Composer -->
        <form
          v-if="!isClosed"
          @submit.prevent="handleSendReply"
          class="space-y-2"
        >
          <div class="flex items-end gap-2">
            <div class="flex-1 relative">
              <textarea
                ref="replyInput"
                v-model="replyText"
                rows="2"
                placeholder="Write a message... (Press Ctrl+Enter to send)"
                maxlength="5000"
                class="w-full px-3.5 py-2 rounded-xl border border-[#ebe8de] bg-[#fffbf4]/40 text-xs sm:text-sm text-neutral-800 placeholder-neutral-400 focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e] leading-relaxed resize-none"
                @keydown.enter.ctrl="handleSendReply"
              ></textarea>
            </div>

            <Button
              variant="primary"
              size="md"
              type="submit"
              :is-loading="supportStore.isSending"
              :disabled="!replyText.trim() || supportStore.isSending"
              class="h-10 px-4 shrink-0"
              title="Send reply"
            >
              <template #left-icon>
                <Send class="w-4 h-4" />
              </template>
              <span class="hidden sm:inline">Send</span>
            </Button>
          </div>

          <div class="flex items-center justify-between text-[11px] text-neutral-400 px-1">
            <span>Confidential member inquiry</span>
            <span>{{ replyText.length }}/5,000</span>
          </div>
        </form>

        <!-- When Closed: Link to new inquiry -->
        <div v-else class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-neutral-500 py-1">
          <span>This conversation is closed.</span>
          <router-link to="/app/support/new">
            <Button variant="primary" size="sm">
              <template #left-icon>
                <Plus class="w-3.5 h-3.5" />
              </template>
              <span>Start New Inquiry</span>
            </Button>
          </router-link>
        </div>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useSupportStore } from '@/stores/support';
import { formatEventDateTime } from '@/utils/formatters';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import {
  ArrowLeft,
  Lock,
  ShieldCheck,
  Send,
  Plus,
  CheckCircle,
  RefreshCw,
} from 'lucide-vue-next';

const route = useRoute();
const supportStore = useSupportStore();

const referenceCode = computed(() => String(route.params.referenceCode || route.params.id || ''));
const inquiry = computed(() => supportStore.activeInquiry);
const messages = computed(() => inquiry.value?.messages || []);
const isClosed = computed(() => inquiry.value?.status === 'closed' || inquiry.value?.status === 'resolved');

const replyText = ref('');
const isClosing = ref(false);
const messagesContainer = ref<HTMLDivElement | null>(null);
const replyInput = ref<HTMLTextAreaElement | null>(null);

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
  });
}

function formatCategory(category: string): string {
  if (!category) return 'General';
  const clean = category.replace(/_/g, ' ');
  return clean.charAt(0).toUpperCase() + clean.slice(1);
}

function formatStatus(status: string): string {
  if (status === 'in_progress' || status === 'in_review') return 'In Progress';
  if (status === 'awaiting_member') return 'Awaiting Your Reply';
  if (status === 'awaiting_staff') return 'Awaiting Staff';
  if (status === 'resolved') return 'Resolved';
  if (status === 'closed') return 'Closed';
  return 'Open';
}

function getCategoryBadgeVariant(category: string): 'burgundy' | 'gold' | 'green' | 'neutral' | 'blue' | 'red' {
  switch (category) {
    case 'new_muslim':
    case 'revert_support':
      return 'gold';
    case 'faith_questions':
    case 'spiritual':
      return 'burgundy';
    case 'academic':
      return 'blue';
    case 'campus_prayer':
      return 'green';
    case 'event_support':
    case 'events':
      return 'green';
    default:
      return 'neutral';
  }
}

function getStatusBadgeVariant(status: string): 'burgundy' | 'gold' | 'green' | 'neutral' | 'blue' | 'red' {
  switch (status) {
    case 'open':
      return 'gold';
    case 'in_progress':
    case 'in_review':
      return 'blue';
    case 'awaiting_member':
      return 'burgundy';
    case 'awaiting_staff':
      return 'gold';
    case 'resolved':
      return 'green';
    case 'closed':
    default:
      return 'neutral';
  }
}

function formatDateTime(dateStr: string): string {
  if (!dateStr) return '';
  return formatEventDateTime(dateStr);
}

async function loadThread() {
  if (!referenceCode.value) return;
  const res = await supportStore.fetchInquiry(referenceCode.value);
  if (res) {
    scrollToBottom();
  }
}

async function refreshThread() {
  if (!referenceCode.value) return;
  await supportStore.fetchInquiry(referenceCode.value);
  scrollToBottom();
}

async function handleSendReply() {
  const text = replyText.value.trim();
  if (!text || supportStore.isSending || !referenceCode.value) return;

  try {
    await supportStore.sendReply(referenceCode.value, text);
    replyText.value = '';
    scrollToBottom();
  } catch (e) {
    console.warn('Failed to send reply:', e);
  }
}

async function handleCloseInquiry() {
  if (!referenceCode.value || isClosing.value) return;
  if (!confirm('Are you sure you want to close this inquiry? You can always create a new request later.')) {
    return;
  }

  isClosing.value = true;
  try {
    await supportStore.closeInquiry(referenceCode.value);
  } finally {
    isClosing.value = false;
  }
}

onMounted(async () => {
  await loadThread();
  if (referenceCode.value) {
    supportStore.startPolling(referenceCode.value);
  }
});

onUnmounted(() => {
  supportStore.stopPolling();
});
</script>
