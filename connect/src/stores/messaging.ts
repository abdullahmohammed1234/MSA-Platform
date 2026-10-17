import { defineStore } from 'pinia';
import { ref } from 'vue';
import { messagingService } from '@/services/messaging.service';
import type {
  Conversation,
  DirectMessage,
  AvailableAdmin,
  CreateConversationPayload,
} from '@/types/messaging';

export const useMessagingStore = defineStore('connect_messaging', () => {
  const conversations = ref<Conversation[]>([]);
  const activeConversation = ref<Conversation | null>(null);
  const messages = ref<DirectMessage[]>([]);
  const unreadCount = ref<number>(0);
  const availableAdmins = ref<AvailableAdmin[]>([]);

  const isLoadingConversations = ref<boolean>(false);
  const isLoadingMessages = ref<boolean>(false);
  const isLoadingAdmins = ref<boolean>(false);
  const isSending = ref<boolean>(false);
  const isCreating = ref<boolean>(false);
  const error = ref<string | null>(null);

  const pagination = ref<{
    current_page: number;
    last_page: number;
    total: number;
  } | null>(null);

  async function fetchConversations(page = 1): Promise<void> {
    isLoadingConversations.value = true;
    error.value = null;
    try {
      const response = await messagingService.getConversations(page);
      conversations.value = response.data || [];
      pagination.value = {
        current_page: response.current_page,
        last_page: response.last_page,
        total: response.total,
      };
    } catch (err: any) {
      error.value = err.message || err.response?.data?.message || 'Failed to load conversations.';
    } finally {
      isLoadingConversations.value = false;
    }
  }

  async function fetchConversation(identifier: string | number): Promise<Conversation | null> {
    isLoadingMessages.value = true;
    error.value = null;
    try {
      const conv = await messagingService.getConversation(identifier);
      activeConversation.value = conv;
      return conv;
    } catch (err: any) {
      error.value = err.message || err.response?.data?.message || 'Conversation could not be found.';
      return null;
    } finally {
      isLoadingMessages.value = false;
    }
  }

  async function fetchMessages(identifier: string | number, page = 1): Promise<void> {
    isLoadingMessages.value = true;
    error.value = null;
    try {
      const response = await messagingService.getMessages(identifier, page, 50);
      messages.value = response.data || [];
    } catch (err: any) {
      error.value = err.message || err.response?.data?.message || 'Failed to load message history.';
    } finally {
      isLoadingMessages.value = false;
    }
  }

  async function sendMessage(identifier: string | number, body: string): Promise<DirectMessage | null> {
    if (!body.trim()) return null;
    isSending.value = true;
    error.value = null;
    try {
      const newMsg = await messagingService.sendMessage(identifier, { body: body.trim() });
      messages.value.push(newMsg);

      // Update local conversation preview and last_message_at
      if (activeConversation.value) {
        activeConversation.value.last_message_at = newMsg.created_at;
        activeConversation.value.last_message_preview = newMsg.body.slice(0, 100);
      }
      const existingConv = conversations.value.find(
        (c) => c.uuid === identifier || String(c.id) === String(identifier)
      );
      if (existingConv) {
        existingConv.last_message_at = newMsg.created_at;
        existingConv.last_message_preview = newMsg.body.slice(0, 100);
      }

      return newMsg;
    } catch (err: any) {
      error.value = err.message || err.response?.data?.message || 'Failed to send message.';
      return null;
    } finally {
      isSending.value = false;
    }
  }

  async function createConversation(
    recipientOrPayload: number | string | CreateConversationPayload,
    message?: string
  ): Promise<Conversation | null> {
    isCreating.value = true;
    error.value = null;
    try {
      const conv = await messagingService.createConversation(recipientOrPayload, message);
      activeConversation.value = conv;
      return conv;
    } catch (err: any) {
      error.value = err.message || err.response?.data?.message || 'Failed to start conversation.';
      return null;
    } finally {
      isCreating.value = false;
    }
  }

  async function markAsRead(identifier: string | number): Promise<void> {
    try {
      await messagingService.markAsRead(identifier);
      if (activeConversation.value && (activeConversation.value.uuid === identifier || String(activeConversation.value.id) === String(identifier))) {
        activeConversation.value.unread_count = 0;
      }
      const existing = conversations.value.find(
        (c) => c.uuid === identifier || String(c.id) === String(identifier)
      );
      if (existing) {
        existing.unread_count = 0;
      }
      await fetchUnreadCount();
    } catch {
      // Non-blocking error
    }
  }

  async function fetchUnreadCount(): Promise<void> {
    try {
      const res = await messagingService.getUnreadCount();
      unreadCount.value = res.unread_count || 0;
    } catch {
      // Non-blocking
    }
  }

  async function fetchAvailableAdmins(): Promise<void> {
    isLoadingAdmins.value = true;
    try {
      const admins = await messagingService.getAvailableAdmins();
      availableAdmins.value = admins || [];
    } catch {
      availableAdmins.value = [];
    } finally {
      isLoadingAdmins.value = false;
    }
  }

  function clearState(): void {
    conversations.value = [];
    activeConversation.value = null;
    messages.value = [];
    unreadCount.value = 0;
    availableAdmins.value = [];
    isLoadingConversations.value = false;
    isLoadingMessages.value = false;
    isLoadingAdmins.value = false;
    isSending.value = false;
    isCreating.value = false;
    error.value = null;
    pagination.value = null;
  }

  return {
    conversations,
    activeConversation,
    messages,
    unreadCount,
    availableAdmins,
    isLoadingConversations,
    isLoadingMessages,
    isLoadingAdmins,
    isSending,
    isCreating,
    error,
    pagination,
    fetchConversations,
    fetchConversation,
    fetchMessages,
    sendMessage,
    createConversation,
    markAsRead,
    fetchUnreadCount,
    fetchAvailableAdmins,
    clearState,
  };
});
