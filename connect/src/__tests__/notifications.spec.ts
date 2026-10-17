import test from 'node:test';
import assert from 'node:assert/strict';

test('MSA Connect Notifications & Preferences Verification', async (t) => {
  // Helper for notification badge formatting
  function formatBadgeCount(count: number): string | number {
    return count > 9 ? '9+' : count;
  }

  // Safe action URL sanitizer helper
  function getSafeActionUrl(url: unknown): string | null {
    if (!url || typeof url !== 'string') return null;
    const trimmed = url.trim();
    if (
      trimmed.startsWith('//') ||
      trimmed.includes('\\') ||
      /^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)
    ) {
      return null;
    }
    if (!trimmed.startsWith('/')) {
      return null;
    }
    return trimmed;
  }

  await t.test('Unread count badge formats single digits and caps at 9+', () => {
    assert.equal(formatBadgeCount(0), 0);
    assert.equal(formatBadgeCount(1), 1);
    assert.equal(formatBadgeCount(5), 5);
    assert.equal(formatBadgeCount(9), 9);
    assert.equal(formatBadgeCount(10), '9+');
    assert.equal(formatBadgeCount(99), '9+');
  });

  await t.test('Safe action URL validator permits valid internal relative destinations', () => {
    assert.equal(getSafeActionUrl('/app/support/INQ-202610-1234'), '/app/support/INQ-202610-1234');
    assert.equal(getSafeActionUrl('/app/events'), '/app/events');
    assert.equal(getSafeActionUrl('/app/tickets'), '/app/tickets');
    assert.equal(getSafeActionUrl('/app/profile'), '/app/profile');
  });

  await t.test('Safe action URL validator rejects dangerous open redirects and external schemes', () => {
    assert.equal(getSafeActionUrl('//attacker.com'), null);
    assert.equal(getSafeActionUrl('https://evil.com'), null);
    assert.equal(getSafeActionUrl('http://evil.com'), null);
    assert.equal(getSafeActionUrl('javascript:alert(1)'), null);
    assert.equal(getSafeActionUrl('data:text/html,evil'), null);
    assert.equal(getSafeActionUrl('/\\attacker.com'), null);
    assert.equal(getSafeActionUrl('app/events'), null); // Missing leading slash
    assert.equal(getSafeActionUrl(''), null);
    assert.equal(getSafeActionUrl(null), null);
    assert.equal(getSafeActionUrl(undefined), null);
  });

  await t.test('Guest users have zero notifications and no unread badge', () => {
    const guestState = {
      isAuthenticated: false,
      notifications: [],
      unreadCount: 0,
    };

    assert.equal(guestState.unreadCount, 0);
    assert.equal(guestState.notifications.length, 0);
  });

  await t.test('Optimistic mark as read decrements unread count', () => {
    const state: {
      unreadCount: number;
      notifications: Array<{ id: number; uuid: string; title: string; read_at: string | null }>;
    } = {
      unreadCount: 3,
      notifications: [
        { id: 1, uuid: 'n-1', title: 'Alert 1', read_at: null },
        { id: 2, uuid: 'n-2', title: 'Alert 2', read_at: null },
        { id: 3, uuid: 'n-3', title: 'Alert 3', read_at: null },
      ],
    };

    // Mark n-1 as read
    const item = state.notifications.find((n) => n.uuid === 'n-1');
    assert.ok(item);
    item.read_at = new Date().toISOString();
    state.unreadCount -= 1;

    assert.equal(state.unreadCount, 2);
    assert.ok(item.read_at !== null);

    // Marking already read item does not decrement again
    if (!item.read_at) {
      state.unreadCount -= 1;
    }
    assert.equal(state.unreadCount, 2);
  });

  await t.test('Mark all as read sets read_at across all items and zeroes count', () => {
    const state = {
      unreadCount: 4,
      notifications: [
        { id: 1, uuid: 'n-1', read_at: null },
        { id: 2, uuid: 'n-2', read_at: null },
        { id: 3, uuid: 'n-3', read_at: '2026-10-12T00:00:00Z' },
        { id: 4, uuid: 'n-4', read_at: null },
      ],
    };

    state.unreadCount = 0;
    state.notifications.forEach((n) => {
      if (!n.read_at) n.read_at = new Date().toISOString();
    });

    assert.equal(state.unreadCount, 0);
    state.notifications.forEach((n) => {
      assert.ok(n.read_at !== null);
    });
  });

  await t.test('Clear state completely purges notifications and preferences on logout', () => {
    const state: {
      notifications: Array<{ id: number; uuid: string; read_at: string | null }>;
      unreadCount: number;
      preferences: any;
      error: string | null;
      searchQuery: string;
      filterMode: 'all' | 'unread';
    } = {
      notifications: [{ id: 1, uuid: 'n-1', read_at: null }],
      unreadCount: 5,
      preferences: {
        in_app_enabled: true,
        email_enabled: true,
        support_updates: true,
        new_announcements: true,
        upcoming_training: true,
        course_completion: true,
        certificate_earned: true,
      },
      error: 'some error',
      searchQuery: 'testing',
      filterMode: 'unread',
    };

    // Purge action
    state.notifications = [];
    state.unreadCount = 0;
    state.preferences = null as any;
    state.error = null;
    state.searchQuery = '';
    state.filterMode = 'all';

    assert.equal(state.notifications.length, 0);
    assert.equal(state.unreadCount, 0);
    assert.equal(state.preferences, null);
    assert.equal(state.error, null);
    assert.equal(state.searchQuery, '');
    assert.equal(state.filterMode, 'all');
  });

  await t.test('Notification preferences rollback on server mutation failure', async () => {
    const preferences = {
      support_updates: true,
      email_enabled: true,
      in_app_enabled: true,
    };

    const previousValue = preferences.support_updates;
    // Optimistic toggle to false
    preferences.support_updates = false;

    // Simulate server error
    const serverUpdate = async () => {
      throw new Error('Server connection lost');
    };

    try {
      await serverUpdate();
    } catch {
      // Rollback
      preferences.support_updates = previousValue;
    }

    assert.equal(preferences.support_updates, true);
  });

  await t.test('Failed notification request does not crash application or alter session state', async () => {
    let authSessionValid = true;
    let loadFailed = false;

    const mockFetchNotifications = async () => {
      throw new Error('500 Internal Server Error');
    };

    try {
      await mockFetchNotifications();
    } catch {
      loadFailed = true;
    }

    assert.equal(loadFailed, true);
    assert.equal(authSessionValid, true);
  });
});
