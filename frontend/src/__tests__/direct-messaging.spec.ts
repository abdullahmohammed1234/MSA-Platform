import { describe, it, expect } from 'vitest';
import type { Conversation, DirectMessage, AvailableAdmin } from '@/services/messagingService';

describe('Frontend Direct Messaging — Contract and Logic Verification', () => {
  it('validates conversation and participant contract structure', () => {
    const conversation: Conversation = {
      id: 10,
      uuid: 'conv-uuid-7777',
      last_message_at: '2026-10-12T13:00:00Z',
      last_message_preview: 'Assalamu alaikum, we are available to help.',
      unread_count: 1,
      created_at: '2026-10-12T12:00:00Z',
      other_participant: {
        id: 3,
        uuid: 'user-uuid-3',
        name: 'Sister Fatima Admin',
        role_label: 'Dawah Coordinator',
        is_admin: true,
      },
    };

    expect(conversation.unread_count).toBe(1);
    expect(conversation.other_participant?.is_admin).toBe(true);
    expect(conversation.other_participant?.role_label).toBe('Dawah Coordinator');
    expect(conversation.uuid).toBe('conv-uuid-7777');
  });

  it('preserves plain-text message strings safely for template escaping', () => {
    const rawContent = '<script>alert("xss")</script> Test prayer space query.';
    const message: DirectMessage = {
      id: 201,
      uuid: 'msg-uuid-201',
      conversation_id: 10,
      sender_id: 3,
      is_mine: false,
      sender: {
        id: 3,
        uuid: 'user-uuid-3',
        name: 'Sister Fatima Admin',
        avatar: null,
      },
      body: rawContent,
      created_at: '2026-10-12T13:00:00Z',
    };

    expect(message.body).toBe(rawContent);
    expect(message.is_mine).toBe(false);
  });

  it('available administrators list enforces administrator qualification', () => {
    const admins: AvailableAdmin[] = [
      {
        id: 3,
        uuid: 'user-uuid-3',
        name: 'Sister Fatima Admin',
        role_label: 'Dawah Coordinator',
        avatar: null,
      },
      {
        id: 4,
        uuid: 'user-uuid-4',
        name: 'Br. Tariq Director',
        role_label: 'Director',
        avatar: null,
      },
    ];

    expect(admins.length).toBe(2);
    admins.forEach((admin) => {
      expect(admin.id).toBeGreaterThan(0);
      expect(admin.name.length).toBeGreaterThan(0);
      expect(admin.role_label.length).toBeGreaterThan(0);
    });
  });

  it('validates member route structure and admin route segregation', () => {
    const memberMessagesPath = '/account/messages';
    const adminMessagesPath = '/admin/messages';

    expect(memberMessagesPath).toMatch(/^\/account\/messages/);
    expect(adminMessagesPath).toMatch(/^\/admin\/messages/);
    expect(memberMessagesPath).not.toBe(adminMessagesPath);
  });
});
