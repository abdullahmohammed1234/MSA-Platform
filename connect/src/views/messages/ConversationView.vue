<template>
  <div class="space-y-4 max-w-4xl mx-auto">
    <!-- Top Navigation / Header -->
    <div class="flex items-center justify-between gap-3 pb-2 border-b border-[#ebe8de]">
      <router-link
        to="/app/messages"
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600 hover:text-[#640c0e] transition-colors focus:outline-none focus:ring-1 focus:ring-[#640c0e] rounded-md px-1 py-0.5"
      >
        <ArrowLeft class="w-4 h-4" />
        <span>Back to Inbox</span>
      </router-link>

      <span class="text-[11px] text-neutral-400 font-medium">
        Private Direct Messaging
      </span>
    </div>

    <!-- Loading State -->
    <div
      v-if="messagingStore.isLoadingMessages && !conversation"
      class="py-24 flex flex-col items-center justify-center space-y-3"
    >
      <Spinner size="lg" />
      <p class="text-xs text-neutral-500 font-medium">Loading conversation...</p>
    </div>

    <!-- Error State -->
    <ErrorState
      v-else-if="messagingStore.error && !conversation"
      title="Conversation Unavailable"
      :message="messagingStore.error"
      @retry="loadData"
    />

    <!-- Main Conversation Card -->
    <div v-else class="community-card bg-white border border-[#ebe8de] shadow-xs rounded-2xl flex flex-col h-[75vh] max-h-[750px] overflow-hidden">
      <!-- Participant Header Bar -->
      <div class="p-4 sm:p-5 border-b border-[#ebe8de] bg-[#fffbf4]/60 flex items-center justify-between gap-4 shrink-0">
        <div class="flex items-center gap-3 min-w-0">
          <div
            class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#640c0e] text-[#ffdc83] font-bold text-sm flex items-center justify-center shrink-0 ring-1 ring-[#ffdc83]/60 shadow-xs"
          >
            {{ getInitials(conversation?.other_participant?.name || 'Admin') }}
          </div>
          <div class="space-y-0.5 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <h2 class="text-sm sm:text-base font-bold text-[#640c0e] truncate">
                {{ conversation?.other_participant?.name || 'MSA Administrator' }}
              </h2>
              <Badge
                :variant="conversation?.other_participant?.is_admin ? 'gold' : 'neutral'"
                size="sm"
              >
                {{ conversation?.other_participant?.role_label || 'Administrator' }}
              </Badge>
            </div>
            <p class="text-[11px] text-neutral-500 flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span>Encrypted server-side & restricted to authorized participants</span>
            </p>
          </div>
        </div>

        <button
          type="button"
          @click="loadData"
          class="p-2 rounded-lg text-neutral-400 hover:text-[#640c0e] hover:bg-[#fffbf4] transition-colors focus:outline-none"
          title="Refresh messages"
        >
          <RefreshCw class="w-4 h-4" :class="messagingStore.isLoadingMessages ? 'animate-spin' : ''" />
        </button>
      </div>

      <!-- Messages Thread Area -->
      <div
        ref="messagesContainerRef"
        class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-4 bg-gradient-to-b from-white to-[#fffbf4]/30"
      >
        <!-- Empty Messages Note -->
        <div
          v-if="messagingStore.messages.length === 0"
          class="h-full flex flex-col items-center justify-center text-center p-6 space-y-2"
        >
          <MessageSquare class="w-8 h-8 text-[#640c0e]/40" />
          <h3 class="text-sm font-bold text-neutral-700">No messages yet</h3>
          <p class="text-xs text-neutral-500 max-w-sm">
            This is the start of your direct conversation with
            <span class="font-semibold text-neutral-800">{{ conversation?.other_participant?.name || 'the administrator' }}</span>.
            Send a message below to begin.
          </p>
        </div>

        <!-- Message Bubbles -->
        <div
          v-for="msg in messagingStore.messages"
          :key="msg.uuid || msg.id"
          class="flex flex-col"
          :class="msg.is_mine ? 'items-end' : 'items-start'"
        >
          <!-- Sender Label (for other party) -->
          <span
            v-if="!msg.is_mine"
            class="text-[10px] text-neutral-500 font-semibold mb-1 ml-1"
          >
            {{ msg.sender?.name || conversation?.other_participant?.name || 'Administrator' }}
          </span>

          <!-- Message Bubble -->
          <div
            class="max-w-[85%] sm:max-w-md rounded-2xl px-4 py-3 text-xs sm:text-sm leading-relaxed whitespace-pre-wrap break-words shadow-2xs"
            :class="msg.is_mine
              ? 'bg-[#640c0e] text-white rounded-br-xs'
              : 'bg-white border border-[#ebe8de] text-neutral-900 rounded-bl-xs'"
          >
            {{ msg.body }}
          </div>

          <!-- Timestamp -->
          <span
            class="text-[10px] text-neutral-400 mt-1"
            :class="msg.is_mine ? 'mr-1' : 'ml-1'"
          >
            {{ formatMessageTime(msg.created_at) }}
          </span>
        </div>
      </div>

      <!-- Reply Composer Footer -->
      <div class="p-4 sm:p-5 border-t border-[#ebe8de] bg-white shrink-0 space-y-3">
        <!-- Error Banner -->
        <div
          v-if="sendError"
          class="p-2.5 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex items-center justify-between gap-2"
        >
          <span>{{ sendError }}</span>
          <button
            type="button"
            @click="sendError = null"
            class="text-red-500 hover:text-red-800 text-xs font-bold"
          >
            ✕
          </button>
        </div>

        <form @submit.prevent="handleSendMessage" class="space-y-2">
          <div class="relative">
            <textarea
              ref="textareaRef"
              v-model="replyText"
              rows="3"
              maxlength="5000"
              placeholder="Type your reply here... (Shift+Enter for newline, Enter to send)"
              @keydown.enter.exact.prevent="handleSendMessage"
              class="w-full px-4 py-3 rounded-xl border border-[#ebe8de] text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-[#640c0e]/20 focus:border-[#640c0e] resize-none pr-12 transition-all"
            ></textarea>

            <span class="absolute bottom-2.5 right-3 text-[10px] text-neutral-400 font-mono">
              {{ replyText.length }}/5000
            </span>
          </div>

          <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] text-neutral-400 hidden sm:inline">
              Press <kbd class="px-1.5 py-0.5 rounded bg-neutral-100 border border-neutral-300 font-mono text-[10px]">Enter</kbd> to send
            </span>

            <Button
              type="submit"
              variant="primary"
              size="sm"
              class="bg-[#640c0e] text-[#ffdc83] font-semibold text-xs ml-auto"
              :disabled="!replyText.trim() || messagingStore.isSending"
            >
              <template #left-icon>
                <Spinner v-if="messagingStore.isSending" size="sm" />
                <Send v-else class="w-3.5 h-3.5" />
              </template>
              <span>{{ messagingStore.isSending ? 'Sending...' : 'Send' }}</span>
            </Button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useMessagingStore } from '@/stores/messaging';
import Button from '@/components/ui/Button.vue';
import Badge from '@/components/ui/Badge.vue';
import Spinner from '@/components/ui/Spinner.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import {
  ArrowLeft,
  RefreshCw,
  Send,
  MessageSquare,
} from 'lucide-vue-next';

