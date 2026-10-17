<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useMessagingStore } from '@/stores/messaging';
import {
  MessageSquare,
  Plus,
  Send,
  ArrowLeft,
  RefreshCw,
  Check,
  X,
} from 'lucide-vue-next';

const route = useRoute();
const authStore = useAuthStore();
const messagingStore = useMessagingStore();

const activeConvId = ref<string | null>(null);
const replyBody = ref('');
const sendError = ref<string | null>(null);
const messagesContainer = ref<HTMLDivElement | null>(null);

// Modal state
const isModalOpen = ref(false);
const adminSearch = ref('');
const selectedAdminId = ref<number | null>(null);
const initialMessage = ref('');
const modalError = ref<string | null>(null);

const filteredAdmins = computed(() => {
  const query = adminSearch.value.trim().toLowerCase();
  const currentUserId = authStore.user?.id;
  const currentUuid = authStore.user?.uuid;
  const list = messagingStore.availableAdmins.filter(
    (a) => a.id !== currentUserId && a.uuid !== currentUuid
  );
  if (!query) return list;
  return list.filter(
    (a) => a.name.toLowerCase().includes(query) || a.role_label.toLowerCase().includes(query)
  );
});

onMounted(async () => {
  await messagingStore.fetchConversations();
  await messagingStore.fetchUnreadCount();

  if (route.params.id) {
    selectConversation(String(route.params.id));
  } else if (messagingStore.conversations.length > 0 && window.innerWidth >= 1024) {
    selectConversation(messagingStore.conversations[0].uuid);
  }
});

watch(
  () => route.params.id,
  (newId) => {
    if (newId) {
      selectConversation(String(newId));
    }
  }
);

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

async function openNewModal() {
  selectedAdminId.value = null;
  initialMessage.value = '';
  modalError.value = null;
  adminSearch.value = '';
  isModalOpen.value = true;
  await messagingStore.fetchAvailableAdmins();
}

async function handleCreateConversation() {
  if (!selectedAdminId.value) {
    modalError.value = 'Please select an administrator to contact.';
    return;
  }
  if (authStore.user?.id && selectedAdminId.value === authStore.user.id) {
    modalError.value = 'You cannot start a conversation with yourself.';
    return;
  }
  modalError.value = null;

  const conv = await messagingStore.createConversation(
    selectedAdminId.value,
    initialMessage.value.trim() || undefined
  );

  if (conv) {
    isModalOpen.value = false;
    await messagingStore.fetchConversations();
    selectConversation(conv.uuid);
  } else {
    modalError.value = messagingStore.error || 'Failed to start conversation.';
  }
}

