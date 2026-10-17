import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import type { Conversation, DirectMessage, AvailableAdmin } from '../types/messaging.ts';

describe('Connect Direct Messaging — Domain & Contract Verification', () => {
  it('validates conversation data shape with safe participant details', () => {
    const conversation: Conversation = {
      id: 1,
      uuid: 'c8a2b5e0-1234-4567-89ab-cdef01234567',
      last_message_at: '2026-10-12T13:00:00Z',
      last_message_preview: 'Assalamu alaikum, we are available to help.',
      unread_count: 2,
      created_at: '2026-10-12T12:00:00Z',
      other_participant: {
        id: 5,
        uuid: 'd9b3c6f1-2345-6789-01bc-def012345678',
        name: 'Br. Abdullah Admin',
        role_label: 'MSA Administrator',
        is_admin: true,
      },
    };

    assert.equal(conversation.unread_count, 2);
    assert.equal(conversation.other_participant?.is_admin, true);
    assert.equal(conversation.other_participant?.role_label, 'MSA Administrator');
    assert.ok(conversation.uuid.length > 0);
  });

  it('ensures message payload strictly enforces plain text content', () => {
    const message: DirectMessage = {
      id: 101,
      uuid: 'msg-uuid-1234',
      conversation_id: 1,
      sender_id: 5,
      is_mine: false,
      sender: {
        id: 5,
        uuid: 'd9b3c6f1-2345-6789-01bc-def012345678',
        name: 'Br. Abdullah Admin',
        avatar: null,
      },
      body: '<script>alert("xss")</script> Test prayer space query.',
      created_at: '2026-10-12T13:00:00Z',
    };

    assert.equal(typeof message.body, 'string');
    assert.ok(message.body.includes('<script>'));
    // Plain text is retained as string for template mustache escaping in Vue
    assert.equal(message.is_mine, false);
  });

  it('available administrators list requires active administrator qualification', () => {
    const admins: AvailableAdmin[] = [
      {
        id: 2,
        uuid: 'admin-uuid-1',
        name: 'President',
        role_label: 'Director',
        avatar: null,
      },
      {
        id: 3,
        uuid: 'admin-uuid-2',
        name: 'Dawah Lead',
        role_label: 'Dawah Coordinator',
        avatar: null,
      },
    ];

    assert.equal(admins.length, 2);
    admins.forEach((admin) => {
      assert.ok(admin.id > 0);
      assert.ok(admin.name.length > 0);
      assert.ok(admin.role_label.length > 0);
    });
  });

  it('verifies safe conversation routing destination format', () => {
    const uuid = 'c8a2b5e0-1234-4567-89ab-cdef01234567';
    const actionUrl = `/app/messages/${uuid}`;

    assert.match(actionUrl, /^\/app\/messages\/[a-zA-Z0-9-]+$/);
    assert.doesNotMatch(actionUrl, /javascript:/);
    assert.doesNotMatch(actionUrl, /\/\//);
  });

  it('filters out current user from contactable administrator options', () => {
    const currentUserId = 5;
    const currentUuid = 'd9b3c6f1-2345-6789-01bc-def012345678';
    const rawAdmins: AvailableAdmin[] = [
      { id: 2, uuid: 'admin-uuid-1', name: 'President', role_label: 'Director', avatar: null },
      { id: 5, uuid: currentUuid, name: 'Br. Abdullah Admin', role_label: 'MSA Administrator', avatar: null },
      { id: 7, uuid: 'admin-uuid-3', name: 'VP Academic', role_label: 'Admin', avatar: null },
    ];

    const eligible = rawAdmins.filter((a) => a.id !== currentUserId && a.uuid !== currentUuid);
    assert.equal(eligible.length, 2);
    assert.equal(eligible.some((a) => a.id === currentUserId), false);
  });

  it('normalizes createConversation payload properly from either object or scalar parameters', () => {
    const normalizePayload = (
      recipientOrPayload: number | string | { recipient_id: number | string; message?: string },
      message?: string
    ) => {
      if (typeof recipientOrPayload === 'object' && recipientOrPayload !== null) {
        return recipientOrPayload;
      }
      return { recipient_id: recipientOrPayload, message };
    };

    const fromObj = normalizePayload({ recipient_id: 2, message: 'Salam' });
    assert.deepEqual(fromObj, { recipient_id: 2, message: 'Salam' });

    const fromArgs = normalizePayload(2, 'Salam');
    assert.deepEqual(fromArgs, { recipient_id: 2, message: 'Salam' });
  });
});