const route = useRoute();
const messagingStore = useMessagingStore();

const replyText = ref('');
const sendError = ref<string | null>(null);
const messagesContainerRef = ref<HTMLDivElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);

const conversationId = computed(() => route.params.id as string);
const conversation = computed(() => messagingStore.activeConversation);

onMounted(async () => {
  await loadData();
});

watch(
  () => route.params.id,
  async (newId) => {
    if (newId) {
      await loadData();
    }
  }
);

async function loadData() {
  sendError.value = null;
  if (!conversationId.value) return;

  await messagingStore.fetchConversation(conversationId.value);
  await messagingStore.fetchMessages(conversationId.value);
  await messagingStore.markAsRead(conversationId.value);
  await scrollToBottom();
}

async function handleSendMessage() {
  const text = replyText.value.trim();
  if (!text || messagingStore.isSending) return;

  sendError.value = null;
  const result = await messagingStore.sendMessage(conversationId.value, text);

  if (result) {
    replyText.value = '';
    await scrollToBottom();
    textareaRef.value?.focus();
  } else {
    sendError.value = messagingStore.error || 'Failed to send message.';
  }
}

async function scrollToBottom() {
  await nextTick();
  if (messagesContainerRef.value) {
    messagesContainerRef.value.scrollTop = messagesContainerRef.value.scrollHeight;
  }
}

function getInitials(name: string): string {
  if (!name) return 'U';
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
}

function formatMessageTime(dateString?: string): string {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleTimeString(undefined, {
    hour: 'numeric',
    minute: '2-digit',
  });
}
</script>