function getInitials(name?: string): string {
  if (!name) return 'A';
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
    <!-- Header Card -->
    <div class="bg-white p-6 rounded-3xl border border-neutral-ivory shadow-soft flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-lg font-display font-black text-primary flex items-center gap-2">
          <span>Direct Messages</span>
          <span
            v-if="messagingStore.unreadCount > 0"
            class="px-2 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-extrabold"
          >
            {{ messagingStore.unreadCount }} NEW
          </span>
        </h2>
        <p class="text-xs text-neutral-black/60 font-medium mt-1">
          Private, 1-to-1 communications between members and authorized MSA administrators.
        </p>
      </div>

      <button
        @click="openNewModal"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl text-xs font-bold hover:bg-primary-dark transition-all shadow-soft shrink-0 cursor-pointer"
      >
        <Plus class="w-4 h-4" />
        <span>Contact Administration</span>
      </button>
    </div>

    <!-- Main Content Area: Split View for Inbox + Conversation -->
    <div class="bg-white rounded-3xl border border-neutral-ivory shadow-soft overflow-hidden min-h-[600px] flex flex-col lg:flex-row">
      <!-- Left Column: Conversation List -->
      <div
        class="w-full lg:w-80 lg:border-r border-neutral-ivory flex flex-col shrink-0"
        :class="activeConvId ? 'hidden lg:flex' : 'flex'"
      >
        <div class="p-4 border-b border-neutral-ivory bg-neutral-ivory/20 flex items-center justify-between">
          <span class="text-xs font-black text-primary uppercase tracking-wider">Conversations</span>
          <span class="text-[11px] font-bold text-neutral-black/50">
            {{ messagingStore.conversations.length }} total
          </span>
        </div>

        <div class="flex-1 overflow-y-auto divide-y divide-neutral-ivory/60 max-h-[600px]">
          <!-- Loading -->
          <div v-if="messagingStore.isLoadingConversations" class="p-8 text-center">
            <div class="h-6 w-6 border-2 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-2" />
            <p class="text-[11px] text-neutral-black/50 font-bold">Loading inbox...</p>
          </div>

          <!-- Empty -->
          <div v-else-if="messagingStore.conversations.length === 0" class="p-8 text-center space-y-2">
            <MessageSquare class="w-8 h-8 text-neutral-black/20 mx-auto" />
            <p class="text-xs font-bold text-neutral-black/70">No direct messages yet</p>
            <p class="text-[11px] text-neutral-black/50">Reach out to an administrator for questions, guidance, or support.</p>
          </div>

          <!-- Conversation Item -->
          <button
            v-for="conv in messagingStore.conversations"
            :key="conv.uuid"
            @click="selectConversation(conv.uuid)"
            class="w-full p-4 text-left hover:bg-neutral-ivory/30 transition-all flex items-start gap-3 cursor-pointer"
            :class="activeConvId === conv.uuid ? 'bg-primary/5 border-l-4 border-primary' : ''"
          >
            <div class="w-9 h-9 rounded-xl bg-primary text-white font-bold text-xs flex items-center justify-center shrink-0">
              {{ getInitials(conv.other_participant?.name) }}
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex items-center justify-between gap-1">
                <span class="text-xs font-bold text-neutral-black truncate">
                  {{ conv.other_participant?.name || 'Administrator' }}
                </span>
                <span class="text-[10px] text-neutral-black/40 whitespace-nowrap">
                  {{ formatDate(conv.last_message_at || conv.created_at) }}
                </span>
              </div>

              <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold bg-neutral-ivory text-neutral-black/70 mb-1">
                {{ conv.other_participant?.role_label || 'Administrator' }}
              </span>

              <p class="text-[11px] text-neutral-black/50 truncate">
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

      <!-- Right Column: Active Conversation Thread -->
      <div
        class="flex-1 flex flex-col bg-neutral-ivory/10"
        :class="!activeConvId ? 'hidden lg:flex' : 'flex'"
      >
        <!-- Thread Header -->
        <div v-if="messagingStore.activeConversation" class="p-4 border-b border-neutral-ivory bg-white flex items-center justify-between gap-3 shrink-0">
          <div class="flex items-center gap-3 min-w-0">
            <button
              @click="activeConvId = null"
              class="lg:hidden p-1.5 rounded-lg hover:bg-neutral-ivory transition-colors"
            >
              <ArrowLeft class="w-4 h-4 text-neutral-black/70" />
            </button>

            <div class="w-9 h-9 rounded-xl bg-primary text-white font-bold text-xs flex items-center justify-center shrink-0">
              {{ getInitials(messagingStore.activeConversation.other_participant?.name) }}
            </div>

            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h3 class="text-xs sm:text-sm font-bold text-neutral-black truncate">
                  {{ messagingStore.activeConversation.other_participant?.name || 'MSA Administrator' }}
                </h3>
                <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wider bg-primary/10 text-primary">
                  {{ messagingStore.activeConversation.other_participant?.role_label || 'Administrator' }}
                </span>
              </div>
              <p class="text-[10px] text-neutral-black/50">
                Direct & Confidential Message
              </p>
            </div>
          </div>

          <button
            @click="activeConvId && selectConversation(activeConvId)"
            class="p-2 text-neutral-black/40 hover:text-primary transition-colors cursor-pointer"
            title="Refresh messages"
          >
            <RefreshCw class="w-4 h-4" :class="messagingStore.isLoadingMessages ? 'animate-spin' : ''" />
          </button>
        </div>

        <!-- Messages Container -->
        <div
          v-if="messagingStore.activeConversation"
          ref="messagesContainer"
          class="flex-1 p-4 sm:p-6 overflow-y-auto space-y-3 max-h-[480px]"
        >
          <div v-if="messagingStore.isLoadingMessages" class="p-8 text-center">
            <div class="h-6 w-6 border-2 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-2" />
            <p class="text-[11px] text-neutral-black/50 font-bold">Loading message history...</p>
          </div>

          <div
            v-else-if="messagingStore.messages.length === 0"
            class="h-full flex flex-col items-center justify-center text-center p-8 space-y-2"
          >
            <MessageSquare class="w-8 h-8 text-neutral-black/20" />
            <p class="text-xs font-bold text-neutral-black/60">No messages in this conversation</p>
            <p class="text-[11px] text-neutral-black/40">Type a message below to start chatting with the administrator.</p>
          </div>

          <!-- Message Bubbles -->
          <div
            v-for="msg in messagingStore.messages"
            :key="msg.uuid"
            class="flex flex-col"
            :class="msg.is_mine ? 'items-end' : 'items-start'"
          >
            <span v-if="!msg.is_mine" class="text-[10px] font-bold text-neutral-black/50 mb-0.5 ml-1">
              {{ msg.sender?.name }}
            </span>

            <div
              class="max-w-[85%] sm:max-w-md rounded-2xl px-4 py-2.5 text-xs sm:text-sm leading-relaxed whitespace-pre-wrap break-words"
              :class="msg.is_mine
                ? 'bg-primary text-white rounded-br-xs shadow-soft'
                : 'bg-white border border-neutral-ivory text-neutral-black rounded-bl-xs shadow-xs'"
            >
              {{ msg.body }}
            </div>

            <span class="text-[9px] text-neutral-black/40 mt-1" :class="msg.is_mine ? 'mr-1' : 'ml-1'">
              {{ formatTime(msg.created_at) }}
            </span>
          </div>
        </div>

        <!-- Empty Placeholder when no conversation is selected on desktop -->
        <div
          v-else
          class="flex-1 flex flex-col items-center justify-center text-center p-8 space-y-3"
        >
          <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
            <MessageSquare class="w-6 h-6" />
          </div>
          <h3 class="text-sm font-bold text-neutral-black">No Conversation Selected</h3>
          <p class="text-xs text-neutral-black/50 max-w-xs">
            Choose a conversation from the left or contact an administrator to begin.
          </p>
        </div>

        <!-- Reply Composer -->
        <div v-if="messagingStore.activeConversation" class="p-4 border-t border-neutral-ivory bg-white space-y-2">
          <div v-if="sendError" class="p-2 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700 flex justify-between items-center">
            <span>{{ sendError }}</span>
            <button @click="sendError = null" class="text-red-500 font-bold">✕</button>
          </div>

          <form @submit.prevent="handleSendMessage" class="flex gap-2">
            <textarea
              v-model="replyBody"
              rows="2"
              maxlength="5000"
              placeholder="Write a message... (Shift+Enter for newline, Enter to send)"
              @keydown.enter.exact.prevent="handleSendMessage"
              class="flex-1 px-3 py-2 border border-neutral-ivory rounded-xl text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 resize-none"
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

    <!-- Contact Administration Modal -->
    <div
      v-if="isModalOpen"
      class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl border border-neutral-ivory max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-3 border-b border-neutral-ivory">
          <div>
            <h3 class="text-base font-bold text-primary">Contact MSA Administration</h3>
            <p class="text-xs text-neutral-black/60">Select an administrator to initiate a direct conversation.</p>
          </div>
          <button @click="isModalOpen = false" class="text-neutral-black/40 hover:text-neutral-black cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Search -->
        <div>
          <label class="block text-xs font-bold text-neutral-black mb-1">Search Administrators</label>
          <input
            v-model="adminSearch"
            type="text"
            placeholder="Search by name or department..."
            class="w-full px-3 py-2 border border-neutral-ivory rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-primary/20"
          />
        </div>

        <!-- List -->
        <div>
          <label class="block text-xs font-bold text-neutral-black mb-1.5">
            Eligible Administrators ({{ filteredAdmins.length }})
          </label>

          <div v-if="messagingStore.isLoadingAdmins" class="p-6 text-center">
            <div class="h-6 w-6 border-2 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-2" />
            <p class="text-xs text-neutral-black/50">Loading administrators...</p>
          </div>

          <div v-else-if="filteredAdmins.length === 0" class="p-4 text-center text-xs text-neutral-black/50">
            No administrators found matching your search.
          </div>

          <div v-else class="max-h-48 overflow-y-auto space-y-2 pr-1">
            <button
              v-for="admin in filteredAdmins"
              :key="admin.id"
              type="button"
              @click="selectedAdminId = admin.id"
              class="w-full p-3 rounded-xl border text-left flex items-center justify-between gap-3 transition-all cursor-pointer"
              :class="selectedAdminId === admin.id ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-neutral-ivory hover:border-neutral-black/30'"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-primary text-white font-bold text-xs flex items-center justify-center shrink-0">
                  {{ getInitials(admin.name) }}
                </div>
                <div class="truncate">
                  <p class="text-xs font-bold text-neutral-black truncate">{{ admin.name }}</p>
                  <p class="text-[10px] text-neutral-black/50 truncate">{{ admin.role_label }}</p>
                </div>
              </div>

              <Check v-if="selectedAdminId === admin.id" class="w-4 h-4 text-primary shrink-0" />
            </button>
          </div>
        </div>

        <!-- Optional Initial Message -->
        <div>
          <label class="block text-xs font-bold text-neutral-black mb-1">Initial Message (Optional)</label>
          <textarea
            v-model="initialMessage"
            rows="3"
            maxlength="5000"
            placeholder="Assalamu alaikum, I have an inquiry regarding..."
            class="w-full px-3 py-2 border border-neutral-ivory rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 resize-none"
          ></textarea>
        </div>

        <div v-if="modalError" class="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-700">
          {{ modalError }}
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-neutral-ivory">
          <button
            type="button"
            @click="isModalOpen = false"
            class="px-4 py-2 border border-neutral-ivory rounded-xl text-xs font-bold text-neutral-black/70 hover:bg-neutral-ivory transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="!selectedAdminId || messagingStore.isCreating"
            @click="handleCreateConversation"
            class="px-4 py-2 bg-primary text-white rounded-xl text-xs font-bold hover:bg-primary-dark transition-all disabled:opacity-50 cursor-pointer"
          >
            {{ messagingStore.isCreating ? 'Starting...' : 'Start Conversation' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
