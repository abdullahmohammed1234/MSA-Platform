import apiClient from './api.ts';
import type {
  Conversation,
  PaginatedConversations,
  PaginatedMessages,
  DirectMessage,
  AvailableAdmin,
  CreateConversationPayload,
  SendMessagePayload,
} from '../types/messaging.ts';

export const messagingService = {
  /**
   * Fetch paginated list of conversations for the current user.
   */
  async getConversations(page = 1, perPage = 20): Promise<PaginatedConversations> {
    const response = await apiClient.get<PaginatedConversations>('/conversations', {
      params: { page, per_page: perPage },
    });
    return response.data;
  },

  /**
   * Fetch a single conversation by ID or UUID.
   */
  async getConversation(identifier: string | number): Promise<Conversation> {
    const response = await apiClient.get<Conversation>(`/conversations/${identifier}`);
    return response.data;
  },

  /**
   * Fetch message history for a conversation.
   */
  async getMessages(identifier: string | number, page = 1, perPage = 50): Promise<PaginatedMessages> {
    const response = await apiClient.get<PaginatedMessages>(`/conversations/${identifier}/messages`, {
      params: { page, per_page: perPage },
    });
    return response.data;
  },

  /**
   * Create or retrieve a conversation with an eligible recipient.
   */
  async createConversation(
    recipientOrPayload: number | string | CreateConversationPayload,
    message?: string
  ): Promise<Conversation> {
    let payload: CreateConversationPayload;
    if (typeof recipientOrPayload === 'object' && recipientOrPayload !== null) {
      payload = recipientOrPayload;
    } else {
      payload = {
        recipient_id: recipientOrPayload,
        message,
      };
    }
    const response = await apiClient.post<Conversation>('/conversations', payload);
    return response.data;
  },

  /**
   * Send a new message in a conversation.
   */
  async sendMessage(identifier: string | number, payload: SendMessagePayload): Promise<DirectMessage> {
    const response = await apiClient.post<DirectMessage>(`/conversations/${identifier}/messages`, payload);
    return response.data;
  },

  /**
   * Mark a conversation as read for the current user.
   */
  async markAsRead(identifier: string | number): Promise<{ success: boolean; read_at: string; unread_count: number }> {
    const response = await apiClient.post<{ success: boolean; read_at: string; unread_count: number }>(
      `/conversations/${identifier}/read`
    );
    return response.data;
  },

  /**
   * Fetch total unread direct message count.
   */
  async getUnreadCount(): Promise<{ unread_count: number }> {
    const response = await apiClient.get<{ unread_count: number }>('/conversations/unread-count');
    return response.data;
  },

  /**
   * Fetch available administrators that members can reach out to.
   */
  async getAvailableAdmins(): Promise<AvailableAdmin[]> {
    const response = await apiClient.get<{ data: AvailableAdmin[] }>('/conversations/recipients/admins');
    return response.data.data;
  },
};
