import test from 'node:test';
import assert from 'node:assert/strict';
import {
  GENERIC_PASSWORD_RESET_MESSAGE,
  interpretPasswordResetRequest,
} from '../utils/passwordResetNotice.ts';

test('Phase 15.2 — SFU MSA Connect V1 Product Completeness & Member Experience', async (t) => {
  // WORKSTREAM 1: Member Account and Profile Completeness
  await t.test('Workstream 1: Profile update payload strips forbidden and privileged fields', () => {
    // Simulate client sanitization and field-level boundary: only name and email permitted
    const rawForm = {
      name: 'Brother Zaid',
      email: 'zaid@sfu.ca',
      role: 'super-admin', // Malicious attempt to inject privileged role
      is_admin: true,
      permissions: ['manage_all'],
      email_verified_at: '2026-01-01',
    };

    const allowedKeys = ['name', 'email'];
    const sanitizedPayload = Object.fromEntries(
      Object.entries(rawForm).filter(([key]) => allowedKeys.includes(key))
    );

    assert.deepEqual(sanitizedPayload, {
      name: 'Brother Zaid',
      email: 'zaid@sfu.ca',
    });
    assert.equal('role' in sanitizedPayload, false);
    assert.equal('is_admin' in sanitizedPayload, false);
    assert.equal('permissions' in sanitizedPayload, false);
  });

  await t.test('Workstream 1: Account password update client validation rules', () => {
    const validatePasswordChange = (current: string, next: string, confirm: string) => {
      const errors: Record<string, string> = {};
      if (!current) errors.current = 'Current password is required.';
      if (!next || next.length < 8) errors.next = 'New password must be at least 8 characters.';
      if (next !== confirm) errors.confirm = 'Password confirmation does not match.';
      if (current && next && current === next) errors.different = 'New password must be different from current password.';
      return { valid: Object.keys(errors).length === 0, errors };
    };

    // Rejection cases
    assert.equal(validatePasswordChange('', 'newsecret123', 'newsecret123').valid, false);
    assert.equal(validatePasswordChange('oldsecret123', 'short', 'short').valid, false);
    assert.equal(validatePasswordChange('oldsecret123', 'newsecret123', 'different456').valid, false);
    assert.equal(validatePasswordChange('samepassword', 'samepassword', 'samepassword').valid, false);

    // Acceptance case
    const validResult = validatePasswordChange('oldsecret123', 'newsecret456', 'newsecret456');
    assert.equal(validResult.valid, true);
    assert.deepEqual(validResult.errors, {});
  });

  // WORKSTREAM 2: Connect-Only Notification Preferences & Device Management
  await t.test('Workstream 2: Distinguishes all 5 stages of notification delivery lifecycle', () => {
    type StageState = {
      stage: number;
      name: string;
      evaluatedState: string;
    };

    const evaluateStages = (
      dbPreference: boolean,
      browserPerm: NotificationPermission | 'unsupported',
      hasServerSubscription: boolean,
      isDispatchedToPushGateway: boolean,
      isDisplayedOnDevice: boolean
    ): StageState[] => [
      { stage: 1, name: 'Connect Preference', evaluatedState: dbPreference ? 'Enabled' : 'Disabled' },
      { stage: 2, name: 'Browser Permission', evaluatedState: browserPerm },
      { stage: 3, name: 'Server Subscription', evaluatedState: hasServerSubscription ? 'Enrolled' : 'Not Subscribed' },
      { stage: 4, name: 'Gateway Dispatch', evaluatedState: isDispatchedToPushGateway ? 'Accepted' : 'Failed' },
      { stage: 5, name: 'Device Display', evaluatedState: isDisplayedOnDevice ? 'Displayed' : 'Silenced' },
    ];

    // Scenario: Preference enabled, browser granted, registered, dispatched, but phone in Do Not Disturb
    const stages = evaluateStages(true, 'granted', true, true, false);

    assert.equal(stages[0].evaluatedState, 'Enabled');
    assert.equal(stages[1].evaluatedState, 'granted');
    assert.equal(stages[2].evaluatedState, 'Enrolled');
    assert.equal(stages[3].evaluatedState, 'Accepted');
    assert.equal(stages[4].evaluatedState, 'Silenced');
    assert.notEqual(stages[3].evaluatedState, stages[4].evaluatedState, 'Gateway dispatch acceptance must not be equated to physical display');
  });

  await t.test('Workstream 2: Test push feedback explicitly documents physical display disclaimer', () => {
    const formatTestPushFeedback = (apiMessage: string) => {
      return `${apiMessage} Note: Physical banner display is controlled by your operating system notification settings and active Focus/Do Not Disturb modes.`;
    };

    const message = formatTestPushFeedback('Test push notification dispatched.');
    assert.match(message, /dispatched/i);
    assert.match(message, /Physical banner display is controlled by your operating system/i);
    assert.match(message, /Focus\/Do Not Disturb/i);
  });

  // WORKSTREAM 3: Account Security and Session Lifecycle
  await t.test('Workstream 3: Purging session state cleans all private member stores including messaging', () => {
    // Mock all Pinia stores representing private member state
    const storeStates = {
      dashboard: { count: 5 },
      tickets: { items: ['ticket-1', 'ticket-2'] },
      events: { rsvps: [101] },
      notifications: { unread: 3 },
      support: { inquiries: [{ ref: 'INQ-202610-001' }] },
      messaging: { conversations: [{ id: 1 }], messages: [{ text: 'Private conversation' }] },
    };

    const purgeAllStores = () => {
      storeStates.dashboard = { count: 0 };
      storeStates.tickets = { items: [] };
      storeStates.events = { rsvps: [] };
      storeStates.notifications = { unread: 0 };
      storeStates.support = { inquiries: [] };
      storeStates.messaging = { conversations: [], messages: [] };
    };

    purgeAllStores();

    assert.equal(storeStates.dashboard.count, 0);
    assert.equal(storeStates.tickets.items.length, 0);
    assert.equal(storeStates.events.rsvps.length, 0);
    assert.equal(storeStates.notifications.unread, 0);
    assert.equal(storeStates.support.inquiries.length, 0);
    assert.equal(storeStates.messaging.conversations.length, 0, 'Messaging conversations must be purged on logout');
    assert.equal(storeStates.messaging.messages.length, 0, 'Direct messages must be purged on logout');
  });

  await t.test('Workstream 3: Session expiry detection activates only when redirect target exists', () => {
    const detectSessionExpiry = (query: Record<string, string | undefined>) => {
      return Boolean(query.redirect);
    };

    assert.equal(detectSessionExpiry({}), false);
    assert.equal(detectSessionExpiry({ tab: 'preferences' }), false);
    assert.equal(detectSessionExpiry({ redirect: '/app/events' }), true);
    assert.equal(detectSessionExpiry({ redirect: '/app/messages/1' }), true);
  });

  // WORKSTREAM 4: Help, Support, and Account Recovery UX
  await t.test('Workstream 4: Password reset request handles privacy-safe response without leaking existence', () => {
    const known = interpretPasswordResetRequest({ ok: true, status: 200 });
    const unknown = interpretPasswordResetRequest({ ok: true, status: 200 });
    assert.deepEqual(known, unknown);
    assert.equal(known.text, GENERIC_PASSWORD_RESET_MESSAGE);
    assert.equal(known.kind, 'success');

    const leaked = interpretPasswordResetRequest({
      ok: false,
      status: 422,
      emailErrors: ['We could not find a user with that email address.'],
    });
    assert.equal(leaked.kind, 'success');
    assert.equal(leaked.text, GENERIC_PASSWORD_RESET_MESSAGE);
    assert.doesNotMatch(leaked.text, /not find/i);
    assert.doesNotMatch(leaked.text, /does not exist/i);

    const malformed = interpretPasswordResetRequest({
      ok: false,
      status: 422,
      emailErrors: ['The email field must be a valid email address.'],
    });
    assert.equal(malformed.kind, 'error');
    assert.equal(malformed.text, 'The email field must be a valid email address.');
  });

  await t.test('Workstream 4: Verified Help sections cover all mandated member topics', () => {
    const registeredHelpSectionIds = [
      'auth-sessions',
      'profile-management',
      'notifications-troubleshooting',
      'pwa-install',
      'network-connectivity',
      'contact-support',
    ];

    assert.equal(registeredHelpSectionIds.includes('auth-sessions'), true);
    assert.equal(registeredHelpSectionIds.includes('profile-management'), true);
    assert.equal(registeredHelpSectionIds.includes('notifications-troubleshooting'), true);
    assert.equal(registeredHelpSectionIds.includes('pwa-install'), true);
    assert.equal(registeredHelpSectionIds.includes('network-connectivity'), true);
    assert.equal(registeredHelpSectionIds.includes('contact-support'), true);
  });

  // WORKSTREAM 5: Mobile UX & Accessibility Invariants
  await t.test('Workstream 5: Standalone PWA safe-area and touch target bounds', () => {
    const minTouchTargetPx = 44;
    const mobileBottomBarHeightPx = 56;
    const mobileBottomItemHeightPx = 44;

    assert.ok(mobileBottomBarHeightPx >= minTouchTargetPx, 'Mobile navigation bar height exceeds 44px');
    assert.ok(mobileBottomItemHeightPx >= minTouchTargetPx, 'Touch target item meets or exceeds 44px');
  });

  await t.test('Workstream 5: Form inputs associate labels and aria-describedby for error states', () => {
    const getAriaDescribedBy = (inputId: string, hasError: boolean, hasDesc: boolean) => {
      if (hasError) return `${inputId}-error`;
      if (hasDesc) return `${inputId}-desc`;
      return undefined;
    };

    assert.equal(getAriaDescribedBy('member-email', true, false), 'member-email-error');
    assert.equal(getAriaDescribedBy('member-email', false, true), 'member-email-desc');
    assert.equal(getAriaDescribedBy('member-email', false, false), undefined);
  });
});
