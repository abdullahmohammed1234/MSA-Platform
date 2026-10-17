/**
 * Types for Direct Messaging (Phase 12)
 */

export interface Participant {
  id: number;
  uuid: string;
  name: string;
  avatar?: string | null;
  role_label: string;
  is_admin: boolean;
  email?: string;
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

export interface CreateConversationPayload {
  recipient_id: number | string;
  message?: string;
}

export interface SendMessagePayload {
  body: string;
}
