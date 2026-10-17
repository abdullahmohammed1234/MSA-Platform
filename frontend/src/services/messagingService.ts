import api from './api.js';

export interface Participant {
  id: number;
  uuid: string;
  name: string;
  email?: string;
  avatar?: string | null;
  role_label: string;
  is_admin: boolean;
}

export interface Conversation {
  id: number;
  uuid: string;
  last_message_at: string | null;
  last_message_preview: string | null;
  unread_count: number;
  created_at: string;
  other_participant: Participant | null;
}

export interface DirectMessage {
  id: number;
  uuid: string;
  conversation_id: number;
  sender_id: number;
  is_mine: boolean;
  sender: {
    id: number;
    uuid: string;
    name: string;
    avatar: string | null;
  };
  body: string;
  created_at: string;
}

export interface PaginatedConversations {
  data: Conversation[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface PaginatedMessages {
  data: DirectMessage[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AvailableAdmin {
  id: number;
  uuid: string;
  name: string;
  role_label: string;
  avatar: string | null;
}

export const messagingService = {
  async getConversations(page = 1, perPage = 20): Promise<PaginatedConversations> {
    const response = await api.get('/conversations', {
      params: { page, per_page: perPage },
    });
    return response.data;
  },

  async getConversation(identifier: string | number): Promise<Conversation> {
    const response = await api.get(`/conversations/${identifier}`);
    return response.data;
  },

  async getMessages(identifier: string | number, page = 1, perPage = 50): Promise<PaginatedMessages> {
    const response = await api.get(`/conversations/${identifier}/messages`, {
      params: { page, per_page: perPage },
    });
    return response.data;
  },

  async createConversation(recipientId: number | string, message?: string): Promise<Conversation> {
    const response = await api.post('/conversations', {
      recipient_id: recipientId,
      message,
    });
    return response.data;
  },

  async sendMessage(identifier: string | number, body: string): Promise<DirectMessage> {
    const response = await api.post(`/conversations/${identifier}/messages`, {
      body,
    });
    return response.data;
  },

  async markAsRead(identifier: string | number): Promise<{ success: boolean; read_at: string; unread_count: number }> {
    const response = await api.post(`/conversations/${identifier}/read`);
    return response.data;
  },

  async getUnreadCount(): Promise<{ unread_count: number }> {
    const response = await api.get('/conversations/unread-count');
    return response.data;
  },

  async getAvailableAdmins(): Promise<AvailableAdmin[]> {
    const response = await api.get('/conversations/recipients/admins');
    return response.data.data;
  },

  async adminSearchRecipients(
    type: 'members' | 'admins',
    search?: string,
    page = 1,
    perPage = 15
  ): Promise<{ data: Participant[]; current_page: number; last_page: number; total: number }> {
    const response = await api.get('/admin/messaging/recipients', {
      params: { type, search, page, per_page: perPage },
    });
    return response.data;
  },
};
