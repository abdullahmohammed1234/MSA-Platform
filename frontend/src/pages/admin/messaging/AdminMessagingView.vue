<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue';
import { useMessagingStore } from '@/stores/messaging';
import { useToastStore } from '@/components/feedback/toast';
import {
  MessageSquare,
  Plus,
  Send,
  RefreshCw,
  Search,
  Check,
  X,
  Shield,
  Users,
} from 'lucide-vue-next';

const messagingStore = useMessagingStore();
const toast = useToastStore();

const activeConvId = ref<string | null>(null);
const replyBody = ref('');
const sendError = ref<string | null>(null);
const messagesContainer = ref<HTMLDivElement | null>(null);

// Modal State
const isModalOpen = ref(false);
const recipientType = ref<'members' | 'admins'>('members');
const searchInput = ref('');
const selectedRecipientId = ref<number | null>(null);
const initialMessage = ref('');
const modalError = ref<string | null>(null);
const searchDebounce = ref<any>(null);

onMounted(async () => {
  await messagingStore.fetchConversations();
  await messagingStore.fetchUnreadCount();

  if (messagingStore.conversations.length > 0) {
    selectConversation(messagingStore.conversations[0].uuid);
  }
});

async function selectConversation(identifier: string) {
  activeConvId.value = identifier;
  sendError.value = null;
  await messagingStore.fetchConversation(identifier);
  await messagingStore.fetchMessages(identifier);
  await messagingStore.markAsRead(identifier);
  await scrollToBottom();
}

async function handleSendMessage() {
  const text = replyBody.value.trim();
  if (!text || !activeConvId.value || messagingStore.isSending) return;

  sendError.value = null;
  const res = await messagingStore.sendMessage(activeConvId.value, text);
  if (res) {
    replyBody.value = '';
    await scrollToBottom();
  } else {
    sendError.value = messagingStore.error || 'Failed to send message.';
  }
}

async function scrollToBottom() {
  await nextTick();
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
}

function openNewModal() {
  recipientType.value = 'members';
  searchInput.value = '';
  selectedRecipientId.value = null;
  initialMessage.value = '';
  modalError.value = null;
  isModalOpen.value = true;
  triggerSearch();
}

function onSearchInput() {
  clearTimeout(searchDebounce.value);
  searchDebounce.value = setTimeout(() => {
    triggerSearch();
  }, 300);
}

function setRecipientType(type: 'members' | 'admins') {
  recipientType.value = type;
  selectedRecipientId.value = null;
  triggerSearch();
}

async function triggerSearch() {
  await messagingStore.searchRecipients(recipientType.value, searchInput.value.trim());
}

async function handleStartConversation() {
  if (!selectedRecipientId.value) return;
  modalError.value = null;

  const conv = await messagingStore.createConversation(
    selectedRecipientId.value,
    initialMessage.value.trim() || undefined
  );

  if (conv) {
    isModalOpen.value = false;
    await messagingStore.fetchConversations();
    selectConversation(conv.uuid);
    toast.success('Conversation opened successfully.');
  } else {
    modalError.value = messagingStore.error || 'Failed to start conversation.';
  }
}

function getInitials(name?: string): string {
  if (!name) return 'U';
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('');
}

function formatTime(isoString?: string | null): string {
  if (!isoString) return '';
  const date = new Date(isoString);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatDate(isoString?: string | null): string {
  if (!isoString) return '';
  const date = new Date(isoString);
  return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}
</script>

<template>
  <div class="space-y-6">
    <!-- Top Action / Header Bar -->
    <div class="bg-white p-6 rounded-2xl border border-neutral-200 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-neutral-900 flex items-center gap-2">
          <span>Direct Messaging Console</span>
          <span
            v-if="messagingStore.unreadCount > 0"
            class="px-2 py-0.5 rounded-full bg-red-600 text-white text-[11px] font-bold"
          >
            {{ messagingStore.unreadCount }} UNREAD
          </span>
        </h1>
        <p class="text-xs text-neutral-500 mt-1">
          Manage private, direct conversations with members and other authorized platform administrators.
        </p>
      </div>

      <button
        @click="openNewModal"
        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-primary-dark transition-all cursor-pointer shadow-xs shrink-0"
      >
        <Plus class="w-4 h-4" />
        <span>New Conversation</span>
      </button>
    </div>

    <!-- Main Workspace Split Pane -->
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-xs overflow-hidden min-h-[620px] flex flex-col md:flex-row">
      <!-- Left: Conversations List -->
      <div class="w-full md:w-80 lg:w-96 border-b md:border-b-0 md:border-r border-neutral-200 flex flex-col shrink-0">
        <div class="p-3.5 border-b border-neutral-200 bg-neutral-50 flex items-center justify-between">
          <span class="text-xs font-bold text-neutral-700 uppercase tracking-wider">Inbox</span>
          <span class="text-[11px] font-semibold text-neutral-400">
            {{ messagingStore.conversations.length }} active threads
          </span>
        </div>

        <div class="flex-1 overflow-y-auto divide-y divide-neutral-100 max-h-[600px]">
          <!-- Loading -->
          <div v-if="messagingStore.isLoadingConversations" class="p-8 text-center">
            <div class="h-6 w-6 border-2 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-2" />
            <p class="text-[11px] text-neutral-400 font-medium">Loading conversations...</p>
          </div>

          <!-- Empty -->
          <div v-else-if="messagingStore.conversations.length === 0" class="p-8 text-center space-y-2">
            <MessageSquare class="w-8 h-8 text-neutral-300 mx-auto" />
            <p class="text-xs font-bold text-neutral-600">No conversations yet</p>
            <p class="text-[11px] text-neutral-400">Click "New Conversation" to search members or fellow administrators.</p>
          </div>

          <!-- Conversation Item -->
          <button
            v-for="conv in messagingStore.conversations"
            :key="conv.uuid"
            @click="selectConversation(conv.uuid)"
            class="w-full p-4 text-left hover:bg-neutral-50 transition-colors flex items-start gap-3 cursor-pointer"
            :class="activeConvId === conv.uuid ? 'bg-primary/5 border-l-4 border-primary' : ''"
          >
            <div class="w-10 h-10 rounded-xl bg-primary text-white font-bold text-xs flex items-center justify-center shrink-0">
              {{ getInitials(conv.other_participant?.name) }}
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex items-center justify-between gap-1">
                <span class="text-xs font-bold text-neutral-900 truncate">
                  {{ conv.other_participant?.name || 'User' }}
                </span>
                <span class="text-[10px] text-neutral-400 whitespace-nowrap">
                  {{ formatDate(conv.last_message_at || conv.created_at) }}
                </span>
              </div>

              <div class="flex items-center gap-1.5 mt-0.5 mb-1">
                <span
                  class="inline-block px-1.5 py-0.2 rounded text-[9px] font-bold"
                  :class="conv.other_participant?.is_admin ? 'bg-amber-100 text-amber-800' : 'bg-neutral-100 text-neutral-600'"
                >
                  {{ conv.other_participant?.role_label || 'Member' }}
                </span>
              </div>

              <p class="text-[11px] text-neutral-500 truncate">
                {{ conv.last_message_preview || 'No messages yet.' }}
              </p>
            </div>

            <span
              v-if="conv.unread_count > 0"
              class="px-1.5 py-0.5 rounded-full bg-red-600 text-white text-[9px] font-bold shrink-0 self-center"
            >
              {{ conv.unread_count }}
            </span>
          </button>
        </div>
      </div>

      <!-- Right: Active Conversation Thread -->
      <div class="flex-1 flex flex-col bg-neutral-50/50">
        <!-- Thread Header -->
        <div v-if="messagingStore.activeConversation" class="p-4 border-b border-neutral-200 bg-white flex items-center justify-between gap-3 shrink-0">
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-primary text-white font-bold text-xs flex items-center justify-center shrink-0">
              {{ getInitials(messagingStore.activeConversation.other_participant?.name) }}
            </div>

            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-bold text-neutral-900 truncate">
                  {{ messagingStore.activeConversation.other_participant?.name || 'User' }}
                </h3>
                <span
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                  :class="messagingStore.activeConversation.other_participant?.is_admin ? 'bg-amber-100 text-amber-800' : 'bg-neutral-100 text-neutral-700'"
                >
                  {{ messagingStore.activeConversation.other_participant?.role_label || 'Member' }}
                </span>
              </div>
              <p class="text-[11px] text-neutral-500 truncate">
                {{ messagingStore.activeConversation.other_participant?.email }}
              </p>
            </div>
          </div>

          <button
            @click="activeConvId && selectConversation(activeConvId)"
            class="p-2 text-neutral-400 hover:text-neutral-700 transition-colors cursor-pointer"
            title="Refresh messages"
          >
            <RefreshCw class="w-4 h-4" :class="messagingStore.isLoadingMessages ? 'animate-spin' : ''" />
          </button>
        </div>

        <!-- Thread Body -->
        <div
          v-if="messagingStore.activeConversation"
          ref="messagesContainer"
          class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-3 max-h-[500px]"
        >
          <div v-if="messagingStore.isLoadingMessages" class="p-8 text-center">
            <div class="h-6 w-6 border-2 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-2" />
            <p class="text-xs text-neutral-400 font-medium">Loading history...</p>
          </div>

          <div
            v-else-if="messagingStore.messages.length === 0"
            class="h-full flex flex-col items-center justify-center text-center p-8 space-y-2"
          >
            <MessageSquare class="w-8 h-8 text-neutral-300" />
            <p class="text-xs font-bold text-neutral-600">No messages yet</p>
            <p class="text-[11px] text-neutral-400">Type a message below to begin.</p>
          </div>

          <!-- Message Bubbles -->
          <div
            v-for="msg in messagingStore.messages"
            :key="msg.uuid"
            class="flex flex-col"
            :class="msg.is_mine ? 'items-end' : 'items-start'"
          >
            <span v-if="!msg.is_mine" class="text-[10px] font-bold text-neutral-500 mb-0.5 ml-1">
              {{ msg.sender?.name }}
            </span>

            <div
              class="max-w-[85%] sm:max-w-md rounded-2xl px-4 py-2.5 text-xs sm:text-sm leading-relaxed whitespace-pre-wrap break-words"
              :class="msg.is_mine
                ? 'bg-primary text-white rounded-br-xs shadow-xs'
                : 'bg-white border border-neutral-200 text-neutral-900 rounded-bl-xs shadow-xs'"
            >
              {{ msg.body }}
            </div>

            <span class="text-[9px] text-neutral-400 mt-1" :class="msg.is_mine ? 'mr-1' : 'ml-1'">
              {{ formatTime(msg.created_at) }}
            </span>
          </div>
        </div>

        <!-- Placeholder when no conversation is selected -->
        <div
          v-else
          class="flex-1 flex flex-col items-center justify-center text-center p-8 space-y-3"
        >
          <div class="w-12 h-12 rounded-2xl bg-neutral-100 text-neutral-400 flex items-center justify-center">
            <MessageSquare class="w-6 h-6" />
          </div>
          <h3 class="text-sm font-bold text-neutral-700">No Conversation Selected</h3>
          <p class="text-xs text-neutral-400 max-w-xs">
            Select a conversation on the left or create a new conversation with a member or administrator.
          </p>
        </div>

        <!-- Composer -->
        <div v-if="messagingStore.activeConversation" class="p-4 border-t border-neutral-200 bg-white space-y-2">
          <div v-if="sendError" class="p-2 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex justify-between items-center">
            <span>{{ sendError }}</span>
            <button @click="sendError = null" class="text-red-500 font-bold">✕</button>
          </div>

          <form @submit.prevent="handleSendMessage" class="flex gap-2">
            <textarea
              v-model="replyBody"
              rows="2"
              maxlength="5000"
              placeholder="Type an administrative message... (Shift+Enter for newline, Enter to send)"
              @keydown.enter.exact.prevent="handleSendMessage"
              class="flex-1 px-3 py-2 border border-neutral-200 rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 resize-none"
            ></textarea>

            <button
              type="submit"
              :disabled="!replyBody.trim() || messagingStore.isSending"
              class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-primary-dark transition-all disabled:opacity-50 flex items-center gap-1.5 self-end cursor-pointer"
            >
              <Send class="w-3.5 h-3.5" />
              <span>Send</span>
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Admin Recipient Search & Start Conversation Modal -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-2xl border border-neutral-200 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-neutral-200">
          <div>
            <h3 class="text-base font-bold text-neutral-900">Start Direct Conversation</h3>
            <p class="text-xs text-neutral-500">Initiate a private conversation with a member or fellow administrator.</p>
          </div>
          <button @click="isModalOpen = false" class="text-neutral-400 hover:text-neutral-700 cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Recipient Type Switcher -->
        <div class="flex rounded-xl bg-neutral-100 p-1">
          <button
            type="button"
            @click="setRecipientType('members')"
            class="flex-1 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
            :class="recipientType === 'members' ? 'bg-white text-neutral-900 shadow-xs' : 'text-neutral-500 hover:text-neutral-900'"
          >
            <Users class="w-3.5 h-3.5" />
            <span>Community Members</span>
          </button>
          <button
            type="button"
            @click="setRecipientType('admins')"
            class="flex-1 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
            :class="recipientType === 'admins' ? 'bg-white text-neutral-900 shadow-xs' : 'text-neutral-500 hover:text-neutral-900'"
          >
            <Shield class="w-3.5 h-3.5" />
            <span>Fellow Administrators</span>
          </button>
        </div>

        <!-- Search Bar -->
        <div class="relative">
          <Search class="w-4 h-4 text-neutral-400 absolute left-3 top-2.5" />
          <input
            v-model="searchInput"
            @input="onSearchInput"
            type="text"
            :placeholder="`Search ${recipientType === 'members' ? 'members' : 'administrators'} by name or email...`"
            class="w-full pl-9 pr-3 py-2 border border-neutral-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <!-- Search Results List -->
        <div>
          <label class="block text-xs font-bold text-neutral-700 mb-1.5">
            Eligible Recipients ({{ messagingStore.adminSearchResults.length }})
          </label>

          <div v-if="messagingStore.isLoadingSearch" class="p-6 text-center">
            <div class="h-6 w-6 border-2 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-2" />
            <p class="text-xs text-neutral-400">Searching...</p>
          </div>

          <div v-else-if="messagingStore.adminSearchResults.length === 0" class="p-4 text-center text-xs text-neutral-400">
            No matching {{ recipientType === 'members' ? 'members' : 'administrators' }} found.
          </div>

          <div v-else class="max-h-48 overflow-y-auto space-y-2 pr-1">
            <button
              v-for="rec in messagingStore.adminSearchResults"
              :key="rec.id"
              type="button"
              @click="selectedRecipientId = rec.id"
              class="w-full p-3 rounded-xl border text-left flex items-center justify-between gap-3 transition-all cursor-pointer"
              :class="selectedRecipientId === rec.id ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-neutral-200 hover:border-neutral-300'"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-primary text-white font-bold text-xs flex items-center justify-center shrink-0">
                  {{ getInitials(rec.name) }}
                </div>
                <div class="truncate">
                  <p class="text-xs font-bold text-neutral-900 truncate">{{ rec.name }}</p>
                  <p class="text-[10px] text-neutral-500 truncate">{{ rec.email }}</p>
                </div>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <span
                  class="text-[9px] font-bold px-1.5 py-0.5 rounded"
                  :class="rec.is_admin ? 'bg-amber-100 text-amber-800' : 'bg-neutral-100 text-neutral-600'"
                >
                  {{ rec.role_label }}
                </span>
                <Check v-if="selectedRecipientId === rec.id" class="w-4 h-4 text-primary shrink-0" />
              </div>
            </button>
          </div>
        </div>

        <!-- Optional Initial Message -->
        <div>
          <label class="block text-xs font-bold text-neutral-700 mb-1">Opening Message (Optional)</label>
          <textarea
            v-model="initialMessage"
            rows="3"
            maxlength="5000"
            placeholder="Assalamu alaikum, reaching out regarding..."
            class="w-full px-3 py-2 border border-neutral-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 resize-none"
          ></textarea>
        </div>

        <div v-if="modalError" class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700">
          {{ modalError }}
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-neutral-200">
          <button
            type="button"
            @click="isModalOpen = false"
            class="px-4 py-2 border border-neutral-200 rounded-xl text-xs font-bold text-neutral-600 hover:bg-neutral-50 transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="!selectedRecipientId || messagingStore.isCreating"
            @click="handleStartConversation"
            class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-primary-dark transition-all disabled:opacity-50 cursor-pointer"
          >
            {{ messagingStore.isCreating ? 'Opening...' : 'Start Conversation' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
